<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Image;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class MediaUrlResolver
{
    private const RENDITIONS_PREFIX = '.renditions/';
    private const URL_SUFFIX_PATTERN = '/[?#].*$/s';
    private const UNSAFE_PATH_PATTERN = '#(^|/)\.{1,2}(/|$)|//|\\\\|\x00#';

    /**
     * Excluded paths are media folders the web server keeps private, so no public copy may be made of their files.
     *
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param string[] $excludedPaths
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly array $excludedPaths = []
    ) {
    }

    /**
     * Split a store media URL into its base URL and media relative path.
     *
     * @param string $url
     * @return array|null
     */
    public function resolve(string $url): ?array
    {
        $url = (string)preg_replace(self::URL_SUFFIX_PATTERN, '', $url);

        foreach ($this->getMediaBaseUrls() as $baseUrl) {
            if (str_starts_with($url, $baseUrl)) {
                $path = urldecode(str_replace('+', '%2B', substr($url, strlen($baseUrl))));

                if (!$this->isSafe($path)) {
                    return null;
                }

                $path = $this->preferOriginal($path);

                return $this->isExcluded($path) ? null : ['base_url' => $baseUrl, 'path' => $path];
            }
        }

        return null;
    }

    /**
     * Build a URL for a media relative path.
     *
     * @param string $baseUrl
     * @param string $path
     * @return string
     */
    public function getUrl(string $baseUrl, string $path): string
    {
        return $baseUrl . implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * Get the secure and unsecure media URLs of the current store.
     *
     * @return array
     */
    private function getMediaBaseUrls(): array
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();

        return array_unique([
            $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true),
            $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, false),
        ]);
    }

    /**
     * Check that a path stays inside the media folder.
     *
     * @param string $path
     * @return bool
     */
    private function isSafe(string $path): bool
    {
        return $path !== '' && !str_starts_with($path, '/') && !preg_match(self::UNSAFE_PATH_PATTERN, $path);
    }

    /**
     * Check whether a path, or the original of a gallery rendition, is in an excluded folder.
     *
     * @param string $path
     * @return bool
     */
    private function isExcluded(string $path): bool
    {
        $path = strtolower($path);

        if (str_starts_with($path, self::RENDITIONS_PREFIX)) {
            $path = substr($path, strlen(self::RENDITIONS_PREFIX));
        }

        foreach ($this->excludedPaths as $excludedPath) {
            $excludedPath = strtolower(trim($excludedPath, '/'));

            if ($path === $excludedPath || str_starts_with($path, $excludedPath . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Use the original file instead of a gallery rendition when it exists.
     *
     * @param string $path
     * @return string
     */
    private function preferOriginal(string $path): string
    {
        if (!str_starts_with($path, self::RENDITIONS_PREFIX)) {
            return $path;
        }

        $original = substr($path, strlen(self::RENDITIONS_PREFIX));

        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile($original) ? $original : $path;
    }
}
