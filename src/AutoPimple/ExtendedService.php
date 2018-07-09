<?php

namespace AutoPimple;

final class ExtendedService
{
    private $pimple;
    private $id;
    private $baseService;
    private $hasDefinedService;
    private $extender;

    public function __construct(string $id, $baseService, $extender, bool $hasDefinedService, AutoPimple $pimple)
    {
        $this->pimple = $pimple;
        $this->id = $id;
        $this->baseService = $baseService;
        $this->extender = $extender;
        $this->hasDefinedService = $hasDefinedService;
    }

    public function __invoke()
    {
        return $this->pimple->getExtendedService($this);
    }

    public function getExtender()
    {
        return $this->extender;
    }

    public function getHasDefinedService()
    {
        return $this->hasDefinedService;
    }

    public function getBaseService()
    {
        return $this->baseService;
    }

    public function getId()
    {
        return $this->id;
    }
}
