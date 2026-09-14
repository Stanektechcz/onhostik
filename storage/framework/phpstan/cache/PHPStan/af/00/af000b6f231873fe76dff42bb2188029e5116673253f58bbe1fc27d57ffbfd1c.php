<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Invoicing\Models\Invoice.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Invoicing\Models\Invoice
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-43f78dd689b99402b7e467d12330a0a5565b10f7c64bb41e5b7bcd4fe1aa5ac0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Invoicing/Models/Invoice.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
    'name' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
    'shortName' => 'Invoice',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Issued invoices are immutable: number and business content never change (§64.2). */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 79,
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
      'DRAFT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'DRAFT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'DRAFT\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 74,
            'startFilePos' => 432,
            'endTokenPos' => 74,
            'endFilePos' => 438,
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
      ),
      'ISSUED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'ISSUED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ISSUED\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 85,
            'startFilePos' => 468,
            'endTokenPos' => 85,
            'endFilePos' => 475,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'PAID' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'PAID',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PAID\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 96,
            'startFilePos' => 503,
            'endTokenPos' => 96,
            'endFilePos' => 508,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'OVERDUE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'OVERDUE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'OVERDUE\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 107,
            'startFilePos' => 539,
            'endTokenPos' => 107,
            'endFilePos' => 547,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'CANCELLED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'CANCELLED\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 118,
            'startFilePos' => 580,
            'endTokenPos' => 118,
            'endFilePos' => 590,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'CREDITED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'CREDITED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'CREDITED\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 129,
            'startFilePos' => 622,
            'endTokenPos' => 129,
            'endFilePos' => 631,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
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
          'code' => '\'inv\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 54,
            'startFilePos' => 363,
            'endTokenPos' => 54,
            'endFilePos' => 367,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
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
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'invoices\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 63,
            'startFilePos' => 394,
            'endTokenPos' => 63,
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
        'endColumn' => 34,
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
        'startLine' => 30,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'lines' => 
      array (
        'name' => 'lines',
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
        'startLine' => 39,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'total' => 
      array (
        'name' => 'total',
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
        'startLine' => 44,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'tax' => 
      array (
        'name' => 'tax',
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
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'outstanding' => 
      array (
        'name' => 'outstanding',
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
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'isIssued' => 
      array (
        'name' => 'isIssued',
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
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'isCreditNote' => 
      array (
        'name' => 'isCreditNote',
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
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'aliasName' => NULL,
      ),
      'uiState' => 
      array (
        'name' => 'uiState',
        'parameters' => 
        array (
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
        'docComment' => '/** Prototype slug for the API-backed store. */',
        'startLine' => 70,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Invoicing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'implementingClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
        'currentClassName' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
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