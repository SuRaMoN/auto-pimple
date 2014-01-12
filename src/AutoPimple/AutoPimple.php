<?php

namespace AutoPimple;

use InvalidArgumentException;
use Pimple;
use ReflectionClass;
use ReflectionMethod;


class AutoPimple extends Pimple
{
	protected $prefixMap;

	public function __construct(array $prefixMap = array(), array $values = array())
	{
		$this->prefixMap = array_merge(array('' => ''), $prefixMap);
		parent::__construct($values);
	}

	public function alias($from, $to)
	{
		$this->values[$from] = & $this->values[$to];
	}

    public function offsetExists($id)
	{
		foreach($this->prefixMap as $to => $froms) {
			foreach((array) $froms as $from) {
				if($from != '' && strpos($id, $from) !== 0) {
					continue;
				}
				$prefixedId = $to . substr($id, strlen($from));
				$this->tryAutoRegisterServiceFromId($prefixedId);
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
		$name = preg_replace_callback('/(\.|_|^)(.)/', function($m) {
			return ('.' == $m[1] ? '\\' : '') . strtoupper($m[2]);
		}, $name);
		return $name;
	}

	protected function underscore($name)
	{
		$name = preg_replace_callback('/(?<!^|\\\\)[A-Z]/', function($m) {
			return '_' . $m[0];
		}, $name);
		return strtolower(str_replace('\\', '.', $name));
	}

	protected function tryAutoRegisterServiceFromId($id)
	{
		if(parent::offsetExists($id)) {
			return;
		}
		$className = $this->camelize($id);
		if(class_exists($className)) {
			return $this->tryAutoRegisterServiceFromClassName($className);
		}
	}

	protected function tryAutoRegisterServiceFromClassName($className)
	{
		$serviceReflector = new ReflectionClass($className);

		if(! $serviceReflector->hasMethod('__construct')) {
			$dependencies = array();
		} else {
			$constructorReflector = $serviceReflector->getMethod('__construct');

			$dependencies = array();
			foreach($constructorReflector->getParameters() as $parameter) {
				$typeHintClass = $parameter->getClass();
				if(null === $typeHintClass) {
					return;
				}
				$underscoreName = $this->underscore($typeHintClass->getName());
				if(! $this->offsetExists($underscoreName)) {
					return;
				}
				$dependencies[] = $this->offsetGet($underscoreName);
			}
		}

		$underscoreName = $this->underscore($className);
		$this->offsetSet($underscoreName, $this->share(function($c) use ($serviceReflector, $dependencies) {
			return $serviceReflector->newInstanceArgs($dependencies);
		}));
	}
}
 
