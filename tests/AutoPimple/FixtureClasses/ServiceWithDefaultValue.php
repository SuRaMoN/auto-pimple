<?php

namespace AutoPimple\FixtureClasses;

use PHPUnit_Framework_TestCase;


class ServiceWithDefaultValue
{
	protected $otherValue;
	protected $value;

	public function __construct($value = 30, $otherValue = 40)
	{
		$this->otherValue = $otherValue;
		$this->value = $value;
	}

 	public function getValue()
 	{
 		return $this->value;
 	}
 
 	public function getOtherValue()
 	{
 		return $this->otherValue;
 	}
}

