<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Image;

use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Math\Random;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Generates resized variants of media images with the native catalog image adapter.
 *
 * @api
 */
class VariantGenerator
{
    public const CACHE_PATH = 'pagebuilder/cache';

    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

    /**
     * @var array
     */
    private array $results = [];

    /**
     * @param MediaUrlResolver $mediaUrlResolver
     * @param ResizeSettingsNormalizer $normalizer
     * @param ResizeHash $resizeHash
     * @param ParamsBuilder $paramsBuilder
     * @param ImageFactory $imageFactory
     * @param Filesystem $filesystem
     * @param LockManagerInterface $lockManager
     * @param Random $random
     * @param LoggerInterface $logger
     * @param int $maxPixels
     * @param int $lockTimeout
     */
    public function __construct(
        private readonly MediaUrlResolver $mediaUrlResolver,
        private readonly ResizeSettingsNormalizer $normalizer,
        private readonly ResizeHash $resizeHash,
        private readonly ParamsBuilder $paramsBuilder,
        private readonly ImageFactory $imageFactory,
        private readonly Filesystem $filesystem,
        private readonly LockManagerInterface $lockManager,
        private readonly Random $random,
        private readonly LoggerInterface $logger,
        private readonly int $maxPixels = 40000000,
        private readonly int $lockTimeout = 10
    ) {
    }

    /**
     * Get the resized variant of a media URL, or null when the original must be kept.
     *
     * @param string $url
     * @param array $settings
     * @return array|null
     */
    public function generate(string $url, array $settings): ?array
    {
        $settings = $this->normalizer->normalize($settings);

        if (!$this->normalizer->isEnabled($settings)) {
            return null;
        }

        $key = $url . '|' . $this->resizeHash->get($settings);

        if (!array_key_exists($key, $this->results)) {
            $this->results[$key] = $this->generateSafely($url, $settings);
        }

        return $this->results[$key];
    }

