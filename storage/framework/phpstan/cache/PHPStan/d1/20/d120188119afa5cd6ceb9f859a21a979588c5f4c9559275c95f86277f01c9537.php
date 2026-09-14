<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Compliance\Models\ComplianceTimer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Compliance\Models\ComplianceTimer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-bdd4130c75d9236e69f539279b6699558529eb198c4c24db9c76d07b505fd899',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Compliance/Models/ComplianceTimer.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Compliance\\Models',
    'name' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
    'shortName' => 'ComplianceTimer',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Regulatory deadline clock: running → met | missed | waived. Warned once at 75 % of the window. */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 35,
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
      'LABELS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'name' => 'LABELS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'NIS2_EARLY_WARNING\' => \'NIS2 včasné varování (24 h)\', \'NIS2_NOTIFICATION\' => \'NIS2 oznámení incidentu (72 h)\', \'NIS2_FINAL_REPORT\' => \'NIS2 závěrečná zpráva (1 měsíc)\', \'GDPR_72H\' => \'GDPR oznámení ÚOOÚ (72 h)\', \'DSA_ART18_PROMPT\' => \'DSA čl. 18 oznámení orgánům\', \'DATA_ACT_SWITCHING\' => \'Data Act přechod k jinému poskytovateli (30 dní)\']',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 19,
            'startTokenPos' => 64,
            'startFilePos' => 381,
            'endTokenPos' => 108,
            'endFilePos' => 770,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'idPrefix' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
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
          'code' => '\'tmr\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 44,
            'startFilePos' => 302,
            'endTokenPos' => 44,
            'endFilePos' => 306,
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
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'compliance_timers\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 53,
            'startFilePos' => 333,
            'endTokenPos' => 53,
            'endFilePos' => 351,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 43,
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
        'namespace' => 'Onhost\\Domain\\Compliance\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'aliasName' => NULL,
      ),
      'label' => 
      array (
        'name' => 'label',
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
        'startLine' => 26,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'aliasName' => NULL,
      ),
      'remainingSeconds' => 
      array (
        'name' => 'remainingSeconds',
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
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Models',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Models\\ComplianceTimer',
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