<?php

namespace AutoPimple\FixtureClasses;

use PHPUnit_Framework_TestCase;


class ServiceWithDependencies
{
	protected $dependency;

	public function __construct(SimpleServiceWithoutDependencies $dependency)
	{
		$this->dependency = $dependency;
	}

	public function getName()
	{
		return 'ServiceWithDependencies';
	}

	public function getDependency()
	{
		return $this->dependency;
	}

	public function getDependenciesNames()
	{
		return array($this->dependency->getName());
	}
}

