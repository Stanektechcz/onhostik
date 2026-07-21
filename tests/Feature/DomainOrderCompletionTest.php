<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Provisioning\Actions\EnsureOrderProvisionedAction;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\RegisterDomainJob;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Domain-only orders reaching Active (audit E68).
 *
 * Two separate defects combined here: a WEDOS product dispatched no
 * provisioning job at all (RegisterDomainJob only ran when the
 * register_domain add-on flag was set), and RegisterDomainJob never marked
 * the service Active on success. Either one alone left the order stuck in
 * Processing forever.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** A WEDOS (domain) plan — the catalogue seeds hosting/VPS/game only. */
function domainPlan(): PricingPlan
{
    return PricingPlan::factory()->for(
        Product::factory()->state([
            'type'                => \App\Domains\Products\Enums\ProductType::Domain,
            'provisioning_driver' => ProvisioningDriver::Wedos,
        ]),
    )->create();
}

it('activates the service when a domain is registered', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Wedos,
        'status'              => ServiceStatus::Pending,
        'label'               => 'aktivace.cz',
    ]);

    RegisterDomainJob::dispatchSync($service->id, 'aktivace.cz');

    // Registering the domain IS the delivery — it must go live, not linger.
    expect($service->fresh()->status)->toBe(ServiceStatus::Active);
});

it('dispatches domain registration for a domain product without the add-on flag', function (): void {
    $user = customerUser();
    $plan = domainPlan();

    // A domain product ordered on its own: no register_domain flag anywhere.
    $order = app(\App\Domains\Billing\Actions\CreateCartOrderAction::class)->execute(
        $user->customer,
        [['plan' => $plan, 'qty' => 1, 'config' => ['domain' => 'samostatna.cz']]],
    );
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    app(EnsureOrderProvisionedAction::class)->execute($order, sync: true);

    $service = Service::where('order_item_id', $order->items->first()->id)->firstOrFail();

    expect($service->status)->toBe(ServiceStatus::Active);
});

it('advances a domain-only order all the way to Active', function (): void {
    $user = customerUser();
    $plan = domainPlan();

    $order = app(\App\Domains\Billing\Actions\CreateCartOrderAction::class)->execute(
        $user->customer,
        [['plan' => $plan, 'qty' => 1, 'config' => ['domain' => 'kompletni.cz']]],
    );
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    app(EnsureOrderProvisionedAction::class)->execute($order, sync: true);

    // This is the regression: it used to sit in Processing indefinitely.
    expect($order->fresh()->status)->toBe(OrderStatus::Active);
});

it('does not dispatch domain registration when no domain was given', function (): void {
    $user = customerUser();
    $plan = domainPlan();

    $order = app(\App\Domains\Billing\Actions\CreateCartOrderAction::class)->execute(
        $user->customer,
        [['plan' => $plan, 'qty' => 1, 'config' => []]],
    );
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    app(EnsureOrderProvisionedAction::class)->execute($order, sync: true);

    $service = Service::where('order_item_id', $order->items->first()->id)->firstOrFail();

    // Nothing to register — must stay Pending rather than falsely go live.
    expect($service->status)->toBe(ServiceStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('is idempotent when the registration job is replayed', function (): void {
    $user = customerUser();
    $plan = domainPlan();

    $order = app(\App\Domains\Billing\Actions\CreateCartOrderAction::class)->execute(
        $user->customer,
        [['plan' => $plan, 'qty' => 1, 'config' => ['domain' => 'opakovane.cz']]],
    );
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    app(EnsureOrderProvisionedAction::class)->execute($order, sync: true);
    app(EnsureOrderProvisionedAction::class)->execute($order, sync: true);

    $service = Service::where('order_item_id', $order->items->first()->id)->firstOrFail();

    expect($service->status)->toBe(ServiceStatus::Active)
        ->and(\App\Domains\Provisioning\Models\DomainRegistration::where('service_id', $service->id)->count())->toBe(1);
});
