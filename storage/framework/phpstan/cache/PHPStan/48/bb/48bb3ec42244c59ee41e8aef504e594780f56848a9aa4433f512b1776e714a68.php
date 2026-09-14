<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Services\Commands\WebToolsCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Services\Commands\WebToolsCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-edc946fc76a40bc6b9a06e72a6f008f5f48b78b6ae8a5ac5f57b985d08366613',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Services/Commands/WebToolsCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Services\\Commands',
    'name' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
    'shortName' => 'WebToolsCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Configuration writes of the web toolkit that are not node operations: uptime monitors, the git deploy source
 * and its webhook secret, the backup schedule. payload: service_id, op, params{}.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 43,
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
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'monitoring.set\', \'monitoring.delete\', \'deploy.configure\', \'deploy.rotate_secret\', \'deploy.disconnect\', \'backup_schedule.set\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 56,
            'startFilePos' => 559,
            'endTokenPos' => 73,
            'endFilePos' => 685,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 151,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
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
        'startLine' => 19,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'currentClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
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
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'currentClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'currentClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
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
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'currentClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
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
        'startLine' => 39,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
        'currentClassName' => 'Onhost\\Domain\\Services\\Commands\\WebToolsCommand',
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