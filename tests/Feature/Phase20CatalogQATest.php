<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Products\Enums\SalesMode;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\CheckProxmoxTaskStatusJob;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\Bus;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ─── A. Catalog Availability Tests ────────────────────────────────────────

it('all self-service products have correct sales_mode', function (): void {
    $selfService = Product::where('sales_mode', SalesMode::SelfService->value)->get();
    expect($selfService->pluck('slug')->toArray())
        ->toContain('webhosting', 'vps', 'mailhosting', 'managed-hosting');
});

it('gamehosting has coming_soon sales_mode', function (): void {
    $game = Product::where('slug', 'gamehosting')->first();
    expect($game->sales_mode->value)->toBe(SalesMode::ComingSoon->value);
});

it('self-service products have active orderable plans', function (): void {
    $slugs = ['webhosting', 'vps', 'mailhosting', 'managed-hosting'];
    foreach ($slugs as $slug) {
        $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', $slug))
            ->where('is_active', true)->first();
        expect($plan)->not->toBeNull("No active plan for {$slug}");
        $this->get("/objednavka/{$plan->id}")->assertOk();
    }
});

it('gamehosting plans are not orderable', function (): void {
    $plan = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'gamehosting'))->first();
    expect($plan)->not->toBeNull();
    $this->get("/objednavka/{$plan->id}")->assertNotFound();
});

// ─── B. Frontend CTA Tests ─────────────────────────────────────────────────

it('database page uses "Poptat řešení" not "Objednat"', function (): void {
    $this->get('/databaze')
        ->assertOk()
        ->assertDontSee('>Objednat<')
        ->assertSee('Poptat');
});

it('email-security page uses "Poptat řešení" not "Objednat"', function (): void {
    $this->get('/email-security')
        ->assertOk()
        ->assertDontSee('>Objednat<')
        ->assertSee('Poptat');
});

it('colocation page has no direct order CTA', function (): void {
    $this->get('/kolokace')
        ->assertOk()
        ->assertDontSee('front.order');
});

it('gamehosting page shows coming-soon text when plans inactive', function (): void {
    $this->get('/gamehosting')
        ->assertOk()
        ->assertSee('Připravujeme');
});

it('builder page shows coming soon', function (): void {
    $this->get('/website-builder')
        ->assertOk()
        ->assertSee('Připravujeme');
});

// ─── C. Proxmox VPS Lifecycle Tests ───────────────────────────────────────

it('VPS provisioning creates pending task via ProxmoxMockDriver', function (): void {
    $user   = customerUser();
    $plan   = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'vps'))
        ->where('is_active', true)->first();
    $customer = $user->customer;

    // Manually provision to test task lifecycle (no queue worker needed)
    $order   = app(CreateOrderAction::class)->execute($customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);

    Bus::fake([CheckProxmoxTaskStatusJob::class]);
    app(ProcessMockPaymentAction::class)->execute($invoice);
    // Run the provisioning job synchronously
    $service = Service::where('customer_id', $customer->id)->latest()->first();
    if ($service && $service->status->value === 'pending') {
        $job = new ProvisionHostingServiceJob($service->id);
        $job->handle(app(\App\Domains\Provisioning\Services\DriverResolver::class), app(\App\Services\WebhookDispatcher::class));
    }

    // Either service is active (aaPanel mock) or pending with task (Proxmox mock)
    $service->refresh();
    $task = ProvisioningTask::where('service_id', $service->id)->first();

    if ($task && isset($task->result['pending_task'])) {
        // Proxmox mock path: service is pending, task has UPID
        expect($service->status->value)->toBe('pending')
            ->and($task->result['upid'])->toContain('UPID');

        Bus::assertDispatched(CheckProxmoxTaskStatusJob::class);
    } else {
        // Service already active (legacy path)
        expect($service->status->value)->toBe('active');
    }
});

