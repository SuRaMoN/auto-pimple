
AutoPimple
==========

The [Pimple](https://github.com/silexphp/Pimple) DI container extended with auto-injection functionality

![PHP Composer](https://github.com/SuRaMoN/auto-pimple/workflows/PHP%20Composer/badge.svg)
[![Build Status](https://travis-ci.org/SuRaMoN/auto-pimple.png?branch=master)](https://travis-ci.org/SuRaMoN/auto-pimple)


Quick Introduction
------------------

If you do not know what [Pimple](https://github.com/silexphp/Pimple) is, or you are not using Pimple v1, this is probably nothing for you. AutoPimple is a drop-in replacement for Pimple (AutoPimple extends Pimple) that can auto-inject your services reducing configuration. This introduction assumes you already know the Pimple v1 API.

A typical old-style Pimple setup would look like this:

    namespace MyLittleProject;
    
    class A {}
    class B {}
    class C { function __construct(A $a, B $b) {} }
    
    $c = new \Pimple();
    $c['my_little_project.a'] = $c->share(function($c) { return new A; });
    $c['my_little_project.b'] = $c->share(function($c) { return new B; });
    $c['my_little_project.c'] = $c->share(function($c) {
        return new C($c['my_little_project.a'], $c['my_little_project.b']);
    });
    echo get_class($c['my_little_project.c']); // outputs MyLittleProject\C

If you use AutoPimple all the configuration above can be dropped:

    $c = new \AutoPimple\AutoPimple();
    echo get_class($c['my_little_project.c']); // outputs MyLittleProject\C

AutoPimple will automatically convert [snake case](https://en.wikipedia.org/wiki/Snake_case) service names (abc_def.hij) to [camel case](https://en.wikipedia.org/wiki/CamelCase) names (AbcDef\Hij) and will try to inject services based on the type hinting of the constructor parameters.


Dropping namespace prefixes
---------------------------

Every class can be retrieved automatically (if auto-injections doesn't fail) from the AutoPimple container by converting is full class name to [snake case](https://en.wikipedia.org/wiki/Snake_case). This can cause pretty long and inconvenient service names. You can drop (or replace) prefixes of the namespace like this:

    namespace MyCompany\MyProject\RatherLong\Namespace;
    class A {}

    $c = new \AutoPimple\AutoPimple(array(
        'my_company.my_project.' => '',
        'my_company.my_project.rather_long.namespace.' => '',
        'my_company.my_project.rather_long.' => 'blieblabloe.',
    ));
    // Full path will always work
    $c['my_company.my_project.rather_long.namespace.a'];
    // We mapped 'my_company.my_project.' to ''
    $c['rather_long.namespace.a'];
    // We mapped 'my_company.my_project.rather_long.namespace.' to ''
    $c['a'];
    // We mapped 'my_company.my_project.rather_long.' to 'blieblabloe.'
    $c['blieblabloe.a'];
    // Even though the service name is different, they all point to the same service instance
    var_dump($c['a'] === $c['blieblabloe.a']); // true


Aliases
-------

AutoPimple provides an easy aliasing function. The aliased services are guaranteed to always point to the same data.

    $c['default_timeout'] = 5;
    $c->alias('http_timeout', 'default_timeout');
    
    echo $c['http_timeout']; // outputs 5


Handling services with non-auto-injectable constructor parameters
-----------------------------------------------------------------

Sometimes the construct parameters of your services cannot be type hinted or are type hinted to interfaces. The former can be resolved once, but the latter should be resolved for each individual service. For example assume we have a class setup like this:

    namespace MilkFactory;
    
    interface Animal { }
    class Cow implements Animal {}
    class Sheep implements Animal {}

    class Milker {
        function __construct(Animal $aminal, $milkingFrequency) { }
    }

    $c = new \AutoPimple\AutoPimple(array('milk_factory.' => ''));

As both $animal and $milkingFrequency cannot be injected automatically we can resolve this on multiple ways:

    // 1) old-style resolution (AutoPimple)
    $c['milker'] = $c->share(function($c) {
        return new \MilkFactory\Milker($['cow'], 5);
    });
    
    // 2) AutoPimple style using shortened service name
    $c->alias('milker.animal', 'cow');
    $c['milker.frequency'] = 5;
    
    // 3) AutoPimple style using full service name
    $c->alias('milk_factory.milker.animal', 'cow');
    $c['milk_factory.milker.frequency'] = 5;
    
    // 4) By usings cows as default
    $c->alias('animal', 'cow');
    $c['milker.frequency'] = 5;

The last method is perhaps the hardest to understand. By aliasing 'animal' (short for milk_factory.animal which is the snake case for the MilkFactory\Animal interface name) to cow, we tell AutoPimple all Animal interfaces can be auto-injected with a Cow. So we define a default class for the Animal interface. We can always override the default with method (2).


Testing auto-injected services
------------------------------

When you want to test auto-injected services but you want to mock certain dependencies of the services, AutoPimple has a really easy way of helping you with this. Assume we have the following setup:

    class A {
        function __construct(B $b, C $c, D $d, E $e) {}
    }

If you want the get the service A but you want to mock the parameter $d with a class MockD, then you can to this with the getModified method:

    $c->getModified('a', array('d' => new MockD()));

This will create a new instance of A (getModified will ALWAYS create a new instance) and replace the dependency $d with MockD. The second argument of getModified should be an associative array with the snake cased parameter names (NOT paramater class names) as keys and the mocked dependecy instances as values.
