<?php

namespace AutoPimple;

use ArrayObject;
use InvalidArgumentException;
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
	public function testServiceMethod()
	{
		$c = new AutoPimple();
		$c['array'] = new ArrayObject();

		$countFunction = $c->serviceMethod('array', 'count');
		$this->assertEquals(0, $countFunction());
		$c['array']->append(123);
		$this->assertEquals(1, $countFunction());

		$appendFunction = $c->serviceMethod('array', 'append');
		$appendFunction(456);
		$this->assertEquals(2, $countFunction());
	}

	/** @test */
	public function testAlias()
	{
		$c = new AutoPimple();
		$c->alias('true', 'false');
		$hasFailed = false;

		try {
			$c['true'];
		} catch(InvalidArgumentException $e) {
			$hasFailed = true;
		}
		$this->assertTrue($hasFailed);
		$c['false'] = false;
		$this->assertFalse($c['true']);
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

	/** @test */
	public function testFactoryByClassName()
	{
		$c = new AutoPimple();
		$c['jos'] = $c->createFactory('stdClass');
		$this->assertTrue($c['jos']->newInstance() instanceof \stdClass);
	}

	/** @test */
	public function testFactoryByCallback()
	{
		$c = new AutoPimple();
		$c['jos'] = $c->createFactory(function() { return new \stdClass(); });
		$this->assertTrue($c['jos']->newInstance() instanceof \stdClass);
	}

	/** @test */
	public function testDefaultValues()
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.service_with_default_value'];
		$this->assertEquals(30, $service->getValue());
	}

	/** @test */
	public function testAutoFactory()
	{
		$c = new AutoPimple();
		$serviceFactory = $c->autoFactory('AutoPimple\FixtureClasses\ServiceWithDependencies');
		$this->assertEquals('ServiceWithDependencies', $serviceFactory->newInstance()->getName());
	}

	/**
	 * @test
	 * @expectedException InvalidArgumentException
	 */
	public function testExceptionWIthDependenciesAreUnfulfillable()
	{
		$c = new AutoPimple();
		$serviceFactory = $c->autoFactory('AutoPimple\FixtureClasses\ServiceWithUnfulfillableDependencies');
		$serviceFactory->newInstance();
	}

	/** @test */
	public function testInjectingParamaters()
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_unfulfillable_dependencies.value'] = 1;
		$service = $c['fixture_classes.service_with_unfulfillable_dependencies'];
		$this->assertEquals(1, $service->getValue());
	}
}
 
