<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Image;

use Fom\PageBuilderImageResize\Model\Image\ResizeSettingsNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResizeSettingsNormalizerTest extends TestCase
{
    /**
     * @var ResizeSettingsNormalizer
     */
    private ResizeSettingsNormalizer $normalizer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->normalizer = new ResizeSettingsNormalizer();
    }

    /**
     * @return void
     */
    public function testDefaults(): void
    {
        $this->assertSame(
            [
                'width' => null,
                'height' => null,
                'aspect_ratio' => true,
                'frame' => false,
                'constrain' => true,
                'transparency' => true,
                'background' => [255, 255, 255],
            ],
            $this->normalizer->normalize([])
        );
    }

    /**
     * @param mixed $value
     * @param int|null $expected
     * @return void
     * @dataProvider dimensionProvider
     */
    #[DataProvider('dimensionProvider')]
    public function testDimensions(mixed $value, ?int $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize(['width' => $value])['width']);
    }

    /**
     * @return array
     */
    public static function dimensionProvider(): array
    {
        return [
            'integer' => [800, 800],
            'string' => ['800', 800],
            'empty' => ['', null],
            'null' => [null, null],
            'zero' => [0, null],
            'negative' => ['-5', null],
            'text' => ['wide', null],
        ];
    }

    /**
     * @param mixed $value
     * @param bool $expected
     * @return void
     * @dataProvider flagProvider
     */
    #[DataProvider('flagProvider')]
    public function testFlags(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize(['frame' => $value])['frame']);
    }

    /**
     * @return array
     */
    public static function flagProvider(): array
    {
        return [
            'true' => [true, true],
            'one' => [1, true],
            'one string' => ['1', true],
            'false' => [false, false],
            'zero' => [0, false],
            'zero string' => ['0', false],
            'empty takes default' => ['', false],
        ];
    }

    /**
     * @param mixed $value
     * @param array $expected
     * @return void
     * @dataProvider backgroundProvider
     */
    #[DataProvider('backgroundProvider')]
    public function testBackground(mixed $value, array $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize(['background' => $value])['background']);
    }

    /**
     * @return array
     */
    public static function backgroundProvider(): array
    {
        return [
            'short hex' => ['#FFF', [255, 255, 255]],
            'long hex' => ['#10a0ff', [16, 160, 255]],
            'hex without hash' => ['000000', [0, 0, 0]],
            'empty' => ['', [255, 255, 255]],
            'invalid text' => ['red', [255, 255, 255]],
            'array' => [[1, 2, 3], [255, 255, 255]],
        ];
    }

    /**
     * @return void
     */
    public function testUnknownKeysAreDropped(): void
    {
        $normalized = $this->normalizer->normalize(['preset' => 'hero', 'type' => 'image', 'width' => 10]);

        $this->assertArrayNotHasKey('preset', $normalized);
        $this->assertArrayNotHasKey('type', $normalized);
    }

    /**
     * @return void
     */
    public function testIsEnabled(): void
    {
        $this->assertFalse($this->normalizer->isEnabled($this->normalizer->normalize([])));
        $this->assertTrue($this->normalizer->isEnabled($this->normalizer->normalize(['height' => 10])));
    }
}
