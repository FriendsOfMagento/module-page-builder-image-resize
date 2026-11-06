<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Image;

use Fom\PageBuilderImageResize\Model\Image\ResizeHash;
use Fom\PageBuilderImageResize\Model\Image\ResizeSettingsNormalizer;
use Magento\Catalog\Model\Product\Image;
use Magento\Catalog\Model\Product\Image\ConvertImageMiscParamsToReadableFormat;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Config\View;
use Magento\Framework\View\ConfigInterface;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResizeHashTest extends TestCase
{
    private const GOLDEN_HASH = '10b9aa821d255e6f9aef01c2368d80a3';
    private const BASE_SETTINGS = [
        'width' => 1920,
        'height' => 800,
        'aspect_ratio' => true,
        'frame' => false,
        'constrain' => true,
        'transparency' => true,
        'background' => [255, 255, 255],
    ];

    /**
     * @var ParamsBuilder
     */
    private ParamsBuilder $paramsBuilder;

    /**
     * @var ResizeHash
     */
    private ResizeHash $resizeHash;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $path === Image::XML_PATH_JPEG_QUALITY ? '80' : null
        );
        $view = $this->createStub(View::class);
        $view->method('getVarValue')->willReturn('1');
        $viewConfig = $this->createStub(ConfigInterface::class);
        $viewConfig->method('getViewConfig')->willReturn($view);
        $themeFactory = $this->createStub(FlyweightFactory::class);
        $themeFactory->method('create')->willReturn($this->createStub(ThemeInterface::class));
        $design = $this->createStub(DesignInterface::class);
        $design->method('getConfigurationDesignTheme')->willReturn('1');

        $this->paramsBuilder = new ParamsBuilder(
            $scopeConfig,
            $viewConfig,
            $design,
            $themeFactory
        );
        $this->resizeHash = new ResizeHash($this->paramsBuilder, new ConvertImageMiscParamsToReadableFormat());
    }

    /**
     * @return void
     */
    public function testGoldenHash(): void
    {
        $this->assertSame(self::GOLDEN_HASH, $this->resizeHash->get(self::BASE_SETTINGS));
    }

    /**
     * @param array $changes
     * @param array $removed
     * @param string $expectedStart
     * @return void
     * @dataProvider rawValuesProvider
     */
    #[DataProvider('rawValuesProvider')]
    public function testRawValues(array $changes, array $removed, string $expectedStart): void
    {
        $settings = array_diff_key(array_merge(self::BASE_SETTINGS, $changes), array_flip($removed));

        $this->assertStringStartsWith($expectedStart, $this->resizeHash->get($settings));
    }

    /**
     * @return array
     */
    public static function rawValuesProvider(): array
    {
        return [
            'form strings' => [
                [
                    'width' => '1920',
                    'height' => '800',
                    'aspect_ratio' => '1',
                    'frame' => '0',
                    'constrain' => '1',
                    'transparency' => '1',
                ],
                [],
                '10b9aa82',
            ],
            'with type' => [['type' => 'image'], [], '10b9aa82'],
            'background left out' => [[], ['background'], '10b9aa82'],
            'empty height string' => [['height' => ''], [], '54958c70'],
            'null height' => [['height' => null], [], '0942687e'],
            'false string frame' => [['frame' => 'false'], [], 'ada6e57a'],
            'frame left out uses theme' => [[], ['frame'], 'ada6e57a'],
            'empty background' => [['background' => []], [], '8bade41c'],
        ];
    }

    /**
     * @return void
     */
    public function testKeyOrderDoesNotChangeHash(): void
    {
        $this->assertSame(
            self::GOLDEN_HASH,
            $this->resizeHash->get(array_reverse(self::BASE_SETTINGS, true))
        );
    }

    /**
     * @param array $settings
     * @return void
     * @dataProvider normalizedValuesProvider
     */
    #[DataProvider('normalizedValuesProvider')]
    public function testNormalizedValuesGiveGoldenHash(array $settings): void
    {
        $normalized = (new ResizeSettingsNormalizer())->normalize($settings);

        $this->assertSame(self::GOLDEN_HASH, $this->resizeHash->get($normalized));
    }

    /**
     * @return array
     */
    public static function normalizedValuesProvider(): array
    {
        return [
            'only sizes' => [['width' => 1920, 'height' => 800]],
            'form strings' => [['width' => '1920', 'height' => '800', 'frame' => 'false', 'background' => '']],
            'hex background' => [['width' => 1920, 'height' => 800, 'background' => '#FFF']],
            'preset is dropped' => [['preset' => 'hero_desktop', 'width' => 1920, 'height' => 800]],
            'type is dropped' => [['type' => 'image', 'width' => 1920, 'height' => 800]],
        ];
    }
}
