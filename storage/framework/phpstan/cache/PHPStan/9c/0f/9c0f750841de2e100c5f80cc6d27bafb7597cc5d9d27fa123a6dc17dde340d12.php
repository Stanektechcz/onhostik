<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Dns\Models\DnsZone.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Dns\Models\DnsZone
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-6055584448814248d8deaa62d9abecec210cc71e5267dda06c209d4460e2def7',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Dns/Models/DnsZone.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Dns\\Models',
    'name' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
    'shortName' => 'DnsZone',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Canonical DNS zone; PowerDNS is the executor, this table is the truth (blueprint §48). */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 48,
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
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
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
          'code' => '\'zone\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 59,
            'startFilePos' => 398,
            'endTokenPos' => 59,
            'endFilePos' => 403,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
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
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'dns_zones\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 68,
            'startFilePos' => 430,
            'endTokenPos' => 68,
            'endFilePos' => 440,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 35,
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
        'startLine' => 20,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Dns\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'aliasName' => NULL,
      ),
      'records' => 
      array (
        'name' => 'records',
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
        'startLine' => 25,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'aliasName' => NULL,
      ),
      'versions' => 
      array (
        'name' => 'versions',
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
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'aliasName' => NULL,
      ),
      'pendingChanges' => 
      array (
        'name' => 'pendingChanges',
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
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'aliasName' => NULL,
      ),
      'nextSerial' => 
      array (
        'name' => 'nextSerial',
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
        'docComment' => '/** Next SOA serial in YYYYMMDDnn form, monotonic even after >99 commits a day. */',
        'startLine' => 41,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
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