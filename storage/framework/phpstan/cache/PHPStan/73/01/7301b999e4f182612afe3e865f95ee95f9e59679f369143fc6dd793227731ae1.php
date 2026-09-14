<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Models\RegistrarConnection.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Models\RegistrarConnection
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-9235c086470f098f43eed69f0b234b2dea91fb440134ef4d51d33c282db0d8ce',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Models/RegistrarConnection.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Models',
    'name' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
    'shortName' => 'RegistrarConnection',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** A customer\'s own registrar account connected to the platform (blueprint: bring your own WEDOS API). */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 47,
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
      'STATES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'name' => 'STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'pending\', \'active\', \'error\', \'disabled\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 79,
            'startFilePos' => 536,
            'endTokenPos' => 90,
            'endFilePos' => 577,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 69,
      ),
      'DEFAULT_SETTINGS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'name' => 'DEFAULT_SETTINGS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'auto_sync\' => true, \'sync_dns\' => true, \'notices\' => true, \'credit_threshold_minor\' => 20000, \'pair_service_id\' => null]',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 101,
            'startFilePos' => 617,
            'endTokenPos' => 135,
            'endFilePos' => 738,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 159,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
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
          'code' => '\'rcon\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 59,
            'startFilePos' => 452,
            'endTokenPos' => 59,
            'endFilePos' => 457,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 47,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'registrar_connections\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 68,
            'startFilePos' => 484,
            'endTokenPos' => 68,
            'endFilePos' => 506,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 47,
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
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'aliasName' => NULL,
      ),
      'organization' => 
      array (
        'name' => 'organization',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'aliasName' => NULL,
      ),
      'secretRef' => 
      array (
        'name' => 'secretRef',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'aliasName' => NULL,
      ),
      'setting' => 
      array (
        'name' => 'setting',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 29,
            'endColumn' => 39,
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
                'startLine' => 38,
                'endLine' => 38,
                'startTokenPos' => 266,
                'startFilePos' => 1234,
                'endTokenPos' => 266,
                'endFilePos' => 1237,
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 42,
            'endColumn' => 62,
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
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'aliasName' => NULL,
      ),
      'isActive' => 
      array (
        'name' => 'isActive',
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
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
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