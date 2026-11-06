<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Test\Unit\Model\Html;

use Fom\PageBuilderImageResize\Model\Html\TagAttributes;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TagAttributesTest extends TestCase
{
    private const DECOY_TAG = '<div title=" data-image-resize-x=\'" alt=\'" onmouseover="x()" data-x="\' class="">';

    /**
     * @var TagAttributes
     */
    private TagAttributes $tagAttributes;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->tagAttributes = new TagAttributes(
            new Json(),
            $this->createStub(Escaper::class),
            $this->createStub(LoggerInterface::class)
        );
    }

    /**
     * @return void
     */
    public function testReadsEveryQuoteStyle(): void
    {
        $this->assertSame(
            ['a' => '1', 'b' => '2', 'c' => '3', 'd' => '', 'e' => '5/6'],
            $this->tagAttributes->getAll('<img a="1" b=\'2\' c=3 d e = "5/6"/>')
        );
    }

    /**
     * @return void
     */
    public function testTextInsideValuesIsNotAnAttribute(): void
    {
        $this->assertSame(
            ['title' => ' data-image-resize-x=\'', 'alt' => '" onmouseover="x()" data-x="', 'class' => ''],
            $this->tagAttributes->getAll(self::DECOY_TAG)
        );
        $this->assertNull($this->tagAttributes->get(self::DECOY_TAG, 'onmouseover'));
        $this->assertSame(self::DECOY_TAG, $this->tagAttributes->remove(self::DECOY_TAG, 'data-image-resize-x'));
        $this->assertSame(self::DECOY_TAG, $this->tagAttributes->set(self::DECOY_TAG, 'onmouseover', 'y'));
    }

    /**
     * @return void
     */
    public function testNamesAreCaseInsensitiveAndTheFirstDuplicateWins(): void
    {
        $tag = '<img SRC="a.jpg" src="b.jpg">';

        $this->assertSame('a.jpg', $this->tagAttributes->get($tag, 'src'));
        $this->assertSame('<img SRC="c.jpg" src="b.jpg">', $this->tagAttributes->set($tag, 'src', 'c.jpg'));
        $this->assertSame('<img>', $this->tagAttributes->remove($tag, 'Src'));
    }

    /**
     * @return void
     */
    public function testReadingStopsAtMalformedMarkup(): void
    {
        $tag = '<div a="1" b="2"c="3" d="4">';

        $this->assertSame(['a' => '1'], $this->tagAttributes->getAll($tag));
        $this->assertSame($tag, $this->tagAttributes->remove($tag, 'd'));
    }

    /**
     * @return void
     */
    public function testSetKeepsTheQuoteAndEscapesIt(): void
    {
        $this->assertSame('<a b=\'x&#039;y\'>', $this->tagAttributes->set('<a b=\'1\'>', 'b', 'x\'y'));
        $this->assertSame('<a b="x&quot;y">', $this->tagAttributes->set('<a b=1>', 'b', 'x"y'));
        $this->assertSame('<a b="x" c>', $this->tagAttributes->set('<a b c>', 'b', 'x'));
        $this->assertSame('<a b="1">', $this->tagAttributes->set('<a b="1">', 'c', 'x'));
    }

    /**
     * @return void
     */
    public function testAddPutsAttributesAfterTheTagName(): void
    {
        $this->assertSame(
            '<img width="10" height="&quot;" src="a.jpg">',
            $this->tagAttributes->add('<img src="a.jpg">', ['width' => 10, 'height' => '"'])
        );
        $this->assertSame('<img/>', $this->tagAttributes->remove('<img src="a.jpg"/>', 'src'));
    }
}
