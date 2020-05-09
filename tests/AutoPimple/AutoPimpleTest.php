<?php

namespace AutoPimple;

use ArrayObject;
use AutoPimple\FixtureClasses\SimpleServiceWithoutDependencies;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use AutoPimple\FixtureClasses\ServiceWithDependencies;
use AutoPimple\FixtureClasses\ServiceWithUnfulfillableDependencies;

class AutoPimpleTest extends TestCase
{
    public function testNormalDependencyInjection(): void
    {
        $c = new AutoPimple();
        $c['true'] = $c::share(static function () {return true;});
        $this->assertTrue($c['true']);
    }

    public function testServiceMethod(): void
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

    public function testAlias(): void
    {
        $c = new AutoPimple();
        $c->alias('true', 'false');
        $hasFailed = false;

        try {
            $c['true'];
        } catch (NotFoundException $e) {
            $hasFailed = true;
        }
        $this->assertTrue($hasFailed);
		$c['false'] = false;
		$this->assertFalse($c['true']);
	}

    public function testSimpleAutoServiceLoading(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.simple_service_without_dependencies'];
		$this->assertEquals('SimpleServiceWithoutDependencies', $service->getName());
	}

    public function testAutoServiceLoadingWithDependencies(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.service_with_dependencies'];
		$this->assertEquals('ServiceWithDependencies', $service->getName());
		$this->assertEquals(array('SimpleServiceWithoutDependencies'), $service->getDependenciesNames());
	}

    public function testFactoryByClassName(): void
	{
		$c = new AutoPimple();
		$c['jos'] = $c->createFactory('stdClass');
		$this->assertInstanceOf(\stdClass::class, $c['jos']->newInstance());
	}

    public function testFactoryByCallback(): void
	{
		$c = new AutoPimple();
		$c['jos'] = $c->createFactory(static function() { return new \stdClass(); });
		$this->assertInstanceOf(\stdClass::class, $c['jos']->newInstance());
	}

    public function testDefaultValues(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$service = $c['fixture_classes.service_with_default_value'];
		$this->assertEquals(30, $service->getValue());
	}

    public function testAutoFactory(): void
	{
		$c = new AutoPimple();
		$serviceFactory = $c->autoFactory(ServiceWithDependencies::class);
		$this->assertEquals('ServiceWithDependencies', $serviceFactory->newInstance()->getName());
	}

    public function testFactoryByServiceName(): void
	{
		$c = new AutoPimple();
		$serviceFactory = $c['auto_pimple.fixture_classes.service_with_dependencies.factory'];
		$this->assertEquals('ServiceWithDependencies', $serviceFactory->newInstance()->getName());
	}

    public function testFactoryByServiceNameWithCustomParameters(): void
	{
		$c = new AutoPimple();
		$serviceFactory = $c['auto_pimple.fixture_classes.service_with_default_value.factory'];
		$this->assertEquals('success', $serviceFactory->newInstance(array('value' => 'success'))->getValue());
	}

    public function testInjectParametersIntoFactoryByServiceName(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_unfulfillable_dependencies.value'] = 1;
		$serviceFactory = $c['fixture_classes.service_with_unfulfillable_dependencies.factory'];
		$this->assertEquals(1, $serviceFactory->newInstance()->getValue());
	}

	/**
	 * @test
	 * @expectedException InvalidArgumentException
	 */
	public function testExceptionWithDependenciesAreUnfulfillable(): void
	{
		$c = new AutoPimple();
		$serviceFactory = $c->autoFactory(ServiceWithUnfulfillableDependencies::class);
		$serviceFactory->newInstance();
	}

    public function testInjectingParamaters(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_unfulfillable_dependencies.value'] = 1;
		$service = $c['fixture_classes.service_with_unfulfillable_dependencies'];
		$this->assertEquals(1, $service->getValue());
	}

    public function testInjectingParamatersWithDefaultValues(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_default_value.other_value'] = 1;
		$service = $c['fixture_classes.service_with_default_value'];
		$this->assertEquals(1, $service->getOtherValue());
	}

    public function testOverwriteAlias(): void
	{
		$c = new AutoPimple();
		$c['constant-1'] = 1;
		$c['constant-2'] = 2;
		$c->alias('1+1', 'constant-1');
		$c->alias('1+1', 'constant-2');
		$this->assertEquals(2, $c['1+1']);
	}

    public function testFactoryHelperMethod(): void
	{
		$c = new AutoPimple();
		$c['array_factory'] = $c::share($c->factory(static function() {
			return new ArrayObject();
		}));
		$firstArray = $c['array_factory']->newInstance();
		$secondArray = $c['array_factory']->newInstance();
		$this->assertInstanceOf(ArrayObject::class, $firstArray);
		$this->assertNotSame($firstArray, $secondArray);
	}

    public function testGetModifiedWithAutoInjectedParameters(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$newDependency = new FixtureClasses\SimpleServiceWithoutDependencies();
		$service = $c->getModified('fixture_classes.service_with_dependencies', array('dependency' => $newDependency));
		$this->assertSame($newDependency, $service->getDependency());
	}

    public function testGetModifiedWithCustomInjectedParameters(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_unfulfillable_dependencies.value'] = 1;
		$service = $c->getModified('fixture_classes.service_with_unfulfillable_dependencies', array('value' => 2));
		$this->assertEquals(2, $service->getValue());
	}

    public function testExtend(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c->extend('fixture_classes.simple_service_without_dependencies', function($service, $c) {
			return array($service, $c);
		});
		$this->assertTrue(is_array($c['fixture_classes.simple_service_without_dependencies']));
		$this->assertInstanceOf(
            SimpleServiceWithoutDependencies::class,
            $c['fixture_classes.simple_service_without_dependencies'][0]
        );
		$this->assertSame($c, $c['fixture_classes.simple_service_without_dependencies'][1]);
	}

    public function testShareExtend(): void
	{
		$count = 0;
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$extendedService = $c->extend('fixture_classes.simple_service_without_dependencies', static function($service, AutoPimple $c) use(&$count) {
			$count += 1;
			return array($service, $c);
		});
		$this->assertEquals(0, $count);
		$c['fixture_classes.simple_service_without_dependencies'];
		$this->assertEquals(1, $count);
		$c['fixture_classes.simple_service_without_dependencies'];
		$this->assertEquals(2, $count);
		$c['fixture_classes.simple_service_without_dependencies'] = $c::share($extendedService);
		$this->assertTrue(is_array($c['fixture_classes.simple_service_without_dependencies']));
		$this->assertEquals(3, $count);
	}

    public function testBugWithCachedGetModified(): void
	{
		$c = new AutoPimple(array('auto_pimple.' => ''));
		$c['fixture_classes.service_with_unfulfillable_dependencies.value'] = 1;
		$c['fixture_classes.service_with_unfulfillable_dependencies'];
		$service = $c->getModified('fixture_classes.service_with_unfulfillable_dependencies', array('value' => 2));
		$this->assertEquals(2, $service->getValue());
	}
}
 
