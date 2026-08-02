<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Actions;

use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\OrderItem;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Jobs\RegisterDomainJob;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServerSelector;

/**
 * Ensures every item of a paid order has a Service and that provisioning has
 * been kicked off. Extracted from HandleInvoicePaid so the same, single
 * source of truth heals orders whose service was never created or whose
 * provisioning job was lost (see ProvisionPendingServicesCommand).
 *
 * Fully idempotent: firstOrCreate keeps one service per order_item, and the
 * provisioning job is a no-op once the service is already Active.
 */
final class EnsureOrderProvisionedAction
{
    /** @param bool $sync run provisioning synchronously (queue-worker fallback) */
    public function execute(Order $order, bool $sync = false): void
    {
        $provisionable = [
            ProvisioningDriver::AAPanel,
            ProvisioningDriver::Proxmox,
            ProvisioningDriver::Pterodactyl,
        ];

        foreach ($order->items as $item) {
            $service = $this->ensureService($order, $item);

            if ($service === null || $service->status === ServiceStatus::Active) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $config = $item->config ?? [];

            if (in_array($service->provisioning_driver, $provisionable, true)) {
                $sync
                    ? ProvisionHostingServiceJob::dispatchSync($service->id)
                    : ProvisionHostingServiceJob::dispatch($service->id);
            }

            $rawDomain = $config['domain'] ?? null;
            $domain    = is_string($rawDomain) ? $rawDomain : '';

            if ($domain === '') {
                continue;
            }

            // For a WEDOS product the registration IS the service, so it must
            // run regardless of the register_domain flag — that flag only
            // means "also register this domain" for a hosting product. Without
            // this, a domain-only order dispatched nothing at all and stayed
            // Pending forever (audit E68).
            $isDomainProduct = $service->provisioning_driver === ProvisioningDriver::Wedos;
            $registerAddon   = ($config['register_domain'] ?? false) === true;

            if ($isDomainProduct || $registerAddon) {
                $sync
                    ? RegisterDomainJob::dispatchSync($service->id, $domain)
                    : RegisterDomainJob::dispatch($service->id, $domain);
            }
        }
    }

    public function ensureService(Order $order, OrderItem $item): ?Service
    {
        $plan    = $item->pricingPlan;
        $product = $plan?->product;

        if ($plan === null || $product === null) {
            return null;
        }

        $driver = $product->provisioning_driver ?? ProvisioningDriver::AAPanel;

        /** @var array<string, mixed> $config */
        $config = $item->config ?? [];
        $domain = is_string($config['domain'] ?? null) ? $config['domain'] : null;

        // Spread load by free capacity instead of always filling the default
        // server until it falls over (audit E69). Select + create under a
        // per-driver lock so concurrent orders can't over-fill the same server.
        $service = app(ServerSelector::class)->pickAndReserve(
            $driver,
            fn (?Server $server): Service => Service::firstOrCreate(
                ['order_item_id' => $item->id],
                [
                    'customer_id'         => $order->customer_id,
                    'product_id'          => $product->id,
                    'server_id'           => $server?->id,
                    'provisioning_driver' => $driver,
                    'status'              => ServiceStatus::Pending,
                    'label'               => $domain ?? mb_strtolower((string) $plan->name) . '-' . $item->id,
                    'resources'           => $plan->resources,
                    'next_due_date'       => $item->period_to,
                ],
            ),
        );

        if ($service->wasRecentlyCreated) {
            activity('service')
                ->performedOn($service)
                ->withProperties(['order_id' => $order->id, 'order_item_id' => $item->id])
                ->log('service.created');
        }

        return $service;
    }
}
