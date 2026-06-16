<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

/*
 * Queue runs SYNC in tests, so the queued jobs execute inline during the
 * payment request — the full vertical slice is exercised end to end.
 */

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('provisions hosting and registers the domain after payment (mock end to end)', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, [
        'domain'          => 'kompletni-flow.cz',
        'register_domain' => true,
    ]);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $service = Service::firstOrFail();
    expect($service->status)->toBe(ServiceStatus::Active)
        ->and((string) $service->external_id)->toStartWith('MOCK-AAP-')
        ->and($service->server_id)->not->toBeNull();

    $createTask = ProvisioningTask::where('operation', 'create')->firstOrFail();
    $domainTask = ProvisioningTask::where('operation', 'register_domain')->firstOrFail();
    expect($createTask->status)->toBe(TaskStatus::Success)
        ->and($createTask->finished_at)->not->toBeNull()
        ->and($domainTask->status)->toBe(TaskStatus::Success);

    $domain = DomainRegistration::firstOrFail();
    expect($domain->service_id)->toBe($service->id)
        ->and($domain->fqdn())->toBe('kompletni-flow.cz')
        ->and((string) $domain->wedos_domain_id)->toStartWith('MOCK-WD-')
        ->and($domain->registered_at)->not->toBeNull()
        ->and($domain->nameservers)->toBe(['ns1.onhost.cz', 'ns2.onhost.cz']);

    // Customer sees the results in the panel.
    $this->actingAs($user)->get('/panel/sluzby')->assertOk()->assertSee('kompletni-flow.cz');
    $this->actingAs($user)->get(route('panel.services.show', $service))->assertOk()->assertSee('MOCK-AAP-');
    $this->actingAs($user)->get('/panel/domeny')->assertOk()->assertSee('kompletni-flow.cz');
    $this->actingAs($user)->get(route('panel.domains.show', $domain))->assertOk()->assertSee('ns1.onhost.cz');

    // Audit trail of the provisioning pipeline.
    foreach (['provisioning.started', 'provisioning.succeeded'] as $event) {
        expect(Activity::where('log_name', 'provisioning')->where('description', $event)->exists())
            ->toBeTrue("missing activity {$event}");
    }
    expect(Activity::where('log_name', 'domain')->where('description', 'domain.registered')->exists())->toBeTrue();
});

it('dispatches ProvisionHostingServiceJob for a VPS (Proxmox) service after payment', function (): void {
    Queue::fake();

    $user = customerUser();
    $plan = PricingPlan::query()
        ->whereHas('product', fn ($query) => $query->where('type', ProductType::Vps))
        ->firstOrFail();

    $order   = app(CreateOrderAction::class)->execute($user->customer, $plan);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);

    app(ProcessMockPaymentAction::class)->execute($invoice);

    $service = Service::where('provisioning_driver', ProvisioningDriver::Proxmox)->firstOrFail();

    expect($service->status)->toBe(ServiceStatus::Pending);

    Queue::assertPushed(
        ProvisionHostingServiceJob::class,
        fn (ProvisionHostingServiceJob $job): bool => $job->serviceId === $service->id,
    );
});

it('handles a simulated provisioning failure and succeeds on admin retry', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, [
        'domain'           => 'selhavajici-web.cz',
        'register_domain'  => true,
        'simulate_failure' => true,
    ]);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $service = Service::firstOrFail();
    expect($service->status)->toBe(ServiceStatus::Failed)
        ->and($service->external_id)->toBeNull();

    $createTask = ProvisioningTask::where('operation', 'create')->firstOrFail();
    $domainTask = ProvisioningTask::where('operation', 'register_domain')->firstOrFail();
    expect($createTask->status)->toBe(TaskStatus::Failed)
        ->and($createTask->error_message)->toContain('Simulated')
        ->and($createTask->attempts)->toBe(1)
        ->and($domainTask->status)->toBe(TaskStatus::Failed);

    expect(Activity::where('log_name', 'provisioning')->where('description', 'provisioning.failed')->exists())->toBeTrue()
        ->and(Activity::where('log_name', 'domain')->where('description', 'domain.registration_failed')->exists())->toBeTrue();

    // ---- admin retry: the one-shot simulated failure has been consumed ----
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.provisioning.retry', $createTask))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($createTask->refresh()->status)->toBe(TaskStatus::Success)
        ->and($createTask->attempts)->toBe(2)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active)
        ->and((string) $service->external_id)->toStartWith('MOCK-AAP-');

    $this->actingAs($admin)->post(route('admin.provisioning.retry', $domainTask));

    expect($domainTask->refresh()->status)->toBe(TaskStatus::Success)
        ->and(DomainRegistration::count())->toBe(1);

    expect(
        Activity::where('log_name', 'provisioning')
            ->where('description', 'provisioning.retry_requested')
            ->whereMorphedTo('causer', $admin)
            ->exists()
    )->toBeTrue();
});

it('keeps provisioning idempotent when the job runs twice', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    $service    = Service::firstOrFail();
    $externalId = $service->external_id;

    // Re-run the job manually — nothing may change, nothing may duplicate.
    (new ProvisionHostingServiceJob($service->id))->handle(app(DriverResolver::class));

    expect($service->refresh()->external_id)->toBe($externalId)
        ->and(ProvisioningTask::where('operation', 'create')->count())->toBe(1)
        ->and(ProvisioningTask::where('operation', 'create')->firstOrFail()->attempts)->toBe(1);
});
