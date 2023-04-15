<?php

namespace Cangokdayi\WPFacades\Traits;

/**
 * @todo Trait probably isn't a good idea for these funcs due to method names
 *       being too generic so they might be problematic in the future. Think of
 *       a better solution like Laravel's "Arr" helper class or Collections.
 */
trait HandlesArrays
{

    /**
     * Keys the given array with the given inner-key name or a callback function 
     *
     * @param string|callable $key Name of the inner-key or a callback function
     *                             that returns the key value that will be used.
     *                             Callback function takes a single parameter as
     *                             the current array item in the loop.
     * 
     * @param array<int|string, object|array> $array The array of items to use
     */
    public function keyBy($key, array $array): array
    {
        $result = [];

        foreach ($array as $item) {
            $newKey = is_callable($key)
                ? $key($item)
                : $this->getValueFromItem($item, $key);

            $result += [
                $newKey => $item
            ];
        }

        return $result;
    }

    /**
     * Plucks a specific value from the given array using the specified key name
     * or by calling the provided callback function.
     * 
     * @param string|callable $key
     */
    public function pluck($key, array $array): array
    {
        foreach ($array as $i => $item) {
            $array[$i] = is_callable($key)
                ? $key($item)
                : $this->getValueFromItem($item, $key);
        }

        return $array;
    }

    /**
     * Returns the duplicated values from the given array. Keys are preserved.
     * 
     * @return array<string|int, mixed>
     */
    public function getDuplicates(array $array): array 
    {
        return array_diff_assoc($array, array_unique($array));
    }

    /**
     * Returns the value of the given key/prop from the object/array
     *
     * @param object|array $item Array or object that holds the value
     * @param string|int $key Key or object prop
     * 
     * @throws \InvalidArgumentException If the given $item parameter is neither
     *                                   an array nor object
     *                                      
     * @return mixed Value of the given key/prop otherwise null
     */
    private function getValueFromItem($item, $key)
    {
        if (!is_object($item) && !is_array($item)) {
            throw new \InvalidArgumentException(
                'The item parameter must be an array or object'
            );
        }

        return is_object($item)
            ? ($item->{$key} ?? null)
            : ($item[$key] ?? null);
    }
}
