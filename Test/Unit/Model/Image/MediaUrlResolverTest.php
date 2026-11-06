<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Image;

use Fom\PageBuilderImageResize\Model\Image\MediaUrlResolver;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaUrlResolverTest extends TestCase
{
    private const SECURE_BASE = 'https://example.test/media/';
    private const UNSECURE_BASE = 'http://example.test/media/';

    /**
     * @var ReadInterface
     */
    private ReadInterface $mediaDirectory;

    /**
     * @var MediaUrlResolver
     */
    private MediaUrlResolver $resolver;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type, bool $secure): string => $secure ? self::SECURE_BASE : self::UNSECURE_BASE
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->mediaDirectory = $this->createStub(ReadInterface::class);
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($this->mediaDirectory);

        $this->resolver = new MediaUrlResolver($storeManager, $filesystem, ['downloadable', 'customer/']);
    }

    /**
     * @param string $url
     * @param array|null $expected
     * @return void
     * @dataProvider urlProvider
     */
    #[DataProvider('urlProvider')]
    public function testResolve(string $url, ?array $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($url));
    }

    /**
     * @return array
     */
    public static function urlProvider(): array
    {
        return [
            'secure' => [
                self::SECURE_BASE . 'wysiwyg/a.jpg',
                ['base_url' => self::SECURE_BASE, 'path' => 'wysiwyg/a.jpg'],
            ],
            'unsecure' => [
                self::UNSECURE_BASE . 'wysiwyg/a.jpg',
                ['base_url' => self::UNSECURE_BASE, 'path' => 'wysiwyg/a.jpg'],
            ],
            'encoded space' => [
                self::SECURE_BASE . 'wysiwyg/a%20b.jpg',
                ['base_url' => self::SECURE_BASE, 'path' => 'wysiwyg/a b.jpg'],
            ],
            'literal plus' => [
                self::SECURE_BASE . 'wysiwyg/a+b.jpg',
                ['base_url' => self::SECURE_BASE, 'path' => 'wysiwyg/a+b.jpg'],
            ],
            'query string' => [
                self::SECURE_BASE . 'wysiwyg/a.jpg?v=1',
                ['base_url' => self::SECURE_BASE, 'path' => 'wysiwyg/a.jpg'],
            ],
            'other host' => ['https://cdn.test/media/wysiwyg/a.jpg', null],
            'root relative' => ['/media/wysiwyg/a.jpg', null],
            'traversal' => [self::SECURE_BASE . 'wysiwyg/../../app/etc/env.php', null],
            'encoded traversal' => [self::SECURE_BASE . 'wysiwyg/%2e%2e/%2e%2e/env.php', null],
            'encoded backslash' => [self::SECURE_BASE . 'wysiwyg%5Ca.jpg', null],
            'encoded null byte' => [self::SECURE_BASE . 'wysiwyg/a%00.jpg', null],
            'empty path' => [self::SECURE_BASE, null],
            'current folder segment' => [self::SECURE_BASE . './downloadable/a.jpg', null],
            'empty segment' => [self::SECURE_BASE . 'wysiwyg//a.jpg', null],
            'excluded folder' => [self::SECURE_BASE . 'downloadable/files/links/a.jpg', null],
            'excluded folder with trailing slash in config' => [self::SECURE_BASE . 'customer/a/b/id.jpg', null],
            'excluded folder in other case' => [self::SECURE_BASE . 'Downloadable/files/a.jpg', null],
            'excluded folder rendition' => [self::SECURE_BASE . '.renditions/downloadable/a.jpg', null],
            'folder that only starts like an excluded one' => [
                self::SECURE_BASE . 'downloadables/a.jpg',
                ['base_url' => self::SECURE_BASE, 'path' => 'downloadables/a.jpg'],
            ],
        ];
    }

    /**
     * @return void
     */
    public function testRenditionUsesExistingOriginal(): void
    {
        $this->mediaDirectory->method('isFile')->willReturnCallback(
            static fn (string $path): bool => $path === 'wysiwyg/a.jpg'
        );

        $this->assertSame(
            'wysiwyg/a.jpg',
            $this->resolver->resolve(self::SECURE_BASE . '.renditions/wysiwyg/a.jpg')['path']
        );
    }

    /**
     * @return void
     */
    public function testRenditionIsKeptWithoutOriginal(): void
    {
        $this->mediaDirectory->method('isFile')->willReturn(false);

        $this->assertSame(
            '.renditions/wysiwyg/a.jpg',
            $this->resolver->resolve(self::SECURE_BASE . '.renditions/wysiwyg/a.jpg')['path']
        );
    }

    /**
     * @return void
     */
    public function testGetUrlEncodesSegments(): void
    {
        $this->assertSame(
            self::SECURE_BASE . 'pagebuilder/cache/abc/a%20b.jpg',
            $this->resolver->getUrl(self::SECURE_BASE, 'pagebuilder/cache/abc/a b.jpg')
        );
    }
}
