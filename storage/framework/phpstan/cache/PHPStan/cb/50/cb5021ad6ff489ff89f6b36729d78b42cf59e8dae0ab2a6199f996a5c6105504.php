<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Provisioning\Workflows\ServiceActionWorkflow.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-e657249a92335eb549599a966edf0b8fda90a082be51e1902acaf2f36a2ecab6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Provisioning/Workflows/ServiceActionWorkflow.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
    'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
    'shortName' => 'ServiceActionWorkflow',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Day-2 operations on an existing service (blueprint §5.3): power, suspend/resume,
 * resize, terminate, backup, restore, snapshot. `desired.action` selects the path;
 * every path is idempotent (read-before-act) and ends by reading the actual state back.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 46,
    'endLine' => 758,
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
      'CORE_ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'name' => 'CORE_ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'power\', \'suspend\', \'resume\', \'resize\', \'terminate\', \'backup\', \'restore\', \'snapshot\', \'rollback_snapshot\']',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 48,
            'startTokenPos' => 202,
            'startFilePos' => 1971,
            'endTokenPos' => 228,
            'endFilePos' => 2077,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 140,
      ),
      'FEATURE_ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'name' => 'FEATURE_ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    \'php.set\',
    \'database.create\',
    \'database.delete\',
    \'ftp.create\',
    \'ftp.delete\',
    \'ftp.password\',
    \'cron.create\',
    \'cron.delete\',
    \'subdomain.add\',
    \'subdomain.remove\',
    \'redirect.set\',
    \'ssl.issue\',
    \'https.force\',
    \'snapshot.delete\',
    \'firewall.apply\',
    \'command.send\',
    \'schedule.create\',
    \'mailbox.create\',
    \'mailbox.update\',
    \'mailbox.delete\',
    \'alias.create\',
    \'alias.delete\',
    \'sending.set\',
    \'errpages.set\',
    \'directives.set\',
    \'folder.protect\',
    \'folder.unprotect\',
    \'dbuser.create\',
    \'dbuser.password\',
    \'dbuser.delete\',
    \'shell.create\',
    \'shell.key\',
    \'shell.delete\',
    \'stats.set\',
    \'ssl.upload\',
    \'file.mkdir\',
    \'file.delete\',
    \'file.save\',
    \'app.install\',
    // tools on top of the panel (WebToolsProvider) and mail tools (MailToolsProvider): still one provider call each
    \'command.run\',
    \'php.settings\',
    \'security.set\',
    \'http3.set\',
    \'cron.update\',
    \'cron.run\',
    \'database.export\',
    \'database.import\',
    \'database.access\',
    \'backup.delete\',
    \'file.rename\',
    \'file.copy\',
    \'file.chmod\',
    \'file.archive\',
    \'file.extract\',
    \'node.create\',
    \'node.action\',
    \'proxy.create\',
    \'proxy.delete\',
    \'proxies.set\',
    \'index.set\',
    \'forward.create\',
    \'forward.delete\',
    \'catchall.set\',
    \'autoresponder.set\',
    \'spam.policy\',
    \'spam.list.add\',
    \'spam.list.delete\',
    \'filter.create\',
    \'filter.delete\',
    \'list.create\',
    \'list.delete\',
    \'fetchmail.create\',
    \'fetchmail.delete\',
    \'mailbox.backup\',
    \'mailbox.restore\',
    // game tools (GameToolsProvider): startup, settings, schedules, databases, collaborators, files, ports, backups, panel account
    ...\\Onhost\\Domain\\Services\\ServiceFeatures::GAME_ACTIONS,
]',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 64,
            'startTokenPos' => 241,
            'startFilePos' => 2237,
            'endTokenPos' => 478,
            'endFilePos' => 3836,
          ),
        ),
        'docComment' => '/** Feature actions: one provider call each, validated by ServiceService::featureParams, no service state change. */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'PLATFORM_ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'name' => 'PLATFORM_ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'staging.create\', \'staging.refresh\', \'staging.push\', \'staging.delete\', \'deploy.run\', \'deploy.rollback\', \'wp.install\', \'wp.update\', \'wp.cache\', \'wp.plugin\', \'import.run\', \'cdn.enable\', \'cdn.disable\', \'cdn.purge\', \'ssl.wildcard\']',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 67,
            'startTokenPos' => 491,
            'startFilePos' => 4008,
            'endTokenPos' => 535,
            'endFilePos' => 4235,
          ),
        ),
        'docComment' => '/** Actions that run as sagas of their own (ServiceService::actionWorkflowFor); listed here so the API validates them alike. */',
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 265,
      ),
      'ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'name' => 'ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[...self::CORE_ACTIONS, ...self::FEATURE_ACTIONS, ...self::PLATFORM_ACTIONS]',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 546,
            'startFilePos' => 4266,
            'endTokenPos' => 563,
            'endFilePos' => 4341,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 104,
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
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
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
            'startLine' => 76,
            'endLine' => 76,
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
        'startLine' => 76,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
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
            'startLine' => 83,
            'endLine' => 83,
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
        'startLine' => 83,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
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
            'startLine' => 101,
            'endLine' => 101,
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
        'startLine' => 101,
        'endLine' => 119,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'powerStep' => 
      array (
        'name' => 'powerStep',
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
        'startLine' => 121,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'featureStep' => 
      array (
        'name' => 'featureStep',
        'parameters' => 
        array (
          'action' => 
          array (
            'name' => 'action',
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
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 34,
            'endColumn' => 47,
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
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * One feature action = one provider call on the service\'s primary resource (site, VM, game server, mail domain).
 * Asynchronous executors (ISPConfig job queue, Proxmox tasks) are awaited through the handle; resource listings
 * cached for the customer panel are dropped so the next read shows the change.
 */',
        'startLine' => 151,
        'endLine' => 413,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'verifyPowerStep' => 
      array (
        'name' => 'verifyPowerStep',
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
        'startLine' => 415,
        'endLine' => 438,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'suspendStep' => 
      array (
        'name' => 'suspendStep',
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
        'startLine' => 440,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'resumeStep' => 
      array (
        'name' => 'resumeStep',
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
        'startLine' => 456,
        'endLine' => 470,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'finishStateStep' => 
      array (
        'name' => 'finishStateStep',
        'parameters' => 
        array (
          'state' => 
          array (
            'name' => 'state',
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
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'event' => 
          array (
            'name' => 'event',
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
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 53,
            'endColumn' => 65,
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
            'name' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceStep',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 472,
        'endLine' => 492,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'resizeStep' => 
      array (
        'name' => 'resizeStep',
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
        'startLine' => 494,
        'endLine' => 522,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'finishResizeStep' => 
      array (
        'name' => 'finishResizeStep',
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
        'startLine' => 524,
        'endLine' => 544,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'finalBackupStep' => 
      array (
        'name' => 'finalBackupStep',
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
        'startLine' => 546,
        'endLine' => 586,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'terminateStep' => 
      array (
        'name' => 'terminateStep',
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
        'startLine' => 588,
        'endLine' => 602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'platformDnsStep' => 
      array (
        'name' => 'platformDnsStep',
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
        'docComment' => '/** Hosting subdomains in a platform zone (<label>.web.onhost.cz) lose their A/AAAA rows when the service goes. */',
        'startLine' => 605,
        'endLine' => 632,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'releaseStep' => 
      array (
        'name' => 'releaseStep',
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
        'startLine' => 634,
        'endLine' => 655,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
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
        'startLine' => 657,
        'endLine' => 690,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'restoreStep' => 
      array (
        'name' => 'restoreStep',
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
        'startLine' => 692,
        'endLine' => 722,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'snapshotStep' => 
      array (
        'name' => 'snapshotStep',
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
        'startLine' => 724,
        'endLine' => 741,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'aliasName' => NULL,
      ),
      'rollbackSnapshotStep' => 
      array (
        'name' => 'rollbackSnapshotStep',
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
        'startLine' => 743,
        'endLine' => 757,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Provisioning\\Workflows',
        'declaringClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'implementingClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
        'currentClassName' => 'Onhost\\Domain\\Provisioning\\Workflows\\ServiceActionWorkflow',
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