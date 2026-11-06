<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Integration\Model\Image;

use Fom\PageBuilderImageResize\Model\Image\ResizeHash;
use Fom\PageBuilderImageResize\Model\Image\ResizeSettingsNormalizer;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\ImageFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ResizeHashTest extends TestCase
{
    /**
     * @return void
     */
    public function testHashMatchesNativeCatalogImagePath(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $settings = $objectManager->get(ResizeSettingsNormalizer::class)->normalize(
            ['width' => 1920, 'height' => 800]
        );
        $miscParams = $objectManager->get(ParamsBuilder::class)->build($settings + ['type' => 'image']);
        $asset = $objectManager->get(ImageFactory::class)->create(
            ['miscParams' => $miscParams, 'filePath' => '/a/b/test.jpg']
        );

        $this->assertStringContainsString(
            '/cache/' . $objectManager->get(ResizeHash::class)->get($settings) . '/',
            $asset->getPath()
        );
    }
}
