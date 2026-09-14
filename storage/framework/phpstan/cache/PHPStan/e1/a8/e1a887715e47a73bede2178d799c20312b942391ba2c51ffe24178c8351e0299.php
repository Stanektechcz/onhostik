<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\Workflows\GameMigrationWorkflow.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-d7fb44563b55f55b666adb84bfa51a5a34b9aa463ee59b9fdb2c80c0256bb392',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/Workflows/GameMigrationWorkflow.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
    'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
    'shortName' => 'GameMigrationWorkflow',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Moves a game server to another node — of the same panel or of another one (audit §5g-2, §5h-2) — as one saga:
 * pick the target → stop the source → back it up → allocation on the target → the same server created there
 * (template, image, variables, limits, owner; on another panel the catalogue template mapped there and the
 * customer\'s panel account ensured) → the archive streamed daemon to daemon by a queued job → the platform switched
 * to the new server (binding, game server row, panel, node, access address) and the new server started → the old
 * one deleted. Nothing is deleted before the switch; a failure before it deletes the half-built target and starts
 * the source again, so the customer is back where they were. Staff start it per server or per node (evacuation),
 * optionally inside a window the customer may move (§5h-3); the customer gets the new address as a notification.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 50,
    'endLine' => 462,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Onhost\\Domain\\Provisioning\\Workflow\\Workflow',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'TARGET_BINDING' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'name' => 'TARGET_BINDING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'server_migration\'',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 201,
            'startFilePos' => 2604,
            'endTokenPos' => 201,
            'endFilePos' => 2621,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 53,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'kind' => 
      array (
        'name' => 'kind',
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
        'startLine' => 54,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'queue' => 
      array (
        'name' => 'queue',
        'parameters' => 
        array (
          'operation' => 
          array (
            'name' => 'operation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 27,
            'endColumn' => 46,
            'parameterIndex' => 0,
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
        'docComment' => NULL,
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'steps' => 
      array (
        'name' => 'steps',
        'parameters' => 
        array (
          'operation' => 
          array (
            'name' => 'operation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 27,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => NULL,
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'sourceRef' => 
      array (
        'name' => 'sourceRef',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Providers\\Contracts\\ResourceRef',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The server the customer runs on now, from the facts recorded at the start (the binding is rewritten at the switch). */',
        'startLine' => 70,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'targetAdapter' => 
      array (
        'name' => 'targetAdapter',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 42,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The panel the new server lives on: the source panel unless the target node belongs to another one. */',
        'startLine' => 76,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'targetBinding' => 
      array (
        'name' => 'targetBinding',
        'parameters' => 
        array (
          'serviceId' => 
          array (
            'name' => 'serviceId',
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
            'startLine' => 83,
            'endLine' => 83,
            'startColumn' => 42,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
                  'name' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderBinding',
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
        'docComment' => NULL,
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'pollOn' => 
      array (
        'name' => 'pollOn',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 35,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'handle' => 
          array (
            'name' => 'handle',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Providers\\Contracts\\AsyncHandle',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 61,
            'endColumn' => 79,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'onSuccess' => 
          array (
            'name' => 'onSuccess',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 82,
            'endColumn' => 100,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Polls an asynchronous handle on a given adapter (the target panel\'s, where the default step polling would ask the source). */',
        'startLine' => 89,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'compensate' => 
      array (
        'name' => 'compensate',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\Workflow\\StepContext',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 32,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 108,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'targetStep' => 
      array (
        'name' => 'targetStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 135,
        'endLine' => 211,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'stopSourceStep' => 
      array (
        'name' => 'stopSourceStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Stops the source so nothing changes between the backup and the switch; waits (up to four minutes) for it to be offline, then kills it. */',
        'startLine' => 214,
        'endLine' => 248,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'backupStep' => 
      array (
        'name' => 'backupStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 250,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'allocationStep' => 
      array (
        'name' => 'allocationStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 273,
        'endLine' => 302,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'createStep' => 
      array (
        'name' => 'createStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 304,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'transferStep' => 
      array (
        'name' => 'transferStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The data half runs in a queued job (hours are fine there); the step only starts it and polls its record. */',
        'startLine' => 348,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'switchStep' => 
      array (
        'name' => 'switchStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The platform now points at the new server; the customer keeps the service, gets the new address, the game starts. */',
        'startLine' => 393,
        'endLine' => 443,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'aliasName' => NULL,
      ),
      'cleanupStep' => 
      array (
        'name' => 'cleanupStep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 445,
        'endLine' => 461,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\GameMigrationWorkflow',
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