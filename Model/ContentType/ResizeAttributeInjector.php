<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\ContentType;

/**
 * Adds the resize attributes and converters to content types that store images in a known attribute.
 *
 * @api
 */
class ResizeAttributeInjector
{
    public const CONVERTER_COMPONENT = 'Fom_PageBuilderImageResize/js/mass-converter/image-resize';

    private const ATTRIBUTE_READER = 'Magento_PageBuilder/js/property/attribute-reader';

    /**
     * Each source names the DOM attribute that holds an image URL, and the resize settings to add next to it.
     *
     * @param array $sources
     * @param string[] $excludedContentTypes
     */
    public function __construct(
        private readonly array $sources = [],
        private readonly array $excludedContentTypes = []
    ) {
    }

    /**
     * Add the resize attributes and converters to every appearance element that holds a supported image.
     *
     * @param array $contentTypes
     * @return array
     */
    public function inject(array $contentTypes): array
    {
        foreach ($contentTypes as $typeName => $contentType) {
            if (in_array($typeName, $this->excludedContentTypes, true)) {
                continue;
            }

            foreach ($contentType['appearances'] ?? [] as $appearanceName => $appearance) {
                $contentTypes[$typeName]['appearances'][$appearanceName] = $this->injectAppearance($appearance);
            }
        }

        return $contentTypes;
    }

    /**
     * Add the resize settings next to each supported image of one appearance.
     *
     * @param array $appearance
     * @return array
     */
    private function injectAppearance(array $appearance): array
    {
        foreach ($appearance['elements'] ?? [] as $elementName => $element) {
            $attributeNames = array_column($element['attributes'] ?? [], 'name');

            foreach ($this->sources as $source) {
                if (!in_array($source['attribute'] ?? null, $attributeNames, true)) {
                    continue;
                }

                foreach ($source['settings'] ?? [] as $storageKey => $resizeAttribute) {
                    $appearance = $this->addAttribute($appearance, $elementName, $storageKey, $resizeAttribute);
                    $appearance = $this->addConverter($appearance, $storageKey);
                }
            }
        }

        return $appearance;
    }

    /**
     * Add a resize attribute unless the content type already declares it on any element.
     *
     * @param array $appearance
     * @param string $elementName
     * @param string $storageKey
     * @param string $resizeAttribute
     * @return array
     */
    private function addAttribute(
        array $appearance,
        string $elementName,
        string $storageKey,
        string $resizeAttribute
    ): array {
        foreach ($appearance['elements'] as $element) {
            if (in_array($storageKey, array_column($element['attributes'] ?? [], 'var'), true)) {
                return $appearance;
            }
        }

        $appearance['elements'][$elementName]['attributes'][] = [
            'var' => $storageKey,
            'name' => $resizeAttribute,
            'converter' => null,
            'preview_converter' => null,
            'persistence_mode' => 'readwrite',
            'reader' => self::ATTRIBUTE_READER,
        ];

        return $appearance;
    }

    /**
     * Add the mass converter that packs the form fields of one resize attribute.
     *
     * @param array $appearance
     * @param string $storageKey
     * @return array
     */
    private function addConverter(array $appearance, string $storageKey): array
    {
        if (in_array($storageKey, array_column($appearance['converters'] ?? [], 'name'), true)) {
            return $appearance;
        }

        $appearance['converters'][] = [
            'name' => $storageKey,
            'component' => self::CONVERTER_COMPONENT,
            'config' => ['attribute_name' => $storageKey],
        ];

        return $appearance;
    }
}
