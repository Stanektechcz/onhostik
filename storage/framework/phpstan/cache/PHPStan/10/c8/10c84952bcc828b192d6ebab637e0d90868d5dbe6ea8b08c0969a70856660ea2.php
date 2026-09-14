<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Events/Dispatcher.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Events\Dispatcher
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4e6cfda694257cd70621ceb3bca7895b0e071fbdc1362437dc6b52ce8aeb5d60-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Events\\Dispatcher',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Events/Dispatcher.php',
      ),
    ),
    'namespace' => 'Illuminate\\Events',
    'name' => 'Illuminate\\Events\\Dispatcher',
    'shortName' => 'Dispatcher',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 45,
    'endLine' => 924,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Events\\Dispatcher',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
      1 => 'Illuminate\\Support\\Traits\\ReadsClassAttributes',
      2 => 'Illuminate\\Support\\Traits\\ReflectsClosures',
      3 => 'Illuminate\\Support\\Queue\\Concerns\\ResolvesQueueRoutes',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'container' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'container',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The IoC container instance.
 *
 * @var \\Illuminate\\Contracts\\Container\\Container
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'listeners' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'listeners',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 61,
            'endLine' => 61,
            'startTokenPos' => 258,
            'startFilePos' => 2148,
            'endTokenPos' => 259,
            'endFilePos' => 2149,
          ),
        ),
        'docComment' => '/**
 * The registered event listeners.
 *
 * @var array<string, callable|array|class-string|null>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wildcards' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'wildcards',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 68,
            'endLine' => 68,
            'startTokenPos' => 270,
            'startFilePos' => 2277,
            'endTokenPos' => 271,
            'endFilePos' => 2278,
          ),
        ),
        'docComment' => '/**
 * The wildcard listeners.
 *
 * @var array<string, \\Closure|string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wildcardsCache' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'wildcardsCache',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 75,
            'endLine' => 75,
            'startTokenPos' => 282,
            'startFilePos' => 2418,
            'endTokenPos' => 283,
            'endFilePos' => 2419,
          ),
        ),
        'docComment' => '/**
 * The cached wildcard listeners.
 *
 * @var array<string, \\Closure|string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queueResolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'queueResolver',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The queue resolver instance.
 *
 * @var callable(): \\Illuminate\\Contracts\\Queue\\Queue
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'transactionManagerResolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'transactionManagerResolver',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database transaction manager resolver instance.
 *
 * @var callable
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 89,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'deferredEvents' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'deferredEvents',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 96,
            'endLine' => 96,
            'startTokenPos' => 308,
            'startFilePos' => 2829,
            'endTokenPos' => 309,
            'endFilePos' => 2830,
          ),
        ),
        'docComment' => '/**
 * The currently deferred events.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 96,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'deferringEvents' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'deferringEvents',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 103,
            'endLine' => 103,
            'startTokenPos' => 320,
            'startFilePos' => 2954,
            'endTokenPos' => 320,
            'endFilePos' => 2958,
          ),
        ),
        'docComment' => '/**
 * Indicates if events should be deferred.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'eventsToDefer' => 
      array (
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'name' => 'eventsToDefer',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 110,
            'endLine' => 110,
            'startTokenPos' => 331,
            'startFilePos' => 3109,
            'endTokenPos' => 331,
            'endFilePos' => 3112,
          ),
        ),
        'docComment' => '/**
 * The specific events to defer (null means defer all events).
 *
 * @var string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 110,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 115,
                'endLine' => 115,
                'startTokenPos' => 349,
                'startFilePos' => 3243,
                'endTokenPos' => 349,
                'endFilePos' => 3246,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Illuminate\\Contracts\\Container\\Container',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 33,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new event dispatcher instance.
 */',
        'startLine' => 115,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'listen' => 
      array (
        'name' => 'listen',
        'parameters' => 
        array (
          'events' => 
          array (
            'name' => 'events',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 127,
                'endLine' => 127,
                'startTokenPos' => 387,
                'startFilePos' => 3648,
                'endTokenPos' => 387,
                'endFilePos' => 3651,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register an event listener with the dispatcher.
 *
 * @param  \\Illuminate\\Events\\QueuedClosure|callable|array|class-string|string  $events
 * @param  \\Illuminate\\Events\\QueuedClosure|callable|array|class-string|null  $listener
 * @return void
 */',
        'startLine' => 127,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'setupWildcardListen' => 
      array (
        'name' => 'setupWildcardListen',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 159,
            'endLine' => 159,
            'startColumn' => 52,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Setup a wildcard listener callback.
 *
 * @param  string  $event
 * @param  \\Closure|string  $listener
 * @return void
 */',
        'startLine' => 159,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'hasListeners' => 
      array (
        'name' => 'hasListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if a given event has listeners.
 *
 * @param  string  $eventName
 * @return bool
 */',
        'startLine' => 172,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'hasWildcardListeners' => 
      array (
        'name' => 'hasWildcardListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 42,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given event has any wildcard listeners.
 *
 * @param  string  $eventName
 * @return bool
 */',
        'startLine' => 185,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'push' => 
      array (
        'name' => 'push',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 197,
            'endLine' => 197,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 197,
                'endLine' => 197,
                'startTokenPos' => 765,
                'startFilePos' => 5749,
                'endTokenPos' => 766,
                'endFilePos' => 5750,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 197,
            'endLine' => 197,
            'startColumn' => 34,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register an event and payload to be fired later.
 *
 * @param  string  $event
 * @param  object|array  $payload
 * @return void
 */',
        'startLine' => 197,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'flush' => 
      array (
        'name' => 'flush',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 210,
            'endLine' => 210,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Flush a set of pushed events.
 *
 * @param  string  $event
 * @return void
 */',
        'startLine' => 210,
        'endLine' => 213,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'subscribe' => 
      array (
        'name' => 'subscribe',
        'parameters' => 
        array (
          'subscriber' => 
          array (
            'name' => 'subscriber',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register an event subscriber with the dispatcher.
 *
 * @param  object|string  $subscriber
 * @return void
 */',
        'startLine' => 221,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'resolveSubscriber' => 
      array (
        'name' => 'resolveSubscriber',
        'parameters' => 
        array (
          'subscriber' => 
          array (
            'name' => 'subscriber',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 248,
            'endLine' => 248,
            'startColumn' => 42,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resolve the subscriber instance.
 *
 * @param  object|class-string  $subscriber
 * @return ($subscriber is object ? object : mixed)
 */',
        'startLine' => 248,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'until' => 
      array (
        'name' => 'until',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 264,
                'endLine' => 264,
                'startTokenPos' => 1043,
                'startFilePos' => 7488,
                'endTokenPos' => 1044,
                'endFilePos' => 7489,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 35,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fire an event until the first non-null response is returned.
 *
 * @param  string|object  $event
 * @param  mixed  $payload
 * @return array|null
 */',
        'startLine' => 264,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'dispatch' => 
      array (
        'name' => 'dispatch',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 277,
                'endLine' => 277,
                'startTokenPos' => 1082,
                'startFilePos' => 7798,
                'endTokenPos' => 1083,
                'endFilePos' => 7799,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'halt' => 
          array (
            'name' => 'halt',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 277,
                'endLine' => 277,
                'startTokenPos' => 1090,
                'startFilePos' => 7810,
                'endTokenPos' => 1090,
                'endFilePos' => 7814,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fire an event and call the listeners.
 *
 * @param  string|object  $event
 * @param  mixed  $payload
 * @param  bool  $halt
 * @return array|null
 */',
        'startLine' => 277,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'invokeListeners' => 
      array (
        'name' => 'invokeListeners',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 48,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'halt' => 
          array (
            'name' => 'halt',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 317,
                'endLine' => 317,
                'startTokenPos' => 1282,
                'startFilePos' => 9327,
                'endTokenPos' => 1282,
                'endFilePos' => 9331,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Broadcast an event and call its listeners.
 *
 * @param  string|object  $event
 * @param  mixed  $payload
 * @param  bool  $halt
 * @return array|null
 */',
        'startLine' => 317,
        'endLine' => 346,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'parseEventAndPayload' => 
      array (
        'name' => 'parseEventAndPayload',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 53,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the given event and payload and prepare them for dispatching.
 *
 * @param  mixed  $event
 * @param  mixed  $payload
 * @return array{string, array}
 */',
        'startLine' => 355,
        'endLine' => 362,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'shouldBroadcast' => 
      array (
        'name' => 'shouldBroadcast',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 369,
            'endLine' => 369,
            'startColumn' => 40,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the payload has a broadcastable event.
 *
 * @return bool
 */',
        'startLine' => 369,
        'endLine' => 374,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'broadcastWhen' => 
      array (
        'name' => 'broadcastWhen',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 382,
            'endLine' => 382,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if the event should be broadcasted by the condition.
 *
 * @param  mixed  $event
 * @return bool
 */',
        'startLine' => 382,
        'endLine' => 387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'broadcastEvent' => 
      array (
        'name' => 'broadcastEvent',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 395,
            'endLine' => 395,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Broadcast the given event class.
 *
 * @param  \\Illuminate\\Contracts\\Broadcasting\\ShouldBroadcast  $event
 * @return void
 */',
        'startLine' => 395,
        'endLine' => 398,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'getListeners' => 
      array (
        'name' => 'getListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 406,
            'endLine' => 406,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the listeners for a given event name.
 *
 * @param  string  $eventName
 * @return array
 */',
        'startLine' => 406,
        'endLine' => 416,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'getWildcardListeners' => 
      array (
        'name' => 'getWildcardListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 424,
            'endLine' => 424,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the wildcard listeners for the event.
 *
 * @param  string  $eventName
 * @return array
 */',
        'startLine' => 424,
        'endLine' => 437,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'addInterfaceListeners' => 
      array (
        'name' => 'addInterfaceListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 445,
            'endLine' => 445,
            'startColumn' => 46,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'listeners' => 
          array (
            'name' => 'listeners',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 445,
                'endLine' => 445,
                'startTokenPos' => 1818,
                'startFilePos' => 13005,
                'endTokenPos' => 1819,
                'endFilePos' => 13006,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 445,
            'endLine' => 445,
            'startColumn' => 58,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add the listeners for the event\'s interfaces to the given array.
 *
 * @param  string  $eventName
 * @return array
 */',
        'startLine' => 445,
        'endLine' => 456,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'prepareListeners' => 
      array (
        'name' => 'prepareListeners',
        'parameters' => 
        array (
          'eventName' => 
          array (
            'name' => 'eventName',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 463,
            'endLine' => 463,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the listeners for a given event.
 *
 * @return \\Closure[]
 */',
        'startLine' => 463,
        'endLine' => 472,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'makeListener' => 
      array (
        'name' => 'makeListener',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 481,
            'endLine' => 481,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'wildcard' => 
          array (
            'name' => 'wildcard',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 481,
                'endLine' => 481,
                'startTokenPos' => 1983,
                'startFilePos' => 13977,
                'endTokenPos' => 1983,
                'endFilePos' => 13981,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 481,
            'endLine' => 481,
            'startColumn' => 45,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register an event listener with the dispatcher.
 *
 * @param  \\Closure|string|array{class-string, string}  $listener
 * @param  bool  $wildcard
 * @return \\Closure
 */',
        'startLine' => 481,
        'endLine' => 498,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'createClassListener' => 
      array (
        'name' => 'createClassListener',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'wildcard' => 
          array (
            'name' => 'wildcard',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 507,
                'endLine' => 507,
                'startTokenPos' => 2135,
                'startFilePos' => 14750,
                'endTokenPos' => 2135,
                'endFilePos' => 14754,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 52,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a class based listener using the IoC container.
 *
 * @param  string  $listener
 * @param  bool  $wildcard
 * @return \\Closure
 */',
        'startLine' => 507,
        'endLine' => 518,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'createClassCallable' => 
      array (
        'name' => 'createClassCallable',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 526,
            'endLine' => 526,
            'startColumn' => 44,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create the class based event callable.
 *
 * @param  array{class-string, string}|string  $listener
 * @return callable
 */',
        'startLine' => 526,
        'endLine' => 546,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'parseClassCallable' => 
      array (
        'name' => 'parseClassCallable',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 554,
            'endLine' => 554,
            'startColumn' => 43,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the class listener into class and method.
 *
 * @param  string  $listener
 * @return array{class-string, string}
 */',
        'startLine' => 554,
        'endLine' => 557,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'handlerShouldBeQueued' => 
      array (
        'name' => 'handlerShouldBeQueued',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 567,
            'endLine' => 567,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event handler class should be queued.
 *
 * @param  class-string  $class
 * @return bool
 *
 * @phpstan-assert-if-true class-string<\\Illuminate\\Contracts\\Queue\\ShouldQueue> $class
 */',
        'startLine' => 567,
        'endLine' => 576,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'createQueuedHandlerCallable' => 
      array (
        'name' => 'createQueuedHandlerCallable',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 585,
            'endLine' => 585,
            'startColumn' => 52,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 585,
            'endLine' => 585,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a callable for putting an event handler on the queue.
 *
 * @param  class-string  $class
 * @param  string  $method
 * @return \\Closure(): void
 */',
        'startLine' => 585,
        'endLine' => 596,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'handlerShouldBeDispatchedAfterDatabaseTransactions' => 
      array (
        'name' => 'handlerShouldBeDispatchedAfterDatabaseTransactions',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 604,
            'endLine' => 604,
            'startColumn' => 75,
            'endColumn' => 83,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given event handler should be dispatched after all database transactions have committed.
 *
 * @param  mixed  $listener
 * @return bool
 */',
        'startLine' => 604,
        'endLine' => 609,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'createCallbackForListenerRunningAfterCommits' => 
      array (
        'name' => 'createCallbackForListenerRunningAfterCommits',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 69,
            'endColumn' => 77,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 80,
            'endColumn' => 86,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a callable for dispatching a listener after database transactions.
 *
 * @param  mixed  $listener
 * @param  string  $method
 * @return \\Closure
 */',
        'startLine' => 618,
        'endLine' => 629,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'handlerWantsToBeQueued' => 
      array (
        'name' => 'handlerWantsToBeQueued',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'arguments' => 
          array (
            'name' => 'arguments',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 638,
            'endLine' => 638,
            'startColumn' => 55,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event handler wants to be queued.
 *
 * @param  class-string  $class
 * @param  array  $arguments
 * @return bool
 */',
        'startLine' => 638,
        'endLine' => 647,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'queueHandler' => 
      array (
        'name' => 'queueHandler',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 659,
            'endLine' => 659,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 659,
            'endLine' => 659,
            'startColumn' => 45,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'arguments' => 
          array (
            'name' => 'arguments',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 659,
            'endLine' => 659,
            'startColumn' => 54,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Queue the handler class.
 *
 * @param  string  $class
 * @param  string  $method
 * @param  array  $arguments
 * @return void
 *
 * @throws \\LogicException
 */',
        'startLine' => 659,
        'endLine' => 707,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'createListenerAndJob' => 
      array (
        'name' => 'createListenerAndJob',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 53,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'arguments' => 
          array (
            'name' => 'arguments',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 721,
            'endLine' => 721,
            'startColumn' => 62,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create the listener and job for a queued listener.
 *
 * @template TListener
 *
 * @param  class-string<TListener>  $class
 * @param  string  $method
 * @param  array  $arguments
 * @return array{TListener, mixed}
 *
 * @throws \\ReflectionException
 */',
        'startLine' => 721,
        'endLine' => 728,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'propagateListenerOptions' => 
      array (
        'name' => 'propagateListenerOptions',
        'parameters' => 
        array (
          'listener' => 
          array (
            'name' => 'listener',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 737,
            'endLine' => 737,
            'startColumn' => 49,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 737,
            'endLine' => 737,
            'startColumn' => 60,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Propagate listener options to the job.
 *
 * @param  mixed  $listener
 * @param  \\Illuminate\\Events\\CallQueuedListener  $job
 * @return \\Illuminate\\Events\\CallQueuedListener
 */',
        'startLine' => 737,
        'endLine' => 786,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'forget' => 
      array (
        'name' => 'forget',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 794,
            'endLine' => 794,
            'startColumn' => 28,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove a set of listeners from the dispatcher.
 *
 * @param  string  $event
 * @return void
 */',
        'startLine' => 794,
        'endLine' => 807,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'forgetPushed' => 
      array (
        'name' => 'forgetPushed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Forget all of the pushed listeners.
 *
 * @return void
 */',
        'startLine' => 814,
        'endLine' => 821,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'resolveQueue' => 
      array (
        'name' => 'resolveQueue',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queue implementation from the resolver.
 *
 * @return \\Illuminate\\Contracts\\Queue\\Queue
 */',
        'startLine' => 828,
        'endLine' => 831,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'setQueueResolver' => 
      array (
        'name' => 'setQueueResolver',
        'parameters' => 
        array (
          'resolver' => 
          array (
            'name' => 'resolver',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 839,
            'endLine' => 839,
            'startColumn' => 38,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the queue resolver implementation.
 *
 * @param  callable(): \\Illuminate\\Contracts\\Queue\\Queue  $resolver
 * @return $this
 */',
        'startLine' => 839,
        'endLine' => 844,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'resolveTransactionManager' => 
      array (
        'name' => 'resolveTransactionManager',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database transaction manager implementation from the resolver.
 *
 * @return \\Illuminate\\Database\\DatabaseTransactionsManager|null
 */',
        'startLine' => 851,
        'endLine' => 854,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'setTransactionManagerResolver' => 
      array (
        'name' => 'setTransactionManagerResolver',
        'parameters' => 
        array (
          'resolver' => 
          array (
            'name' => 'resolver',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 862,
            'endLine' => 862,
            'startColumn' => 51,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the database transaction manager resolver implementation.
 *
 * @param  (callable(): (\\Illuminate\\Database\\DatabaseTransactionsManager|null))  $resolver
 * @return $this
 */',
        'startLine' => 862,
        'endLine' => 867,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'defer' => 
      array (
        'name' => 'defer',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 878,
            'endLine' => 878,
            'startColumn' => 27,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'events' => 
          array (
            'name' => 'events',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 878,
                'endLine' => 878,
                'startTokenPos' => 4320,
                'startFilePos' => 27434,
                'endTokenPos' => 4320,
                'endFilePos' => 27437,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 878,
            'endLine' => 878,
            'startColumn' => 47,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the given callback while deferring events, then dispatch all deferred events.
 *
 * @template TResult
 *
 * @param  callable(): TResult  $callback
 * @param  string[]|null  $events
 * @return TResult
 */',
        'startLine' => 878,
        'endLine' => 903,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'shouldDeferEvent' => 
      array (
        'name' => 'shouldDeferEvent',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 910,
            'endLine' => 910,
            'startColumn' => 41,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given event should be deferred.
 *
 * @return bool
 */',
        'startLine' => 910,
        'endLine' => 913,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
      'getRawListeners' => 
      array (
        'name' => 'getRawListeners',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the raw, unprepared listeners.
 *
 * @return array<string, callable|array|class-string|null>
 */',
        'startLine' => 920,
        'endLine' => 923,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Events',
        'declaringClassName' => 'Illuminate\\Events\\Dispatcher',
        'implementingClassName' => 'Illuminate\\Events\\Dispatcher',
        'currentClassName' => 'Illuminate\\Events\\Dispatcher',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));