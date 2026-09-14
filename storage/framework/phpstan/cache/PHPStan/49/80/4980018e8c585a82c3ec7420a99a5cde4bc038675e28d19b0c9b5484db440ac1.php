<?php declare(strict_types = 1);

// osfsl-C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Http/Client/Factory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Http\Client\Factory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-76793488119e96189da11457d53b3ca395c52d11ce6333a5dd4ab5edee6cd8d9-8.3.33-6.70.0.6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Http\\Client\\Factory',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/vendor/composer/../laravel/framework/src/Illuminate/Http/Client/Factory.php',
      ),
    ),
    'namespace' => 'Illuminate\\Http\\Client',
    'name' => 'Illuminate\\Http\\Client\\Factory',
    'shortName' => 'Factory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin \\Illuminate\\Http\\Client\\PendingRequest
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 660,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'dispatcher' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'dispatcher',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher implementation.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'globalMiddleware' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'globalMiddleware',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 133,
            'startFilePos' => 990,
            'endTokenPos' => 134,
            'endFilePos' => 991,
          ),
        ),
        'docComment' => '/**
 * The middleware to apply to every request.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'globalOptions' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'globalOptions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 145,
            'startFilePos' => 1122,
            'endTokenPos' => 146,
            'endFilePos' => 1123,
          ),
        ),
        'docComment' => '/**
 * The options to apply to every request.
 *
 * @var \\Closure|array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stubCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'stubCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The stub callables that will handle requests.
 *
 * @var \\Illuminate\\Support\\Collection
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recording' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'recording',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 164,
            'startFilePos' => 1413,
            'endTokenPos' => 164,
            'endFilePos' => 1417,
          ),
        ),
        'docComment' => '/**
 * Indicates if the factory is recording requests and responses.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recorded' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'recorded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 71,
            'endLine' => 71,
            'startTokenPos' => 175,
            'startFilePos' => 1608,
            'endTokenPos' => 176,
            'endFilePos' => 1609,
          ),
        ),
        'docComment' => '/**
 * The recorded response array.
 *
 * @var list<array{0: \\Illuminate\\Http\\Client\\Request, 1: \\Illuminate\\Http\\Client\\Response|null}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'responseSequences' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'responseSequences',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 78,
            'endLine' => 78,
            'startTokenPos' => 187,
            'startFilePos' => 1769,
            'endTokenPos' => 188,
            'endFilePos' => 1770,
          ),
        ),
        'docComment' => '/**
 * All created response sequences.
 *
 * @var list<\\Illuminate\\Http\\Client\\ResponseSequence>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preventStrayRequests' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'preventStrayRequests',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 85,
            'endLine' => 85,
            'startTokenPos' => 199,
            'startFilePos' => 1933,
            'endTokenPos' => 199,
            'endFilePos' => 1937,
          ),
        ),
        'docComment' => '/**
 * Indicates that an exception should be thrown if any request is not faked.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedStrayRequestUrls' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'allowedStrayRequestUrls',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 92,
            'endLine' => 92,
            'startTokenPos' => 210,
            'startFilePos' => 2118,
            'endTokenPos' => 211,
            'endFilePos' => 2119,
          ),
        ),
        'docComment' => '/**
 * A list of URL patterns that are allowed to bypass the stray request guard.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 44,
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
          'dispatcher' => 
          array (
            'name' => 'dispatcher',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 99,
                'endLine' => 99,
                'startTokenPos' => 229,
                'startFilePos' => 2315,
                'endTokenPos' => 229,
                'endFilePos' => 2318,
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
                      'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 33,
            'endColumn' => 62,
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
 * Create a new factory instance.
 *
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher|null  $dispatcher
 */',
        'startLine' => 99,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalMiddleware' => 
      array (
        'name' => 'globalMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 112,
            'endLine' => 112,
            'startColumn' => 38,
            'endColumn' => 48,
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
 * Add middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 112,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalRequestMiddleware' => 
      array (
        'name' => 'globalRequestMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 125,
            'endLine' => 125,
            'startColumn' => 45,
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
 * Add request middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 125,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalResponseMiddleware' => 
      array (
        'name' => 'globalResponseMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 46,
            'endColumn' => 56,
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
 * Add response middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 138,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalOptions' => 
      array (
        'name' => 'globalOptions',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 35,
            'endColumn' => 42,
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
 * Set the options to apply to every request.
 *
 * @param  \\Closure|array  $options
 * @return $this
 */',
        'startLine' => 151,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'withoutGlobalConfiguration' => 
      array (
        'name' => 'withoutGlobalConfiguration',
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
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 166,
            'endLine' => 166,
            'startColumn' => 48,
            'endColumn' => 64,
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
 * Execute a callback while requests are created without global middleware or global options.
 *
 * @template TReturn
 *
 * @param  (\\Closure(): TReturn)  $callback
 * @return TReturn
 */',
        'startLine' => 166,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'response' => 
      array (
        'name' => 'response',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 187,
                'endLine' => 187,
                'startTokenPos' => 503,
                'startFilePos' => 4497,
                'endTokenPos' => 503,
                'endFilePos' => 4500,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 187,
            'endLine' => 187,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 187,
                'endLine' => 187,
                'startTokenPos' => 510,
                'startFilePos' => 4513,
                'endTokenPos' => 510,
                'endFilePos' => 4515,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 187,
            'endLine' => 187,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 187,
                'endLine' => 187,
                'startTokenPos' => 517,
                'startFilePos' => 4529,
                'endTokenPos' => 518,
                'endFilePos' => 4530,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 187,
            'endLine' => 187,
            'startColumn' => 66,
            'endColumn' => 78,
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
 * Create a new response instance for use during stubbing.
 *
 * @param  \\Psr\\Http\\Message\\StreamInterface|array|string|resource|null  $body
 * @param  int  $status
 * @param  array  $headers
 * @return \\GuzzleHttp\\Promise\\PromiseInterface
 */',
        'startLine' => 187,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'psr7Response' => 
      array (
        'name' => 'psr7Response',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 204,
                'endLine' => 204,
                'startTokenPos' => 562,
                'startFilePos' => 5022,
                'endTokenPos' => 562,
                'endFilePos' => 5025,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 204,
            'endLine' => 204,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 204,
                'endLine' => 204,
                'startTokenPos' => 569,
                'startFilePos' => 5038,
                'endTokenPos' => 569,
                'endFilePos' => 5040,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 204,
            'endLine' => 204,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 204,
                'endLine' => 204,
                'startTokenPos' => 576,
                'startFilePos' => 5054,
                'endTokenPos' => 577,
                'endFilePos' => 5055,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 204,
            'endLine' => 204,
            'startColumn' => 70,
            'endColumn' => 82,
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
 * Create a new PSR-7 response instance for use during stubbing.
 *
 * @param  \\Psr\\Http\\Message\\StreamInterface|array|string|resource|null  $body
 * @param  int  $status
 * @param  array  $headers
 * @return \\GuzzleHttp\\Psr7\\Response
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 204,
        'endLine' => 221,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'normalizeResponseHeaders' => 
      array (
        'name' => 'normalizeResponseHeaders',
        'parameters' => 
        array (
          'headers' => 
          array (
            'name' => 'headers',
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
            'startLine' => 231,
            'endLine' => 231,
            'startColumn' => 56,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Normalize the given fake response headers.
 *
 * @param  array  $headers
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 231,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'normalizeScalarString' => 
      array (
        'name' => 'normalizeScalarString',
        'parameters' => 
        array (
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
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 53,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Normalize a scalar to a string without triggering PHP 8.5 non-finite float warnings.
 *
 * @param  scalar  $value
 * @return string
 */',
        'startLine' => 272,
        'endLine' => 283,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'failedRequest' => 
      array (
        'name' => 'failedRequest',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 293,
                'endLine' => 293,
                'startTokenPos' => 1108,
                'startFilePos' => 8293,
                'endTokenPos' => 1108,
                'endFilePos' => 8296,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 293,
                'endLine' => 293,
                'startTokenPos' => 1115,
                'startFilePos' => 8309,
                'endTokenPos' => 1115,
                'endFilePos' => 8311,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 56,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 293,
                'endLine' => 293,
                'startTokenPos' => 1122,
                'startFilePos' => 8325,
                'endTokenPos' => 1123,
                'endFilePos' => 8326,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 71,
            'endColumn' => 83,
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
 * Create a new RequestException instance for use during stubbing.
 *
 * @param  \\Psr\\Http\\Message\\StreamInterface|array|string|resource|null  $body
 * @param  int  $status
 * @param  array  $headers
 * @return \\Illuminate\\Http\\Client\\RequestException
 */',
        'startLine' => 293,
        'endLine' => 296,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'failedConnection' => 
      array (
        'name' => 'failedConnection',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 304,
                'endLine' => 304,
                'startTokenPos' => 1170,
                'startFilePos' => 8717,
                'endTokenPos' => 1170,
                'endFilePos' => 8720,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 304,
            'endLine' => 304,
            'startColumn' => 45,
            'endColumn' => 59,
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
 * Create a new connection exception for use during stubbing.
 *
 * @param  string|null  $message
 * @return \\Closure(\\Illuminate\\Http\\Client\\Request): \\GuzzleHttp\\Promise\\PromiseInterface
 */',
        'startLine' => 304,
        'endLine' => 312,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'sequence' => 
      array (
        'name' => 'sequence',
        'parameters' => 
        array (
          'responses' => 
          array (
            'name' => 'responses',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 320,
                'endLine' => 320,
                'startTokenPos' => 1269,
                'startFilePos' => 9397,
                'endTokenPos' => 1270,
                'endFilePos' => 9398,
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
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 30,
            'endColumn' => 50,
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
 * Get an invokable object that returns a sequence of responses in order for use during stubbing.
 *
 * @param  array  $responses
 * @return \\Illuminate\\Http\\Client\\ResponseSequence
 */',
        'startLine' => 320,
        'endLine' => 323,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'fake' => 
      array (
        'name' => 'fake',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 331,
                'endLine' => 331,
                'startTokenPos' => 1307,
                'startFilePos' => 9733,
                'endTokenPos' => 1307,
                'endFilePos' => 9736,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 331,
            'endLine' => 331,
            'startColumn' => 26,
            'endColumn' => 41,
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
 * Register a stub callable that will intercept requests and be able to return stub responses.
 *
 * @param  callable|array<string, mixed>|null  $callback
 * @return $this
 */',
        'startLine' => 331,
        'endLine' => 371,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'fakeSequence' => 
      array (
        'name' => 'fakeSequence',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => 
            array (
              'code' => '\'*\'',
              'attributes' => 
              array (
                'startLine' => 379,
                'endLine' => 379,
                'startTokenPos' => 1572,
                'startFilePos' => 11059,
                'endTokenPos' => 1572,
                'endFilePos' => 11061,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 379,
            'endLine' => 379,
            'startColumn' => 34,
            'endColumn' => 43,
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
 * Register a response sequence for the given URL pattern.
 *
 * @param  string  $url
 * @return \\Illuminate\\Http\\Client\\ResponseSequence
 */',
        'startLine' => 379,
        'endLine' => 384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'stubUrl' => 
      array (
        'name' => 'stubUrl',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startColumn' => 29,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 395,
            'endLine' => 395,
            'startColumn' => 35,
            'endColumn' => 43,
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
 * Stub the given URL using the given callback.
 *
 * @param  string  $url
 * @param  \\Illuminate\\Http\\Client\\Response|\\GuzzleHttp\\Promise\\PromiseInterface|callable|int|string|array|\\Illuminate\\Http\\Client\\ResponseSequence  $callback
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 395,
        'endLine' => 420,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'preventStrayRequests' => 
      array (
        'name' => 'preventStrayRequests',
        'parameters' => 
        array (
          'prevent' => 
          array (
            'name' => 'prevent',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 428,
                'endLine' => 428,
                'startTokenPos' => 1836,
                'startFilePos' => 12597,
                'endTokenPos' => 1836,
                'endFilePos' => 12600,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 428,
            'endLine' => 428,
            'startColumn' => 42,
            'endColumn' => 56,
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
 * Indicate that an exception should be thrown if any request is not faked.
 *
 * @param  bool  $prevent
 * @return $this
 */',
        'startLine' => 428,
        'endLine' => 433,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'preventingStrayRequests' => 
      array (
        'name' => 'preventingStrayRequests',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if stray requests are being prevented.
 *
 * @return bool
 */',
        'startLine' => 440,
        'endLine' => 443,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'allowStrayRequests' => 
      array (
        'name' => 'allowStrayRequests',
        'parameters' => 
        array (
          'only' => 
          array (
            'name' => 'only',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 451,
                'endLine' => 451,
                'startTokenPos' => 1893,
                'startFilePos' => 13120,
                'endTokenPos' => 1893,
                'endFilePos' => 13123,
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
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 40,
            'endColumn' => 58,
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
 * Allow stray, unfaked requests entirely, or optionally allow only specific URLs.
 *
 * @param  array<int, string>|null  $only
 * @return $this
 */',
        'startLine' => 451,
        'endLine' => 462,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'record' => 
      array (
        'name' => 'record',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin recording request / response pairs.
 *
 * @return $this
 */',
        'startLine' => 469,
        'endLine' => 474,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'recordRequestResponsePair' => 
      array (
        'name' => 'recordRequestResponsePair',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 483,
            'endLine' => 483,
            'startColumn' => 47,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 483,
            'endLine' => 483,
            'startColumn' => 57,
            'endColumn' => 65,
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
 * Record a request response pair.
 *
 * @param  \\Illuminate\\Http\\Client\\Request  $request
 * @param  \\Illuminate\\Http\\Client\\Response|null  $response
 * @return void
 */',
        'startLine' => 483,
        'endLine' => 488,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSent' => 
      array (
        'name' => 'assertSent',
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
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 32,
            'endColumn' => 40,
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
 * Assert that a request / response pair was recorded matching a given truth test.
 *
 * @param  callable|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)  $callback
 * @return void
 */',
        'startLine' => 496,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSentInOrder' => 
      array (
        'name' => 'assertSentInOrder',
        'parameters' => 
        array (
          'callbacks' => 
          array (
            'name' => 'callbacks',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 510,
            'endLine' => 510,
            'startColumn' => 39,
            'endColumn' => 48,
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
 * Assert that the given request was sent in the given order.
 *
 * @param  list<string|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)|callable>  $callbacks
 * @return void
 */',
        'startLine' => 510,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertNotSent' => 
      array (
        'name' => 'assertNotSent',
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
            'startLine' => 532,
            'endLine' => 532,
            'startColumn' => 35,
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
 * Assert that a request / response pair was not recorded matching a given truth test.
 *
 * @param  callable|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)  $callback
 * @return void
 */',
        'startLine' => 532,
        'endLine' => 538,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertNothingSent' => 
      array (
        'name' => 'assertNothingSent',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that no request / response pair was recorded.
 *
 * @return void
 */',
        'startLine' => 545,
        'endLine' => 551,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSentCount' => 
      array (
        'name' => 'assertSentCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 559,
            'endLine' => 559,
            'startColumn' => 37,
            'endColumn' => 42,
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
 * Assert how many requests have been recorded.
 *
 * @param  int  $count
 * @return void
 */',
        'startLine' => 559,
        'endLine' => 562,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSequencesAreEmpty' => 
      array (
        'name' => 'assertSequencesAreEmpty',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that every created response sequence is empty.
 *
 * @return void
 */',
        'startLine' => 569,
        'endLine' => 577,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'recorded' => 
      array (
        'name' => 'recorded',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 585,
                'endLine' => 585,
                'startTokenPos' => 2352,
                'startFilePos' => 16937,
                'endTokenPos' => 2352,
                'endFilePos' => 16940,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 585,
            'endLine' => 585,
            'startColumn' => 30,
            'endColumn' => 45,
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
 * Get a collection of the request / response pairs matching the given truth test.
 *
 * @param  (\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)|callable  $callback
 * @return \\Illuminate\\Support\\Collection<int, array{0: \\Illuminate\\Http\\Client\\Request, 1: \\Illuminate\\Http\\Client\\Response|null}>
 */',
        'startLine' => 585,
        'endLine' => 598,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'createPendingRequest' => 
      array (
        'name' => 'createPendingRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new pending request instance for this factory.
 *
 * @return \\Illuminate\\Http\\Client\\PendingRequest
 */',
        'startLine' => 605,
        'endLine' => 613,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'newPendingRequest' => 
      array (
        'name' => 'newPendingRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Instantiate a new pending request instance for this factory.
 *
 * @return \\Illuminate\\Http\\Client\\PendingRequest
 */',
        'startLine' => 620,
        'endLine' => 623,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'getDispatcher' => 
      array (
        'name' => 'getDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current event dispatcher implementation.
 *
 * @return \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'startLine' => 630,
        'endLine' => 633,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'getGlobalMiddleware' => 
      array (
        'name' => 'getGlobalMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the array of global middleware.
 *
 * @return array
 */',
        'startLine' => 640,
        'endLine' => 643,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
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
            'startLine' => 652,
            'endLine' => 652,
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
            'startLine' => 652,
            'endLine' => 652,
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
 * Execute a method against a new pending request instance.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 652,
        'endLine' => 659,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
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