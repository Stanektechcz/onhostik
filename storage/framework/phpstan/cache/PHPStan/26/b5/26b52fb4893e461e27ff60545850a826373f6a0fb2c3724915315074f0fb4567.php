<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Models\Domain.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Models\Domain
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-9e9f9055b36c2da6e3ce108052296924f327f2f17045e0774297fe6cd6cd6a58',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Models/Domain.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Models',
    'name' => 'Onhost\\Domain\\Domains\\Models\\Domain',
    'shortName' => 'Domain',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Registered domain (blueprint §46). Public id `dom_…`; `fqdn_ascii` is the canonical key. */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 96,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
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
          'code' => '\'dom\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 69,
            'startFilePos' => 505,
            'endTokenPos' => 69,
            'endFilePos' => 509,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'domains\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 78,
            'startFilePos' => 536,
            'endTokenPos' => 78,
            'endFilePos' => 544,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'attributes' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'name' => 'attributes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'registrar_provider\' => \'wedos\', \'dns_provider\' => \'powerdns\', \'state\' => \\Onhost\\Domain\\Domains\\DomainStateMachine::PENDING_REGISTRATION, \'auto_renew\' => true, \'renewal_period\' => 1, \'auto_renew_priority\' => \'domain\', \'dnssec\' => false, \'transfer_lock\' => true, \'privacy_mode\' => \'registry_default\', \'critical\' => false]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 25,
            'startTokenPos' => 87,
            'startFilePos' => 576,
            'endTokenPos' => 161,
            'endFilePos' => 897,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 25,
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
        'startLine' => 27,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'registrant' => 
      array (
        'name' => 'registrant',
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
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'admin' => 
      array (
        'name' => 'admin',
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
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'nsset' => 
      array (
        'name' => 'nsset',
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'consents' => 
      array (
        'name' => 'consents',
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
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'renewalJobs' => 
      array (
        'name' => 'renewalJobs',
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
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'registrarOperations' => 
      array (
        'name' => 'registrarOperations',
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
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
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
        'startLine' => 66,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'daysToExpiry' => 
      array (
        'name' => 'daysToExpiry',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
                  'name' => 'int',
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
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'renewalBucket' => 
      array (
        'name' => 'renewalBucket',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
                  'name' => 'string',
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
        'attributes' => 
        array (
        ),
        'docComment' => '/** Derived renewal bucket for the UI (blueprint §46.4: RENEW_DUE_60/30/7). */',
        'startLine' => 77,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'aliasName' => NULL,
      ),
      'usesOnhostDns' => 
      array (
        'name' => 'usesOnhostDns',
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
        'startLine' => 92,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\Domain',
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