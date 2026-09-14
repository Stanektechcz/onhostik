<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Domains\RegistrarConnectionService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '21e2beb0fb80cbb38aaf7737487bcb53' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '65549bc8dddb101e1417289f4b092d17' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'c4ec9d4300cff6c0d03a6312dc4a3e15' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'connect',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'dfa5d2fcc52ac05db743766886d92fcd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'probe',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '94a9969ad2d95f79845e5a4ab870c325' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'sync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '6bff39d0ae9ef05d972396354d184126' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'updateSettings',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'c4344f936c02d1a22a0ae2ed99b22308' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'disconnect',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'fb432d8e07c6e0a935507c0d46aa6a39' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'setEnabled',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'b4a2f78f5a3480276f91a1cfcc5b67bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'mirrored',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '706dbdb3405b8d029e7448f1ad994340' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'registrar',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'e10425be0d0ed70d68fdab1a8833227e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'zones',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '56ff39bafe16b832ef44316e9bb774b9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'syncZones',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'd15370bf62e64980027d990782e3c8a2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'importRows',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '19d9ca3b5846017e35566a7448004be1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'notices',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'ec1d10e80c43e56cd682192a1ad33ff3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'creditWatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'a8a7ce9f3e16203c20c923e13f2d59c1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'run',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '332e51e8d8c88a9b514cc480b9b8de93' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'instance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '41812ec2fa7696b9614d98fdfdf901d6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'discard',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'b37b00e87fd2442ca4d1a416d75e4bd5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'dropInstances',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'bb27931fc13b035a8edcfcf6815091f5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'dropZone',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      '737b68468cfccd74d6cfc2d19486cdf2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'canonical',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'fd34fb2f263487f718d17b05c52f3922' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'reason',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'd175f20c0f45971384d08b541a7eabaf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Domains',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
          'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
          'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
          'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
          'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
          'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
          'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'hostname' => 'Onhost\\Platform\\Support\\Hostname',
          'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
          'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
        ),
         'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
         'functionName' => 'mask',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Domains',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'builder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'dnsservice' => 'Onhost\\Domain\\Dns\\DnsService',
            'dnschange' => 'Onhost\\Domain\\Dns\\Models\\DnsChange',
            'dnsrecord' => 'Onhost\\Domain\\Dns\\Models\\DnsRecord',
            'dnszone' => 'Onhost\\Domain\\Dns\\Models\\DnsZone',
            'dnszoneversion' => 'Onhost\\Domain\\Dns\\Models\\DnsZoneVersion',
            'domain' => 'Onhost\\Domain\\Domains\\Models\\Domain',
            'registrarconnection' => 'Onhost\\Domain\\Domains\\Models\\RegistrarConnection',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'providerregistry' => 'Onhost\\Domain\\Provisioning\\ProviderRegistry',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'dbsecretstore' => 'Onhost\\Platform\\Secrets\\DbSecretStore',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'hostname' => 'Onhost\\Platform\\Support\\Hostname',
            'dnsprovider' => 'Onhost\\Providers\\Contracts\\DnsProvider',
            'registrarprovider' => 'Onhost\\Providers\\Contracts\\RegistrarProvider',
          ),
           'className' => 'Onhost\\Domain\\Domains\\RegistrarConnectionService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Domains\\RegistrarConnectionService.php' => '417f4e0deeea41ad268f6c5ba481586e477435fc6f0269af2105cccb0d814300',
    ),
  ),
));