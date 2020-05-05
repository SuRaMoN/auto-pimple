<?php

namespace AutoPimple;

use Pimple;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Extension for pimple allowing auto-wiring
 */
class AutoPimple extends Pimple implements ContainerInterface
{
    /** @var string */
    private $cacheFolder;

    /** @var array<string,string> */
    private $cacheData = [];

    /** @var array<string,string> */
    private $prefixMap;

    /** @var array<string,string> */
    private $aliases = [];

    public function __construct(array $prefixMap = [], array $values = [], $cacheFolder = null)
    {
        parent::__construct($values);
        $this->cacheFolder = $cacheFolder;
        if (null !== $cacheFolder && is_dir($cacheFolder)) {
            $this->cacheData = @include("{$this->cacheFolder}/autopimple.php");
        }
        if (false === $this->cacheData) {
            $this->cacheData = [];
        }
        $this->prefixMap = array_merge(['' => ''], $prefixMap);
    }

    /**
     * {@inheritdoc}
     */
    public function extend($id, $callable): ExtendedService
    {
        $hasDefinedService = array_key_exists($id, $this->values);
        $baseService = $hasDefinedService ? $this->values[$id] : null;
        return $this->values[$id] = new ExtendedService($id, $baseService, $callable, $hasDefinedService, $this);
    }

    /**
     * {@inheritdoc}
     */
    public static function share($callable)
    {
        if (! is_object($callable) || ! method_exists($callable, '__invoke')) {
            throw new InvalidDefinitionException('Service definition is not a Closure or invokable object.');
        }

        return static function ($c) use ($callable) {
            static $object;

            if (null === $object) {
                $object = $callable($c);
            }

            return $object;
        };
    }

    /**
     * {@inheritdoc}
     * @template T
     * @param class-string<T> $type
     * @return T
     */
    public function get($id)
    {
        $serviceName = StringUtil::underscore($id);
        foreach ($this->prefixMap as $prefix => $newPrefix) {
            if ('' !== $prefix && strpos($serviceName, $prefix) === 0) {
                $serviceName = $newPrefix . substr($serviceName, strlen($prefix));
                break;
            }
        }
        $service = $this->offsetGet($serviceName);
        if (!$service instanceof $id) {
            throw new NotFoundException('Expected service of class "' . $id . '"');
        }
        return $service;
    }

    /**
     * Creates a factory for a given classname and this factory will use auto-injection
     *
     * @template T
     * @param class-string<T> $type
     * @return \AutoPimple\Factory<T>
     */
    public function autoFactory(string $className)
    {
        $serviceReflector = new ReflectionClass($className);
        $factoryCallback = $this->serviceFactoryFromReflector($serviceReflector);
        if (null === $factoryCallback) {
            throw new InvalidFactoryException('Unable to create factory for this class ' . $className);
        }
        return new Factory($factoryCallback);
    }

    public function lazyModified($serviceId, array $arguments)
    {
        return function () use ($serviceId, $arguments) {
            return $this->getModified($serviceId, $arguments);
        };
    }

    public function lazy($serviceId)
    {
        return function () use ($serviceId) {
            return $this->offsetGet($serviceId);
        };
    }

    /**
     * @template T
     * @param class-string<T> $type
     * @return callable:T
     */
    public function lazyGet(string $className)
    {
        return function () use ($className) {
            return $this->get($className);
        };
    }

    public function factory($factory)
    {
        $self = $this;
        return static function() use ($factory, $self) {
            return new Factory(static function() use ($self, $factory) { return $factory($self); });
        };
    }

    public function createFactory($factory)
    {
        return new Factory($factory);
    }

    public function createServiceFactory($serviceId, array $arguments = [])
    {
        $self = $this;
        return new Factory(static function(array $arguments = []) use ($self, $serviceId) {
            return $self->getModified($serviceId, $arguments);
        });
    }

    public function alias($from, $to)
    {
        $pairKey = serialize([$from, $to]);
        if ($from === $to || (array_key_exists($from, $this->values) && array_key_exists($pairKey, $this->aliases) &&
                $this->values[$from] === $this->aliases[$pairKey])
        ) {
            return;
        }
        $this->values[$from] = $this->aliases[$pairKey] = new AliasedService($to);
    }

    public function serviceMethod($serviceId, $methodName)
    {
        $self = $this;
        return static function() use ($self, $serviceId, $methodName) {
            $arguments = func_get_args();
            return call_user_func_array([$self->offsetGet($serviceId), $methodName], $arguments);
        };
    }

