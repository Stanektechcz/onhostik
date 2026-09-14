<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Partners\PartnerService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '6013b72ed0e4f9a649fac22e22212036' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'b5c0926e5a5cb7ee174244875fed991f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '4d8850fc32f239427862fd677f273141' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'apply',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'dc3513530143186e662a513426bf7d75' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'approve',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'ccda96101a67f31c9fcc6d8b52f2c671' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'setState',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '175862822ec58337182b3d4922429e00' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'attribute',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'c0f8e4a7eba1b4ed9138c1d8c2158b39' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'partnerFor',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'f689b43734f0d3f3da50a8e7a6573388' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'partnerOfClient',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '68d68b6bed47b1d4388f469664d35851' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'accrueForInvoice',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'b6c95b6b482025fa1f926cd4b53db37f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'reverseForCreditNote',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '9c41a8b0ecdb6b757d49a3555b306ddb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'recomputeTier',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '157ee1ff4561d3e435a84337373cb214' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'recomputeAllTiers',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '9fa6e29c4de1c76ec99c9f06d1f4581b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'tierRate',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '6c111be0588bd36f376edac49f5f984d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'balance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '42045730f58939bf70f66c3af806a8c3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'requestPayout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '53453d0fb8ab2bf7fffef83542ee62e2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'approvePayout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'f2148f9b616e2ba5640712b51908912f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'rejectPayout',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '5403a4ce41c4e4987709bb23a5d8d433' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'markPayoutPaid',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '5ee908920348197c1b4ecad9d124de0a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'clients',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '099d728a104310681760e10fcdce870f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'overview',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '91530e63db671199c69ac866892873e5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'commissionMonths',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'f8525aa998528269fcb162c53af4a011' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'updateWhitelabel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '7d4f5674ae1836b9a4d3576edf4eb356' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'verifyWhitelabelDomains',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '60d81ba1fe478e5b355362b14b6ffea6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'requestModelChange',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '901d6eedb25344f0f3f5e9973fc210c2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'requestChange',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '576e8f64aefb5e17a468b0a8e579eafd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'autoApproveReason',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '8edfb0ec668dff9e61758d3df487398b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'decideModelChange',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '7ef7f7b923a1dc88ac4ccc4aa4e04bc2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'decideChange',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '528c8a2928e90bf71f6544e195c60f9f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'applyPendingModels',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '558a525d22888cec09ec3dc178660cdf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'applyPendingChanges',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '136211e06554b98659fc832f829e3ce9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'applyChange',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'd1b92eb89206900ba56c2d267f2ab79a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'termValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '5c50be609488d0682be6f8b817a83bfb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'terms',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '007404e9b1140cc78064bdc7a979e2bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'pendingOf',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'b889dea47a294a9e5ebe402a33320a5b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'modelRequest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '3ac0286685e482e612b2486a225e9824' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'presentRequest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '42f32816d06843534801198c49b0b481' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'autoPayouts',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'e30181ee2daea45759f0b005b1ec90ea' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'clientQuery',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      '37dcba9ddcd3f013d94d9f5d19b19a8e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'code',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'd2b8264248a7b81479494e518c419364' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'payoutNumber',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'ce414cfcbcbcbd0ba4594ccdac2a3852' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Partners',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'collection' => 'Illuminate\\Support\\Collection',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'str' => 'Illuminate\\Support\\Str',
          'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Partners\\PartnerService',
         'functionName' => 'selfBilling',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Partners',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'collection' => 'Illuminate\\Support\\Collection',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'str' => 'Illuminate\\Support\\Str',
            'dunningcase' => 'Onhost\\Domain\\Billing\\Models\\DunningCase',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnerchangerequest' => 'Onhost\\Domain\\Partners\\Models\\PartnerChangeRequest',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'partnerpayout' => 'Onhost\\Domain\\Partners\\Models\\PartnerPayout',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'ledgerservice' => 'Onhost\\Domain\\WalletLedger\\LedgerService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Partners\\PartnerService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Partners\\PartnerService.php' => 'b4afae86183715981dca3fc301f67d246b2186a577c6ff1241d92661dac3a7b5',
    ),
  ),
));