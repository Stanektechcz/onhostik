<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Models\RegistrarContact.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Models\RegistrarContact
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-87cc1d0dea29174fe0e197a2166ce4eba5b717b288a1d8fdab4cb39266690485',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Models/RegistrarContact.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Models',
    'name' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
    'shortName' => 'RegistrarContact',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Registrant/admin/tech contact synced to the registrar (per-TLD schema, blueprint §46.1). */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 58,
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
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
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
          'code' => '\'rct\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 44,
            'startFilePos' => 293,
            'endTokenPos' => 44,
            'endFilePos' => 297,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
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
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'registrar_contacts\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 53,
            'startFilePos' => 324,
            'endTokenPos' => 53,
            'endFilePos' => 343,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
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
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'aliasName' => NULL,
      ),
      'siblingFor' => 
      array (
        'name' => 'siblingFor',
        'parameters' => 
        array (
          'provider' => 
          array (
            'name' => 'provider',
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
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 32,
            'endColumn' => 47,
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
        'docComment' => '/**
 * Contact handles are registrar-specific: the copy of this contact synced at `$provider`
 * (created on first use, linked through `source_contact_id`).
 */',
        'startLine' => 25,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'aliasName' => NULL,
      ),
      'isSynced' => 
      array (
        'name' => 'isSynced',
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
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'aliasName' => NULL,
      ),
      'toProviderContact' => 
      array (
        'name' => 'toProviderContact',
        'parameters' => 
        array (
          'tld' => 
          array (
            'name' => 'tld',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 39,
            'endColumn' => 49,
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
        'docComment' => '/** Provider-facing payload (mapped in the adapter to WAPI fields). */',
        'startLine' => 48,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Models\\RegistrarContact',
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