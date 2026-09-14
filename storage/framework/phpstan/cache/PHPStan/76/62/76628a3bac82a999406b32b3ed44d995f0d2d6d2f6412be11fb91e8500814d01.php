<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Routing/RouteRegistrar.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Routing\RouteRegistrar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-a109ea778a54ab70bcece9c3e37981f38216c0be6c763121bb2ac320b039b0f0-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Routing\\RouteRegistrar',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Routing/RouteRegistrar.php',
      ),
    ),
    'namespace' => 'Illuminate\\Routing',
    'name' => 'Illuminate\\Routing\\RouteRegistrar',
    'shortName' => 'RouteRegistrar',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @method \\Illuminate\\Routing\\Route any(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route delete(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route get(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route options(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route patch(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route post(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\Route put(string $uri, \\Closure|array|string|null $action = null)
 * @method \\Illuminate\\Routing\\RouteRegistrar as(string $value)
 * @method \\Illuminate\\Routing\\RouteRegistrar can(\\UnitEnum|string  $ability, array|string $models = [])
 * @method \\Illuminate\\Routing\\RouteRegistrar controller(string $controller)
 * @method \\Illuminate\\Routing\\RouteRegistrar domain(\\BackedEnum|string $value)
 * @method \\Illuminate\\Routing\\RouteRegistrar metadata(array $metadata)
 * @method \\Illuminate\\Routing\\RouteRegistrar middleware(array|string|null $middleware)
 * @method \\Illuminate\\Routing\\RouteRegistrar missing(\\Closure $missing)
 * @method \\Illuminate\\Routing\\RouteRegistrar name(\\BackedEnum|string $value)
 * @method \\Illuminate\\Routing\\RouteRegistrar namespace(string|null $value)
 * @method \\Illuminate\\Routing\\RouteRegistrar prefix(string $prefix)
 * @method \\Illuminate\\Routing\\RouteRegistrar scopeBindings()
 * @method \\Illuminate\\Routing\\RouteRegistrar where(array $where)
 * @method \\Illuminate\\Routing\\RouteRegistrar withoutMiddleware(array|string $middleware)
 * @method \\Illuminate\\Routing\\RouteRegistrar withoutScopedBindings()
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 36,
    'endLine' => 347,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Routing\\CreatesRegularExpressionRouteConstraints',
      1 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'router' => 
      array (
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'name' => 'router',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The router instance.
 *
 * @var \\Illuminate\\Routing\\Router
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'attributes' => 
      array (
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'name' => 'attributes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 85,
            'startFilePos' => 2356,
            'endTokenPos' => 86,
            'endFilePos' => 2357,
          ),
        ),
        'docComment' => '/**
 * The attributes to pass on to the router.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'passthru' => 
      array (
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'name' => 'passthru',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'get\', \'post\', \'put\', \'patch\', \'delete\', \'options\', \'any\']',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 64,
            'startTokenPos' => 97,
            'startFilePos' => 2493,
            'endTokenPos' => 120,
            'endFilePos' => 2566,
          ),
        ),
        'docComment' => '/**
 * The methods to dynamically pass through to the router.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedAttributes' => 
      array (
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'name' => 'allowedAttributes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'as\', \'can\', \'controller\', \'domain\', \'metadata\', \'middleware\', \'missing\', \'name\', \'namespace\', \'prefix\', \'scopeBindings\', \'where\', \'withoutMiddleware\', \'withoutScopedBindings\']',
          'attributes' => 
          array (
            'startLine' => 71,
            'endLine' => 86,
            'startTokenPos' => 131,
            'startFilePos' => 2707,
            'endTokenPos' => 175,
            'endFilePos' => 3002,
          ),
        ),
        'docComment' => '/**
 * The attributes that can be set through this class.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'aliases' => 
      array (
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'name' => 'aliases',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'name\' => \'as\', \'scopeBindings\' => \'scope_bindings\', \'withoutScopedBindings\' => \'scope_bindings\', \'withoutMiddleware\' => \'excluded_middleware\']',
          'attributes' => 
          array (
            'startLine' => 93,
            'endLine' => 98,
            'startTokenPos' => 186,
            'startFilePos' => 3112,
            'endTokenPos' => 216,
            'endFilePos' => 3294,
          ),
        ),
        'docComment' => '/**
 * The attributes that are aliased.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 93,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 6,
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
          'router' => 
          array (
            'name' => 'router',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Routing\\Router',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create a new route registrar instance.
 *
 * @param  \\Illuminate\\Routing\\Router  $router
 */',
        'startLine' => 105,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'attribute' => 
      array (
        'name' => 'attribute',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 37,
            'endColumn' => 42,
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
 * Set the value for a given attribute.
 *
 * @param  string  $key
 * @param  mixed  $value
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 119,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'resource' => 
      array (
        'name' => 'resource',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'controller' => 
          array (
            'name' => 'controller',
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
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 172,
                'endLine' => 172,
                'startTokenPos' => 595,
                'startFilePos' => 5355,
                'endTokenPos' => 596,
                'endFilePos' => 5356,
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
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 50,
            'endColumn' => 68,
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
 * Route a resource to a controller.
 *
 * @param  string  $name
 * @param  string  $controller
 * @param  array  $options
 * @return \\Illuminate\\Routing\\PendingResourceRegistration
 */',
        'startLine' => 172,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'apiResource' => 
      array (
        'name' => 'apiResource',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'controller' => 
          array (
            'name' => 'controller',
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
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 185,
                'endLine' => 185,
                'startTokenPos' => 647,
                'startFilePos' => 5758,
                'endTokenPos' => 648,
                'endFilePos' => 5759,
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
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 53,
            'endColumn' => 71,
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
 * Route an API resource to a controller.
 *
 * @param  string  $name
 * @param  string  $controller
 * @param  array  $options
 * @return \\Illuminate\\Routing\\PendingResourceRegistration
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
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'singleton' => 
      array (
        'name' => 'singleton',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 198,
            'endLine' => 198,
            'startColumn' => 31,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'controller' => 
          array (
            'name' => 'controller',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 198,
            'endLine' => 198,
            'startColumn' => 38,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 198,
                'endLine' => 198,
                'startTokenPos' => 699,
                'startFilePos' => 6176,
                'endTokenPos' => 700,
                'endFilePos' => 6177,
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
            'startLine' => 198,
            'endLine' => 198,
            'startColumn' => 51,
            'endColumn' => 69,
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
 * Route a singleton resource to a controller.
 *
 * @param  string  $name
 * @param  string  $controller
 * @param  array  $options
 * @return \\Illuminate\\Routing\\PendingSingletonResourceRegistration
 */',
        'startLine' => 198,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'apiSingleton' => 
      array (
        'name' => 'apiSingleton',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 211,
            'endLine' => 211,
            'startColumn' => 34,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'controller' => 
          array (
            'name' => 'controller',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 211,
            'endLine' => 211,
            'startColumn' => 41,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 211,
                'endLine' => 211,
                'startTokenPos' => 751,
                'startFilePos' => 6600,
                'endTokenPos' => 752,
                'endFilePos' => 6601,
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
            'startLine' => 211,
            'endLine' => 211,
            'startColumn' => 54,
            'endColumn' => 72,
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
 * Route an API singleton resource to a controller.
 *
 * @param  string  $name
 * @param  string  $controller
 * @param  array  $options
 * @return \\Illuminate\\Routing\\PendingSingletonResourceRegistration
 */',
        'startLine' => 211,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'group' => 
      array (
        'name' => 'group',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 222,
            'endLine' => 222,
            'startColumn' => 27,
            'endColumn' => 35,
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
 * Create a route group with shared attributes.
 *
 * @param  \\Closure|array|string  $callback
 * @return $this
 */',
        'startLine' => 222,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'match' => 
      array (
        'name' => 'match',
        'parameters' => 
        array (
          'methods' => 
          array (
            'name' => 'methods',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 27,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 37,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'action' => 
          array (
            'name' => 'action',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 237,
                'endLine' => 237,
                'startTokenPos' => 836,
                'startFilePos' => 7271,
                'endTokenPos' => 836,
                'endFilePos' => 7274,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 43,
            'endColumn' => 56,
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
 * Register a new route with the given verbs.
 *
 * @param  array|string  $methods
 * @param  string  $uri
 * @param  \\Closure|array|string|null  $action
 * @return \\Illuminate\\Routing\\Route
 */',
        'startLine' => 237,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'metadata' => 
      array (
        'name' => 'metadata',
        'parameters' => 
        array (
          'metadata' => 
          array (
            'name' => 'metadata',
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
            'startLine' => 248,
            'endLine' => 248,
            'startColumn' => 30,
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
 * Add metadata to routes registered by the registrar.
 *
 * @param  array  $metadata
 * @return $this
 */',
        'startLine' => 248,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'registerRoute' => 
      array (
        'name' => 'registerRoute',
        'parameters' => 
        array (
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
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 47,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'action' => 
          array (
            'name' => 'action',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 261,
                'endLine' => 261,
                'startTokenPos' => 914,
                'startFilePos' => 7905,
                'endTokenPos' => 914,
                'endFilePos' => 7908,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 53,
            'endColumn' => 66,
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
 * Register a new route with the router.
 *
 * @param  string  $method
 * @param  string  $uri
 * @param  \\Closure|array|string|null  $action
 * @return \\Illuminate\\Routing\\Route
 */',
        'startLine' => 261,
        'endLine' => 268,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      'compileAction' => 
      array (
        'name' => 'compileAction',
        'parameters' => 
        array (
          'action' => 
          array (
            'name' => 'action',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 276,
            'endLine' => 276,
            'startColumn' => 38,
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
 * Compile the action into an array including the attributes.
 *
 * @param  \\Closure|array|string|null  $action
 * @return array
 */',
        'startLine' => 276,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 37,
            'endColumn' => 47,
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
 * Dynamically handle calls into the route registrar.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return \\Illuminate\\Routing\\Route|$this
 *
 * @throws \\BadMethodCallException
 */',
        'startLine' => 321,
        'endLine' => 346,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Routing',
        'declaringClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'implementingClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'currentClassName' => 'Illuminate\\Routing\\RouteRegistrar',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));