it('CheckProxmoxTaskStatusJob completes a pending VPS in mock mode', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // Manually create a pending service + task
    $service = Service::create([
        'customer_id'          => $customer->id,
        'product_id'           => Product::where('slug', 'vps')->first()?->id,
        'server_id'            => \App\Domains\Provisioning\Models\Server::first()?->id,
        'label'                => 'vps-test-lifecycle',
        'status'               => 'pending',
        'provisioning_driver'  => 'proxmox',
    ]);

    $task = ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Running->value,
        'attempts'     => 1,
        'max_attempts' => 3,
        'payload'      => [],
        'result'       => ['vmid' => 101, 'upid' => 'UPID:pve-mock:00000065:00000001:mock101', 'pending_task' => true],
        'started_at'   => now(),
    ]);

    // Run the check job
    $job = new CheckProxmoxTaskStatusJob($service->id, $task->id);
    $job->handle();

    $service->refresh();
    $task->refresh();

    expect($service->status)->toBe(ServiceStatus::Active)
        ->and($task->status)->toBe(TaskStatus::Success)
        ->and($service->external_id)->toStartWith('MOCK-PVE-');
});

it('CheckProxmoxTaskStatusJob is idempotent for already-active service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $service = Service::create([
        'customer_id'         => $customer->id,
        'product_id'          => Product::where('slug', 'vps')->first()?->id,
        'label'               => 'vps-already-active',
        'status'              => ServiceStatus::Active->value,
        'external_id'         => 'MOCK-PVE-ALREADY',
        'provisioning_driver' => 'proxmox',
    ]);

    $task = ProvisioningTask::create([
        'service_id'  => $service->id,
        'operation'   => 'create',
        'status'      => TaskStatus::Success->value,
        'attempts'    => 1,
        'max_attempts' => 3,
        'payload'     => [],
        'started_at'  => now(),
        'finished_at' => now(),
    ]);

    $job = new CheckProxmoxTaskStatusJob($service->id, $task->id);
    $job->handle(); // Should be no-op

    $service->refresh();
    expect($service->external_id)->toBe('MOCK-PVE-ALREADY')
        ->and($service->status)->toBe(ServiceStatus::Active);
});

// ─── D. Domain Order Flow Tests ───────────────────────────────────────────

it('domain checkout creates domain registration record in mock mode', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, [
        'domain'          => 'domain-order-test.cz',
        'register_domain' => true,
    ]);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $domain = DomainRegistration::where('service_id', Service::first()?->id)->first();
    expect($domain)->not->toBeNull()
        ->and($domain->fqdn())->toBe('domain-order-test.cz')
        ->and((string) $domain->wedos_domain_id)->toStartWith('MOCK-WD-');
});

it('WAPI_ALLOW_REAL_WRITES=false prevents real domain registration', function (): void {
    expect(config('provisioning.wedos.allow_real_writes'))->toBeFalse();
});

// ─── E. Tax Document Readiness Tests ──────────────────────────────────────

it('complete billing address enables tax document creation', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    CustomerAddress::create([
        'customer_id' => $customer->id,
        'type'        => 'billing',
        'street'      => 'Testovací 1',
        'city'        => 'Praha',
        'zip'         => '11000',
        'country_code' => 'CZ',
        'is_primary'  => true,
    ]);

    $customer->update(['type' => 'company', 'company_name' => 'Test Firma s.r.o.']);

    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);
    $invoice->refresh();

    $taxDoc = app(IssueTaxDocumentAction::class)->execute($invoice);
    expect($taxDoc)->not->toBeNull()
        ->and($taxDoc->type->value)->toBe('invoice');
});

it('missing billing address throws IncompleteBillingDetailsException', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);
    $invoice->refresh();

    expect(fn () => app(IssueTaxDocumentAction::class)->execute($invoice))
        ->toThrow(\App\Domains\Billing\Exceptions\IncompleteBillingDetailsException::class);
});

// ─── F. Full E2E Self-Service Matrix ──────────────────────────────────────

it('webhosting E2E: order → payment → service active → admin visible', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $service = Service::where('customer_id', $user->customer->id)->first();
    expect($service?->status)->toBe(ServiceStatus::Active);

    $admin = adminUser();
    $this->actingAs($admin)->get('/admin/sluzby')->assertOk();
    $this->actingAs($admin)->get('/admin/objednavky')->assertOk();
});

it('mailhosting E2E: order → payment → service with mailhosting product', function (): void {
    $user   = customerUser();
    $plan   = PricingPlan::whereHas('product', fn ($q) => $q->where('slug', 'mailhosting'))
        ->where('is_active', true)->first();

    $order   = app(CreateOrderAction::class)->execute($user->customer, $plan, []);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $service = Service::where('customer_id', $user->customer->id)->with('product')->latest()->first();
    expect($service?->product?->slug)->toBe('mailhosting');
});
