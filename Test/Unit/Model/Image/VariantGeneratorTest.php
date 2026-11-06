<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Image;

use Fom\PageBuilderImageResize\Model\Image\MediaUrlResolver;
use Fom\PageBuilderImageResize\Model\Image\ResizeHash;
use Fom\PageBuilderImageResize\Model\Image\ResizeSettingsNormalizer;
use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Math\Random;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VariantGeneratorTest extends TestCase
{
    private const BASE_URL = 'https://x.test/media/';
    private const URL = self::BASE_URL . 'wysiwyg/a.png';
    private const SOURCE = 'wysiwyg/a.png';
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42'
        . 'mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    private const MAX_PIXELS = 100;

    /**
     * @param array $settings
     * @return void
     * @dataProvider tooLargeProvider
     */
    #[DataProvider('tooLargeProvider')]
    public function testResultAbovePixelLimitKeepsTheOriginal(array $settings): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Page Builder image resize skipped, result too large: ' . self::SOURCE);
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');
        $imageFactory = $this->createMock(ImageFactory::class);
        $imageFactory->expects($this->never())->method('create');
        $generator = $this->createGenerator($logger, $lockManager, $imageFactory);

        $this->assertNull($generator->generate(self::URL, $settings));
    }

    /**
     * @return array
     */
    public static function tooLargeProvider(): array
    {
        return [
            'frame' => [['width' => 11, 'height' => 10, 'frame' => 1]],
            'frame with one side is square' => [['width' => 11, 'frame' => 1]],
            'upscale' => [['width' => 101, 'height' => 1, 'constrain' => 0]],
            'one side upscaled in proportion' => [['width' => 11, 'constrain' => 0]],
        ];
    }

    /**
     * @return void
     */
    public function testResultWithinPixelLimitIsWritten(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())->method('lock')->willReturn(false);
        $generator = $this->createGenerator(
            $this->createStub(LoggerInterface::class),
            $lockManager,
            $this->createStub(ImageFactory::class)
        );

        $this->assertNull($generator->generate(self::URL, ['width' => 10, 'height' => 10, 'frame' => 1]));
    }

    /**
     * @param LoggerInterface $logger
     * @param LockManagerInterface $lockManager
     * @param ImageFactory $imageFactory
     * @return VariantGenerator
     */
    private function createGenerator(
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        ImageFactory $imageFactory
    ): VariantGenerator {
        $mediaUrlResolver = $this->createStub(MediaUrlResolver::class);
        $mediaUrlResolver->method('resolve')->willReturn(['base_url' => self::BASE_URL, 'path' => self::SOURCE]);
        $resizeHash = $this->createStub(ResizeHash::class);
        $resizeHash->method('get')->willReturn('hash');
        $directory = $this->createStub(WriteInterface::class);
        $directory->method('isFile')->willReturnCallback(static fn (string $path): bool => $path === self::SOURCE);
        $directory->method('readFile')->willReturn(base64_decode(self::PNG_1X1));
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);

        return new VariantGenerator(
            $mediaUrlResolver,
            new ResizeSettingsNormalizer(),
            $resizeHash,
            $this->createStub(ParamsBuilder::class),
            $imageFactory,
            $filesystem,
            $lockManager,
            $this->createStub(Random::class),
            $logger,
            self::MAX_PIXELS
        );
    }
}
