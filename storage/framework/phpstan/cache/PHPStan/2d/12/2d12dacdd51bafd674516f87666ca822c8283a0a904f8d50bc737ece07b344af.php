<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Identity\Commands\ApiTokenCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Identity\Commands\ApiTokenCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-3899fdcaf0c9304409d68d03c5c12d8732c659e6590212b54ee3f5e256510510',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Identity/Commands/ApiTokenCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Identity\\Commands',
    'name' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
    'shortName' => 'ApiTokenCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** op: create{name, scopes[], expires_in_days?} · revoke{token_id}. Tokens are personal, scoped to an organization and to documented abilities. */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 45,
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
      'SCOPES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'name' => 'SCOPES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'services:read\', \'services:power\', \'invoices:read\', \'tickets:write\', \'dns:write\', \'domains:read\', \'wallet:read\']',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 56,
            'startFilePos' => 508,
            'endTokenPos' => 76,
            'endFilePos' => 620,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 140,
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
        'startLine' => 16,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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
        'startLine' => 21,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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
        'startLine' => 26,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Identity\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'implementingClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
        'currentClassName' => 'Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand',
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