    /**
     * Keep the original URL on any failure, because an image must never break the page.
     *
     * @param string $url
     * @param array $settings
     * @return array|null
     */
    private function generateSafely(string $url, array $settings): ?array
    {
        try {
            $media = $this->mediaUrlResolver->resolve($url);

            if ($media === null) {
                $this->logger->warning(
                    sprintf('Page Builder image resize skipped, not an allowed media URL: %s', $url)
                );

                return null;
            }

            return $this->getVariant($media['base_url'], $media['path'], $settings);
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf('Page Builder image resize failed for %s: %s', $url, $exception->getMessage())
            );

            return null;
        }
    }

    /**
     * Reuse a fresh variant or create a new one.
     *
     * @param string $baseUrl
     * @param string $source
     * @param array $settings
     * @return array|null
     */
    private function getVariant(string $baseUrl, string $source, array $settings): ?array
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        if (!$directory->isFile($source)) {
            $this->logger->warning(sprintf('Page Builder image resize skipped, missing file: %s', $source));

            return null;
        }

        $variant = sprintf('%s/%s/%s', self::CACHE_PATH, $this->resizeHash->get($settings), $source);

        if (!$this->isFresh($directory, $source, $variant)
            && !$this->createVariant($directory, $source, $variant, $settings)
        ) {
            return null;
        }

        $size = $this->getImageSize($directory, $variant);

        return [
            'url' => $this->mediaUrlResolver->getUrl($baseUrl, $variant),
            'width' => (int)$size[0],
            'height' => (int)$size[1],
        ];
    }

    /**
     * Check that a variant exists and is not older than its source.
     *
     * @param WriteInterface $directory
     * @param string $source
     * @param string $variant
     * @return bool
     */
    private function isFresh(WriteInterface $directory, string $source, string $variant): bool
    {
        return $directory->isFile($variant)
            && (int)$directory->stat($variant)['mtime'] >= (int)$directory->stat($source)['mtime'];
    }

    /**
     * Validate the source and write the variant under a lock.
     *
     * @param WriteInterface $directory
     * @param string $source
     * @param string $variant
     * @param array $settings
     * @return bool
     */
    private function createVariant(
        WriteInterface $directory,
        string $source,
        string $variant,
        array $settings
    ): bool {
        if (!$this->canResize($directory, $source, $settings)) {
            return false;
        }

        $lockName = hash('md5', $variant);

        if (!$this->lockManager->lock($lockName, $this->lockTimeout)) {
            $this->logger->warning(sprintf('Page Builder image resize skipped, file is locked: %s', $variant));

            return false;
        }

        try {
            if (!$this->isFresh($directory, $source, $variant)) {
                $this->writeVariant($directory, $source, $variant, $settings);
            }
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return true;
    }

    /**
     * Check the source type, the pixel limit of the source and of the result, and whether resizing would help.
     *
     * @param WriteInterface $directory
     * @param string $source
     * @param array $settings
     * @return bool
     */
    private function canResize(WriteInterface $directory, string $source, array $settings): bool
    {
        [$width, $height, $type] = $this->getImageSize($directory, $source);

        if (!in_array($type, self::ALLOWED_IMAGE_TYPES, true)) {
            $this->logger->warning(sprintf('Page Builder image resize skipped, unsupported type: %s', $source));

            return false;
        }

        if ($width * $height > $this->maxPixels) {
            $this->logger->warning(sprintf('Page Builder image resize skipped, source too large: %s', $source));

            return false;
        }

        if ($this->alreadyFits($width, $height, $settings)) {
            return false;
        }

        if ($this->getResultPixels($width, $height, $settings) > $this->maxPixels) {
            $this->logger->warning(sprintf('Page Builder image resize skipped, result too large: %s', $source));

            return false;
        }

        return true;
    }

    /**
     * Get the largest number of pixels the result can have, the way the image adapter sizes it.
     *
     * A frame with one side left empty is square. Without a frame, an empty side follows the source proportions.
     *
     * @param int $width
     * @param int $height
     * @param array $settings
     * @return float
     */
    private function getResultPixels(int $width, int $height, array $settings): float
    {
        if ($settings['frame']) {
            $frameWidth = $settings['width'] ?? $settings['height'];
            $frameHeight = $settings['height'] ?? $settings['width'];

            return (float)$frameWidth * (float)$frameHeight;
        }

        if ($settings['constrain']) {
            return (float)($width * $height);
        }

        $targetWidth = $settings['width'] ?? ($height ? $settings['height'] * $width / $height : 0);
        $targetHeight = $settings['height'] ?? ($width ? $settings['width'] * $height / $width : 0);

        return (float)$targetWidth * (float)$targetHeight;
    }

    /**
     * Check whether the source is already within a target it could not be enlarged to.
     *
     * @param int $width
     * @param int $height
     * @param array $settings
     * @return bool
     */
    private function alreadyFits(int $width, int $height, array $settings): bool
    {
        return $settings['constrain']
            && !$settings['frame']
            && ($settings['width'] === null || $width <= $settings['width'])
            && ($settings['height'] === null || $height <= $settings['height']);
    }

    /**
     * Write to a temporary name first, so nobody can receive a partial file.
     *
     * @param WriteInterface $directory
     * @param string $source
     * @param string $variant
     * @param array $settings
     * @return void
     */
    private function writeVariant(
        WriteInterface $directory,
        string $source,
        string $variant,
        array $settings
    ): void {
        $temporary = sprintf('%s.%s.tmp', $variant, $this->random->getRandomString(8));
        $params = $this->paramsBuilder->build($settings);

        try {
            $image = $this->imageFactory->create($directory->getAbsolutePath($source));
            $image->keepAspectRatio($params['keep_aspect_ratio']);
            $image->keepFrame($params['keep_frame']);
            $image->keepTransparency($params['keep_transparency']);
            $image->constrainOnly($params['constrain_only']);
            $image->backgroundColor($params['background']);
            $image->quality($params['quality']);
            $image->resize($params['image_width'], $params['image_height']);
            $image->save($directory->getAbsolutePath($temporary));
            $directory->renameFile($temporary, $variant);
        } finally {
            if ($directory->isFile($temporary)) {
                $directory->delete($temporary);
            }
        }
    }

    /**
     * Read the width, height and type of a media file.
     *
     * @param WriteInterface $directory
     * @param string $path
     * @return array
     */
    private function getImageSize(WriteInterface $directory, string $path): array
    {
        $size = getimagesizefromstring($directory->readFile($path));

        return $size === false ? [0, 0, 0] : [(int)$size[0], (int)$size[1], (int)$size[2]];
    }
}
