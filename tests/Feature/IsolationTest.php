<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('never exposes another customer\'s order, invoice, service or domain', function (): void {
    $alice = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($alice, [
        'domain'          => 'alice-web.cz',
        'register_domain' => true,
    ]);
    $this->actingAs($alice)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $service = Service::firstOrFail();
    $domain  = DomainRegistration::firstOrFail();

    $bob = customerUser();

    $this->actingAs($bob)->get(route('panel.orders.show', $order))->assertForbidden();
    $this->actingAs($bob)->get(route('panel.billing.invoices.show', $invoice))->assertForbidden();
    $this->actingAs($bob)->get(route('panel.services.show', $service))->assertForbidden();
    $this->actingAs($bob)->get(route('panel.domains.show', $domain))->assertForbidden();

    // Bob's own lists never contain Alice's records.
    $this->actingAs($bob)->get('/panel/objednavky')->assertOk()->assertDontSee($invoice->number);
    $this->actingAs($bob)->get('/panel/sluzby')->assertOk()->assertDontSee('alice-web.cz');
    $this->actingAs($bob)->get('/panel/domeny')->assertOk()->assertDontSee('alice-web.cz');
});

it('forbids paying another customer\'s invoice', function (): void {
    $alice = customerUser();
    ['invoice' => $invoice] = placeOrder($alice);

    $bob = customerUser();

    $this->actingAs($bob)->post(route('panel.billing.invoices.pay-mock', $invoice))->assertForbidden();
    $this->actingAs($bob)->post(route('panel.billing.invoices.pay-credit', $invoice))->assertForbidden();
});
