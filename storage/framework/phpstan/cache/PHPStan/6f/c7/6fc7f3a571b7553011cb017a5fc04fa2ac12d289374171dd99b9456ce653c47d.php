<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\Models\Subscription.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Billing\Models\Subscription
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-be08ca668877db1e83e242b892ee299676a6235b784af1d1c86dc4b4a2d98197',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Billing/Models/Subscription.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Billing\\Models',
    'name' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
    'shortName' => 'Subscription',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Recurring charge attached to a service or a domain (blueprint §21). Domain subscriptions renew with `domain` priority. */',
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 44,
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
      'ACTIVE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'ACTIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'active\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 69,
            'startFilePos' => 427,
            'endTokenPos' => 69,
            'endFilePos' => 434,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'PAST_DUE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'PAST_DUE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'past_due\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 80,
            'startFilePos' => 466,
            'endTokenPos' => 80,
            'endFilePos' => 475,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'PAUSED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'PAUSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'paused\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 91,
            'startFilePos' => 505,
            'endTokenPos' => 91,
            'endFilePos' => 512,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'CANCELLED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'CANCELLED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'cancelled\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 102,
            'startFilePos' => 545,
            'endTokenPos' => 102,
            'endFilePos' => 555,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'EXPIRED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'EXPIRED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'expired\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 113,
            'startFilePos' => 586,
            'endTokenPos' => 113,
            'endFilePos' => 594,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
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
          'code' => '\'sub\'',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 49,
            'startFilePos' => 352,
            'endTokenPos' => 49,
            'endFilePos' => 356,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
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
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'subscriptions\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 58,
            'startFilePos' => 383,
            'endTokenPos' => 58,
            'endFilePos' => 397,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 39,
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
        'startLine' => 27,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Billing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'currentClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'aliasName' => NULL,
      ),
      'amount' => 
      array (
        'name' => 'amount',
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
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'currentClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'aliasName' => NULL,
      ),
      'isDomain' => 
      array (
        'name' => 'isDomain',
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
        'startLine' => 40,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Billing\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'implementingClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
        'currentClassName' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
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