    /**
     * The same as offsetGet(), but is accepts an extra parameter that is an array of values that
     * can be injected in the constructor of the service.
     * eg. if there a class A { function __construct(B b, C c) .. }, autopimple will try to auto-inject
     * the parameters b and c. If you want non default parameters you can specify them like this:
     * getModified('a', array('c' => $otherC));
     * The c parameter will be injected with the $otherC variable and b will be auto-injected like always
     *
     * @throws \AutoPimple\NotFoundException
     */
    public function getModified($id, array $modifiedInjectables = [])
    {
        list($prefixedId, $service) = $this->serviceFactoryAndNameFromPartialServiceId($id, $modifiedInjectables);
        if (null === $prefixedId) {
            throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $id));
        }
        $isFactory = is_object($service) && method_exists($service, '__invoke');
        return $isFactory ? $service($this) : $service;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($id)
    {
        list($prefixedId, $serviceFactory) = $this->serviceFactoryAndNameFromPartialServiceId($id);
        if (null === $prefixedId) {
            return false;
        }
        if (! array_key_exists($prefixedId, $this->values)) {
            $this->offsetSet($prefixedId, self::share($serviceFactory));
        }
        $this->alias($id, $prefixedId);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($id)
    {
        $this->offsetExists($id);
        if (array_key_exists($id, $this->values) && $this->values[$id] instanceof ExtendedService) {
            return $this->getExtendedService($this->values[$id]);
        }

        if (array_key_exists($id, $this->values) && $this->values[$id] instanceof AliasedService) {
            return $this->offsetGet($this->values[$id]->getTarget());
        }

        try {
            return parent::offsetGet($id);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundException($e->getMessage(), null, $e);
        } catch (\Exception $e) {
            throw new ContainerException($e->getMessage(), null, $e);
        }
    }

    public function getExtendedService(ExtendedService $sharedService)
    {
        $id = $sharedService->getId();
        $originalService = $this->values[$id];
        $this->values[$id] = $sharedService;
        while ($this->values[$id] instanceof ExtendedService) {
            $extender = $this->values[$id]->getExtender();
            if ($this->values[$id]->getHasDefinedService()) {
                $this->values[$id] = $this->values[$id]->getBaseService();
            } else {
                unset($this->values[$id]);
            }
            $this->values[$id] = $extender($this->offsetGet($id), $this);
        }
        $service = $this->values[$id];
        $this->values[$id] = $originalService;
        return $service;
    }

    protected function writeCache($key, $value)
    {
        $this->cacheData[$key] = $value;
        // One chance in 50 to write to cache. This will fill the cache fast but avoids
        // writing too much after a cache clear
        if (null !== $this->cacheFolder && is_dir($this->cacheFolder) && rand(1, 50) == 1) {
            $data = var_export($this->cacheData, true);
            $status = @file_put_contents("{$this->cacheFolder}/autopimple.php.tmp", "<?php return $data;", LOCK_EX);
            if (false !== $status) {
                rename("{$this->cacheFolder}/autopimple.php.tmp", "{$this->cacheFolder}/autopimple.php");
            }
        }
    }

    protected function serviceFactoryAndNameFromPartialServiceId($id, array $modifiedInjectables = [])
    {
        if (count($modifiedInjectables) === 0) {
            foreach ($this->prefixMap as $to => $froms) {
                foreach ((array) $froms as $from) {
                    if ('' !== $to && strpos($id, $to) !== 0) {
                        continue;
                    }
                    $prefixedId = $from . substr($id, strlen($to));
                    if (array_key_exists($prefixedId, $this->values)) {
                        return [$prefixedId, $this->values[$prefixedId]];
                    }
                }
            }
        }

        foreach ($this->prefixMap as $to => $froms) {
            foreach ((array) $froms as $from) {
                if ('' !== $from && strpos($id, $from) !== 0) {
                    continue;
                }
                $prefixedId = $to . substr($id, strlen($from));
                if (array_key_exists("no_class:$prefixedId", $this->cacheData)) {
                    continue;
                }
                $serviceFactory = $this->serviceFactoryFromFullServiceName($prefixedId, $modifiedInjectables);
                if (null !== $serviceFactory) {
                    return [$prefixedId, $serviceFactory];
                }
            }
        }

        return [null, null];
    }

    protected function serviceFactoryFromFullServiceName($id, array $modifiedInjectables = [])
    {
        if (parent::offsetExists($id) && count($modifiedInjectables) === 0) {
            return $this->values[$id];
        }
        $className = StringUtil::camelize($id);
        if (class_exists($className)) {
            return $this->serviceFactoryFromClassName($className, $id, $modifiedInjectables);
        }
        if (substr($id, -8) === '.factory') {
            $self = $this;
            $serviceId = substr($id, 0, -8);
            return static function () use ($self, $serviceId) {
                return $self->createServiceFactory($serviceId);
            };
        }
        $this->writeCache("no_class:$id", true);
    }

    protected function serviceFactoryFromClassName($className, $serviceName = null, array $modifiedInjectables = [])
    {
        $serviceReflector = new ReflectionClass($className);
        $serviceFactoryCallback = $this->serviceFactoryFromReflector($serviceReflector, $serviceName, $modifiedInjectables);
        if (null === $serviceFactoryCallback) {
            return null;
        }
        return $serviceFactoryCallback;
    }

    protected function serviceFactoryFromReflector(ReflectionClass $serviceReflector, $serviceName = null, array $modifiedInjectables = [])
    {
        if (! $serviceReflector->hasMethod('__construct')) {
            $dependencies = [];
        } else {
            $constructorReflector = $serviceReflector->getMethod('__construct');

            $dependencies = [];
            foreach ($constructorReflector->getParameters() as $parameter) {
                $underscoredParameterName = StringUtil::underscore(ucfirst($parameter->getName()));
                if (array_key_exists($underscoredParameterName, $modifiedInjectables)) {
                    $dependencies[] = $modifiedInjectables[$underscoredParameterName];
                    continue;
                }
                if (null !== $serviceName && $this->offsetExists("$serviceName.$underscoredParameterName")) {
                    $dependencies[] = $this->offsetGet("$serviceName.$underscoredParameterName");
                    continue;
                }
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                $typeHintClass = $parameter->getClass();
                if (null === $typeHintClass) {
                    return null;
                }
                $underscoreName = StringUtil::underscore($typeHintClass->getName());
                if (! $this->offsetExists($underscoreName)) {
                    return null;
                }
                $dependencies[] = $this->offsetGet($underscoreName);
            }
        }

        return static function() use ($serviceReflector, $dependencies) {
            return $serviceReflector->newInstanceArgs($dependencies);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        $serviceName = StringUtil::underscore($id);
        foreach ($this->prefixMap as $prefix => $newPrefix) {
            if ('' !== $prefix && strpos($serviceName, $prefix) === 0) {
                $serviceName = $newPrefix . substr($serviceName, strlen($prefix));
                break;
            }
        }

        return $this->offsetExists($serviceName);
    }
}
