<?php

declare(strict_types=1);

use App\Domains\Ai\Enums\ApprovalStatus;
use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Services\TicketService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('loads the admin dashboard and module pages', function (string $route): void {
    $this->actingAs(adminUser())->get(route($route))->assertOk();
})->with([
    'admin.dashboard',
    'admin.integrations.index',
    'admin.system.index',
    'admin.monitoring.index',
    'admin.backups.index',
    'admin.domains.index',
    'admin.support.index',
    'admin.ai.index',
]);

it('keeps the new admin pages closed to customers', function (string $route): void {
    $this->actingAs(customerUser())->get(route($route))->assertForbidden();
})->with(['admin.integrations.index', 'admin.system.index', 'admin.ai.index']);

it('updates integration settings with audit log and masked secrets', function (): void {
    $admin       = adminUser();
    $integration = IntegrationSetting::query()->where('provider', 'aapanel')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.integrations.update', $integration), [
            'mock_mode'   => 1,
            'dry_run'     => 1,
            'credentials' => ['base_url' => 'https://panel.example.test', 'api_key' => 'super-secret-key-123'],
        ])
        ->assertRedirect();

    $integration->refresh();

    expect($integration->credentials['api_key'])->toBe('super-secret-key-123')
        ->and($integration->maskedCredentials()['api_key'])->toEndWith('-123')
        ->and($integration->maskedCredentials()['api_key'])->not->toContain('super-secret');

    $audit = Activity::query()->where('description', 'integration.settings_updated')->latest('id')->firstOrFail();

    // Secret VALUES must never reach the audit log — key names only.
    expect(json_encode($audit->properties))->not->toContain('super-secret-key-123');
});

it('runs a mock connection test and records health', function (): void {
    $integration = IntegrationSetting::query()->where('provider', 'wedos')->firstOrFail();

    $this->actingAs(adminUser())
        ->post(route('admin.integrations.test', $integration))
        ->assertRedirect();

    expect($integration->fresh()->last_success_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'integration.connection_test_ok')->exists())->toBeTrue();
});

it('updates a pricing plan from the admin products page', function (): void {
    $plan = PricingPlan::query()->orderBy('sort_order')->firstOrFail();

    $this->actingAs(adminUser())
        ->put(route('admin.products.plans.update', $plan), [
            'price_czk'   => 59,
            'is_active'   => 1,
            'is_featured' => 1,
        ])
        ->assertRedirect();

    expect($plan->fresh()->price_czk)->toBe(5_900)
        ->and($plan->fresh()->is_featured)->toBeTrue()
        ->and(Activity::query()->where('description', 'product.plan_updated')->exists())->toBeTrue();
});

it('adjusts customer credit with a required reason and full audit', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    // reason is mandatory
    $this->actingAs($admin)
        ->post(route('admin.customers.credit', $user->customer), ['amount' => 100])
        ->assertSessionHasErrors('reason');

    $this->actingAs($admin)
        ->post(route('admin.customers.credit', $user->customer), [
            'amount' => 250,
            'reason' => 'Kompenzace za výpadek služby',
        ])
        ->assertRedirect();

    $entry = CreditTransaction::query()->firstOrFail();

    expect($entry->amount?->getMinorAmount()->toInt())->toBe(25_000)
        ->and($entry->created_by)->toBe($admin->id)
        ->and(Activity::query()->where('description', 'credit.admin_adjusted')->exists())->toBeTrue();

    // overdraw via negative adjustment is rejected, balance unchanged
    $this->actingAs($admin)
        ->post(route('admin.customers.credit', $user->customer), [
            'amount' => -999,
            'reason' => 'Test overdraw guard',
        ])
        ->assertSessionHasErrors('amount');

    expect(app(CreditLedger::class)->getBalance($user->customer->fresh())->getMinorAmount()->toInt())->toBe(25_000);
});

it('marks an invoice paid via the full mock payment flow', function (): void {
    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user, ['domain' => 'adminpaid.cz', 'register_domain' => true]);

    $this->actingAs(adminUser())
        ->post(route('admin.invoices.mark-paid', $invoice))
        ->assertRedirect();

    $service = Service::query()->firstOrFail();

    expect($invoice->fresh()->status->value)->toBe('paid')
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($service->status)->toBe(ServiceStatus::Active)
        ->and($service->domainRegistration?->wedos_domain_id)->toStartWith('MOCK-WD-');
});

it('suspends and reactivates a service through queued driver jobs', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);
    $this->actingAs(adminUser())->post(route('admin.invoices.mark-paid', $invoice));

    $service = Service::query()->firstOrFail();
    expect($service->status)->toBe(ServiceStatus::Active);

    $this->actingAs(adminUser())
        ->post(route('admin.services.suspend', $service), ['reason' => 'Neuhrazená faktura'])
        ->assertRedirect();

    $service->refresh();
    expect($service->status)->toBe(ServiceStatus::Suspended)
        ->and($service->suspension_reason)->toBe('Neuhrazená faktura')
        ->and($service->provisioningTasks()->where('operation', 'suspend')->where('status', 'success')->exists())->toBeTrue();

    $this->actingAs(adminUser())
        ->post(route('admin.services.unsuspend', $service))
        ->assertRedirect();

    expect($service->fresh()->status)->toBe(ServiceStatus::Active);
});

it('lets the admin reply to a ticket and change its status', function (): void {
    $user   = customerUser();
    $ticket = app(TicketService::class)->open($user->customer, $user, 'Dotaz na fakturu', 'Prosím o vysvětlení položky.');

    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.support.reply', $ticket), ['message' => 'Dobrý den, položka je za doménu.'])
        ->assertRedirect();

    expect($ticket->fresh()->status->value)->toBe('answered');

    $this->actingAs($admin)
        ->put(route('admin.support.update', $ticket), ['status' => 'closed'])
        ->assertRedirect();

    expect($ticket->fresh()->status->value)->toBe('closed')
        ->and($ticket->events()->where('event', 'status_changed')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'support.status_changed')->exists())->toBeTrue();
});

it('parks high-risk AI actions for approval and audits the decision', function (): void {
    $admin     = adminUser();
    $assistant = app(AiAssistantService::class);

    $approval = $assistant->requestAction($admin, 'restart_service', ['service_id' => 1]);

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and(Activity::query()->where('description', 'ai.approval_requested')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.ai.review', $approval), ['decision' => 'approve', 'reason' => 'Bezpečná akce'])
        ->assertRedirect();

    $approval->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->reviewed_by)->toBe($admin->id)
        ->and(Activity::query()->where('description', 'ai.approval_reviewed')->exists())->toBeTrue();

    // decision is final — a second review is a no-op kept as approved
    $this->actingAs($admin)
        ->post(route('admin.ai.review', $approval), ['decision' => 'reject'])
        ->assertSessionHasErrors('approval');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});
