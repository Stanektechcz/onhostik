<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Console\Commands\EdgeConfig.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\EdgeConfig
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-361b190bf82739a985eeb0ae48dcfa209d02fa2e6519b82980f0e51ce569a329',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\EdgeConfig',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Console/Commands/EdgeConfig.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\EdgeConfig',
    'shortName' => 'EdgeConfig',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Prints the edge configuration for the customers\' own status hosts (audit §5l-3) with the portal host and the upstream
 * filled in: Caddy (on-demand TLS with the platform\'s "ask") or nginx (default server + certbot). `--write` stores it
 * next to the templates so the deploy can ship it.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 58,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'implementingClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'onhost:edge:config {--format=caddy : caddy|nginx} {--upstream=127.0.0.1:8000 : the portal upstream (host:port)} {--write : Write infra/edge/<format>.generated} {--out= : Write the rendered configuration to this path (the Ansible role uses it)}\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 45,
            'startFilePos' => 523,
            'endTokenPos' => 45,
            'endFilePos' => 767,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 273,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'implementingClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Edge configuration for the customers\\\' status hosts (on-demand TLS asks the platform first)\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 54,
            'startFilePos' => 800,
            'endTokenPos' => 54,
            'endFilePos' => 892,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 123,
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
      'handle' => 
      array (
        'name' => 'handle',
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
        'startLine' => 21,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'implementingClassName' => 'App\\Console\\Commands\\EdgeConfig',
        'currentClassName' => 'App\\Console\\Commands\\EdgeConfig',
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