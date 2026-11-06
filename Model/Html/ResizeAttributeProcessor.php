<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html;

use InvalidArgumentException;

/**
 * Applies the saved resize settings of every tag in Page Builder HTML through the image rewriters.
 *
 * @api
 */
class ResizeAttributeProcessor
{
    private const ATTRIBUTE_PREFIX = 'data-image-resize-';
    private const TAG_PATTERN = '/<[a-z][a-z0-9-]*+(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>/i';
    private const SETTING_NAME_PATTERN = '/^[a-z0-9-]+$/';

    /**
     * @var ImageRewriterInterface[]
     */
    private readonly array $rewriters;

    /**
     * @param TagAttributes $tagAttributes
     * @param ImageRewriterInterface[] $rewriters
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly TagAttributes $tagAttributes,
        array $rewriters = []
    ) {
        foreach ($rewriters as $code => $rewriter) {
            if (!$rewriter instanceof ImageRewriterInterface) {
                throw new InvalidArgumentException(
                    sprintf('Image rewriter "%s" must implement %s.', $code, ImageRewriterInterface::class)
                );
            }
        }

        $this->rewriters = $rewriters;
    }

    /**
     * Replace image URLs in tags that carry resize settings, and remove the settings from the output.
     *
     * @param string $html
     * @return string
     */
    public function process(string $html): string
    {
        if (stripos($html, self::ATTRIBUTE_PREFIX) === false) {
            return $html;
        }

        return preg_replace_callback(self::TAG_PATTERN, $this->rewriteTag(...), $html) ?? $html;
    }

    /**
     * Rewrite one opening tag.
     *
     * @param array $matches
     * @return string
     */
    private function rewriteTag(array $matches): string
    {
        $tag = $matches[0];

        if (stripos($tag, self::ATTRIBUTE_PREFIX) === false) {
            return $tag;
        }

        $settings = [];

        foreach ($this->tagAttributes->getAll($tag) as $name => $value) {
            if (!str_starts_with($name, self::ATTRIBUTE_PREFIX)) {
                continue;
            }

            $tag = $this->tagAttributes->remove($tag, $name);
            $settingName = substr($name, strlen(self::ATTRIBUTE_PREFIX));
            $decoded = preg_match(self::SETTING_NAME_PATTERN, $settingName) ? $this->tagAttributes->decode($value) : [];

            if ($decoded) {
                $settings[$settingName] = $decoded;
            }
        }

        if (!$settings) {
            return $tag;
        }

        foreach ($this->rewriters as $rewriter) {
            $tag = $rewriter->rewrite($tag, $settings);
        }

        return $tag;
    }
}
