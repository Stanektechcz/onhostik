<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Identity\Models\PersonalAccessToken.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Identity\Models\PersonalAccessToken
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-33d54d3e1e0d93d8bd6b35023f573f22267584d2101bf67d02444b9fa5822b44',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Identity/Models/PersonalAccessToken.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Identity\\Models',
    'name' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
    'shortName' => 'PersonalAccessToken',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Sanctum token with ONhost extensions: organization scope, per-token rate limit,
 * revocation, last-used IP. Secret shown once; prefix `onh_live_` (config/sanctum).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 42,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laravel\\Sanctum\\PersonalAccessToken',
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
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'personal_access_tokens\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 44,
            'startFilePos' => 387,
            'endTokenPos' => 44,
            'endFilePos' => 410,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'guarded' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'name' => 'guarded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 53,
            'startFilePos' => 439,
            'endTokenPos' => 54,
            'endFilePos' => 440,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 28,
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
        'startLine' => 19,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Identity\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'aliasName' => NULL,
      ),
      'isRevoked' => 
      array (
        'name' => 'isRevoked',
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
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'aliasName' => NULL,
      ),
      'findToken' => 
      array (
        'name' => 'findToken',
        'parameters' => 
        array (
          'token' => 
          array (
            'name' => 'token',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 33,
            'endLine' => 33,
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
        'docComment' => '/** Sanctum resolves the token via this method; revoked tokens must not authenticate. */',
        'startLine' => 33,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Identity\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Models\\PersonalAccessToken',
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