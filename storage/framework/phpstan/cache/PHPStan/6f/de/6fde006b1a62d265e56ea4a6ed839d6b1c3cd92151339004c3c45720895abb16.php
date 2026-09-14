<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Console\Commands\GenerateOssInventory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\GenerateOssInventory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-1f20707d2fb3fa8cbf62ba83f2ab5deb56eb63e7db290b4a5af22260fe413e14',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\GenerateOssInventory',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Console/Commands/GenerateOssInventory.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\GenerateOssInventory',
    'shortName' => 'GenerateOssInventory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Writes `compliance/oss-inventory.yml` (blueprint §26: OSS licence inventory, NIS2/CRA supply-chain evidence)
 * from composer.lock and the vendored front-end runtime declared in docs/ui/template-inventory.md.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 83,
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
      'FRONTEND' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'implementingClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'name' => 'FRONTEND',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'name\' => \'react\', \'version\' => \'18.x (UMD via unpkg — to be vendored, backlog UI-01)\', \'license\' => \'MIT\', \'scope\' => \'surfaces\'], [\'name\' => \'react-dom\', \'version\' => \'18.x\', \'license\' => \'MIT\', \'scope\' => \'surfaces\'], [\'name\' => \'@babel/standalone\', \'version\' => \'7.x\', \'license\' => \'MIT\', \'scope\' => \'surfaces (in-browser compile, backlog UI-02)\'], [\'name\' => \'Archivo (Google Fonts)\', \'version\' => \'variable\', \'license\' => \'OFL-1.1\', \'scope\' => \'design system\'], [\'name\' => \'Modernist design system (_ds bundle)\', \'version\' => \'31154b91\', \'license\' => \'proprietary — ONhost\', \'scope\' => \'surfaces\']]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 28,
            'startTokenPos' => 72,
            'startFilePos' => 771,
            'endTokenPos' => 224,
            'endFilePos' => 1427,
          ),
        ),
        'docComment' => '/** Front-end runtime pulled by the prototype surfaces (docs/ui/template-inventory.md §7). */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'implementingClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'onhost:oss-inventory {--out=compliance/oss-inventory.yml}\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 50,
            'startFilePos' => 470,
            'endTokenPos' => 50,
            'endFilePos' => 528,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 87,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'implementingClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Generate the open-source component inventory with licences from composer.lock\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 59,
            'startFilePos' => 561,
            'endTokenPos' => 59,
            'endFilePos' => 639,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 109,
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
        'startLine' => 30,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'implementingClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
        'currentClassName' => 'App\\Console\\Commands\\GenerateOssInventory',
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