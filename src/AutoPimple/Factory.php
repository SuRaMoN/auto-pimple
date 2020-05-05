<?php

namespace AutoPimple;

/**
* @template T
*/
final class Factory
{
    private $factoryCallback;

    /**
     * @param class-string<T>|callable:T $type
     */
    public function __construct($factory)
    {
        if (is_string($factory)) {
            $this->factoryCallback = function () use ($factory) {
                return new $factory();
            };
        } else {
            $this->factoryCallback = $factory;
        }
    }

    /**
     * @return T
     */
    public function newInstance()
    {
        $arguments = func_get_args();
        return call_user_func_array($this->factoryCallback, $arguments);
    }
}
