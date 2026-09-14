<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Console\Commands\ProductionPrepare.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\ProductionPrepare
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-e15f7b1f1534db97ae0653af2ebd7376d8b9e4307fd2256dcec0e0a6bdb71b78',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\ProductionPrepare',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Console/Commands/ProductionPrepare.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\ProductionPrepare',
    'shortName' => 'ProductionPrepare',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The last steps before the first customer (go-live checklist): removes the development accounts the
 * `DevAccountSeeder` created, writes the legal entity from the production environment (`ONHOST_LEGAL_NAME`,
 * `ONHOST_ICO`, `ONHOST_BANK_IBAN`, …), caches configuration and routes, and ends with the doctor — every remaining
 * FAIL is printed so nothing is forgotten. Nothing here touches customer data.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 71,
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
      'DEV_ACCOUNTS' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'implementingClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'name' => 'DEV_ACCOUNTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'demo@onhost.cz\', \'agentura@onhost.cz\', \'admin@onhost.cz\', \'noc@onhost.cz\', \'finance@onhost.cz\', \'support@onhost.cz\']',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 72,
            'startFilePos' => 840,
            'endTokenPos' => 89,
            'endFilePos' => 957,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 151,
      ),
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'implementingClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'onhost:production:prepare {--purge-dev-accounts : Delete the DevAccountSeeder accounts and their role bindings} {--legal : Write the legal entity from ONHOST_LEGAL_* / ONHOST_BANK_* variables} {--cache : php artisan config:cache, route:cache, event:cache} {--yes : Do not ask}\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 98,
            'startFilePos' => 988,
            'endTokenPos' => 98,
            'endFilePos' => 1265,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 306,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'implementingClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Prepare the installation for production: purge development accounts, seed the legal entity from the environment, cache, and run the doctor\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 107,
            'startFilePos' => 1298,
            'endTokenPos' => 107,
            'endFilePos' => 1437,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 170,
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
          'audit' => 
          array (
            'name' => 'audit',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Platform\\Audit\\AuditRecorder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 28,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'implementingClassName' => 'App\\Console\\Commands\\ProductionPrepare',
        'currentClassName' => 'App\\Console\\Commands\\ProductionPrepare',
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