<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\Commands\DomainCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\Commands\DomainCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-b4bed28cb48b614dce040301bcbefabb6c3fda61243e27d0306204cbb424849a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/Commands/DomainCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains\\Commands',
    'name' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
    'shortName' => 'DomainCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * One command class for the domain platform, dispatched by `op` (blueprint §46):
 *  renew{fqdn,years} · nameservers{fqdn,nameservers[],dns_provider?} · use_onhost_dns{fqdn,template?,vars?} ·
 *  auto_renew{fqdn,enabled} · transfer_lock{fqdn,locked} · auth_info{fqdn} · transfer_in{fqdn,auth_info,registrant…} ·
 *  publish_ds{fqdn} · contact{…} · register{fqdn,period,registrant…,consent} (staff/manual; customers order through checkout)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 58,
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
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'renew\', \'nameservers\', \'use_onhost_dns\', \'auto_renew\', \'transfer_lock\', \'auth_info\', \'transfer_in\', \'publish_ds\', \'contact\', \'register\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 56,
            'startFilePos' => 817,
            'endTokenPos' => 85,
            'endFilePos' => 954,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 162,
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
        'startLine' => 21,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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
        'startLine' => 26,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Domains\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
        'currentClassName' => 'Onhost\\Domain\\Domains\\Commands\\DomainCommand',
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