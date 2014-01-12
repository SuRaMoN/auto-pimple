<?php

namespace AutoPimple\FixtureClasses;

use PHPUnit_Framework_TestCase;


class SimpleServiceWithoutDependencies
{
	public function getName()
	{
		return 'SimpleServiceWithoutDependencies';
	}
}

