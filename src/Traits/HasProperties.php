<?php

namespace Cangokdayi\WPFacades\Traits;

use ReflectionProperty;

/**
 * Helper methods for classes and objects with props/getters/setters
 */
trait HasProperties {

    /**
     * Gets or sets the value of the given property 
     *
     * @param string $property Name of the property
     * @param mixed $givenValue Value passed to the getter/setter method
     * @param mixed $defaultValue Default dumb value used on the value parameter
     *                            of the getter/setter method, defaults to null.
     * 
     * @throws \ReflectionException If the given property doesn't exist
     * 
     * @return mixed|static Value of the given prop if called as a getter
     *                      otherwise the current object instance
     */
    public function getSet(string $property, $givenValue, $defaultValue = null)
    {
        $property = new ReflectionProperty($this, $property);
        
        if ($givenValue !== $defaultValue) {
            $this->{$property->name} = $givenValue;

            return $this;
        }   

        return $this->{$property->name};
    }
}
