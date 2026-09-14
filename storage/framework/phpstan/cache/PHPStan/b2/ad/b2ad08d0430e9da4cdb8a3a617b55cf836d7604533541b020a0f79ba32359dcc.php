<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Commands\RegistrarConnectionCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Commands\RegistrarConnectionCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-ca765f527cedcd5b3ebc7b512c2b74eed71e559acc051be49bbf53f56e408b56',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Commands/RegistrarConnectionCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Commands',
    'name' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
    'shortName' => 'RegistrarConnectionCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * A customer\'s connected registrar account, dispatched by `op`:
 *  connect{provider,login,password,label?,customer_number?} · probe{connection_id} · sync{connection_id} · settings{connection_id,…}
 *  · disconnect{connection_id} · pair{domain_id,service_id} · unpair{domain_id}
 *  staff: disable{connection_id,reason?} · enable{connection_id} · staff_sync{connection_id}
 * Storing or dropping credentials is HIGH risk (fresh step-up); the password never reaches the audit trail.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 54,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\OrganizationCommand',
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Domain\\Identity\\Authorization\\RiskAwareCommand',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'connect\', \'probe\', \'sync\', \'settings\', \'disconnect\', \'pair\', \'unpair\', \'disable\', \'enable\', \'staff_sync\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 56,
            'startFilePos' => 867,
            'endTokenPos' => 85,
            'endFilePos' => 973,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 131,
      ),
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
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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
        'startLine' => 27,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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
        'startLine' => 40,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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
        'docComment' => NULL,
        'startLine' => 45,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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
        'startLine' => 50,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand',
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