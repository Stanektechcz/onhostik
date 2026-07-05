<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    Bus::fake();
});

// ── Index ──────────────────────────────────────────────────────────────────────

it('admin can access bulk operations dashboard', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bulk.index'))
        ->assertOk()
        ->assertSee('Hromadné operace');
});

it('non-admin cannot access bulk operations', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.bulk.index'))
        ->assertStatus(403);
});

// ── Service: Extend Due Date ───────────────────────────────────────────────────

it('admin can bulk extend due dates on services', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create([
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(5),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-extend'), [
            'ids'  => [$service->id],
            'days' => 30,
        ])
        ->assertRedirect();

    expect($service->fresh()->next_due_date->format('Y-m-d'))
        ->toBe(now()->addDays(35)->format('Y-m-d'));
});

it('bulk extend skips services without due date', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create([
        'status'        => ServiceStatus::Active,
        'next_due_date' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-extend'), ['ids' => [$service->id], 'days' => 30])
        ->assertSessionHasNoErrors();

    expect($service->fresh()->next_due_date)->toBeNull();
});

it('bulk extend validates days range', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-extend'), ['ids' => [$service->id], 'days' => 0])
        ->assertSessionHasErrors('days');

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-extend'), ['ids' => [$service->id], 'days' => 400])
        ->assertSessionHasErrors('days');
});

// ── Service: Terminate ────────────────────────────────────────────────────────

it('admin can bulk terminate services', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-terminate'), [
            'ids'    => [$service->id],
            'reason' => 'Non-payment after 30 days',
        ])
        ->assertRedirect();

    Bus::assertDispatched(\App\Domains\Provisioning\Jobs\ChangeServiceStateJob::class);
});

it('bulk terminate requires a reason', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-terminate'), ['ids' => [$service->id]])
        ->assertSessionHasErrors('reason');
});

// ── Invoice: Void ─────────────────────────────────────────────────────────────

it('admin can bulk void pending invoices', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    ['order' => $order] = placeOrder($user);

    $invoice = Invoice::where('order_id', $order->id)->first();
    $invoice->update(['status' => 'sent']);

    $this->actingAs($admin)
        ->post(route('admin.bulk.invoice-void'), ['ids' => [$invoice->id]])
        ->assertRedirect();

    expect($invoice->fresh()->status->value)->toBe('cancelled');
});

it('bulk void does not affect paid invoices', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    ['order' => $order] = placeOrder($user);
    $invoice = Invoice::where('order_id', $order->id)->first();
    $invoice->update(['status' => 'paid']);

    $this->actingAs($admin)
        ->post(route('admin.bulk.invoice-void'), ['ids' => [$invoice->id]])
        ->assertRedirect();

    expect($invoice->fresh()->status->value)->toBe('paid');
});

// ── Service Export ────────────────────────────────────────────────────────────

it('admin can export selected services as CSV', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    $response = $this->actingAs($admin)
        ->post(route('admin.bulk.service-export'), ['ids' => [$service->id]]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

// ── Customer Export ───────────────────────────────────────────────────────────

it('admin can export selected customers as CSV', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $response = $this->actingAs($admin)
        ->post(route('admin.bulk.customer-export'), ['ids' => [$customer->id]]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('bulk export requires at least one ID', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.bulk.service-export'), ['ids' => []])
        ->assertSessionHasErrors('ids');
});
