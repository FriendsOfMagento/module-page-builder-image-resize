<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html\ImageRewriter;

use Fom\PageBuilderImageResize\Model\Html\ImageRewriterInterface;
use Fom\PageBuilderImageResize\Model\Html\TagAttributes;
use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;

class ImageSourceRewriter implements ImageRewriterInterface
{
    private const IMAGE_TAG_PATTERN = '/^<img(?=[\s\/>])/i';
    private const SIZE_ATTRIBUTES = ['width', 'height'];

    /**
     * @param VariantGenerator $variantGenerator
     * @param TagAttributes $tagAttributes
     * @param string[] $settingNames
     * @param string $sourceAttribute
     */
    public function __construct(
        private readonly VariantGenerator $variantGenerator,
        private readonly TagAttributes $tagAttributes,
        private readonly array $settingNames = ['desktop', 'mobile'],
        private readonly string $sourceAttribute = 'src'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function rewrite(string $tag, array $settings): string
    {
        $imageSettings = $this->getImageSettings($settings);
        $source = $this->tagAttributes->get($tag, $this->sourceAttribute);

        if (!$imageSettings || !$source || !preg_match(self::IMAGE_TAG_PATTERN, $tag)) {
            return $tag;
        }

        $variant = $this->variantGenerator->generate($source, $imageSettings);

        if ($variant === null) {
            return $tag;
        }

        $tag = $this->tagAttributes->set($tag, $this->sourceAttribute, $variant['url']);

        foreach (self::SIZE_ATTRIBUTES as $attribute) {
            if ($this->tagAttributes->get($tag, $attribute) === '') {
                $tag = $this->tagAttributes->remove($tag, $attribute);
            }
        }

        $attributes = $this->tagAttributes->getAll($tag);

        if (isset($attributes['width']) || isset($attributes['height']) || !$variant['width'] || !$variant['height']) {
            return $tag;
        }

        return $this->tagAttributes->add($tag, ['width' => $variant['width'], 'height' => $variant['height']]);
    }

    /**
     * Take the first settings this rewriter handles. An image carries settings for one screen only.
     *
     * @param array $settings
     * @return array
     */
    private function getImageSettings(array $settings): array
    {
        foreach ($this->settingNames as $name) {
            if (!empty($settings[$name])) {
                return $settings[$name];
            }
        }

        return [];
    }
}
