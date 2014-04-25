<?php

namespace AutoPimple;

use InvalidArgumentException;
use Pimple;
use ReflectionClass;
use ReflectionMethod;


class AutoPimple extends Pimple
{
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
		$underscoreName = $this->underscore($className);
		$factoryCallback = $this->factoryCallbackFromReflectorOrNull($serviceReflector);
		if(null === $factoryCallback) {
            throw new InvalidArgumentException('Unable to create factory for this class');
		}
		return new Factory($factoryCallback);
	}

	public function createFactory($factory)
	{
		return new Factory($factory);
	}

	public function alias($from, $to)
	{
		if($from == $to || (array_key_exists($from, $this->values) && array_key_exists($from, $this->aliases) &&
				$this->values[$from] === $this->aliases[$from])) {
			return;
		}
		$self = $this;
		$this->values[$from] = $this->aliases[$from] = function() use ($self, $to) { return $self[$to]; };
	}

	public function serviceMethod($serviceId, $methodName)
	{
		$self = $this;
		return function() use ($self, $serviceId, $methodName) {
			$arguments = func_get_args();
			return call_user_func_array(array($self->offsetGet($serviceId), $methodName), $arguments);
		};
	}

    public function offsetExists($id)
	{
		foreach($this->prefixMap as $to => $froms) {
			foreach((array) $froms as $from) {
				if($to != '' && strpos($id, $to) !== 0) {
					continue;
				}
				$prefixedId = $from . substr($id, strlen($to));
				if(array_key_exists($prefixedId, $this->values)) {
					$this->alias($id, $prefixedId);
					return true;
				}
			}
		}

		foreach($this->prefixMap as $to => $froms) {
			foreach((array) $froms as $from) {
				if($from != '' && strpos($id, $from) !== 0) {
					continue;
				}
				$prefixedId = $to . substr($id, strlen($from));
				if(! array_key_exists($prefixedId, $this->values)) {
					$this->tryAutoRegisterServiceFromFullServiceName($prefixedId);
				}
				if(array_key_exists($prefixedId, $this->values)) {
					$this->alias($id, $prefixedId);
					return true;
				}
			}
		}
		return false;
	}

    public function offsetGet($id)
	{
		$this->offsetExists($id);
		return parent::offsetGet($id);
	}

	protected function camelize($name)
	{
		$name = preg_replace_callback('/(\.|__|_|-|^)(.)/', function($m) {
			$name = ('.' == $m[1] ? '\\' : '');
			if($m['1'] == '__') {
				return $name . '_' . strtoupper($m[2]);
			} else {
				return $name . ('-' == $m[1] ? $m[2] : strtoupper($m[2]));
			}
		}, $name);
		return $name;
	}

	protected function underscore($name)
	{
		$name = str_replace('\\', '.', $name);
		$name = preg_replace_callback('/(?<!^|\.)[A-Z]/', function($m) {
			return '_' . $m[0];
		}, $name);
		$name = preg_replace_callback('/(^|\.)([a-z])/', function($m) {
			return '-' . $m[2];
		}, $name);
		return strtolower($name);
	}

	protected function tryAutoRegisterServiceFromFullServiceName($id)
	{
		if(parent::offsetExists($id)) {
			return;
		}
		$className = $this->camelize($id);
		if(class_exists($className)) {
			return $this->tryAutoRegisterServiceFromClassName($className, $id);
		}
	}

	protected function tryAutoRegisterServiceFromClassName($className, $serviceName = null)
	{
		$serviceReflector = new ReflectionClass($className);
		$underscoreName = $this->underscore($className);
		$serviceFactory = $this->factoryCallbackFromReflectorOrNull($serviceReflector, $serviceName);
		if(null !== $serviceFactory) {
			$this->offsetSet($underscoreName, $this->share($serviceFactory));
		}
	}

	public function factoryCallbackFromReflectorOrNull(ReflectionClass $serviceReflector, $serviceName = null)
	{
		if(! $serviceReflector->hasMethod('__construct')) {
			$dependencies = array();
		} else {
			$constructorReflector = $serviceReflector->getMethod('__construct');

			$dependencies = array();
			foreach($constructorReflector->getParameters() as $parameter) {
				$underscoredParameterName = $this->underscore(ucfirst($parameter->getName()));
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
				$underscoreName = $this->underscore($typeHintClass->getName());
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
 
