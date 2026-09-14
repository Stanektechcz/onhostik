<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\Commands\ProvisioningCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\Commands\ProvisioningCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-eb4ab93e28898f36a53b1f0fae468738d8f0e6eeed90297bf29e44a77eeed868',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/Commands/ProvisioningCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
    'name' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
    'shortName' => 'ProvisioningCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Staff provisioning controls, dispatched by `op`:
 *  retry{operation_id,reason?} · cancel{operation_id,reason} · resolve_drift{drift_id,resolution:approved|ignored|repair,note} ·
 *  freeze{reason} · thaw{} · reconcile{service_id}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 62,
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
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'retry\', \'cancel\', \'resolve_drift\', \'freeze\', \'thaw\', \'reconcile\', \'instance.upsert\', \'instance.probe\', \'instance.state\', \'instance.discover\', \'node.upsert\', \'placement.upsert\', \'placement.delete\', \'registrar.costs.refresh\', \'registrar.costs.scrape\', \'registrar.costs.upsert\', \'registrar.policy.set\', \'service.create\']',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 56,
            'startFilePos' => 599,
            'endTokenPos' => 109,
            'endFilePos' => 917,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 343,
      ),
      'AUDIT_STRIP' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'name' => 'AUDIT_STRIP',
        'modifiers' => 2,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'password\', \'secret\', \'token\', \'credentials\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 120,
            'startFilePos' => 955,
            'endTokenPos' => 131,
            'endFilePos' => 1000,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 81,
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
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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
        'docComment' => '/** Registering credentials / changing base URLs touches production executors: fresh step-up. */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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
        'startLine' => 58,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand',
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