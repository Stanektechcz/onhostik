<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Marketplace\MarketplaceService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'c41f948b7e32aede422ee07604ec3e8a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '38f10412c630114685b9a4b3b455ef03' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'a38ae544e499dfb63b2fa1a4ad43905f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'createListing',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'e6390a8a8289527d4f5349ca73ec804d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'updateListing',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '70f04447c221e2519de3aa6bbae6dfc4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'setListingState',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'be7bc6f13857cdfd8fed12e5fbe6b1e1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'order',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '987eeb891360509e51dc5f282d1d324a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'start',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '6c66c3adfe1d94bc2a87bedf09df3693' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'deliver',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '4277e4e0c7a5f525a466c82375b3c4a8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'deliverPeriod',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'd4631439c8757a1dcd5e471143a210e1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'accept',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '57cceeb40550cdc40883df20f9e03323' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'dispute',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'b608a58171b098476ee86847961fbdd1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'cancel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'a6fae2791ffeccfd7524d6d09cc49624' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'resolveDispute',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '58f88f90575426bb434283e4d4b87702' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'autoAccept',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '961a24a841ceeae4913293e5186a60bd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'autoAcceptDays',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'd97ab83d00c896355c3543399495cf89' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'presentListing',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'ee314eaf709ba4014a33b7bbd5825638' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'presentOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '709854bd72a6b468d64ad80e1859eb29' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'presentEvidence',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '06ae4a24f4f50eb6e883efff668277b2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'sla',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '27d95dedd808fc764c02eeb1972a4d6f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'daysLate',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '83e0528142f2822b8d5269d0fa56d5bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'lateCredit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '3977df5e0399e7263b07010b8e85706c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'overdueGraceDays',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '814bf307447de91d2edbef97853752a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'sweepOverdue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '3e72123939d624a1a66cc73152c2cbc1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'sweepPeriods',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'aa5cb15d3125a6523739f6e89cd0df87' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'subscriptionInfo',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'd757fbaffdd73f81a30d0ff818f6ee55' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'renewDue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '6dbdcdf995d851b20cdd5341f4c702d9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'attachEvidence',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '2f5f2112371a6900b4ae3377205782eb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'rescanEvidence',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '7ba95513ed510a26fb3da09a88dcee9e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'evidenceFile',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'dfea6a133a7c27a93b466c2f973ae19a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'periodServed',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '520deb3b990e8f04451f5a32be9b8a01' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'missedPeriodCredit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'de9b938edc5750959bd58fd42b60903a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'periodInfo',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'ca42acb109c36824ac2a27cdbd1a8a9c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'endSubscription',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '95c1e89d9c9d151ddfe705350c3d6744' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'settleAccepted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '0e8da87cec88a04aaeb529e950e7122d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'refund',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'd29bc195de85c50a2de969a40ca888b0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'transition',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'da989ef4fe8ae100552368c0e1f22980' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'commissionPct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '97573d70a854d994a63a690972d3955e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'price',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '34bed92025c58f7fde5a10d077091f42' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'listingData',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'e457bbe0656d8d91255f46acb509f5a7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'normalizeChecklist',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '249afa59d314402dde45080f9364cb06' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'checklistFor',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '195fb768f103b018b9f234bccb2f6eee' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'assertActivePartner',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '486b69d5d26608edfaaa16dc978d1ab8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'assertOwner',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      '62150c9ae4750e6c39013d212c9c5fe4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'assertPartnerOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'e4bf10923998313662c4164dadc8ac34' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Marketplace',
         'uses' => 
        array (
          'carboninterface' => 'Carbon\\CarbonInterface',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'storage' => 'Illuminate\\Support\\Facades\\Storage',
          'str' => 'Illuminate\\Support\\Str',
          'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
          'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
          'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
          'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
          'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'filestore' => 'Onhost\\Platform\\Files\\FileStore',
          'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
         'functionName' => 'assertCustomerOrder',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Marketplace',
           'uses' => 
          array (
            'carboninterface' => 'Carbon\\CarbonInterface',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'storage' => 'Illuminate\\Support\\Facades\\Storage',
            'str' => 'Illuminate\\Support\\Str',
            'subscription' => 'Onhost\\Domain\\Billing\\Models\\Subscription',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'invoiceservice' => 'Onhost\\Domain\\Invoicing\\InvoiceService',
            'invoice' => 'Onhost\\Domain\\Invoicing\\Models\\Invoice',
            'marketplacelisting' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceListing',
            'marketplaceorder' => 'Onhost\\Domain\\Marketplace\\Models\\MarketplaceOrder',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'partner' => 'Onhost\\Domain\\Partners\\Models\\Partner',
            'partnercommission' => 'Onhost\\Domain\\Partners\\Models\\PartnerCommission',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'auditrecorder' => 'Onhost\\Platform\\Audit\\AuditRecorder',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'filestore' => 'Onhost\\Platform\\Files\\FileStore',
            'uploadguard' => 'Onhost\\Platform\\Files\\UploadGuard',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Marketplace\\MarketplaceService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Marketplace\\MarketplaceService.php' => '12dd2f6faf301737286df10417b37c89318bf753596e5a7a469b18880c1dfa19',
    ),
  ),
));