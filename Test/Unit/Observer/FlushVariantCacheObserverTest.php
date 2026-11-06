<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Observer;

use Fom\PageBuilderImageResize\Observer\FlushVariantCacheObserver;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\TestCase;

class FlushVariantCacheObserverTest extends TestCase
{
    /**
     * @return void
     */
    public function testDeletesVariantsThenCleansCaches(): void
    {
        $calls = [];
        $directory = $this->createStub(WriteInterface::class);
        $directory->method('delete')->willReturnCallback(
            static function (string $path) use (&$calls): bool {
                $calls[] = 'delete:' . $path;

                return true;
            }
        );
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);
        $cacheTypeList = $this->createStub(TypeListInterface::class);
        $cacheTypeList->method('cleanType')->willReturnCallback(
            static function (string $type) use (&$calls): void {
                $calls[] = 'clean:' . $type;
            }
        );

        (new FlushVariantCacheObserver($filesystem, $cacheTypeList))->execute(new Observer());

        $this->assertSame(['delete:pagebuilder/cache', 'clean:full_page', 'clean:block_html'], $calls);
    }
}
