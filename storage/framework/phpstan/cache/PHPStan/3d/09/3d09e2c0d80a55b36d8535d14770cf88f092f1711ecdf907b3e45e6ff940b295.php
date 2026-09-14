<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\Commands\IncidentCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Incidents\Commands\IncidentCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-9d58c54872d8ec925ee148e23f31e627a04a18b1e204c92522f638916561f0b0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Incidents/Commands/IncidentCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
    'name' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
    'shortName' => 'IncidentCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Staff incident/status/SLA controls, dispatched by `op`:
 *  open{title,severity,components,impact?,affected_services?,security?,visibility?,note?} · update{incident_id,note,state?,public?} ·
 *  resolve{incident_id,note} · postmortem{incident_id,summary,root_cause,timeline?,actions?,publish?} ·
 *  maintenance.schedule{title,components,starts_at,ends_at,impact?,rollback,affected_services?,sla_treatment?,emergency?} ·
 *  maintenance.approve{maintenance_id} · maintenance.cancel{maintenance_id,reason} · maintenance.complete{maintenance_id,note?} ·
 *  probe.register{key,component_key,kind,target,location,expected?,interval_seconds?} ·
 *  credit.candidates{incident_id} · credit.approve{credit_id} · credit.reject{credit_id,reason} · credit.issue{credit_id}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 61,
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
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'open\', \'update\', \'resolve\', \'postmortem\', \'maintenance.schedule\', \'maintenance.approve\', \'maintenance.cancel\', \'maintenance.complete\', \'probe.register\', \'credit.candidates\', \'credit.approve\', \'credit.reject\', \'credit.issue\']',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 56,
            'startFilePos' => 1132,
            'endTokenPos' => 94,
            'endFilePos' => 1357,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 250,
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
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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
        'startLine' => 29,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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
        'docComment' => '/** Issuing money (SLA credit) is a financial action: fresh step-up required. */',
        'startLine' => 52,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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
        'startLine' => 57,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Incidents\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'implementingClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
        'currentClassName' => 'Onhost\\Domain\\Incidents\\Commands\\IncidentCommand',
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