<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
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
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, ['simulate_failure' => true]);
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

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
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, ['simulate_failure' => true]);
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $task = ProvisioningTask::where('status', TaskStatus::Failed->value)->firstOrFail();

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
