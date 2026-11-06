<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Image;

use Magento\Catalog\Model\Product\Image\ConvertImageMiscParamsToReadableFormat;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;

class ResizeHash
{
    private const HASH_ALGORITHM = 'md5';

    /**
     * @param ParamsBuilder $paramsBuilder
     * @param ConvertImageMiscParamsToReadableFormat $readableFormat
     */
    public function __construct(
        private readonly ParamsBuilder $paramsBuilder,
        private readonly ConvertImageMiscParamsToReadableFormat $readableFormat
    ) {
    }

    /**
     * Calculate the same hash native catalog images use for the same values.
     *
     * @param array $settings
     * @return string
     */
    public function get(array $settings): string
    {
        $params = $this->paramsBuilder->build($settings);
        unset($params['image_type']);

        return hash(
            self::HASH_ALGORITHM,
            implode('_', $this->readableFormat->convertImageMiscParamsToReadableFormat($params))
        );
    }
}
