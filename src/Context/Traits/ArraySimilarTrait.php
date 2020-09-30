<?php

declare(strict_types=1);

namespace SymfonyBehatContext\Context\Traits;

use Behat\Gherkin\Node\PyStringNode;
use RuntimeException;

trait ArraySimilarTrait
{
    /**
     * Compare structure Response
     *
     * @param string       $variableFields
     * @param PyStringNode $string
     * @param string       $actualJSON
     *
     * @throws RuntimeException
     */
    protected function compareStructureResponse(string $variableFields, PyStringNode $string, string $actualJSON): void
    {
        if ($actualJSON === '') {
            throw new RuntimeException('Response is not JSON');
        }

        $expectedResponse = json_decode(trim($string->getRaw()), true);
        $actualResponse = json_decode($actualJSON, true);
        $variableFields = $variableFields ? array_map('trim', explode(',', $variableFields)) : [];

        if (!$this->isArraysSimilar($expectedResponse, $actualResponse, $variableFields)) {
            $prettyJSON = json_encode($actualResponse, JSON_PRETTY_PRINT);
            $message = sprintf(
                "Expected JSON is not similar to the actual JSON with variable fields:\n%s\n",
                $prettyJSON
            );

            throw new RuntimeException($message);
        }
    }

    /**
     * Checks whether two arrays are similar (has the same keys and type of values).
     *
     * @param array $expected
     * @param array $actual
     * @param array $variableFields
     *
     * @return boolean
     */
    protected function isArraysSimilar($expected, $actual, array $variableFields = []): bool
    {
        if (!is_array($expected) || !is_array($actual) || array_keys($expected) !== array_keys($actual)) {
            return false;
        }

        foreach ($expected as $k => $v) {
            if (!isset($actual[$k]) && $v !== null) {
                return false;
            }

            if (gettype($expected[$k]) !== gettype($actual[$k]) && !in_array($k, $variableFields)) {
                return false;
            }

            if (is_array($v)) {
                if (!$this->isArraysSimilar($expected[$k], $actual[$k], $variableFields)) {
                    return false;
                }
            } elseif (!in_array($k, $variableFields, true) && ($expected[$k] !== $actual[$k])) {
                return false;
            } elseif (in_array($k, $variableFields, true)) {
                if (
                    is_string($expected[$k]) && strpos($expected[$k], '~') === 0
                    && !preg_match(sprintf('|%s|', substr($expected[$k], 1)), $actual[$k])
                ) {
                    return false;
                }
            }
        }

        return true;
    }
}
