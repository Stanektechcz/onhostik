<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\WalletLedger\Models\Wallet.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\WalletLedger\Models\Wallet
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-ef02881ebe6dce6cc3ed7a0a1cce11e5c5d9006fe3e03f9cdcfb7d1b96cb0639',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/WalletLedger/Models/Wallet.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
    'name' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
    'shortName' => 'Wallet',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Cached balances; the ledger is canonical (§62.3). `available = posted - reserved`
 * (+ approved credit line for postpaid organizations).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 50,
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
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
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
          'code' => '\'wal\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 49,
            'startFilePos' => 374,
            'endTokenPos' => 49,
            'endFilePos' => 378,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
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
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'wallets\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 58,
            'startFilePos' => 405,
            'endTokenPos' => 58,
            'endFilePos' => 413,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 33,
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
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'aliasName' => NULL,
      ),
      'posted' => 
      array (
        'name' => 'posted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Money\\Money',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'aliasName' => NULL,
      ),
      'reserved' => 
      array (
        'name' => 'reserved',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Money\\Money',
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
        'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'aliasName' => NULL,
      ),
      'available' => 
      array (
        'name' => 'available',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\Money\\Money',
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
        'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'aliasName' => NULL,
      ),
      'isFrozen' => 
      array (
        'name' => 'isFrozen',
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\WalletLedger\\Models',
        'declaringClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'implementingClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
        'currentClassName' => 'Onhost\\Domain\\WalletLedger\\Models\\Wallet',
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