<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html;

/**
 * Rewrites the image URL of one kind of image holder in an opening tag.
 *
 * @api
 */
interface ImageRewriterInterface
{
    /**
     * Point the image of the tag at its resized variant.
     *
     * The settings are keyed by the attribute suffix they were saved under, for example "desktop" for
     * data-image-resize-desktop. The resize attributes are already removed from the tag.
     *
     * @param string $tag
     * @param array $settings
     * @return string
     */
    public function rewrite(string $tag, array $settings): string;
}
