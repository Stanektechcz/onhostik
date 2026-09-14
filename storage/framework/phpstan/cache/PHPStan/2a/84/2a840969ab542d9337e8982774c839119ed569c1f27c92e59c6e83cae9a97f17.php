<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Compliance\Models\AbuseCase.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Compliance\Models\AbuseCase
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-5d7aaf76acd770f5b7b3064e6da8520744205444060965ecd997a5ce819d1691',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Compliance/Models/AbuseCase.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Compliance\\Models',
    'name' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
    'shortName' => 'AbuseCase',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** DSA notice-and-action case (Art. 16/17/20): receipt → triage → customer statement of reasons → action/dismissal → appeal. */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 26,
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
      'CATEGORIES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'name' => 'CATEGORIES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'illegal_content\', \'copyright\', \'phishing\', \'malware\', \'spam\', \'csam\', \'terrorism\', \'life_safety\', \'other\']',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 64,
            'startFilePos' => 406,
            'endTokenPos' => 90,
            'endFilePos' => 513,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 139,
      ),
      'ART18_CATEGORIES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'name' => 'ART18_CATEGORIES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'csam\', \'terrorism\', \'life_safety\']',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 101,
            'startFilePos' => 553,
            'endTokenPos' => 109,
            'endFilePos' => 588,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 73,
      ),
      'STATES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'name' => 'STATES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'RECEIVED\', \'TRIAGED\', \'CUSTOMER_NOTIFIED\', \'ACTIONED\', \'DISMISSED\', \'APPEALED\', \'CLOSED\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 120,
            'startFilePos' => 618,
            'endTokenPos' => 140,
            'endFilePos' => 708,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 118,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
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
          'code' => '\'abu\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 44,
            'startFilePos' => 329,
            'endTokenPos' => 44,
            'endFilePos' => 333,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
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
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'abuse_cases\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 53,
            'startFilePos' => 360,
            'endTokenPos' => 53,
            'endFilePos' => 372,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 37,
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
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Onhost\\Domain\\Compliance\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Models\\AbuseCase',
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