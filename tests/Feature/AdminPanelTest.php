<?php

declare(strict_types=1);

use App\Domains\Ai\Enums\ApprovalStatus;
use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\GameServerPreset;
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

// ── Game preset CRUD ──────────────────────────────────────────────────────────

it('admin can create a game preset', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.game-presets.store'), [
            'name'              => 'Minecraft Java',
            'game_slug'         => 'minecraft-java',
            'description'       => 'Populární sandbox hra',
            'nest_id'           => 1,
            'egg_id'            => 2,
            'default_memory_mb' => 2048,
            'default_disk_mb'   => 10240,
            'default_cpu_limit' => 200,
            'is_active'         => '1',
            'sort_order'        => 10,
        ])
        ->assertRedirect(route('admin.game-presets.index'));

    expect(GameServerPreset::where('game_slug', 'minecraft-java')->exists())->toBeTrue();
});

it('game preset slug must be unique', function (): void {
    $admin = adminUser();
    GameServerPreset::create([
        'name'              => 'CS2',
        'game_slug'         => 'cs2',
        'nest_id'           => 1,
        'egg_id'            => 3,
        'default_memory_mb' => 2048,
        'default_disk_mb'   => 10240,
        'default_cpu_limit' => 100,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.game-presets.store'), [
            'name'              => 'CS2 Duplicate',
            'game_slug'         => 'cs2',
            'nest_id'           => 1,
            'egg_id'            => 3,
            'default_memory_mb' => 2048,
            'default_disk_mb'   => 10240,
            'default_cpu_limit' => 100,
        ])
        ->assertSessionHasErrors('game_slug');
});

it('admin can update a game preset', function (): void {
    $admin  = adminUser();
    $preset = GameServerPreset::create([
        'name'              => 'Old Name',
        'game_slug'         => 'ark-survival',
        'nest_id'           => 1,
        'egg_id'            => 4,
        'default_memory_mb' => 4096,
        'default_disk_mb'   => 20480,
        'default_cpu_limit' => 300,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.game-presets.update', $preset), [
            'name'              => 'ARK: Survival Evolved',
            'game_slug'         => 'ark-survival',
            'nest_id'           => 1,
            'egg_id'            => 4,
            'default_memory_mb' => 8192,
            'default_disk_mb'   => 20480,
            'default_cpu_limit' => 300,
        ])
        ->assertRedirect(route('admin.game-presets.edit', $preset));

    expect($preset->fresh()->name)->toBe('ARK: Survival Evolved')
        ->and($preset->fresh()->default_memory_mb)->toBe(8192);
});

it('admin can delete a game preset', function (): void {
    $admin  = adminUser();
    $preset = GameServerPreset::create([
        'name'              => 'Valheim',
        'game_slug'         => 'valheim',
        'nest_id'           => 1,
        'egg_id'            => 5,
        'default_memory_mb' => 4096,
        'default_disk_mb'   => 20480,
        'default_cpu_limit' => 100,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.game-presets.destroy', $preset))
        ->assertRedirect(route('admin.game-presets.index'));

    expect(GameServerPreset::find($preset->id))->toBeNull();
});

it('customer cannot access game preset admin routes', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
        ->get(route('admin.game-presets.index'))
        ->assertForbidden();
});

// ── Admin subscriber management ───────────────────────────────────────────────

it('admin can toggle subscriber active status', function (): void {
    $admin = adminUser();

    $subscriber = \App\Models\Subscriber::create([
        'email'        => 'toggler@example.com',
        'locale'       => 'cs',
        'source'       => 'website',
        'is_active'    => true,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.subscribers.toggle', $subscriber))
        ->assertRedirect();

    expect($subscriber->fresh()->is_active)->toBeFalse()
        ->and($subscriber->fresh()->unsubscribed_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.subscribers.toggle', $subscriber))
        ->assertRedirect();

    expect($subscriber->fresh()->is_active)->toBeTrue()
        ->and($subscriber->fresh()->unsubscribed_at)->toBeNull();
});

it('admin can delete a subscriber', function (): void {
    $admin = adminUser();

    $subscriber = \App\Models\Subscriber::create([
        'email'     => 'delete-me@example.com',
        'locale'    => 'cs',
        'source'    => 'website',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.subscribers.destroy', $subscriber))
        ->assertRedirect();

    expect(\App\Models\Subscriber::where('email', 'delete-me@example.com')->exists())->toBeFalse();
});

it('admin can export subscribers as CSV', function (): void {
    $admin = adminUser();

    \App\Models\Subscriber::create([
        'email'        => 'csv-test@example.com',
        'locale'       => 'cs',
        'source'       => 'website',
        'is_active'    => true,
        'confirmed_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.subscribers.export'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('csv-test@example.com');
});

it('customer cannot access subscriber admin routes', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.subscribers.index'))
        ->assertForbidden();
});

// ── Admin customer notes ──────────────────────────────────────────────────────

it('admin can save customer notes', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->put(route('admin.customers.notes', $customer), [
            'admin_notes' => 'VIP zákazník, platí vždy včas.',
        ])
        ->assertRedirect();

    expect($customer->fresh()->admin_notes)->toBe('VIP zákazník, platí vždy včas.');
});

it('admin can clear customer notes', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $customer->update(['admin_notes' => 'Old note']);

    $this->actingAs($admin)
        ->put(route('admin.customers.notes', $customer), ['admin_notes' => ''])
        ->assertRedirect();

    expect($customer->fresh()->admin_notes)->toBeNull();
});

it('customer cannot update their own admin notes', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $this->actingAs($user)
        ->put(route('admin.customers.notes', $customer), [
            'admin_notes' => 'Self-promoted to VIP.',
        ])
        ->assertForbidden();
});
