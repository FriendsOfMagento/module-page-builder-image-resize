<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Html;

use Fom\PageBuilderImageResize\Model\Html\ResizeAttributeProcessor;
use Fom\PageBuilderImageResize\Model\Html\ImageRewriterInterface;
use Fom\PageBuilderImageResize\Model\Html\ImageRewriter\AttributeSourceRewriter;
use Fom\PageBuilderImageResize\Model\Html\ImageRewriter\BackgroundImagesRewriter;
use Fom\PageBuilderImageResize\Model\Html\ImageRewriter\ImageSourceRewriter;
use Fom\PageBuilderImageResize\Model\Html\TagAttributes;
use Fom\PageBuilderImageResize\Model\Image\VariantGenerator;
use InvalidArgumentException;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

class ResizeAttributeProcessorTest extends TestCase
{
    private const DESKTOP_URL = 'https://x.test/media/wysiwyg/d.jpg';
    private const MOBILE_URL = 'https://x.test/media/wysiwyg/m.jpg';
    private const FALLBACK_URL = 'https://x.test/media/wysiwyg/f.jpg';
    private const SETTINGS_100 = '{\&quot;width\&quot;:100}';
    private const SETTINGS_50 = '{\&quot;width\&quot;:50}';

    /**
     * @var VariantGenerator
     */
    private VariantGenerator $variantGenerator;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ResizeAttributeProcessor
     */
    private ResizeAttributeProcessor $processor;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->variantGenerator = $this->createStub(VariantGenerator::class);
        $this->variantGenerator->method('generate')->willReturnCallback(
            static fn (string $url, array $settings): array => [
                'url' => str_replace('/media/', '/media/v' . $settings['width'] . '/', $url),
                'width' => (int)$settings['width'],
                'height' => 20,
            ]
        );
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->processor = $this->createProcessor($this->variantGenerator);
    }

    /**
     * @return void
     */
    public function testHtmlWithoutSettingsIsUntouched(): void
    {
        $html = '<div class="pagebuilder-mobile-only"><img src="' . self::DESKTOP_URL . '" alt="a > b"></div>';

        $this->assertSame($html, $this->createProcessorThatNeverResizes()->process($html));
    }

    /**
     * @return void
     */
    public function testDesktopSettingsKeepTheMobileImageOriginal(): void
    {
        $html = $this->desktopImage(self::DESKTOP_URL, self::SETTINGS_100)
            . $this->mobileImage(self::DESKTOP_URL);

        $this->assertSame(
            '<img width="100" height="20" class="pagebuilder-mobile-hidden"'
            . ' src="https://x.test/media/v100/wysiwyg/d.jpg" alt="a > b">'
            . '<img class="pagebuilder-mobile-only" src="' . self::DESKTOP_URL . '">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testMobileSettingsResizeMobileImageOnly(): void
    {
        $result = $this->processor->process(
            $this->desktopImage(self::DESKTOP_URL, '')
            . $this->mobileImage(self::MOBILE_URL, self::SETTINGS_50)
        );

        $this->assertStringContainsString('src="' . self::DESKTOP_URL . '"', $result);
        $this->assertStringContainsString('v50/wysiwyg/m.jpg', $result);
        $this->assertStringNotContainsString('data-image-resize', $result);
    }

    /**
     * @return void
     */
    public function testBothImagesUseTheirOwnSettings(): void
    {
        $result = $this->processor->process(
            $this->desktopImage(self::DESKTOP_URL, self::SETTINGS_100)
            . $this->mobileImage(self::DESKTOP_URL, self::SETTINGS_50)
        );

        $this->assertStringContainsString('v100/wysiwyg/d.jpg', $result);
        $this->assertStringContainsString('v50/wysiwyg/d.jpg', $result);
    }

    /**
     * @return void
     */
    public function testNativeSizeAttributesAreKept(): void
    {
        $html = '<img width="300" class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '"'
            . ' data-image-resize-desktop="' . self::SETTINGS_100 . '">';

        $this->assertSame(
            '<img width="300" class="pagebuilder-mobile-hidden" src="https://x.test/media/v100/wysiwyg/d.jpg">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testEmptyNativeSizeAttributesAreReplaced(): void
    {
        $html = '<img class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '" width="" height=""'
            . ' data-image-resize-desktop="' . self::SETTINGS_100 . '">';

        $this->assertSame(
            '<img width="100" height="20" class="pagebuilder-mobile-hidden"'
            . ' src="https://x.test/media/v100/wysiwyg/d.jpg">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testBackgroundKeepsSavedFormatAndOriginalOnMobile(): void
    {
        $html = '<div data-image-resize-desktop="' . self::SETTINGS_100 . '" data-background-images="'
            . $this->backgroundJson(['desktop_image' => self::DESKTOP_URL]) . '" data-element="main">';

        $this->assertSame(
            '<div data-background-images="{\&quot;desktop_image\&quot;:'
            . '\&quot;https://x.test/media/v100/wysiwyg/d.jpg\&quot;,\&quot;mobile_image\&quot;:'
            . '\&quot;https://x.test/media/wysiwyg/d.jpg\&quot;}" data-element="main">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testBackgroundMobileSettingsWithoutMobileImageResizeTheDesktopFile(): void
    {
        $html = '<div data-image-resize-mobile="' . self::SETTINGS_50 . '" data-background-images="'
            . $this->backgroundJson(['desktop_image' => self::DESKTOP_URL]) . '">';

        $this->assertSame(
            '<div data-background-images="{\&quot;desktop_image\&quot;:'
            . '\&quot;https://x.test/media/wysiwyg/d.jpg\&quot;,\&quot;mobile_image\&quot;:'
            . '\&quot;https://x.test/media/v50/wysiwyg/d.jpg\&quot;}">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testBackgroundWithBothSettingsUsesOneVariantPerScreen(): void
    {
        $html = '<div data-image-resize-desktop="' . self::SETTINGS_100 . '"'
            . ' data-image-resize-mobile="' . self::SETTINGS_50 . '" data-background-images="'
            . $this->backgroundJson(['desktop_image' => self::DESKTOP_URL, 'mobile_image' => self::MOBILE_URL])
            . '">';
        $result = $this->processor->process($html);

        $this->assertStringContainsString('desktop_image\&quot;:\&quot;https://x.test/media/v100/', $result);
        $this->assertStringContainsString('mobile_image\&quot;:\&quot;https://x.test/media/v50/wysiwyg/m', $result);
    }

    /**
     * @return void
     */
    public function testBackgroundDesktopSettingsKeepTheUploadedMobileImageOriginal(): void
    {
        $html = '<div data-image-resize-desktop="' . self::SETTINGS_100 . '" data-background-images="'
            . $this->backgroundJson(['desktop_image' => self::DESKTOP_URL, 'mobile_image' => self::MOBILE_URL])
            . '">';
        $result = $this->processor->process($html);

        $this->assertStringContainsString('mobile_image\&quot;:\&quot;' . self::MOBILE_URL, $result);
    }

    /**
     * @return void
     */
    public function testVideoFallbackImageIsResized(): void
    {
        $html = '<div data-video-fallback-src="' . self::FALLBACK_URL . '"'
            . ' data-image-resize-fallback="' . self::SETTINGS_100 . '" data-background-images="{}">';

        $this->assertSame(
            '<div data-video-fallback-src="https://x.test/media/v100/wysiwyg/f.jpg" data-background-images="{}">',
            $this->processor->process($html)
        );
    }

    /**
     * @return void
     */
    public function testEmptyVideoFallbackIsKept(): void
    {
        $html = '<div data-video-fallback-src="" data-image-resize-fallback="' . self::SETTINGS_100 . '">';

        $this->assertSame('<div data-video-fallback-src="">', $this->createProcessorThatNeverResizes()->process($html));
    }

    /**
     * @return void
     */
    public function testUnknownSettingsAreRemovedWithoutResizing(): void
    {
        $html = '<img src="' . self::DESKTOP_URL . '" data-image-resize-custom="' . self::SETTINGS_100 . '">';

        $this->assertSame(
            '<img src="' . self::DESKTOP_URL . '">',
            $this->createProcessorThatNeverResizes()->process($html)
        );
    }

    /**
     * @return void
     */
    public function testCustomRewriterReceivesItsSettings(): void
    {
        $rewriter = $this->createMock(ImageRewriterInterface::class);
        $rewriter->expects($this->once())
            ->method('rewrite')
            ->with('<a href="#">', ['custom' => ['width' => 100]])
            ->willReturn('<a href="#resized">');
        $processor = new ResizeAttributeProcessor($this->createTagAttributes(), ['custom' => $rewriter]);

        $this->assertSame(
            '<a href="#resized">',
            $processor->process('<a href="#" data-image-resize-custom="' . self::SETTINGS_100 . '">')
        );
    }

    /**
     * @return void
     */
    public function testInvalidRewriterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResizeAttributeProcessor($this->createTagAttributes(), ['wrong' => new stdClass()]);
    }

    /**
     * @return void
     */
    public function testInvalidJsonKeepsOriginalAndRemovesAttribute(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->assertSame(
            '<img class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '">',
            $this->createProcessorThatNeverResizes($logger)->process(
                '<img class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '"'
                . ' data-image-resize-desktop="{not json">'
            )
        );
    }

    /**
     * @return void
     */
    public function testFailedVariantKeepsOriginalAndRemovesAttribute(): void
    {
        $variantGenerator = $this->createStub(VariantGenerator::class);
        $variantGenerator->method('generate')->willReturn(null);

        $this->assertSame(
            '<img class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '">',
            $this->createProcessor($variantGenerator)->process(
                '<img class="pagebuilder-mobile-hidden" src="' . self::DESKTOP_URL . '"'
                . ' data-image-resize-desktop="' . self::SETTINGS_100 . '">'
            )
        );
    }

    /**
     * @return void
     */
    public function testSettingsTextInsideAnotherValueCannotCreateAttributes(): void
    {
        $html = '<div title=" data-image-resize-x=\'" alt=\'" onmouseover="x()" data-x="\' class="">'
            . '<img alt=\' data-image-resize-desktop="' . self::SETTINGS_100 . '"\' src="' . self::DESKTOP_URL . '">';

        $this->assertSame($html, $this->createProcessorThatNeverResizes()->process($html));
    }

    /**
     * @return void
     */
    public function testUppercaseSettingsAttributeIsApplied(): void
    {
        $this->assertSame(
            '<img width="100" height="20" src="https://x.test/media/v100/wysiwyg/d.jpg">',
            $this->processor->process(
                '<img src="' . self::DESKTOP_URL . '" DATA-IMAGE-RESIZE-DESKTOP="' . self::SETTINGS_100 . '">'
            )
        );
    }

    /**
     * @return void
     */
    public function testProcessedOutputIsNotProcessedTwice(): void
    {
        $first = $this->processor->process($this->desktopImage(self::DESKTOP_URL, self::SETTINGS_100));

        $this->assertSame($first, $this->createProcessorThatNeverResizes()->process($first));
    }

    /**
     * @param LoggerInterface|null $logger
     * @return ResizeAttributeProcessor
     */
    private function createProcessorThatNeverResizes(?LoggerInterface $logger = null): ResizeAttributeProcessor
    {
        $variantGenerator = $this->createMock(VariantGenerator::class);
        $variantGenerator->expects($this->never())->method('generate');

        return $this->createProcessor($variantGenerator, $logger);
    }

    /**
     * @param VariantGenerator $variantGenerator
     * @param LoggerInterface|null $logger
     * @return ResizeAttributeProcessor
     */
    private function createProcessor(
        VariantGenerator $variantGenerator,
        ?LoggerInterface $logger = null
    ): ResizeAttributeProcessor {
        $tagAttributes = $this->createTagAttributes($logger);

        return new ResizeAttributeProcessor(
            $tagAttributes,
            [
                'image' => new ImageSourceRewriter($variantGenerator, $tagAttributes),
                'background_images' => new BackgroundImagesRewriter($variantGenerator, $tagAttributes),
                'video_fallback' => new AttributeSourceRewriter(
                    $variantGenerator,
                    $tagAttributes,
                    'data-video-fallback-src',
                    'fallback'
                ),
            ]
        );
    }

    /**
     * @param LoggerInterface|null $logger
     * @return TagAttributes
     */
    private function createTagAttributes(?LoggerInterface $logger = null): TagAttributes
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static fn (string $value): string => str_replace('"', '&quot;', $value)
        );

        return new TagAttributes(new Json(), $escaper, $logger ?? $this->logger);
    }

    /**
     * @param string $url
     * @param string $settings
     * @return string
     */
    private function desktopImage(string $url, string $settings): string
    {
        $attribute = $settings === '' ? '' : ' data-image-resize-desktop="' . $settings . '"';

        return '<img class="pagebuilder-mobile-hidden" src="' . $url . '" alt="a > b"' . $attribute . '>';
    }

    /**
     * @param string $url
     * @param string $settings
     * @return string
     */
    private function mobileImage(string $url, string $settings = ''): string
    {
        $attribute = $settings === '' ? '' : ' data-image-resize-mobile="' . $settings . '"';

        return '<img class="pagebuilder-mobile-only" src="' . $url . '"' . $attribute . '>';
    }

    /**
     * @param array $images
     * @return string
     */
    private function backgroundJson(array $images): string
    {
        $pairs = [];

        foreach ($images as $key => $url) {
            $pairs[] = '\&quot;' . $key . '\&quot;:\&quot;' . $url . '\&quot;';
        }

        return '{' . implode(',', $pairs) . '}';
    }
}
