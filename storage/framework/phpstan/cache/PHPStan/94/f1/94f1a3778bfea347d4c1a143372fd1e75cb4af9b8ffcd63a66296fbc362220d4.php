<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\Models\OnCallAlert.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Incidents\Models\OnCallAlert
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-7959f69d06af820cfc83c3417b913ea2df5a989068d2eef6c70093fa94e7e7be',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Incidents/Models/OnCallAlert.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Incidents\\Models',
    'name' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
    'shortName' => 'OnCallAlert',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * One page to the on-call (audit §5q-1): opened by an operational event, acknowledged by a person (console or the
 * pager\'s own webhook), escalated while nobody acknowledges, resolved by a person or the recovery event.
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
      'OPEN' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'OPEN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'open\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 42,
            'startFilePos' => 407,
            'endTokenPos' => 42,
            'endFilePos' => 412,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'ACKED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'ACKED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'acked\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 53,
            'startFilePos' => 441,
            'endTokenPos' => 53,
            'endFilePos' => 447,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'ESCALATED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'ESCALATED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'escalated\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 64,
            'startFilePos' => 480,
            'endTokenPos' => 64,
            'endFilePos' => 490,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'RESOLVED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'RESOLVED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'resolved\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 75,
            'startFilePos' => 522,
            'endTokenPos' => 75,
            'endFilePos' => 531,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'ACTIVE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'ACTIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::OPEN, self::ESCALATED]',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 86,
            'startFilePos' => 561,
            'endTokenPos' => 95,
            'endFilePos' => 589,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
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
          'code' => '\'onc\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 108,
            'startFilePos' => 633,
            'endTokenPos' => 108,
            'endFilePos' => 637,
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
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'oncall_alerts\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 117,
            'startFilePos' => 664,
            'endTokenPos' => 117,
            'endFilePos' => 678,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
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