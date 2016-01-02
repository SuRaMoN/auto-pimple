<?php

namespace AutoPimple;

class ExtendedService
{
	protected $pimple;
	protected $id;
	protected $baseService;
	protected $hasDefinedService;
	protected $extender;

	public function __construct($id, $baseService, $extender, $hasDefinedService, AutoPimple $pimple)
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
