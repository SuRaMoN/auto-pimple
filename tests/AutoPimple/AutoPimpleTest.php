<?php

namespace AutoPimple;

use PHPUnit_Framework_TestCase;


class AutoPimpleTest extends PHPUnit_Framework_TestCase
{
	/** @test */
	public function testNormalDependencyInjection()
	{
		$c = new AutoPimple();
		$c['true'] = $c->share(function() { return true; });
		$this->assertTrue($c['true']);
	}

	/** @test */
	public function testSimpleAutoServiceLoading()
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.simple_service_without_dependencies'];
		$this->assertEquals('SimpleServiceWithoutDependencies', $service->getName());
	}

	/** @test */
	public function testAutoServiceLoadingWithDependencies()
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.service_with_dependencies'];
		$this->assertEquals('ServiceWithDependencies', $service->getName());
		$this->assertEquals(array('SimpleServiceWithoutDependencies'), $service->getDependenciesNames());
	}
}
 
