<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\OnCallService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'e4cc19f2ebbf58248ed7d1481ded2f1b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '525b73130e5fc4165771b9b4d6eceae8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '62d8fcb61d4ee4abb57a894eb31ec0e2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'handle',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '768f626a616b1c6e6006018ebf82de60' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'open',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '440aed6eea0682b106d6cec071dabc7e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'acknowledge',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      'd98d1bd1cc02a71753b3fef0e5c74780' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'resolve',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '942183b292bb038ec38325b4c090c767' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'resolveByDedup',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      'ca403f036059ee535f1077cc0284ad27' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'escalateDue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '5ae74a15f56dec7f835c525459ac3aa7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'test',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '2d8d11ded27ac6e6730b5324d76d4097' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'inbound',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      'd145a7df69fcbe0860be575a18f94362' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'provider',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '1206bed446e86df79a26c9c3b58cdeae' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'status',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '5600492d8a5d3b25ff1dd5b753207926' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'present',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '63ea8326fb81841e53f0849c075fd022' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'dedupKey',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      '0b43c824a863501bce3164327a42dbcc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'page',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      'e81ebb21db3dc16cedcb1f17e3595b90' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
          'request' => 'Illuminate\\Http\\Request',
          'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
          'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
          'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
          'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
          'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
          'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
          'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
          'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
          'throwable' => 'Throwable',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
         'functionName' => 'escalateAfterMinutes',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'httpfactory' => 'Illuminate\\Http\\Client\\Factory',
            'request' => 'Illuminate\\Http\\Request',
            'oncallalert' => 'Onhost\\Domain\\Incidents\\Models\\OnCallAlert',
            'notification' => 'Onhost\\Domain\\Notifications\\Models\\Notification',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'tracer' => 'Onhost\\Platform\\Observability\\Tracer',
            'outboxeventdispatched' => 'Onhost\\Platform\\Outbox\\OutboxEventDispatched',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
            'secretref' => 'Onhost\\Platform\\Secrets\\SecretRef',
            'secretstore' => 'Onhost\\Platform\\Secrets\\SecretStore',
            'oncallprovider' => 'Onhost\\Providers\\Contracts\\OnCallProvider',
            'opsgenieoncallprovider' => 'Onhost\\Providers\\OnCall\\OpsgenieOnCallProvider',
            'pagerdutyoncallprovider' => 'Onhost\\Providers\\OnCall\\PagerDutyOnCallProvider',
            'webhookoncallprovider' => 'Onhost\\Providers\\OnCall\\WebhookOnCallProvider',
            'throwable' => 'Throwable',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\OnCallService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Incidents\\OnCallService.php' => 'd2d25adee36dca52784874b35944c1d2bef7bcc869f485346e787562d2eb2419',
    ),
  ),
));