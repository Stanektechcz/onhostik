<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Identity\Authorization\PermissionCatalog.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Identity\Authorization\PermissionCatalog
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-af18bdedf15446a8c59704ede72d442038929dd3b567548f3360aca397089a2c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Identity/Authorization/PermissionCatalog.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
    'name' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
    'shortName' => 'PermissionCatalog',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Canonical capability list (blueprint §61.3). Never `if role === admin` in code —
 * everything is a capability with a risk class. `high` => WebAuthn/TOTP step-up,
 * `critical` => step-up + two-person approval (§61.6).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 181,
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
      'NORMAL' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'name' => 'NORMAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'normal\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 33,
            'startFilePos' => 374,
            'endTokenPos' => 33,
            'endFilePos' => 381,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'HIGH' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'name' => 'HIGH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'high\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 44,
            'startFilePos' => 409,
            'endTokenPos' => 44,
            'endFilePos' => 414,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'CRITICAL' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'name' => 'CRITICAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'critical\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 55,
            'startFilePos' => 446,
            'endTokenPos' => 55,
            'endFilePos' => 455,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'all' => 
      array (
        'name' => 'all',
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
        'docComment' => '/**
 * @return array<string, array{description:string, risk:string, audience:string}>
 */',
        'startLine' => 23,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'aliasName' => NULL,
      ),
      'keys' => 
      array (
        'name' => 'keys',
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
        'docComment' => '/** @return list<string> */',
        'startLine' => 157,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'aliasName' => NULL,
      ),
      'risk' => 
      array (
        'name' => 'risk',
        'parameters' => 
        array (
          'permission' => 
          array (
            'name' => 'permission',
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 33,
            'endColumn' => 50,
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
        'startLine' => 162,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'aliasName' => NULL,
      ),
      'requiresStepUp' => 
      array (
        'name' => 'requiresStepUp',
        'parameters' => 
        array (
          'permission' => 
          array (
            'name' => 'permission',
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
            'startLine' => 167,
            'endLine' => 167,
            'startColumn' => 43,
            'endColumn' => 60,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 167,
        'endLine' => 170,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'aliasName' => NULL,
      ),
      'requiresFourEyes' => 
      array (
        'name' => 'requiresFourEyes',
        'parameters' => 
        array (
          'permission' => 
          array (
            'name' => 'permission',
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
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 45,
            'endColumn' => 62,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 172,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'aliasName' => NULL,
      ),
      'exists' => 
      array (
        'name' => 'exists',
        'parameters' => 
        array (
          'permission' => 
          array (
            'name' => 'permission',
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
            'startLine' => 177,
            'endLine' => 177,
            'startColumn' => 35,
            'endColumn' => 52,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 177,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Authorization',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Authorization\\PermissionCatalog',
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