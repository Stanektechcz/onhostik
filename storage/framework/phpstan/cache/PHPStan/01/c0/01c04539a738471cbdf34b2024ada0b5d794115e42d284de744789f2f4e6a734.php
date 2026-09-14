<?php declare(strict_types = 1);

// odsl-C:\Users\medion\Desktop\ONHOST-NEW\onhost-platform\app\Providers\DomainServiceProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Providers\DomainServiceProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.6-8.3.33-9f1b2ea6e2f2b99fa61f7b0812777d32034b0e39ad73f0d68a28c67911449225',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Providers\\DomainServiceProvider',
        'filename' => 'C:/Users/medion/Desktop/ONHOST-NEW/onhost-platform/app/Providers/DomainServiceProvider.php',
      ),
    ),
    'namespace' => 'App\\Providers',
    'name' => 'App\\Providers\\DomainServiceProvider',
    'shortName' => 'DomainServiceProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Cross-domain wiring: domain events -> listeners. Domains never call each other\'s
 * models directly. Outbox events are delivered as `onhost.<event name>` string
 * events carrying the OutboxMessage (see OutboxPublisher::relayPending).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 80,
    'endLine' => 137,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\ServiceProvider',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'HANDLERS' => 
      array (
        'declaringClassName' => 'App\\Providers\\DomainServiceProvider',
        'implementingClassName' => 'App\\Providers\\DomainServiceProvider',
        'name' => 'HANDLERS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\\Onhost\\Domain\\Orders\\Commands\\PlaceOrderCommand::class => \\Onhost\\Domain\\Orders\\Commands\\OrdersCommandHandler::class, \\Onhost\\Domain\\Orders\\Commands\\ReviewOrderCommand::class => \\Onhost\\Domain\\Orders\\Commands\\OrdersCommandHandler::class, \\Onhost\\Domain\\Billing\\Commands\\ChargebackCommand::class => \\Onhost\\Domain\\Billing\\Commands\\ChargebackCommandHandler::class, \\Onhost\\Domain\\Billing\\Commands\\ChargebackStaffCommand::class => \\Onhost\\Domain\\Billing\\Commands\\ChargebackCommandHandler::class, \\Onhost\\Domain\\Loyalty\\Commands\\LoyaltyCommand::class => \\Onhost\\Domain\\Loyalty\\Commands\\LoyaltyCommandHandler::class, \\Onhost\\Domain\\Loyalty\\Commands\\AccountLoyaltyCommand::class => \\Onhost\\Domain\\Loyalty\\Commands\\LoyaltyCommandHandler::class, \\Onhost\\Domain\\Marketplace\\Commands\\MarketplaceCommand::class => \\Onhost\\Domain\\Marketplace\\Commands\\MarketplaceCommandHandler::class, \\Onhost\\Domain\\Marketplace\\Commands\\MarketplaceStaffCommand::class => \\Onhost\\Domain\\Marketplace\\Commands\\MarketplaceCommandHandler::class, \\Onhost\\Domain\\Catalog\\Commands\\CatalogCommand::class => \\Onhost\\Domain\\Catalog\\Commands\\CatalogCommandHandler::class, \\Onhost\\Domain\\WalletLedger\\Commands\\TopUpWalletCommand::class => \\Onhost\\Domain\\WalletLedger\\Commands\\WalletCommandHandler::class, \\Onhost\\Domain\\WalletLedger\\Commands\\AutoTopupCommand::class => \\Onhost\\Domain\\WalletLedger\\Commands\\WalletCommandHandler::class, \\Onhost\\Domain\\WalletLedger\\Commands\\RemovePaymentMethodCommand::class => \\Onhost\\Domain\\WalletLedger\\Commands\\WalletCommandHandler::class, \\Onhost\\Domain\\Payments\\Commands\\BankCommand::class => \\Onhost\\Domain\\Payments\\Commands\\BankCommandHandler::class, \\Onhost\\Domain\\Services\\Commands\\ServiceActionCommand::class => \\Onhost\\Domain\\Services\\Commands\\ServicesCommandHandler::class, \\Onhost\\Domain\\Services\\Commands\\IssueConsoleTokenCommand::class => \\Onhost\\Domain\\Services\\Commands\\ServicesCommandHandler::class, \\Onhost\\Domain\\Services\\Commands\\WebToolsCommand::class => \\Onhost\\Domain\\Services\\Commands\\WebToolsCommandHandler::class, \\Onhost\\Domain\\Integrations\\Commands\\IntegrationCommand::class => \\Onhost\\Domain\\Integrations\\Commands\\IntegrationCommandHandler::class, \\Onhost\\Domain\\Domains\\Commands\\DomainCommand::class => \\Onhost\\Domain\\Domains\\Commands\\DomainsCommandHandler::class, \\Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionCommand::class => \\Onhost\\Domain\\Domains\\Commands\\RegistrarConnectionsCommandHandler::class, \\Onhost\\Domain\\Dns\\Commands\\DnsCommand::class => \\Onhost\\Domain\\Dns\\Commands\\DnsCommandHandler::class, \\Onhost\\Domain\\Organizations\\Commands\\OrganizationCommand::class => \\Onhost\\Domain\\Organizations\\Commands\\OrganizationsCommandHandler::class, \\Onhost\\Domain\\Identity\\Commands\\ApiTokenCommand::class => \\Onhost\\Domain\\Identity\\Commands\\IdentityCommandHandler::class, \\Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommand::class => \\Onhost\\Domain\\Provisioning\\Commands\\ProvisioningCommandHandler::class, \\Onhost\\Domain\\Provisioning\\Commands\\CapacityCommand::class => \\Onhost\\Domain\\Provisioning\\Commands\\CapacityCommandHandler::class, \\Onhost\\Domain\\Invoicing\\Commands\\InvoiceCommand::class => \\Onhost\\Domain\\Invoicing\\Commands\\InvoicingCommandHandler::class, \\Onhost\\Domain\\Incidents\\Commands\\IncidentCommand::class => \\Onhost\\Domain\\Incidents\\Commands\\IncidentsCommandHandler::class, \\Onhost\\Domain\\Incidents\\Commands\\OnCallCommand::class => \\Onhost\\Domain\\Incidents\\Commands\\OnCallCommandHandler::class, \\Onhost\\Domain\\Compliance\\Commands\\ComplianceCommand::class => \\Onhost\\Domain\\Compliance\\Commands\\ComplianceCommandHandler::class, \\Onhost\\Domain\\Partners\\Commands\\PartnerCommand::class => \\Onhost\\Domain\\Partners\\Commands\\PartnersCommandHandler::class, \\Onhost\\Domain\\Partners\\Commands\\PartnerPortalCommand::class => \\Onhost\\Domain\\Partners\\Commands\\PartnersCommandHandler::class]',
          'attributes' => 
          array (
            'startLine' => 83,
            'endLine' => 114,
            'startTokenPos' => 374,
            'startFilePos' => 4314,
            'endTokenPos' => 706,
            'endFilePos' => 6355,
          ),
        ),
        'docComment' => '/** Command → handler map for the audited command bus (one handler per domain, dispatch by command class / `op`). */',
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'boot' => 
      array (
        'name' => 'boot',
        'parameters' => 
        array (
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
        'startLine' => 116,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\DomainServiceProvider',
        'implementingClassName' => 'App\\Providers\\DomainServiceProvider',
        'currentClassName' => 'App\\Providers\\DomainServiceProvider',
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