<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\DomainStateMachine.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Domains\DomainStateMachine
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-7956b61e039ef58d985b5a89299972e0b251f4a512dcf264409cbbf2ab758ede',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Domains/DomainStateMachine.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Domains',
    'name' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
    'shortName' => 'DomainStateMachine',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/** Domain lifecycle (blueprint §46.4). Renewal-due buckets are derived from expires_at, not stored. */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 65,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'PENDING_REGISTRATION' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'PENDING_REGISTRATION',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PENDING_REGISTRATION\'',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 12,
            'startTokenPos' => 38,
            'startFilePos' => 293,
            'endTokenPos' => 38,
            'endFilePos' => 314,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 12,
        'startColumn' => 5,
        'endColumn' => 63,
      ),
      'PENDING_REGISTRY' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'PENDING_REGISTRY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PENDING_REGISTRY\'',
          'attributes' => 
          array (
            'startLine' => 14,
            'endLine' => 14,
            'startTokenPos' => 49,
            'startFilePos' => 354,
            'endTokenPos' => 49,
            'endFilePos' => 371,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
      'ACTIVE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'ACTIVE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ACTIVE\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 60,
            'startFilePos' => 401,
            'endTokenPos' => 60,
            'endFilePos' => 408,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'EXPIRED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'EXPIRED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'EXPIRED\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 71,
            'startFilePos' => 439,
            'endTokenPos' => 71,
            'endFilePos' => 447,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'GRACE' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'GRACE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'GRACE\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 82,
            'startFilePos' => 476,
            'endTokenPos' => 82,
            'endFilePos' => 482,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'REDEMPTION' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'REDEMPTION',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'REDEMPTION\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 93,
            'startFilePos' => 516,
            'endTokenPos' => 93,
            'endFilePos' => 527,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'TRANSFER_IN_PENDING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'TRANSFER_IN_PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'TRANSFER_IN_PENDING\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 104,
            'startFilePos' => 570,
            'endTokenPos' => 104,
            'endFilePos' => 590,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 61,
      ),
      'TRANSFER_OUT_PENDING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'TRANSFER_OUT_PENDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'TRANSFER_OUT_PENDING\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 115,
            'startFilePos' => 634,
            'endTokenPos' => 115,
            'endFilePos' => 655,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 63,
      ),
      'TRANSFERRED_OUT' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'TRANSFERRED_OUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'TRANSFERRED_OUT\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 126,
            'startFilePos' => 694,
            'endTokenPos' => 126,
            'endFilePos' => 710,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 53,
      ),
      'FAILED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'FAILED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'FAILED\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 137,
            'startFilePos' => 740,
            'endTokenPos' => 137,
            'endFilePos' => 747,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'DELETED' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'name' => 'DELETED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'DELETED\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 148,
            'startFilePos' => 778,
            'endTokenPos' => 148,
            'endFilePos' => 786,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'machine' => 
      array (
        'name' => 'machine',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Platform\\StateMachine\\StateMachine',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 34,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Domains',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'currentClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'aliasName' => NULL,
      ),
      'fromRegistryStatus' => 
      array (
        'name' => 'fromRegistryStatus',
        'parameters' => 
        array (
          'status' => 
          array (
            'name' => 'status',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 47,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expiresAt' => 
          array (
            'name' => 'expiresAt',
            'default' => NULL,
            'type' => 
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
                      'name' => 'DateTimeInterface',
                      'isIdentifier' => false,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 63,
            'endColumn' => 92,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
        'docComment' => '/** Registry status strings (WAPI domain-info) -> ONhost state. */',
        'startLine' => 52,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Domains',
        'declaringClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'implementingClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
        'currentClassName' => 'Onhost\\Domain\\Domains\\DomainStateMachine',
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