<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\Models\Operation.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\Models\Operation
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-d041582d66820e7967fe5e62b49b74ae73ba11987cdd1535d59ae3baad6fc8b1',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/Models/Operation.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
    'name' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
    'shortName' => 'Operation',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Durable, idempotent, resumable unit of provisioning work (blueprint §5.2):
 * operation id, actor, idempotency key, correlation, desired state, attempts.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 78,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'PENDING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PENDING\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 74,
            'startFilePos' => 531,
            'endTokenPos' => 74,
            'endFilePos' => 539,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'RUNNING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'RUNNING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'RUNNING\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 85,
            'startFilePos' => 570,
            'endTokenPos' => 85,
            'endFilePos' => 578,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'WAITING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'WAITING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'WAITING\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 96,
            'startFilePos' => 609,
            'endTokenPos' => 96,
            'endFilePos' => 617,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'SUCCEEDED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'SUCCEEDED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'SUCCEEDED\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 107,
            'startFilePos' => 650,
            'endTokenPos' => 107,
            'endFilePos' => 660,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'FAILED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'FAILED\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 118,
            'startFilePos' => 690,
            'endTokenPos' => 118,
            'endFilePos' => 697,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'CANCELLED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'CANCELLED\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 129,
            'startFilePos' => 730,
            'endTokenPos' => 129,
            'endFilePos' => 740,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'COMPENSATED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'COMPENSATED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'COMPENSATED\'',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 140,
            'startFilePos' => 775,
            'endTokenPos' => 140,
            'endFilePos' => 787,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 45,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'idPrefix',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '\'op\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 54,
            'startFilePos' => 459,
            'endTokenPos' => 54,
            'endFilePos' => 462,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'operations\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 63,
            'startFilePos' => 489,
            'endTokenPos' => 63,
            'endFilePos' => 500,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
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
      'casts' => 
      array (
        'name' => 'casts',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 35,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'aliasName' => NULL,
      ),
      'machine' => 
      array (
        'name' => 'machine',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\StateMachine\\StateMachine',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 44,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'aliasName' => NULL,
      ),
      'attempts' => 
      array (
        'name' => 'attempts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 57,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'aliasName' => NULL,
      ),
      'isTerminal' => 
      array (
        'name' => 'isTerminal',
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
        'startLine' => 62,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'aliasName' => NULL,
      ),
      'ctx' => 
      array (
        'name' => 'ctx',
        'parameters' => 
        array (
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
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 25,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 67,
                'endLine' => 67,
                'startTokenPos' => 696,
                'startFilePos' => 2633,
                'endTokenPos' => 696,
                'endFilePos' => 2636,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 38,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 67,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'aliasName' => NULL,
      ),
      'withContext' => 
      array (
        'name' => 'withContext',
        'parameters' => 
        array (
          'patch' => 
          array (
            'name' => 'patch',
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
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 33,
            'endColumn' => 44,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 72,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
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