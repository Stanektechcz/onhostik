<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Integrations\DiscordService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '7c0b704e7adbcf260a5349146f74f0c8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8861f07bee7608eed46acb5d7f41e3ce' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '4f805b77d4fc9a4c70b36350a969d9c8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'configured',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '782f4ce17e53e2bf54d7120a30af2826' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'inviteUrl',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '0362079f2519062a7fbbfe24de6ea583' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'status',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f0fe312a46d290f4313c820af3bc622e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'createLinkCode',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c5059ecd66a54c4d0f7efeef67625c97' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'unlink',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '9a0f6d01dc2e21e7b3f6257a8bd699de' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'verifySignature',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '714e2887ac22590f5e6ac30c454626df' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'handleInteraction',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f672794b8b537dc695f2304ca981bef2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'registerCommands',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a87865b0f519b22982bb53f7e2f8b5a7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'commandDefinition',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '32fd74ccaf229d9be9ba2805451cd91f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'link',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '64f8b6acafc2b4dc9200895b0a419257' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'servicesReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '101c1a03e04cfade106d04e7a4d3f453' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'statusReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a87aa7d46fc949134ed6d64205f49fdc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'runReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '110e5ae638a87238dae1ab2c22f3d5e3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'proposeReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '03a744061906dea4c0a1ca52396b09d8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'askReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a7e739dd125d19a9058863d884edae21' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'component',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'b2ebbe3d31491f32281c77612a343b49' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'execute',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '40c8c21ecfe08967066616b8a810254b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'confirmReply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '54d6c433cb0c1f40362a1b0af0279072' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'reply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c38cbe2bd97a1a3f1f43f37f58c3ae4d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'principal',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '01d2f30017b0cad0071c9ce46923ab80' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'context',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3972654343c119279ace13c361814d54' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'resolveService',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '654feb63bbc2a4c3730c78cbf8fca72f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'rateOk',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '46e79225c0a8bd74e99ff87674e2275e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'botToken',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '9ae9a07fcc97429862cbeec6146b544a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'family',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'aa7b8c84d93dd46dd73d3ae45346053d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Integrations',
         'uses' => 
        array (
          'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
          'http' => 'Illuminate\\Support\\Facades\\Http',
          'str' => 'Illuminate\\Support\\Str',
          'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
          'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
         'functionName' => 'actionLabel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Integrations',
           'uses' => 
          array (
            'cacherepository' => 'Illuminate\\Contracts\\Cache\\Repository',
            'http' => 'Illuminate\\Support\\Facades\\Http',
            'str' => 'Illuminate\\Support\\Str',
            'authorizer' => 'Onhost\\Domain\\Identity\\Authorization\\Authorizer',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'discordlink' => 'Onhost\\Domain\\Integrations\\Models\\DiscordLink',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'operation' => 'Onhost\\Domain\\Provisioning\\Models\\Operation',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'servicefeatures' => 'Onhost\\Domain\\Services\\ServiceFeatures',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'uptimemonitor' => 'Onhost\\Domain\\Services\\Web\\UptimeMonitor',
            'assistantservice' => 'Onhost\\Domain\\Support\\Assistant\\AssistantService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'commandscope' => 'Onhost\\Platform\\Commands\\CommandScope',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Integrations\\DiscordService',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Integrations\\DiscordService.php' => '1d3bef8bf7021c78446910e54b3dca800eb1454163b94d3b51f1c6676511b4a5',
    ),
  ),
));