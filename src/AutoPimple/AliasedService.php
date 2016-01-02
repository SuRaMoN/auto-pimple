<?php

namespace AutoPimple;

class AliasedService
{
    private $target;

    public function __construct($target)
    {
        $this->target = $target;
    }

    public function getTarget()
    {
        return $this->target;
    }
}
