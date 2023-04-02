<?php

namespace Cangokdayi\WPFacades;

abstract class Enum
{
    /**
     * Returns the values as an array
     */
    final public static function asArray()
    {
        return (new \ReflectionClass(static::class))->getConstants();
    }
}
