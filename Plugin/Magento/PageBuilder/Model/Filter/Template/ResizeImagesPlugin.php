<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Plugin\Magento\PageBuilder\Model\Filter\Template;

use Fom\PageBuilderImageResize\Model\Html\ResizeAttributeProcessor;
use Magento\PageBuilder\Model\Filter\Template;

class ResizeImagesPlugin
{
    /**
     * @param ResizeAttributeProcessor $processor
     */
    public function __construct(
        private readonly ResizeAttributeProcessor $processor
    ) {
    }

    /**
     * Swap image URLs after media directives are resolved and before backgrounds become style rules.
     *
     * @param Template $subject
     * @param string $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeFilter(Template $subject, string $result): array
    {
        return [$this->processor->process($result)];
    }
}
