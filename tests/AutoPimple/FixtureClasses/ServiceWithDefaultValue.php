<?php

namespace AutoPimple\FixtureClasses;

use PHPUnit_Framework_TestCase;


class ServiceWithDefaultValue
{
	protected $value;

	public function __construct($value = 30)
	{
		$this->value = $value;
	}

 	public function getValue()
 	{
 		return $this->value;
 	}
}

