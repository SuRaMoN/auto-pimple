<?php

namespace AutoPimple;


class Factory
{
	protected $factoryCallback;

	public function __construct($factory)
	{
		$this->factoryCallback = is_string($factory) ? function() use ($factory) { return new $factory(); } : $factory;
	}

	public function newInstance()
	{
		$arguments = func_get_args();
		return call_user_func_array($this->factoryCallback, $arguments);
	}
}
 
