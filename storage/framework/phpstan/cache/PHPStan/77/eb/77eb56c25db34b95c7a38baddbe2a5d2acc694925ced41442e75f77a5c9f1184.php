<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\Models\Incident.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Incidents\Models\Incident
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-29f617a0148ae4b83a037cd9353961eb8dfa5bd0c3f11b2fc1e21155de0119db',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Incidents/Models/Incident.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Incidents\\Models',
    'name' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
    'shortName' => 'Incident',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Service incident (blueprint §73.2). The public status page shows only
 * `public` updates; security incidents never expose exploit detail.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 68,
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
      'SEVERITIES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'name' => 'SEVERITIES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'p1\', \'p2\', \'p3\', \'p4\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 69,
            'startFilePos' => 468,
            'endTokenPos' => 80,
            'endFilePos' => 491,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
      'UI_STATES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'name' => 'UI_STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'DETECTED\' => \'vysetrovani\', \'INVESTIGATING\' => \'vysetrovani\', \'IDENTIFIED\' => \'identifikovano\', \'MITIGATING\' => \'identifikovano\', \'MONITORING\' => \'monitoring\', \'RESOLVED\' => \'vyreseno\', \'POSTMORTEM\' => \'vyreseno\']',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 26,
            'startTokenPos' => 93,
            'startFilePos' => 601,
            'endTokenPos' => 144,
            'endFilePos' => 838,
          ),
        ),
        'docComment' => '/** Prototype INC_FLOW keys (Onhost-app.dc.html) per lifecycle state. */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
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
          'code' => '\'inc\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 49,
            'startFilePos' => 393,
            'endTokenPos' => 49,
            'endFilePos' => 397,
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
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'incidents\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 58,
            'startFilePos' => 424,
            'endTokenPos' => 58,
            'endFilePos' => 434,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
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
        'startLine' => 28,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'aliasName' => NULL,
      ),
      'updates' => 
      array (
        'name' => 'updates',
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
        'startLine' => 37,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'aliasName' => NULL,
      ),
      'isOpen' => 
      array (
        'name' => 'isOpen',
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
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
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
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'aliasName' => NULL,
      ),
      'durationSeconds' => 
      array (
        'name' => 'durationSeconds',
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
        'docComment' => '/** Seconds from start to resolution (or now while open). */',
        'startLine' => 53,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'aliasName' => NULL,
      ),
      'durationLabel' => 
      array (
        'name' => 'durationLabel',
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
        'docComment' => NULL,
        'startLine' => 60,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
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