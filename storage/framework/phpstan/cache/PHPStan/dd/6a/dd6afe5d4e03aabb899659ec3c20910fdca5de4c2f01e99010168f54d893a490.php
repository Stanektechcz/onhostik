<?php declare(strict_types = 1);

// ftm-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Console\Commands\Doctor.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '7240e397a4ebfb7bb099f429a55d8809' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
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
      '55d8edfce938eafa057ecbf802c19ec7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'handle',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      'b6c79a0659407e018b623ea0c4c1d72a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'add',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '0e80393197d3d2ad88f53678641b64ba' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'environment',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '781e41d30bbb90743c93db5d146b65d1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'storage',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      'eb20493eca7fd06e5b0d5bcb469a540e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'automation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      'a1ffcd0b87606647a84da3577397fcc3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'secretsAndTls',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '962cde77b60d67faac4daf1ddd9f2a17' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'providers',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '24d7c6cb9f78c1c590338f8f3924d10c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'payments',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '3366b996f332b6abae32b2eaaae39524' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'documents',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      '89184174f465814884f5dfe27cc4d38f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'identity',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      'e16254fdbf028f4bd83bbc12b7ad5b28' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Console\\Commands',
         'uses' => 
        array (
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'command' => 'Illuminate\\Console\\Command',
          'db' => 'Illuminate\\Support\\Facades\\DB',
          'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
          'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
          'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
          'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
          'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
          'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
          'user' => 'Onhost\\Domain\\Identity\\Models\\User',
          'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
          'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
          'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
          'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
          'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
          'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
          'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
          'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
          'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
          'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
          'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
          'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
        ),
         'className' => 'App\\Console\\Commands\\Doctor',
         'functionName' => 'mailAndObservability',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Console\\Commands',
           'uses' => 
          array (
            'carbonimmutable' => 'Carbon\\CarbonImmutable',
            'command' => 'Illuminate\\Console\\Command',
            'db' => 'Illuminate\\Support\\Facades\\DB',
            'catalogservice' => 'Onhost\\Domain\\Catalog\\CatalogService',
            'product' => 'Onhost\\Domain\\Catalog\\Models\\Product',
            'tldpolicy' => 'Onhost\\Domain\\Catalog\\Models\\TldPolicy',
            'registrartldcost' => 'Onhost\\Domain\\Domains\\Models\\RegistrarTldCost',
            'registrarclient' => 'Onhost\\Domain\\Domains\\RegistrarClient',
            'registrarpricing' => 'Onhost\\Domain\\Domains\\RegistrarPricing',
            'user' => 'Onhost\\Domain\\Identity\\Models\\User',
            'legalentity' => 'Onhost\\Domain\\Invoicing\\Models\\LegalEntity',
            'automationledger' => 'Onhost\\Domain\\Provisioning\\AutomationLedger',
            'integrationhealth' => 'Onhost\\Domain\\Provisioning\\Models\\IntegrationHealth',
            'node' => 'Onhost\\Domain\\Provisioning\\Models\\Node',
            'planplacement' => 'Onhost\\Domain\\Provisioning\\Models\\PlanPlacement',
            'providerinstance' => 'Onhost\\Domain\\Provisioning\\Models\\ProviderInstance',
            'region' => 'Onhost\\Domain\\Provisioning\\Models\\Region',
            'placementservice' => 'Onhost\\Domain\\Provisioning\\PlacementService',
            'providerinstanceservice' => 'Onhost\\Domain\\Provisioning\\ProviderInstanceService',
            'autotopup' => 'Onhost\\Domain\\WalletLedger\\AutoTopup',
            'virusscanner' => 'Onhost\\Platform\\Files\\VirusScanner',
            'platformbackup' => 'Onhost\\Platform\\Ops\\PlatformBackup',
          ),
           'className' => 'App\\Console\\Commands\\Doctor',
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
      'C:\\Users\\medion\\Desktop\\ONHOST-NEW\\onhost-platform\\app\\Console\\Commands\\Doctor.php' => 'fc282c62ef0b05d783179245850c9667eeb52e65a123d235e680a7eacb40cd29',
    ),
  ),
));