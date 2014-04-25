<?php

namespace AutoPimple\FixtureClasses;


class ServiceWithUnfulfillableDependencies
{
	protected $value;

	public function __construct($value)
	{
		$this->value = $value;
	}

	public function getValue()
	{
		return $this->value;
	}
}
 
