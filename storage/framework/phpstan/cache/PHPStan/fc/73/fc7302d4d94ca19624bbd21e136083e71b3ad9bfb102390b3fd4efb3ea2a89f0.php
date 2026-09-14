<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Services\ServiceFeatures.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Onhost\Domain\Services\ServiceFeatures
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-0672c0b46165883df23e4d1037241188938f243ce5c3a9b84cb424bf8f2e8f7a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/domains/Services/ServiceFeatures.php',
      ),
    ),
    'namespace' => 'Onhost\\Domain\\Services',
    'name' => 'Onhost\\Domain\\Services\\ServiceFeatures',
    'shortName' => 'ServiceFeatures',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * What a customer can do with a service — derived from the executor\'s adapter (what the panel behind the service
 * offers), the plan\'s entitlements (limits) and the service state. The customer surfaces render their tabs from this
 * catalogue and never learn which vendor panel is behind it; the same keys drive `ServiceService::requestAction`.
 *
 *  features(): key => {enabled, limit?, options?}      resources(): live listings (databases, cron, ftp, …)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 45,
    'endLine' => 495,
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
      'ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    \'power\' => [\'power\'],
    \'suspend\' => [\'suspend\'],
    \'resume\' => [\'resume\'],
    \'resize\' => [\'resize\'],
    \'terminate\' => [\'terminate\'],
    \'backups\' => [\'backup\'],
    \'restore\' => [\'restore\'],
    \'snapshots\' => [\'snapshot\', \'rollback_snapshot\', \'snapshot.delete\'],
    \'php\' => [\'php.set\'],
    \'databases\' => [\'database.create\', \'database.delete\'],
    \'ftp\' => [\'ftp.create\', \'ftp.delete\', \'ftp.password\'],
    \'ssl\' => [\'ssl.issue\'],
    \'https\' => [\'https.force\'],
    \'cron\' => [\'cron.create\', \'cron.delete\'],
    \'subdomains\' => [\'subdomain.add\', \'subdomain.remove\'],
    \'redirects\' => [\'redirect.set\'],
    \'firewall\' => [\'firewall.apply\'],
    \'command\' => [\'command.send\'],
    \'schedules\' => [\'schedule.create\'],
    \'mailboxes\' => [\'mailbox.create\', \'mailbox.update\', \'mailbox.delete\'],
    \'aliases\' => [\'alias.create\', \'alias.delete\'],
    \'sending\' => [\'sending.set\'],
    // extended web tabs (what the prototype\'s workbench offers and the panels really support)
    \'errpages\' => [\'errpages.set\'],
    \'directives\' => [\'directives.set\'],
    \'protected\' => [\'folder.protect\', \'folder.unprotect\'],
    \'db_users\' => [\'dbuser.create\', \'dbuser.password\', \'dbuser.delete\'],
    \'shell\' => [\'shell.create\', \'shell.key\', \'shell.delete\'],
    \'stats\' => [\'stats.set\'],
    \'ssl_upload\' => [\'ssl.upload\'],
    \'files\' => [\'file.mkdir\', \'file.delete\', \'file.save\'],
    \'apps\' => [\'app.install\'],
    // tools on top of the panels (WebToolsProvider): terminal, PHP settings, security rules, HTTP/3, cron editing, database transfers and access, backups, files, Node projects, wildcard certificates
    \'terminal\' => [\'command.run\'],
    \'php_settings\' => [\'php.settings\'],
    \'security\' => [\'security.set\'],
    \'http3\' => [\'http3.set\'],
    \'cron_edit\' => [\'cron.update\', \'cron.run\'],
    \'db_export\' => [\'database.export\', \'database.import\'],
    \'db_access\' => [\'database.access\'],
    \'backup_delete\' => [\'backup.delete\'],
    \'files_advanced\' => [\'file.rename\', \'file.copy\', \'file.chmod\', \'file.archive\', \'file.extract\'],
    \'node_projects\' => [\'node.create\', \'node.action\'],
    \'proxy\' => [\'proxy.create\', \'proxy.delete\', \'proxies.set\'],
    \'default_docs\' => [\'index.set\'],
    \'ssl_wildcard\' => [\'ssl.wildcard\'],
    // platform features around the site (workflows of their own): staging, git deploy, WordPress toolkit, CDN, imports
    \'staging\' => [\'staging.create\', \'staging.refresh\', \'staging.push\', \'staging.delete\'],
    \'deploy\' => [\'deploy.run\', \'deploy.rollback\'],
    \'wordpress\' => [\'wp.install\', \'wp.update\', \'wp.cache\', \'wp.plugin\'],
    \'cdn\' => [\'cdn.enable\', \'cdn.disable\', \'cdn.purge\'],
    \'import\' => [\'import.run\'],
    // mail tools (MailToolsProvider)
    \'forwards\' => [\'forward.create\', \'forward.delete\'],
    \'catchall\' => [\'catchall.set\'],
    \'autoresponder\' => [\'autoresponder.set\'],
    \'spam\' => [\'spam.policy\', \'spam.list.add\', \'spam.list.delete\'],
    \'mail_filters\' => [\'filter.create\', \'filter.delete\'],
    \'mailing_lists\' => [\'list.create\', \'list.delete\'],
    \'fetchmail\' => [\'fetchmail.create\', \'fetchmail.delete\'],
    \'mail_backups\' => [\'mailbox.backup\', \'mailbox.restore\'],
    // game tools (GameToolsProvider): startup variables and image, server settings, schedule housekeeping, databases, collaborators, files, ports, backup housekeeping, the customer\'s panel account
    \'startup\' => [\'variable.set\', \'image.set\'],
    \'game_settings\' => [\'rename\', \'reinstall\'],
    \'schedule_tools\' => [\'schedule.delete\', \'schedule.toggle\', \'schedule.run\'],
    \'game_databases\' => [\'gamedb.create\', \'gamedb.rotate\', \'gamedb.delete\'],
    \'subusers\' => [\'subuser.create\', \'subuser.delete\'],
    \'game_files\' => [\'gfile.save\', \'gfile.upload\', \'gfile.delete\', \'gfile.mkdir\', \'gfile.rename\'],
    \'allocations\' => [\'allocation.add\', \'allocation.primary\', \'allocation.remove\'],
    \'backup_tools\' => [\'gbackup.delete\', \'gbackup.lock\'],
    \'panel_access\' => [\'panel.password\'],
]',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 72,
            'startTokenPos' => 189,
            'startFilePos' => 2140,
            'endTokenPos' => 963,
            'endFilePos' => 5982,
          ),
        ),
        'docComment' => '/** Customer actions accepted by POST /v1/services/{id}/actions, grouped by the feature that enables them. */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'RESOURCES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'RESOURCES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'databases\', \'ftp\', \'cron\', \'subdomains\', \'certificate\', \'redirect\', \'php\', \'snapshots\', \'mailboxes\', \'aliases\', \'dkim\', \'firewall\', \'site_settings\', \'protected_folders\', \'db_users\', \'shell_users\', \'files\', \'apps\', \'tools\', \'php_settings\', \'security\', \'http_versions\', \'cron_logs\', \'database_access\', \'quotas\', \'node_projects\', \'staging\', \'deploy\', \'deployments\', \'wordpress\', \'monitoring\', \'monitoring_samples\', \'certificates\', \'cdn\', \'imports\', \'mail_forwards\', \'mail_catchall\', \'mail_autoresponder\', \'mail_spam\', \'mail_spam_lists\', \'mail_filters\', \'mail_lists\', \'mail_fetchmail\', \'mail_backups\', \'mail_usage\', \'proxies\', \'default_docs\', \'status\', \'server_detail\', \'startup\', \'schedules\', \'game_databases\', \'subusers\', \'game_files\', \'allocations\', \'panel_access\']',
          'attributes' => 
          array (
            'startLine' => 74,
            'endLine' => 79,
            'startTokenPos' => 974,
            'startFilePos' => 6015,
            'endTokenPos' => 1144,
            'endFilePos' => 6819,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 74,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'GAME_ACTIONS' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'GAME_ACTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'variable.set\', \'image.set\', \'rename\', \'reinstall\', \'schedule.delete\', \'schedule.toggle\', \'schedule.run\', \'gamedb.create\', \'gamedb.rotate\', \'gamedb.delete\', \'subuser.create\', \'subuser.delete\', \'gfile.save\', \'gfile.upload\', \'gfile.delete\', \'gfile.mkdir\', \'gfile.rename\', \'allocation.add\', \'allocation.primary\', \'allocation.remove\', \'gbackup.delete\', \'gbackup.lock\', \'panel.password\']',
          'attributes' => 
          array (
            'startLine' => 82,
            'endLine' => 82,
            'startTokenPos' => 1157,
            'startFilePos' => 6973,
            'endTokenPos' => 1225,
            'endFilePos' => 7355,
          ),
        ),
        'docComment' => '/** Game actions the customer may take (ServiceActionCommand risk): what is destructive needs a fresh step-up. */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 416,
      ),
      'PLATFORM_RESOURCES' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'PLATFORM_RESOURCES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'staging\', \'deploy\', \'deployments\', \'wordpress\', \'monitoring\', \'monitoring_samples\', \'certificates\', \'cdn\', \'imports\']',
          'attributes' => 
          array (
            'startLine' => 85,
            'endLine' => 85,
            'startTokenPos' => 1238,
            'startFilePos' => 7524,
            'endTokenPos' => 1264,
            'endFilePos' => 7642,
          ),
        ),
        'docComment' => '/** resource kinds answered by the platform\'s own records (short cache: the panel refreshes them right after an action) */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 158,
      ),
    ),
    'immediateProperties' => 
    array (
      'providers' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'providers',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 33,
        'endColumn' => 76,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cache' => 
      array (
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'name' => 'cache',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Cache\\Repository',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 79,
        'endColumn' => 117,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'providers' => 
          array (
            'name' => 'providers',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 33,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cache' => 
          array (
            'name' => 'cache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Cache\\Repository',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 79,
            'endColumn' => 117,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 121,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'features' => 
      array (
        'name' => 'features',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 30,
            'endColumn' => 45,
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
        'docComment' => '/** @return array<string, array{enabled:bool, limit?:int|null, options?:mixed, reason?:string}> */',
        'startLine' => 90,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'actions' => 
      array (
        'name' => 'actions',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 171,
            'endLine' => 171,
            'startColumn' => 29,
            'endColumn' => 44,
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
        'docComment' => '/** Actions the customer may request right now (features → actions). @return list<string> */',
        'startLine' => 171,
        'endLine' => 182,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'resources' => 
      array (
        'name' => 'resources',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 186,
            'endLine' => 186,
            'startColumn' => 31,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'kind' => 
          array (
            'name' => 'kind',
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
            'startLine' => 186,
            'endLine' => 186,
            'startColumn' => 49,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'fresh' => 
          array (
            'name' => 'fresh',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 186,
                'endLine' => 186,
                'startTokenPos' => 3913,
                'startFilePos' => 17628,
                'endTokenPos' => 3913,
                'endFilePos' => 17632,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 186,
            'endLine' => 186,
            'startColumn' => 63,
            'endColumn' => 81,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'params' => 
          array (
            'name' => 'params',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 186,
                'endLine' => 186,
                'startTokenPos' => 3922,
                'startFilePos' => 17651,
                'endTokenPos' => 3923,
                'endFilePos' => 17652,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 186,
            'endLine' => 186,
            'startColumn' => 84,
            'endColumn' => 101,
            'parameterIndex' => 3,
            'isOptional' => true,
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
        'docComment' => '/** @param array<string,mixed> $params listing parameters (`path` for the file manager) */',
        'startLine' => 186,
        'endLine' => 317,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'fileContents' => 
      array (
        'name' => 'fileContents',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 34,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 52,
            'endColumn' => 63,
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
        'docComment' => '/** File contents for the download endpoint (never cached, size-capped in the adapter/controller). */',
        'startLine' => 320,
        'endLine' => 335,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'gameTools' => 
      array (
        'name' => 'gameTools',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
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
                      'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
            'startLine' => 338,
            'endLine' => 338,
            'startColumn' => 31,
            'endColumn' => 55,
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
            'name' => 'Onhost\\Providers\\Contracts\\GameToolsProvider',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Game tools (GameToolsProvider) — the adapter of a running game server, or a clear refusal. */',
        'startLine' => 338,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'forget' => 
      array (
        'name' => 'forget',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 347,
            'endLine' => 347,
            'startColumn' => 28,
            'endColumn' => 43,
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
        'startLine' => 347,
        'endLine' => 352,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'logs' => 
      array (
        'name' => 'logs',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 26,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'log' => 
          array (
            'name' => 'log',
            'default' => 
            array (
              'code' => '\'access\'',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 7005,
                'startFilePos' => 30252,
                'endTokenPos' => 7005,
                'endFilePos' => 30259,
              ),
            ),
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
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 44,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'lines' => 
          array (
            'name' => 'lines',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 355,
                'endLine' => 355,
                'startTokenPos' => 7014,
                'startFilePos' => 30275,
                'endTokenPos' => 7014,
                'endFilePos' => 30277,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 355,
            'endLine' => 355,
            'startColumn' => 68,
            'endColumn' => 83,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'docComment' => '/** @return list<string> */',
        'startLine' => 355,
        'endLine' => 384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'scopedName' => 
      array (
        'name' => 'scopedName',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'suffix' => 
          array (
            'name' => 'suffix',
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
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 57,
            'endColumn' => 70,
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
        'docComment' => '/** Names on shared executors are scoped per service, so a customer-chosen suffix becomes `oh…_suffix`. */',
        'startLine' => 387,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'adapter' => 
      array (
        'name' => 'adapter',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 392,
            'endLine' => 392,
            'startColumn' => 30,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'required' => 
          array (
            'name' => 'required',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 392,
                'endLine' => 392,
                'startTokenPos' => 7486,
                'startFilePos' => 32533,
                'endTokenPos' => 7486,
                'endFilePos' => 32537,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 392,
            'endLine' => 392,
            'startColumn' => 48,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
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
                  'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
        'startLine' => 392,
        'endLine' => 411,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'ref' => 
      array (
        'name' => 'ref',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 413,
            'endLine' => 413,
            'startColumn' => 26,
            'endColumn' => 41,
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
        'docComment' => NULL,
        'startLine' => 413,
        'endLine' => 421,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'tools' => 
      array (
        'name' => 'tools',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
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
                      'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
            'startLine' => 424,
            'endLine' => 424,
            'startColumn' => 27,
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
            'name' => 'Onhost\\Providers\\Contracts\\WebToolsProvider',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Tools on top of the panel (WebToolsProvider) — the adapter of a running service, or a clear refusal. */',
        'startLine' => 424,
        'endLine' => 431,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'mailTools' => 
      array (
        'name' => 'mailTools',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
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
                      'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 31,
            'endColumn' => 55,
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
            'name' => 'Onhost\\Providers\\Contracts\\MailToolsProvider',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 433,
        'endLine' => 440,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'toolsFor' => 
      array (
        'name' => 'toolsFor',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 443,
            'endLine' => 443,
            'startColumn' => 30,
            'endColumn' => 45,
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
        'docComment' => '/** The adapter and primary resource of a service for the tool services (staging, deploy, WordPress, imports). @return array{0:WebToolsProvider,1:ResourceRef} */',
        'startLine' => 443,
        'endLine' => 446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'adapterFor' => 
      array (
        'name' => 'adapterFor',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 32,
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
            'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The provider adapter of a provisioned service (platform services such as staging and import need the hosting API next to the tools). */',
        'startLine' => 449,
        'endLine' => 452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'refFor' => 
      array (
        'name' => 'refFor',
        'parameters' => 
        array (
          'service' => 
          array (
            'name' => 'service',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Domain\\Services\\Models\\Service',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 454,
            'endLine' => 454,
            'startColumn' => 28,
            'endColumn' => 43,
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
        'docComment' => NULL,
        'startLine' => 454,
        'endLine' => 457,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'mailboxRef' => 
      array (
        'name' => 'mailboxRef',
        'parameters' => 
        array (
          'domain' => 
          array (
            'name' => 'domain',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Onhost\\Providers\\Contracts\\ResourceRef',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 459,
            'endLine' => 459,
            'startColumn' => 39,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'remoteId' => 
          array (
            'name' => 'remoteId',
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
            'startLine' => 459,
            'endLine' => 459,
            'startColumn' => 60,
            'endColumn' => 75,
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
            'name' => 'Onhost\\Providers\\Contracts\\ResourceRef',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 459,
        'endLine' => 466,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'web' => 
      array (
        'name' => 'web',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
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
                      'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
            'startLine' => 468,
            'endLine' => 468,
            'startColumn' => 26,
            'endColumn' => 50,
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
            'name' => 'Onhost\\Providers\\Contracts\\WebHostingProvider',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 468,
        'endLine' => 475,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'mail' => 
      array (
        'name' => 'mail',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
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
                      'name' => 'Onhost\\Providers\\Contracts\\ProviderAdapter',
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
            'startLine' => 477,
            'endLine' => 477,
            'startColumn' => 27,
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
            'name' => 'Onhost\\Providers\\Contracts\\MailProvider',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 477,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'aliasName' => NULL,
      ),
      'fallbackSite' => 
      array (
        'name' => 'fallbackSite',
        'parameters' => 
        array (
          'executor' => 
          array (
            'name' => 'executor',
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
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 42,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Executor feature set when the adapter cannot be built (credentials missing): what each executor kind offers. */',
        'startLine' => 487,
        'endLine' => 494,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Onhost\\Domain\\Services',
        'declaringClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'implementingClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
        'currentClassName' => 'Onhost\\Domain\\Services\\ServiceFeatures',
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