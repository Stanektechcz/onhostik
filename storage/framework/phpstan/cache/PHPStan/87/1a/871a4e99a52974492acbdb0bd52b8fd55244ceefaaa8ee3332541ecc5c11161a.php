<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\SubscriptionService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '74b7bba183ee72c086fa255246dc01fd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '1287f32b85181d33db1690f768c8590f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '4336f7d739dca9fe869fd94b470e905c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'ensureForService',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '20eccd1c59361f5e79c5d9363a9e6c8c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'isMetered',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '9a925069054cf4777fe72e90315afabe' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'tick',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '400efec75bf0c1244c729cf116e14191' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'renew',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '8d3cb274a2ed68030d50eff62923c164' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'changePlan',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'cdf714cdcf93dba0c57b672590b1fc03' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'cancelAtPeriodEnd',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'a6e51facde2a2c15b1ae608e3167711c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'setAutoRenew',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'f1f26e2f0f96fd8c41a7a7d67f41b747' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'retryPastDue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'caaa85f299508099049fae23eb81b2ed' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'advance',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'c11650e028831ec9236fb785ae1e036e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'rollMetered',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      '7d70953090a27e05b8f27a0a6086e86f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'carbon' => 'Illuminate\\Support\\Carbon',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
          'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
         'functionName' => 'expire',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'carbon' => 'Illuminate\\Support\\Carbon',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'orderitem' => 'Onhost\\Domain\\Orders\\Models\\OrderItem',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'servicestatemachine' => 'Onhost\\Domain\\Services\\Models\\ServiceStateMachine',
            'serviceservice' => 'Onhost\\Domain\\Services\\ServiceService',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\SubscriptionService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Billing\\SubscriptionService.php' => '4f4166d1ae63a885b671a42f165ae285cd80b8224e76a9471adecc9a6b8770b0',
    ),
  ),
));