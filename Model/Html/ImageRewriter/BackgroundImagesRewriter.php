<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html\ImageRewriter;

use Fom\PageBuilderImageResize\Model\Html\ImageRewriterInterface;
use Fom\PageBuilderImageResize\Model\Html\TagAttributes;
use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;

class BackgroundImagesRewriter implements ImageRewriterInterface
{
    private const ATTRIBUTE = 'data-background-images';
    private const DESKTOP_IMAGE_KEY = 'desktop_image';
    private const MOBILE_IMAGE_KEY = 'mobile_image';

    /**
     * @param VariantGenerator $variantGenerator
     * @param TagAttributes $tagAttributes
     * @param string $desktopSettingName
     * @param string $mobileSettingName
     */
    public function __construct(
        private readonly VariantGenerator $variantGenerator,
        private readonly TagAttributes $tagAttributes,
        private readonly string $desktopSettingName = 'desktop',
        private readonly string $mobileSettingName = 'mobile'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function rewrite(string $tag, array $settings): string
    {
        $desktopSettings = $settings[$this->desktopSettingName] ?? [];
        $mobileSettings = $settings[$this->mobileSettingName] ?? [];
        $value = $this->tagAttributes->get($tag, self::ATTRIBUTE);

        if ((!$desktopSettings && !$mobileSettings) || $value === null) {
            return $tag;
        }

        $images = $this->tagAttributes->decode($value);
        $resized = $images ? $this->resize($images, $desktopSettings, $mobileSettings) : $images;

        return $resized === $images
            ? $tag
            : $this->tagAttributes->set($tag, self::ATTRIBUTE, $this->tagAttributes->encode($resized));
    }

    /**
     * Resize each screen from its own settings. A screen without settings keeps its original image.
     *
     * @param array $images
     * @param array $desktopSettings
     * @param array $mobileSettings
     * @return array
     */
    private function resize(array $images, array $desktopSettings, array $mobileSettings): array
    {
        $desktopSource = $images[self::DESKTOP_IMAGE_KEY] ?? null;
        $mobileSource = ($images[self::MOBILE_IMAGE_KEY] ?? null) ?: $desktopSource;
        $desktopVariant = $desktopSource && $desktopSettings
            ? $this->variantGenerator->generate($desktopSource, $desktopSettings)
            : null;
        $mobileVariant = $mobileSource && $mobileSettings
            ? $this->variantGenerator->generate($mobileSource, $mobileSettings)
            : null;

        if ($desktopVariant !== null) {
            $images[self::DESKTOP_IMAGE_KEY] = $desktopVariant['url'];
            $images[self::MOBILE_IMAGE_KEY] = $mobileSource;
        }

        if ($mobileVariant !== null) {
            $images[self::MOBILE_IMAGE_KEY] = $mobileVariant['url'];
        }

        return $images;
    }
}
