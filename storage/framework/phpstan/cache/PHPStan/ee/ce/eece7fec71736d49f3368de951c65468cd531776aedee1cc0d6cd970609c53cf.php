<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Resilience\CircuitBreaker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Resilience\CircuitBreaker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-1c9d468d980b7011b26313c5eab9001c37775cb99fe31f1053c8a9ffa227e8df',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Resilience/CircuitBreaker.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Resilience',
    'name' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
    'shortName' => 'CircuitBreaker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Cache-backed circuit breaker shared by all workers of one provider instance.
 * CLOSED -> (failures >= threshold) OPEN -> (cooldown elapsed) HALF_OPEN -> success CLOSED / failure OPEN.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 88,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'CLOSED' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'CLOSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'closed\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 42,
            'startFilePos' => 386,
            'endTokenPos' => 42,
            'endFilePos' => 393,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'OPEN' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'open\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 53,
            'startFilePos' => 421,
            'endTokenPos' => 53,
            'endFilePos' => 426,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'HALF_OPEN' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'HALF_OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'half_open\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 64,
            'startFilePos' => 459,
            'endTokenPos' => 64,
            'endFilePos' => 469,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
    ),
    'immediateProperties' => 
    array (
      'cache' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'cache',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Cache\\Repository',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 47,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'key' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'key',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 9,
        'endColumn' => 36,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'failureThreshold' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'failureThreshold',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 102,
            'startFilePos' => 642,
            'endTokenPos' => 102,
            'endFilePos' => 642,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cooldownSeconds' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'cooldownSeconds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '60',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 115,
            'startFilePos' => 693,
            'endTokenPos' => 115,
            'endFilePos' => 694,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'windowSeconds' => 
      array (
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'name' => 'windowSeconds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '120',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 128,
            'startFilePos' => 743,
            'endTokenPos' => 128,
            'endFilePos' => 745,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 49,
        'isPromoted' => true,
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
          'cache' => 
          array (
            'name' => 'cache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cache\\Repository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 9,
            'endColumn' => 36,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'failureThreshold' => 
          array (
            'name' => 'failureThreshold',
            'default' => 
            array (
              'code' => '5',
              'attributes' => 
              array (
                'startLine' => 24,
                'endLine' => 24,
                'startTokenPos' => 102,
                'startFilePos' => 642,
                'endTokenPos' => 102,
                'endFilePos' => 642,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cooldownSeconds' => 
          array (
            'name' => 'cooldownSeconds',
            'default' => 
            array (
              'code' => '60',
              'attributes' => 
              array (
                'startLine' => 25,
                'endLine' => 25,
                'startTokenPos' => 115,
                'startFilePos' => 693,
                'endTokenPos' => 115,
                'endFilePos' => 694,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'windowSeconds' => 
          array (
            'name' => 'windowSeconds',
            'default' => 
            array (
              'code' => '120',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 128,
                'startFilePos' => 743,
                'endTokenPos' => 128,
                'endFilePos' => 745,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 9,
            'endColumn' => 49,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'state' => 
      array (
        'name' => 'state',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'allowsRequest' => 
      array (
        'name' => 'allowsRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'recordSuccess' => 
      array (
        'name' => 'recordSuccess',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'recordFailure' => 
      array (
        'name' => 'recordFailure',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 53,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'trip' => 
      array (
        'name' => 'trip',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Force the circuit open (clock skew, repeated auth failure, manual freeze). */',
        'startLine' => 69,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'reset' => 
      array (
        'name' => 'reset',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 74,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'failures' => 
      array (
        'name' => 'failures',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 79,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'aliasName' => NULL,
      ),
      'k' => 
      array (
        'name' => 'k',
        'parameters' => 
        array (
          'suffix' => 
          array (
            'name' => 'suffix',
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
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 24,
            'endColumn' => 37,
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
        'docComment' => NULL,
        'startLine' => 84,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Platform\\Resilience',
        'declaringClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'implementingClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
        'currentClassName' => 'Onhost\\Platform\\Resilience\\CircuitBreaker',
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