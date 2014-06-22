<?php

namespace AutoPimple;

use InvalidArgumentException;
use Pimple;
use ReflectionClass;
use ReflectionMethod;


class AutoPimple extends Pimple
{
	const FACTORY = true;
	const NON_FACTORY = false;

	protected $prefixMap;
	protected $aliases = array();

	public function __construct(array $prefixMap = array(), array $values = array())
	{
		$this->prefixMap = array_merge(array('' => ''), $prefixMap);
		parent::__construct($values);
	}

    public function extend($id, $callable)
    {
		$this->offsetExists($id);
		return parent::extend($id, $callable);
	}

	public function autoFactory($className)
	{
		$serviceReflector = new ReflectionClass($className);
		$underscoreName = StringUtil::underscore($className);
		$factoryCallback = $this->serviceFactoryFromReflector($serviceReflector);
		if(null === $factoryCallback) {
            throw new InvalidArgumentException('Unable to create factory for this class');
		}
		return new Factory($factoryCallback);
	}

	public function factory($factory)
	{
		$self = $this;
		return function() use ($factory, $self) {
			return new Factory(function() use ($self, $factory) { return call_user_func($factory, $self); });
		};
	}

	public function createFactory($factory)
	{
		return new Factory($factory);
	}

	public function alias($from, $to)
	{
		$pairKey = serialize(array($from, $to));
		if($from == $to || (array_key_exists($from, $this->values) && array_key_exists($pairKey, $this->aliases) &&
				$this->values[$from] === $this->aliases[$pairKey])) {
			return;
		}
		$self = $this;
		$this->values[$from] = $this->aliases[$pairKey] = function() use ($self, $to) { return $self[$to]; };
	}

	public function serviceMethod($serviceId, $methodName)
	{
		$self = $this;
		return function() use ($self, $serviceId, $methodName) {
			$arguments = func_get_args();
			return call_user_func_array(array($self->offsetGet($serviceId), $methodName), $arguments);
		};
	}

	public function getModified($id, array $modifiedInjectables = array())
	{
		list($prefixedId, $service) = $this->serviceFactoryAndNameFromPartialServiceId($id, $modifiedInjectables);
        if(null === $prefixedId) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }
        $isFactory = is_object($service) && method_exists($service, '__invoke');
        return $isFactory ? $service($this) : $service;
	}

    public function offsetExists($id)
	{
		list($prefixedId, $serviceFactory) = $this->serviceFactoryAndNameFromPartialServiceId($id);
		if(null === $prefixedId) {
			return false;
		}
		if(! array_key_exists($prefixedId, $this->values)) {
			$this->offsetSet($prefixedId, $this->share($serviceFactory));
		}
		$this->alias($id, $prefixedId);
		return true;
	}

	protected function serviceFactoryAndNameFromPartialServiceId($id, array $modifiedInjectables = array())
	{
		foreach($this->prefixMap as $to => $froms) {
			foreach((array) $froms as $from) {
				if($to != '' && strpos($id, $to) !== 0) {
					continue;
				}
				$prefixedId = $from . substr($id, strlen($to));
				if(array_key_exists($prefixedId, $this->values)) {
					return array($prefixedId, $this->values[$prefixedId]);
				}
			}
		}

		foreach($this->prefixMap as $to => $froms) {
			foreach((array) $froms as $from) {
				if('' != $from && strpos($id, $from) !== 0) {
					continue;
				}
				$prefixedId = $to . substr($id, strlen($from));
				$serviceFactory = $this->serviceFactoryFromFullServiceName($prefixedId, $modifiedInjectables);
				if(null !== $serviceFactory) {
					return array($prefixedId, $serviceFactory);
				}
			}
		}

		return array(null, null);
	}

    public function offsetGet($id)
	{
		$this->offsetExists($id);
		return parent::offsetGet($id);
	}

	protected function serviceFactoryFromFullServiceName($id, array $modifiedInjectables = array())
	{
		if(parent::offsetExists($id)) {
			return;
		}
		$className = StringUtil::camelize($id);
		if(class_exists($className)) {
			return $this->serviceFactoryFromClassName($className, self::NON_FACTORY, $id, $modifiedInjectables);
		}
		if(substr($id, -8) == '.factory') {
			$className = StringUtil::camelize(substr($id, 0, -8));
			if(class_exists($className)) {
				return $this->serviceFactoryFromClassName($className, self::FACTORY, substr($id, 0, -8), $modifiedInjectables);
			}
		}
	}

	protected function serviceFactoryFromClassName($className, $isFactory, $serviceName = null, array $modifiedInjectables = array())
	{
		$serviceReflector = new ReflectionClass($className);
		$serviceFactoryCallback = $this->serviceFactoryFromReflector($serviceReflector, $serviceName, $modifiedInjectables);
		if(null === $serviceFactoryCallback) {
			return null;
		}
		return $isFactory ? function () use ($serviceFactoryCallback) { return new Factory($serviceFactoryCallback); } : $serviceFactoryCallback;
	}

	public function serviceFactoryFromReflector(ReflectionClass $serviceReflector, $serviceName = null, array $modifiedInjectables = array())
	{
		if(! $serviceReflector->hasMethod('__construct')) {
			$dependencies = array();
		} else {
			$constructorReflector = $serviceReflector->getMethod('__construct');

			$dependencies = array();
			foreach($constructorReflector->getParameters() as $parameter) {
				$underscoredParameterName = StringUtil::underscore(ucfirst($parameter->getName()));
				if(array_key_exists($underscoredParameterName, $modifiedInjectables)) {
					$dependencies[] = $modifiedInjectables[$underscoredParameterName];
					continue;
				}
				if(null !== $serviceName && $this->offsetExists("$serviceName.$underscoredParameterName")) {
					$dependencies[] = $this->offsetGet("$serviceName.$underscoredParameterName");
					continue;
				}
				if($parameter->isDefaultValueAvailable()) {
					break;
				}
				$typeHintClass = $parameter->getClass();
				if(null === $typeHintClass) {
					return null;
				}
				$underscoreName = StringUtil::underscore($typeHintClass->getName());
				if(! $this->offsetExists($underscoreName)) {
					return null;
				}
				$dependencies[] = $this->offsetGet($underscoreName);
			}
		}

		return function() use ($serviceReflector, $dependencies) {
			if(count($dependencies) == 0) {
				return $serviceReflector->newInstance();
			} else {
				return $serviceReflector->newInstanceArgs($dependencies);
			}
		};
	}
}
 
