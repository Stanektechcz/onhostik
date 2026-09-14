<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\Models\StatusComponent.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Incidents\Models\StatusComponent
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-354367736296699b00a75a7a726d0e207d400882432d5044b67212d729dcf0a0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Incidents/Models/StatusComponent.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Incidents\\Models',
    'name' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
    'shortName' => 'StatusComponent',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Public status component (blueprint §73.1). The key is the primary key; no ULID is generated. */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 31,
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
      'STATES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'name' => 'STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'operational\', \'degraded\', \'partial_outage\', \'major_outage\', \'maintenance\']',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 60,
            'startFilePos' => 365,
            'endTokenPos' => 74,
            'endFilePos' => 440,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 103,
      ),
      'SEVERITY_STATE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'name' => 'SEVERITY_STATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'p1\' => \'major_outage\', \'p2\' => \'partial_outage\', \'p3\' => \'degraded\', \'p4\' => \'degraded\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 87,
            'startFilePos' => 545,
            'endTokenPos' => 114,
            'endFilePos' => 634,
          ),
        ),
        'docComment' => '/** Severity → component state while an incident is open. */',
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 125,
      ),
    ),
    'immediateProperties' => 
    array (
      'table' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'status_components\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 40,
            'startFilePos' => 281,
            'endTokenPos' => 40,
            'endFilePos' => 299,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'primaryKey' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'key\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 49,
            'startFilePos' => 331,
            'endTokenPos' => 49,
            'endFilePos' => 335,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
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
        'startLine' => 21,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'aliasName' => NULL,
      ),
      'rank' => 
      array (
        'name' => 'rank',
        'parameters' => 
        array (
          'state' => 
          array (
            'name' => 'state',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 33,
            'endColumn' => 45,
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
        'docComment' => '/** Ordering for "worst state wins" when several incidents touch one component. */',
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Incidents\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
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