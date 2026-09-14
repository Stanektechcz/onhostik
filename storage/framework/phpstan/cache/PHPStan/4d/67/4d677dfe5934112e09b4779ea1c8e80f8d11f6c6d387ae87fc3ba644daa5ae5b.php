<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\providers\Contracts\ConsoleCapable.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Providers\Contracts\ConsoleCapable
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-beae29a30b2aab20fd7f412bca4318991286545a7ad7bb4cbe0f9b72e07f02a5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Providers\\Contracts\\ConsoleCapable',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/providers/Contracts/ConsoleCapable.php',
      ),
    ),
    'namespace' => 'Onhost\\Providers\\Contracts',
    'name' => 'Onhost\\Providers\\Contracts\\ConsoleCapable',
    'shortName' => 'ConsoleCapable',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 16,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'consoleAccess' => 
      array (
        'name' => 'consoleAccess',
        'parameters' => 
        array (
          'ref' => 
          array (
            'name' => 'ref',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Providers\\Contracts\\ResourceRef',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 15,
            'endLine' => 15,
            'startColumn' => 35,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Short-lived console access (noVNC ticket, Wings websocket token). Never the
 * provider account credential. TTL is tens of seconds to minutes.
 *
 * @return array{kind:string,url:string,token:string,expires_at:string,meta?:array<string,mixed>}
 */',
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 59,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Providers\\Contracts',
        'declaringClassName' => 'Onhost\\Providers\\Contracts\\ConsoleCapable',
        'implementingClassName' => 'Onhost\\Providers\\Contracts\\ConsoleCapable',
        'currentClassName' => 'Onhost\\Providers\\Contracts\\ConsoleCapable',
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