<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\ContentType;

use Fom\PageBuilderImageResize\Model\ContentType\ResizeAttributeInjector;
use PHPUnit\Framework\TestCase;

class ResizeAttributeInjectorTest extends TestCase
{
    private const SOURCES = [
        'background_images' => [
            'attribute' => 'data-background-images',
            'settings' => [
                'image_resize_desktop' => 'data-image-resize-desktop',
                'image_resize_mobile' => 'data-image-resize-mobile',
            ],
        ],
        'video_fallback' => [
            'attribute' => 'data-video-fallback-src',
            'settings' => ['image_resize_fallback' => 'data-image-resize-fallback'],
        ],
    ];

    /**
     * @return void
     */
    public function testBackgroundElementGetsDesktopAndMobileSettings(): void
    {
        $result = (new ResizeAttributeInjector(self::SOURCES))->inject(
            ['quote' => $this->contentType(['main' => ['background_images' => 'data-background-images']])]
        );
        $appearance = $result['quote']['appearances']['default'];

        $this->assertSame(
            ['background_images', 'image_resize_desktop', 'image_resize_mobile'],
            array_column($appearance['elements']['main']['attributes'], 'var')
        );
        $this->assertSame(
            'data-image-resize-mobile',
            $appearance['elements']['main']['attributes'][2]['name']
        );
        $this->assertSame(
            [
                [
                    'name' => 'image_resize_desktop',
                    'component' => ResizeAttributeInjector::CONVERTER_COMPONENT,
                    'config' => ['attribute_name' => 'image_resize_desktop'],
                ],
                [
                    'name' => 'image_resize_mobile',
                    'component' => ResizeAttributeInjector::CONVERTER_COMPONENT,
                    'config' => ['attribute_name' => 'image_resize_mobile'],
                ],
            ],
            $appearance['converters']
        );
    }

    /**
     * @return void
     */
    public function testVideoFallbackElementGetsFallbackSettings(): void
    {
        $result = (new ResizeAttributeInjector(self::SOURCES))->inject([
            'row' => $this->contentType([
                'main' => [
                    'background_images' => 'data-background-images',
                    'video_fallback_image' => 'data-video-fallback-src',
                ],
            ]),
        ]);

        $this->assertContains(
            'image_resize_fallback',
            array_column($result['row']['appearances']['default']['elements']['main']['attributes'], 'var')
        );
    }

    /**
     * @return void
     */
    public function testContentTypeWithoutSupportedImageIsUnchanged(): void
    {
        $contentTypes = ['text' => $this->contentType(['main' => ['html' => 'data-html']])];

        $this->assertSame($contentTypes, (new ResizeAttributeInjector(self::SOURCES))->inject($contentTypes));
    }

    /**
     * @return void
     */
    public function testExcludedContentTypeIsUnchanged(): void
    {
        $contentTypes = ['quote' => $this->contentType(['main' => ['background_images' => 'data-background-images']])];

        $this->assertSame(
            $contentTypes,
            (new ResizeAttributeInjector(self::SOURCES, ['quote']))->inject($contentTypes)
        );
    }

    /**
     * @return void
     */
    public function testDeclaredAttributeAndConverterAreNotDuplicated(): void
    {
        $contentType = $this->contentType([
            'main' => ['background_images' => 'data-background-images'],
            'inner' => ['image_resize_desktop' => 'data-image-resize-desktop'],
        ]);
        $contentType['appearances']['default']['converters'][] = [
            'name' => 'image_resize_desktop',
            'component' => 'Vendor_Module/js/custom',
            'config' => [],
        ];
        $result = (new ResizeAttributeInjector(self::SOURCES))->inject(['quote' => $contentType]);
        $appearance = $result['quote']['appearances']['default'];

        $this->assertSame(
            ['background_images', 'image_resize_mobile'],
            array_column($appearance['elements']['main']['attributes'], 'var')
        );
        $this->assertSame(
            ['image_resize_desktop', 'image_resize_mobile'],
            array_column($appearance['converters'], 'name')
        );
        $this->assertSame('Vendor_Module/js/custom', $appearance['converters'][0]['component']);
    }

    /**
     * @param array $elements
     * @return array
     */
    private function contentType(array $elements): array
    {
        $config = [];

        foreach ($elements as $elementName => $attributes) {
            foreach ($attributes as $var => $name) {
                $config[$elementName]['attributes'][] = ['var' => $var, 'name' => $name];
            }
        }

        return ['appearances' => ['default' => ['elements' => $config, 'converters' => []]]];
    }
}
