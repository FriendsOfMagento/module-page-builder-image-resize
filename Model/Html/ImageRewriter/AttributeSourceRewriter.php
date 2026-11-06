<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html\ImageRewriter;

use Fom\PageBuilderImageResize\Model\Html\ImageRewriterInterface;
use Fom\PageBuilderImageResize\Model\Html\TagAttributes;
use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;

/**
 * Resizes the image URL stored in one attribute, using the settings saved under one name.
 *
 * @api
 */
class AttributeSourceRewriter implements ImageRewriterInterface
{
    /**
     * @param VariantGenerator $variantGenerator
     * @param TagAttributes $tagAttributes
     * @param string $attribute
     * @param string $settingName
     */
    public function __construct(
        private readonly VariantGenerator $variantGenerator,
        private readonly TagAttributes $tagAttributes,
        private readonly string $attribute,
        private readonly string $settingName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function rewrite(string $tag, array $settings): string
    {
        $imageSettings = $settings[$this->settingName] ?? [];
        $source = $this->tagAttributes->get($tag, $this->attribute);
        $variant = $imageSettings && $source ? $this->variantGenerator->generate($source, $imageSettings) : null;

        return $variant === null ? $tag : $this->tagAttributes->set($tag, $this->attribute, $variant['url']);
    }
}
