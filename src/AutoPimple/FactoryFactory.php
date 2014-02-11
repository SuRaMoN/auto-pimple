<?php

namespace AutoPimple;


class FactoryFactory
{
	protected $factoryCallback;

	public function __construct($factoryCallback)
	{
		$this->factoryCallback = $factoryCallback;
	}

	public function newInstance()
	{
		return call_user_func($this->factoryCallback);
	}
}
 
