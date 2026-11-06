<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Observer;

use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;

class FlushVariantCacheObserver implements ObserverInterface
{
    private const CACHE_TYPES = ['full_page', 'block_html'];

    /**
     * @param Filesystem $filesystem
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * Delete all variants, then the cached pages that point at them.
     *
     * @param Observer $observer
     * @return void
     * @throws FileSystemException
     */
    public function execute(Observer $observer): void
    {
        $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->delete(VariantGenerator::CACHE_PATH);

        foreach (self::CACHE_TYPES as $cacheType) {
            $this->cacheTypeList->cleanType($cacheType);
        }
    }
}
