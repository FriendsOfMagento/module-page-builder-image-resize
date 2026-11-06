<?php

declare(strict_types=1);

namespace Fom\PageBuilderImageResize\Model\Html;

use InvalidArgumentException;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Reads and writes the attributes of one opening tag, in the escaped JSON format Page Builder saves.
 *
 * The tag is read attribute by attribute, the way a browser reads it, so text inside an attribute value is never
 * taken for an attribute. Reading stops at the first malformed part, and anything after it is left as it is.
 *
 * @api
 */
class TagAttributes
{
    private const TAG_NAME_PATTERN = '/^<[a-z][^\s\/>]*+/i';
    private const ATTRIBUTE_PATTERN = '/\G\s++([^\s"\'<>\/=`]++)(?:\s*+=\s*+("[^"]*+"|\'[^\']*+\'|[^\s"\'=<>`]++))?(?=[\s\/>])/';
    private const ESCAPED_CHARACTER_PATTERN = '/\\\\(.)/s';
    private const CHARACTER_TO_ESCAPE_PATTERN = '/[\\\\"\']/';
    private const QUOTE_ENTITIES = ['"' => '&quot;', '\'' => '&#039;'];
    private const DEFAULT_QUOTE = '"';

    /**
     * @param Json $json
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Json $json,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Read the raw value of an attribute. An attribute without a value reads as an empty string.
     *
     * @param string $tag
     * @param string $attribute
     * @return string|null
     */
    public function get(string $tag, string $attribute): ?string
    {
        $found = $this->find($tag, $attribute);

        return $found === null ? null : $found['value'];
    }

    /**
     * Read the raw values of all attributes, keyed by their lowercase name. The first of duplicate names wins.
     *
     * @param string $tag
     * @return array
     */
    public function getAll(string $tag): array
    {
        $values = [];

        foreach ($this->parse($tag)['attributes'] as $parsed) {
            $values[$parsed['name']] ??= $parsed['value'];
        }

        return $values;
    }

    /**
     * Replace the raw value of an existing attribute.
     *
     * @param string $tag
     * @param string $attribute
     * @param string $value
     * @return string
     */
    public function set(string $tag, string $attribute, string $value): string
    {
        $found = $this->find($tag, $attribute);

        if ($found === null) {
            return $tag;
        }

        $quote = $found['quote'] ?: self::DEFAULT_QUOTE;
        $replacement = $quote . str_replace($quote, self::QUOTE_ENTITIES[$quote], $value) . $quote;

        return $found['value_start'] === null
            ? substr_replace($tag, '=' . $replacement, $found['end'], 0)
            : substr_replace($tag, $replacement, $found['value_start'], $found['end'] - $found['value_start']);
    }

    /**
     * Add attributes right after the tag name.
     *
     * @param string $tag
     * @param array $attributes
     * @return string
     */
    public function add(string $tag, array $attributes): string
    {
        $parsed = $this->parse($tag);

        if ($parsed['name_end'] === null) {
            return $tag;
        }

        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, str_replace('"', self::QUOTE_ENTITIES['"'], (string)$value));
        }

        return substr_replace($tag, $html, $parsed['name_end'], 0);
    }

    /**
     * Remove every occurrence of an attribute.
     *
     * @param string $tag
     * @param string $attribute
     * @return string
     */
    public function remove(string $tag, string $attribute): string
    {
        $name = strtolower($attribute);

        foreach (array_reverse($this->parse($tag)['attributes']) as $parsed) {
            if ($parsed['name'] === $name) {
                $tag = substr_replace($tag, '', $parsed['start'], $parsed['end'] - $parsed['start']);
            }
        }

        return $tag;
    }

    /**
     * Decode a JSON attribute value in the format Page Builder saves it.
     *
     * @param string $value
     * @return array
     */
    public function decode(string $value): array
    {
        try {
            $decoded = $this->json->unserialize(
                preg_replace(self::ESCAPED_CHARACTER_PATTERN, '$1', htmlspecialchars_decode($value, ENT_QUOTES))
            );
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning(
                sprintf('Page Builder image resize skipped, invalid JSON: %s', $exception->getMessage())
            );

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encode data as a JSON attribute value in the exact format Page Builder saves it.
     *
     * @param array $data
     * @return string
     */
    public function encode(array $data): string
    {
        $json = str_replace('\/', '/', (string)$this->json->serialize($data));

        return $this->escaper->escapeHtml(preg_replace(self::CHARACTER_TO_ESCAPE_PATTERN, '\\\\$0', $json));
    }

    /**
     * Find the first occurrence of an attribute, the one a browser uses.
     *
     * @param string $tag
     * @param string $attribute
     * @return array|null
     */
    private function find(string $tag, string $attribute): ?array
    {
        $name = strtolower($attribute);

        foreach ($this->parse($tag)['attributes'] as $parsed) {
            if ($parsed['name'] === $name) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Split a tag into its attributes, with the offsets of each attribute and of its value.
     *
     * @param string $tag
     * @return array
     */
    private function parse(string $tag): array
    {
        if (!preg_match(self::TAG_NAME_PATTERN, $tag, $nameMatch)) {
            return ['name_end' => null, 'attributes' => []];
        }

        $offset = strlen($nameMatch[0]);
        $result = ['name_end' => $offset, 'attributes' => []];

        while (preg_match(
            self::ATTRIBUTE_PATTERN,
            $tag,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
            $offset
        )) {
            $end = $offset + strlen($matches[0][0]);
            $result['attributes'][] = $this->describe($matches, $offset, $end);
            $offset = $end;
        }

        return $result;
    }

    /**
     * Describe one matched attribute.
     *
     * @param array $matches
     * @param int $start
     * @param int $end
     * @return array
     */
    private function describe(array $matches, int $start, int $end): array
    {
        [$rawValue, $valueStart] = $matches[2];
        $quote = $rawValue !== null && isset(self::QUOTE_ENTITIES[$rawValue[0]]) ? $rawValue[0] : '';

        return [
            'name' => strtolower($matches[1][0]),
            'value' => $rawValue === null ? '' : ($quote ? substr($rawValue, 1, -1) : $rawValue),
            'quote' => $quote,
            'start' => $start,
            'end' => $end,
            'value_start' => $rawValue === null ? null : $valueStart,
        ];
    }
}
