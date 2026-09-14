<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Incidents\SlaService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '6ef48048313e5f5d313fe00e342d1929' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '65331da14aad7ab6d49c27595365d33b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '2248b7c5e26e18709810c7cb8029aea1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'registerProbe',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'b2563bbfb8a6696d1149874818edac22' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'authenticateProbe',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '5cc660eb4e24f56aef10a6d9c24d61e8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'ingest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '2393931d0e815fdd944936193b1cfd9d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'evaluateQuorum',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '60cec136e23bb2f44aa6045ef3ab1e97' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'settleAutoIncidents',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'eae5caa3073b81fb7e5c76b63d2a895e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'computeWindows',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '27612e0aefb8fa5f53f3d399cedc69a4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'policyState',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '9804747fef94519113fe023c8ad34a46' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'alertBurnRate',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '955715fd069175edca275eab3ae6f199' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'evaluate',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '51f0cfa3713874a2f3f56717e463eff6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'report',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '7156c0a15301ff23f48e513199b81bd2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'creditCandidates',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '6895953b52318b4949cd50bf2caff9d5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'approve',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      '95dc1b71a0ee3743d0a921658495675b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'reject',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'ec47c0a1c8b14a373f56fe924b7d9c8b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'issue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'fbad41591cfab0c86f42729c9170535d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'downtimeSeconds',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'e28f7d9cef8510ea0e424a3de4888f3f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Incidents',
         'uses' => 
        array (
          'queryexception' => 'Illuminate\\Database\\QueryException',
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
          'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
          'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
          'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
          'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
          'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
          'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
          'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Incidents\\SlaService',
         'functionName' => 'servicesForComponent',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Incidents',
           'uses' => 
          array (
            'queryexception' => 'Illuminate\\Database\\QueryException',
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'incident' => 'Onhost\\Domain\\Incidents\\Models\\Incident',
            'maintenance' => 'Onhost\\Domain\\Incidents\\Models\\Maintenance',
            'slacredit' => 'Onhost\\Domain\\Incidents\\Models\\SlaCredit',
            'slacreditpolicy' => 'Onhost\\Domain\\Incidents\\Models\\SlaCreditPolicy',
            'slameasurement' => 'Onhost\\Domain\\Incidents\\Models\\SlaMeasurement',
            'slaprobe' => 'Onhost\\Domain\\Incidents\\Models\\SlaProbe',
            'slowindow' => 'Onhost\\Domain\\Incidents\\Models\\SloWindow',
            'statuscomponent' => 'Onhost\\Domain\\Incidents\\Models\\StatusComponent',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Incidents\\SlaService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Incidents\\SlaService.php' => '53e4c126d2f9e788fe1d701637ab95205145eaf9ccb48bb3cebb359c20c51471',
    ),
  ),
));