<?php

namespace AutoPimple;

class Factory
{
    protected $factoryCallback;

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

    public function newInstance()
    {
        $arguments = func_get_args();
        return call_user_func_array($this->factoryCallback, $arguments);
    }
}
