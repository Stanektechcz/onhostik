<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Dns\Commands\DnsCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Dns\Commands\DnsCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-19a2c3f766852f6a61cc5ba89aeca8184b1427a5dbc4955502ad95d1705fe038',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Dns/Commands/DnsCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Dns\\Commands',
    'name' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
    'shortName' => 'DnsCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * DNS zone edits, dispatched by `op` (blueprint §48, S39):
 *  create_zone{name,template?,vars?} · stage{zone_id,op:add|update|delete,record{},record_id?,confirm_protected?,reason?} ·
 *  discard{zone_id} · commit{zone_id,reason?} · rollback{zone_id,version} · dnssec{zone_id,enabled} · delete_zone{zone_id,reason}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 32,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Onhost\\Platform\\Commands\\OrganizationCommand',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'OPS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'name' => 'OPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'create_zone\', \'stage\', \'discard\', \'commit\', \'rollback\', \'dnssec\', \'delete_zone\']',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 42,
            'startFilePos' => 530,
            'endTokenPos' => 62,
            'endFilePos' => 611,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 106,
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
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
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
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
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
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Dns\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'implementingClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
        'currentClassName' => 'Onhost\\Domain\\Dns\\Commands\\DnsCommand',
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