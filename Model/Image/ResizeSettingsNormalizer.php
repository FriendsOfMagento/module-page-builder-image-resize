<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Image;

class ResizeSettingsNormalizer
{
    private const DEFAULT_BACKGROUND = [255, 255, 255];
    private const FLAG_DEFAULTS = [
        'aspect_ratio' => true,
        'frame' => false,
        'constrain' => true,
        'transparency' => true,
    ];
    private const TRUE_VALUES = [true, 1, '1'];
    private const HEX_COLOR_PATTERN = '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i';

    /**
     * Convert saved settings into the exact values the native hash expects.
     *
     * @param array $settings
     * @return array
     */
    public function normalize(array $settings): array
    {
        $normalized = [
            'width' => $this->toDimension($settings['width'] ?? null),
            'height' => $this->toDimension($settings['height'] ?? null),
        ];

        foreach (self::FLAG_DEFAULTS as $key => $default) {
            $normalized[$key] = $this->toFlag($settings[$key] ?? null, $default);
        }

        $normalized['background'] = $this->toBackground($settings['background'] ?? null);

        return $normalized;
    }

    /**
     * Check whether normalized settings ask for a resize.
     *
     * @param array $normalized
     * @return bool
     */
    public function isEnabled(array $normalized): bool
    {
        return $normalized['width'] !== null || $normalized['height'] !== null;
    }

    /**
     * Convert a dimension to a positive integer or null.
     *
     * @param mixed $value
     * @return int|null
     */
    private function toDimension(mixed $value): ?int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    /**
     * Convert a toggle value to a real boolean, using the default when it is not set.
     *
     * @param mixed $value
     * @param bool $default
     * @return bool
     */
    private function toFlag(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array($value, self::TRUE_VALUES, true);
    }

    /**
     * Convert a hex colour to an RGB integer array.
     *
     * @param mixed $value
     * @return array
     */
    private function toBackground(mixed $value): array
    {
        if (is_string($value) && preg_match(self::HEX_COLOR_PATTERN, $value, $matches)) {
            return $this->fromHex($matches[1]);
        }

        return self::DEFAULT_BACKGROUND;
    }

    /**
     * Convert a three or six digit hex value to RGB.
     *
     * @param string $hex
     * @return array
     */
    private function fromHex(string $hex): array
    {
        $digits = strlen($hex) === 3 ? preg_replace('/./', '$0$0', $hex) : $hex;

        return array_map(hexdec(...), str_split((string)$digits, 2));
    }
}
