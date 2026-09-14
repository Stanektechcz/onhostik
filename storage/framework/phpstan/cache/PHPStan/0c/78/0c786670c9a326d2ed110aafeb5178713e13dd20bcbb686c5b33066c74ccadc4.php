<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Loyalty\Models\Referral.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Loyalty\Models\Referral
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-58fa4a18f17e57d572eef66a62a58a39111202fbf874c3c06b6e8671e9f1d5d9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Loyalty/Models/Referral.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Loyalty\\Models',
    'name' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
    'shortName' => 'Referral',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A customer invited by another customer (audit §5j-2); rewarded once the invited organization pays its first invoice —
 * unless the fraud score holds it for finance (§5l-4) or refuses it outright; a chargeback after the reward claws it back.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 33,
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
      'PENDING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pending\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 42,
            'startFilePos' => 432,
            'endTokenPos' => 42,
            'endFilePos' => 440,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'HELD' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'HELD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'held\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 53,
            'startFilePos' => 468,
            'endTokenPos' => 53,
            'endFilePos' => 473,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'REWARDED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'REWARDED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'rewarded\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 64,
            'startFilePos' => 505,
            'endTokenPos' => 64,
            'endFilePos' => 514,
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
      'REFUSED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'REFUSED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'refused\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 75,
            'startFilePos' => 545,
            'endTokenPos' => 75,
            'endFilePos' => 553,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'CLAWBACK' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'CLAWBACK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'clawback\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 86,
            'startFilePos' => 585,
            'endTokenPos' => 86,
            'endFilePos' => 594,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
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
          'code' => '\'ref\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 99,
            'startFilePos' => 638,
            'endTokenPos' => 99,
            'endFilePos' => 642,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
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
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'referrals\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 108,
            'startFilePos' => 669,
            'endTokenPos' => 108,
            'endFilePos' => 679,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 35,
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Loyalty\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'implementingClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
        'currentClassName' => 'Onhost\\Domain\\Loyalty\\Models\\Referral',
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