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
