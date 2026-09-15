<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Onhost\Domain\Billing\Commands\ChargebackCommand;
use Onhost\Domain\Billing\Commands\ChargebackCommandHandler;
use Onhost\Domain\Billing\Commands\ChargebackStaffCommand;
use Onhost\Domain\Billing\Listeners\ChargebackSettlement;
use Onhost\Domain\Billing\Listeners\SettleBillingAfterPayment;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Commands\CatalogCommandHandler;
use Onhost\Domain\Compliance\Commands\ComplianceCommand;
use Onhost\Domain\Compliance\Commands\ComplianceCommandHandler;
use Onhost\Domain\Dns\Commands\DnsCommand;
use Onhost\Domain\Dns\Commands\DnsCommandHandler;
use Onhost\Domain\Domains\Commands\DomainCommand;
use Onhost\Domain\Domains\Commands\DomainsCommandHandler;
use Onhost\Domain\Domains\Commands\RegistrarConnectionCommand;
use Onhost\Domain\Domains\Commands\RegistrarConnectionsCommandHandler;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Commands\IdentityCommandHandler;
use Onhost\Domain\Incidents\Commands\IncidentCommand;
use Onhost\Domain\Incidents\Commands\IncidentsCommandHandler;
use Onhost\Domain\Incidents\Commands\OnCallCommand;
use Onhost\Domain\Incidents\Commands\OnCallCommandHandler;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Integrations\Commands\IntegrationCommand;
use Onhost\Domain\Integrations\Commands\IntegrationCommandHandler;
use Onhost\Domain\Invoicing\Commands\InvoiceCommand;
use Onhost\Domain\Invoicing\Commands\InvoicingCommandHandler;
use Onhost\Domain\Invoicing\Listeners\SettleInvoicePayment;
use Onhost\Domain\Loyalty\Commands\AccountLoyaltyCommand;
use Onhost\Domain\Loyalty\Commands\LoyaltyCommand;
use Onhost\Domain\Loyalty\Commands\LoyaltyCommandHandler;
use Onhost\Domain\Loyalty\LoyaltyRouter;
use Onhost\Domain\Marketplace\Commands\MarketplaceCommand;
use Onhost\Domain\Marketplace\Commands\MarketplaceCommandHandler;
use Onhost\Domain\Marketplace\Commands\MarketplaceStaffCommand;
use Onhost\Domain\Notifications\NotificationRouter;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Orders\Commands\OrdersCommandHandler;
use Onhost\Domain\Orders\Commands\PlaceOrderCommand;
use Onhost\Domain\Orders\Commands\ReviewOrderCommand;
use Onhost\Domain\Orders\Commands\StaffCustomerCommand;
use Onhost\Domain\Orders\Listeners\FulfillPaidOrder;
use Onhost\Domain\Orders\Listeners\SettleOrderPayment;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OrganizationsCommandHandler;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\Commands\PartnerPortalCommand;
use Onhost\Domain\Partners\Commands\PartnersCommandHandler;
use Onhost\Domain\Partners\Listeners\AccruePartnerCommission;
use Onhost\Domain\Payments\Commands\BankCommand;
use Onhost\Domain\Payments\Commands\BankCommandHandler;
use Onhost\Domain\Payments\Events\PaymentSucceeded;
use Onhost\Domain\Provisioning\Commands\CapacityCommand;
use Onhost\Domain\Provisioning\Commands\CapacityCommandHandler;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommandHandler;
use Onhost\Domain\Services\Commands\IssueConsoleTokenCommand;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Commands\ServicesCommandHandler;
use Onhost\Domain\Services\Commands\WebToolsCommand;
use Onhost\Domain\Services\Commands\WebToolsCommandHandler;
use Onhost\Domain\WalletLedger\Commands\AutoTopupCommand;
use Onhost\Domain\WalletLedger\Commands\RemovePaymentMethodCommand;
use Onhost\Domain\WalletLedger\Commands\TopUpWalletCommand;
use Onhost\Domain\WalletLedger\Commands\WalletCommandHandler;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/**
 * Cross-domain wiring: domain events -> listeners. Domains never call each other's
 * models directly. Outbox events are delivered as `onhost.<event name>` string
 * events carrying the OutboxMessage (see OutboxPublisher::relayPending).
 */
