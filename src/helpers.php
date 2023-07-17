<?php

namespace Cangokdayi\WPFacades;

if (!function_exists(__NAMESPACE__ . '\getPackageRoot')) {

    /**
     * Returns the full path of the package directory
     *
     * @return string
     */
    function getPackageRoot(): string
    {
        return dirname(__FILE__, 2);
    }
}


if (!function_exists(__NAMESPACE__ . '\getProjectRoot')) {

    /**
     * Returns the full path of the parent plugin folder
     *
     * @return string
     */
    function getProjectRoot(): string
    {
        return dirname(getPackageRoot(), 3);
    }
}

if (!function_exists(__NAMESPACE__ . '\flattenArray')) {

    /**
     * Converts the given multi-dimensional array to single dimensional.
     * 
     * @param boolean $preserveKeys When set to TRUE, keys of the 1st level 
     *                              items will be preserved.
     */
    function flattenArray(array $array, bool $preserveKeys = false): array
    {
        $merge = function ($x, $y) {
            $getY = fn () => count($y) > 1 ? [$y] : $y;

            return (bool) array_intersect_key($x, $y)
                ? array_merge($x, $getY())
                : $x + $getY();
        };

        $flat = array_reduce($array, $merge, []);

        return $preserveKeys
            ? array_combine(array_keys($array), $flat)
            : $flat;
    }
}

if (!function_exists(__NAMESPACE__ . '\flatMap')) {

    /**
     * Maps the given array and flattens the result
     */
    function flatMap(callable $callback, array $array): array
    {
        return flattenArray(array_map($callback, $array));
    }
}
