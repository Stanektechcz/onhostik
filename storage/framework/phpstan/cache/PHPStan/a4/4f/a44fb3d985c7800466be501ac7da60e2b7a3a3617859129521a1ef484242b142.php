<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Compliance\Commands\ComplianceCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Compliance\Commands\ComplianceCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-07e122eda60921dc9a7ee0b4b0924746715a95149739cf3ede505e0382d3655c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Compliance/Commands/ComplianceCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
    'name' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
    'shortName' => 'ComplianceCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Staff compliance controls, dispatched by `op`:
 *  cyber.open{…} · cyber.transition{case_id,state,summary?} · cyber.evidence{case_id,name,sha256,path?} ·
 *  timer.submit{timer_id,authority_reference,evidence?} · timer.waive{timer_id,reason} ·
 *  abuse.triage{case_id,decision,reason} · abuse.notify{case_id,statement} · abuse.action{case_id,action,reason} · abuse.close{case_id,note?} ·
 *  legal_hold{organization_id,hold,reason} · data_request.process{}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 60,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\GlobalCommand',
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Domain\\Identity\\Authorization\\RiskAwareCommand',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'op' => 
      array (
        'name' => 'op',
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
        'startLine' => 20,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'aliasName' => NULL,
      ),
      'permission' => 
      array (
        'name' => 'permission',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'aliasName' => NULL,
      ),
      'name' => 
      array (
        'name' => 'name',
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
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'aliasName' => NULL,
      ),
      'riskLevel' => 
      array (
        'name' => 'riskLevel',
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
        'startLine' => 41,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'aliasName' => NULL,
      ),
      'requiresStepUp' => 
      array (
        'name' => 'requiresStepUp',
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
        'docComment' => '/** Suspending a customer for abuse, waiving a regulatory deadline and applying/lifting legal hold need a fresh step-up (legal hold is audited with the reason; the CRITICAL catalog level is enforced by the compliance_legal role scope). */',
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'aliasName' => NULL,
      ),
      'requiresApproval' => 
      array (
        'name' => 'requiresApproval',
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
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Compliance\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'implementingClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
        'currentClassName' => 'Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand',
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