final class DomainServiceProvider extends ServiceProvider
{
    /** Command → handler map for the audited command bus (one handler per domain, dispatch by command class / `op`). */
    public const HANDLERS = [
        PlaceOrderCommand::class => OrdersCommandHandler::class,
        ReviewOrderCommand::class => OrdersCommandHandler::class,
        StaffCustomerCommand::class => OrdersCommandHandler::class,
        ChargebackCommand::class => ChargebackCommandHandler::class,
        ChargebackStaffCommand::class => ChargebackCommandHandler::class,
        LoyaltyCommand::class => LoyaltyCommandHandler::class,
        AccountLoyaltyCommand::class => LoyaltyCommandHandler::class,
        MarketplaceCommand::class => MarketplaceCommandHandler::class,
        MarketplaceStaffCommand::class => MarketplaceCommandHandler::class,
        CatalogCommand::class => CatalogCommandHandler::class,
        TopUpWalletCommand::class => WalletCommandHandler::class,
        AutoTopupCommand::class => WalletCommandHandler::class,
        RemovePaymentMethodCommand::class => WalletCommandHandler::class,
        BankCommand::class => BankCommandHandler::class,
        ServiceActionCommand::class => ServicesCommandHandler::class,
        IssueConsoleTokenCommand::class => ServicesCommandHandler::class,
        WebToolsCommand::class => WebToolsCommandHandler::class,
        IntegrationCommand::class => IntegrationCommandHandler::class,
        DomainCommand::class => DomainsCommandHandler::class,
        RegistrarConnectionCommand::class => RegistrarConnectionsCommandHandler::class,
        DnsCommand::class => DnsCommandHandler::class,
        OrganizationCommand::class => OrganizationsCommandHandler::class,
        ApiTokenCommand::class => IdentityCommandHandler::class,
        ProvisioningCommand::class => ProvisioningCommandHandler::class,
        CapacityCommand::class => CapacityCommandHandler::class,
        InvoiceCommand::class => InvoicingCommandHandler::class,
        IncidentCommand::class => IncidentsCommandHandler::class,
        OnCallCommand::class => OnCallCommandHandler::class,
        ComplianceCommand::class => ComplianceCommandHandler::class,
        PartnerCommand::class => PartnersCommandHandler::class,
        PartnerPortalCommand::class => PartnersCommandHandler::class,
    ];

    public function boot(): void
    {
        Event::listen(PaymentSucceeded::class, SettleOrderPayment::class);
        Event::listen(PaymentSucceeded::class, SettleInvoicePayment::class);
        Event::listen('onhost.order.paid', FulfillPaidOrder::class);
        Event::listen('onhost.invoice.paid', SettleBillingAfterPayment::class);
        Event::listen('onhost.invoice.paid', AccruePartnerCommission::class);
        Event::listen('onhost.invoice.issued', AccruePartnerCommission::class);
        Event::listen('onhost.wallet.topup.completed', SettleBillingAfterPayment::class);
        Event::listen(OutboxEventDispatched::class, NotificationRouter::class);
        Event::listen(OutboxEventDispatched::class, WebhookDispatcher::class);
        Event::listen(OutboxEventDispatched::class, OnCallService::class); // operational events page the on-call (audit §5q-1)
        Event::listen(OutboxEventDispatched::class, ChargebackSettlement::class); // credit back once the service is gone
        Event::listen(OutboxEventDispatched::class, LoyaltyRouter::class); // points for what customers do

        $this->app->afterResolving(CommandBus::class, function (CommandBus $bus): void {
            foreach (self::HANDLERS as $command => $handler) {
                $bus->register($command, $handler);
            }
        });
    }
}
