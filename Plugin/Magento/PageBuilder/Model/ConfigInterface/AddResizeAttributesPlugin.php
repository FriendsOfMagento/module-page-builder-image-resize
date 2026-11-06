<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Plugin\Magento\PageBuilder\Model\ConfigInterface;

use Fom\PageBuilderImageResize\Model\ContentType\ResizeAttributeInjector;
use Magento\PageBuilder\Model\ConfigInterface;

class AddResizeAttributesPlugin
{
    /**
     * @param ResizeAttributeInjector $injector
     */
    public function __construct(
        private readonly ResizeAttributeInjector $injector
    ) {
    }

    /**
     * Give every content type with a background or video fallback image the resize settings.
     *
     * @param ConfigInterface $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetContentTypes(ConfigInterface $subject, array $result): array
    {
        return $this->injector->inject($result);
    }
}
