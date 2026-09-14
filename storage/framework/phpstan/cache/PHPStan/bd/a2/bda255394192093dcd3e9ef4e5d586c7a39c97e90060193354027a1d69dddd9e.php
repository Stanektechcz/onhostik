<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\platform\Commands\Command.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Platform\Commands\Command
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-8b97ab768ac2fa4930b8aa42f49a5dcf4f08af90a1dd3f4289c5a2e843f7f955',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Platform\\Commands\\Command',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/platform/Commands/Command.php',
      ),
    ),
    'namespace' => 'Onhost\\Platform\\Commands',
    'name' => 'Onhost\\Platform\\Commands\\Command',
    'shortName' => 'Command',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A state-changing intent. Every command declares the permission it requires and
 * an idempotency key (blueprint §60.2: UI -> API -> AuthZ -> Command -> Job ...).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 27,
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
        'docComment' => '/** Capability key such as `compute.vm.create`; null for system-internal commands. */',
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Commands',
        'declaringClassName' => 'Onhost\\Platform\\Commands\\Command',
        'implementingClassName' => 'Onhost\\Platform\\Commands\\Command',
        'currentClassName' => 'Onhost\\Platform\\Commands\\Command',
        'aliasName' => NULL,
      ),
      'scope' => 
      array (
        'name' => 'scope',
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
                  'name' => 'Onhost\\Platform\\Commands\\CommandScope',
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
        'attributes' => 
        array (
        ),
        'docComment' => '/** Scope for the permission check: organization id, project id or null for global. */',
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 43,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Commands',
        'declaringClassName' => 'Onhost\\Platform\\Commands\\Command',
        'implementingClassName' => 'Onhost\\Platform\\Commands\\Command',
        'currentClassName' => 'Onhost\\Platform\\Commands\\Command',
        'aliasName' => NULL,
      ),
      'idempotencyKey' => 
      array (
        'name' => 'idempotencyKey',
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
        'docComment' => '/** Stable idempotency key; the same key never produces two business effects. */',
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 45,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Commands',
        'declaringClassName' => 'Onhost\\Platform\\Commands\\Command',
        'implementingClassName' => 'Onhost\\Platform\\Commands\\Command',
        'currentClassName' => 'Onhost\\Platform\\Commands\\Command',
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
        'docComment' => '/** Short type name used in audit and operation records, e.g. `wallet.topup`. */',
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 35,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Commands',
        'declaringClassName' => 'Onhost\\Platform\\Commands\\Command',
        'implementingClassName' => 'Onhost\\Platform\\Commands\\Command',
        'currentClassName' => 'Onhost\\Platform\\Commands\\Command',
        'aliasName' => NULL,
      ),
      'toAudit' => 
      array (
        'name' => 'toAudit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return array<string,mixed> redacted payload for audit */',
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Platform\\Commands',
        'declaringClassName' => 'Onhost\\Platform\\Commands\\Command',
        'implementingClassName' => 'Onhost\\Platform\\Commands\\Command',
        'currentClassName' => 'Onhost\\Platform\\Commands\\Command',
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