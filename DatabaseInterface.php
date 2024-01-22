<?php

namespace FpDbTest;

use Exception;

/**
 * Query builder
 */
class DatabaseInterface
{
    /**
     * Types which are can accept null values.
     * @var string[]
     */
    private array $nullAllowedSpecifiers = ["", "d", "f"];

    /**
     * Main function.
     *
     * @param string $query
     * @param array $args
     * @return string
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $result = preg_replace_callback(
            "/\?([\w#]?)/",
            function ($matches) use (&$args) {
                if (empty($args)) {
                    // the number of args should match with number of patterns
                    throw new Exception("Not enough params!");
                }

                return $this->convertParam(array_shift($args), $matches[1]);
            },
            $query
        );

        return $this->removeSkippedBlocks($result);
    }

    /**
     * @return string
     */
    public function skip()
    {
        return ":SKIP:";
    }

    /**
     * @param mixed $param
     * @param string $formatSpecifier
     * @param $isIdentifier - if we need ` instead of ' for strings
     * @return float|int|mixed|string
     * @throws Exception
     */
    private function convertParam(mixed $param, string $formatSpecifier, $isIdentifier = false)
    {
        if (is_null($param)) {
            if (in_array($formatSpecifier, $this->nullAllowedSpecifiers)) {
                return "NULL";
            } else {
                throw new Exception("NULL value isn't allowed for the format specifier \"$formatSpecifier\".");
            }
        }

        // no change for skipped blocks
        if ($param === $this->skip()) {
            return $param;
        }

        if (is_bool($param)) {
            $param = (int)$param;
        }

        if (empty($formatSpecifier)) {
            return $this->convertParamByDefault($param, $isIdentifier);
        } else {
            return $this->convertParamBySpecifier($param, $formatSpecifier);
        }
    }

    /**
     * Convert when format specifier isn't specified =)
     * @param mixed $param
     * @param $isIdentifier
     * @return float|int|string
     * @throws Exception
     */
    private function convertParamByDefault(mixed $param, $isIdentifier)
    {
        if (is_string($param)) {
            if ($isIdentifier) {
                return "`$param`";
            } else {
                return "'$param'";
            }
        }

        if (!(is_int($param) || is_float($param))) {
            throw new Exception("Invalid parameter type for empty specifier!");
        }

        // if it's int or float just return w/o changes
        return $param;
    }

    /**
     * Convert according to format.
     * @param mixed $param
     * @param string $formatSpecifier
     * @return mixed|string
     * @throws Exception
     */
    private function convertParamBySpecifier(mixed $param, string $formatSpecifier)
    {
        switch ($formatSpecifier) {
            case "d":
                $result = filter_var($param, FILTER_VALIDATE_INT);
                break;
            case "f":
                $result = filter_var($param, FILTER_VALIDATE_FLOAT);
                break;
            case "a":
                $result = is_array($param) ? $this->convertArray($param) : false;
                break;
            case "#":
                $param = is_array($param) ? $param : [$param];
                // for identifiers check if all params are strings
                $result = $this->checkArrayOfStrings($param) ? $this->convertArray($param, true) : false;
                break;
            default:
                throw new Exception("Invalid format specifier \"$formatSpecifier\".");
        }

        if ($result === false) {
            throw new Exception("Invalid parameter type for format specifier \"$formatSpecifier\"");
        }

        return $result;
    }

    /**
     * Validate that all items in array are strings
     * @param array $param
     * @return bool
     */
    private function checkArrayOfStrings(array $param): bool
    {
        return count($param) == count(array_filter($param, "is_string"));
    }

    /**
     * @param array $param
     * @param bool $isIdentifiers
     * @return string
     * @throws Exception
     */
    private function convertArray(array $param, bool $isIdentifiers = false): string
    {
        if (empty($param)) {
            // we don't want to get empty string or ()
            throw new Exception("Array param shouldn't be empty!");
        }

        $isSequential = array_keys($param) === range(0, count($param) - 1);

        $param = array_map(fn($item) => $this->convertParam($item, "", $isIdentifiers && $isSequential), $param);

        if (!$isSequential) {
            $param = array_map(fn($key, $value) => "`$key` = $value", array_keys($param), $param);
        }

        return implode(", ", $param);
    }

    /**
     * Remove blocks marked as skipped including nested blocks like "{{ AND block = :SKIP:} AND block = 2}"
     * @param string $result
     * @return string
     */
    private function removeSkippedBlocks(string $result): string
    {
        $count = 1;

        // can have nested blocks, so each time we remove internal
        // until there are no more skipped blocks
        while ($count > 0) {
            $result = preg_replace("/{[^{.]+:SKIP:}/", "", $result, -1, $count);
        }

        return str_replace(["{", "}"], "", $result);
    }
}