<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('shows the whole vertical slice across the admin pages', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    ['invoice' => $invoice] = placeOrder($user, ['domain' => 'admin-prehled.cz', 'register_domain' => true]);
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $admin = adminUser();

    $this->actingAs($admin)->get('/admin/objednavky')->assertOk()->assertSee($customer->email);
    $this->actingAs($admin)->get('/admin/faktury')->assertOk()->assertSee($invoice->number);
    $this->actingAs($admin)->get('/admin/platby')->assertOk()->assertSee($invoice->number);
    $this->actingAs($admin)->get('/admin/sluzby')->assertOk()->assertSee('admin-prehled.cz')->assertSee('MOCK-AAP-');
    $this->actingAs($admin)->get('/admin/servery')->assertOk()->assertSee('MOCK-AAP-01');
    $this->actingAs($admin)->get('/admin/provisioning')->assertOk()->assertSee('register_domain');
    $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('order.created')->assertSee('provisioning.succeeded');
});

it('filters provisioning tasks by status', function (): void {
    // Phase 35 auto-retry + sync queue means simulate_failure is consumed immediately
    // (retried → success). Build the failed task directly to test status filtering.
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id, 'status' => ServiceStatus::Failed]);
    ProvisioningTask::create([
        'service_id'    => $service->id,
        'operation'     => 'create',
        'status'        => TaskStatus::Failed,
        'attempts'      => 1,
        'max_attempts'  => 3,
        'error_message' => 'Simulated aaPanel failure (mock mode).',
        'payload'       => [],
        'started_at'    => now(),
        'finished_at'   => now(),
    ]);

    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/admin/provisioning?status=failed')
        ->assertOk()
        ->assertSee('Simulated');

    $this->actingAs($admin)
        ->get('/admin/provisioning?status=success')
        ->assertOk()
        ->assertDontSee('Simulated');
});

it('forbids provisioning retry to non-admins', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id, 'status' => ServiceStatus::Failed]);
    $task    = ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Failed,
        'attempts'     => 1,
        'max_attempts' => 3,
        'payload'      => [],
        'started_at'   => now(),
        'finished_at'  => now(),
    ]);

    $this->actingAs($user)
        ->post(route('admin.provisioning.retry', $task))
        ->assertForbidden();
});

it('rejects retrying a task that is not retryable', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $task = ProvisioningTask::where('status', TaskStatus::Success->value)->firstOrFail();

    $this->actingAs(adminUser())
        ->post(route('admin.provisioning.retry', $task))
        ->assertRedirect()
        ->assertSessionHasErrors('retry');
});

// ── Admin impersonation ───────────────────────────────────────────────────────

it('admin can impersonate a customer and return to admin', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    // Start impersonation
    $this->actingAs($admin)
        ->get(route('admin.impersonate.start', $customer))
        ->assertRedirect(route('panel.dashboard'));

    // Now acting as the customer — should see the panel
    $this->get(route('panel.dashboard'))->assertOk();

    // Stop impersonation
    $this->get(route('admin.impersonate.stop'))
        ->assertRedirect(route('admin.customers.index'));

    // Back to admin — panel pages still accessible
    $this->get(route('admin.customers.index'))->assertOk();
});

it('admin cannot impersonate another admin', function (): void {
    $admin1 = adminUser();
    $admin2 = adminUser();

    $this->actingAs($admin1)
        ->get(route('admin.impersonate.start', $admin2))
        ->assertRedirect()
        ->assertSessionHasErrors('impersonate');
});

it('admin cannot impersonate themselves', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.impersonate.start', $admin))
        ->assertRedirect()
        ->assertSessionHasErrors('impersonate');
});
