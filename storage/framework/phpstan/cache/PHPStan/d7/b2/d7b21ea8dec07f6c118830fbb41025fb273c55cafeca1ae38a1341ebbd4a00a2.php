<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Partners\Commands\PartnerPortalCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Partners\Commands\PartnerPortalCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-4b3b79d63edc7e0eeec7d27ef6f8b76d6214ad5b57dfac7abdc40c8de833f3ec',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Partners/Commands/PartnerPortalCommand.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Partners\\Commands',
    'name' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
    'shortName' => 'PartnerPortalCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Partner-portal actions on the partner\'s own organization, dispatched by `op`:
 *  apply{company?,clients?,site?,note?,model?} · payout.request{amount,iban,method?} · whitelabel{domain?,hide_brand?,own_mail?,own_prices?,own_support?}
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 31,
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
      'AUDIT_STRIP' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'implementingClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'name' => 'AUDIT_STRIP',
        'modifiers' => 2,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'password\', \'secret\', \'token\', \'iban\']',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 42,
            'startFilePos' => 471,
            'endTokenPos' => 53,
            'endFilePos' => 509,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 74,
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
        'startLine' => 17,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Partners\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'implementingClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'currentClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
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
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Partners\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'implementingClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'currentClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
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
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Partners\\Commands',
        'declaringClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'implementingClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
        'currentClassName' => 'Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand',
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