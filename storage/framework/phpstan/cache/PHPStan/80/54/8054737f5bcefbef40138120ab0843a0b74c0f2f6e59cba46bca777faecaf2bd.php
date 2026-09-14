<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\domains\Billing\RatingService.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '61820186ee7fb06e8da72dd3c3a99531' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '9941282eef1b0e9c445440779c9aaac1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '07abe2d0fc072b00be42372876e786da' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'rate',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '1efa00631677b1b65d87aac4e0b1ddbf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'chargeDeferred',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '5d19b2bec3abc4604a6279483af6f078' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'unitPrice',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '3e3ceba6c9d124210b168c1e577017ef' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'monthlyCap',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      'aae976eee90d721ba7f5a1f5afb109bf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'rateEvent',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      '6e7855f81d2d2db91750a0493b8c01fa' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Onhost\\Domain\\Billing',
         'uses' => 
        array (
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
          'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
          'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
          'service' => 'Onhost\\Domain\\Services\\Models\\Service',
          'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
          'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
          'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
          'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
          'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
          'money' => 'Onhost\\Platform\\Money\\Money',
          'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
        ),
         'className' => 'Onhost\\Domain\\Billing\\RatingService',
         'functionName' => 'charge',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Onhost\\Domain\\Billing',
           'uses' => 
          array (
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'billingperiod' => 'Onhost\\Domain\\Billing\\Models\\BillingPeriod',
            'ratedusage' => 'Onhost\\Domain\\Billing\\Models\\RatedUsage',
            'usageevent' => 'Onhost\\Domain\\Billing\\Models\\UsageEvent',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'price' => 'Onhost\\Domain\\Catalog\\Models\\Price',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'organization' => 'Onhost\\Domain\\Organizations\\Models\\Organization',
            'service' => 'Onhost\\Domain\\Services\\Models\\Service',
            'taxengine' => 'Onhost\\Domain\\Tax\\TaxEngine',
            'walletservice' => 'Onhost\\Domain\\WalletLedger\\WalletService',
            'commandcontext' => 'Onhost\\Platform\\Commands\\CommandContext',
            'domainerror' => 'Onhost\\Platform\\Errors\\DomainError',
            'genericevent' => 'Onhost\\Platform\\Events\\GenericEvent',
            'money' => 'Onhost\\Platform\\Money\\Money',
            'outboxpublisher' => 'Onhost\\Platform\\Outbox\\OutboxPublisher',
          ),
           'className' => 'Onhost\\Domain\\Billing\\RatingService',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\domains\\Billing\\RatingService.php' => '3be372ed0bce969994f7063e6afb652e11cd35798425b25861a0b3fb091e1dab',
    ),
  ),
));