<?php

declare(strict_types=1);

use App\Domains\Audit\Services\AdminAuditService;
use App\Domains\Billing\Services\CreditLedger;
use App\Models\AdminActionLog;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model ─────────────────────────────────────────────────────────────────────

it('AdminActionLog has UPDATED_AT null (append-only)', function (): void {
    expect(AdminActionLog::UPDATED_AT)->toBeNull();
});

it('AdminActionLog ACTIONS constant contains expected keys', function (): void {
    expect(AdminActionLog::ACTIONS)
        ->toHaveKey('impersonate_start')
        ->toHaveKey('impersonate_stop')
        ->toHaveKey('credit_adjustment')
        ->toHaveKey('manual_payment')
        ->toHaveKey('service_suspend');
});

it('actionLabel returns human label', function (): void {
    $log = new AdminActionLog(['action' => 'impersonate_start']);
    expect($log->actionLabel())->toBe('Zahájení impersonace');
});

it('actionLabel falls back to raw action for unknown keys', function (): void {
    $log = new AdminActionLog(['action' => 'unknown_action_xyz']);
    expect($log->actionLabel())->toBe('unknown_action_xyz');
});

// ── AdminAuditService ─────────────────────────────────────────────────────────

it('AdminAuditService::log creates a row in admin_action_logs', function (): void {
    $admin   = adminUser();
    $service = app(AdminAuditService::class);

    $log = $service->log($admin, 'credit_adjustment', null, ['amount_minor' => 5000]);

    expect(AdminActionLog::find($log->id))->not->toBeNull()
        ->and($log->admin_user_id)->toBe($admin->id)
        ->and($log->action)->toBe('credit_adjustment')
        ->and($log->metadata['amount_minor'])->toBe(5000);
});

it('AdminAuditService::log stores target morph', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = app(AdminAuditService::class);

    $log = $service->log($admin, 'impersonate_start', $user, ['target_email' => $user->email]);

    expect($log->target_type)->toBe(\App\Models\User::class)
        ->and($log->target_id)->toBe($user->id);
});

it('AdminAuditService::log stores null metadata when no metadata provided', function (): void {
    $admin   = adminUser();
    $service = app(AdminAuditService::class);

    $log = $service->log($admin, 'impersonate_stop');

    expect($log->metadata)->toBeNull();
});

// ── Credit adjustment triggers audit ──────────────────────────────────────────

it('CreditLedger::adjust creates an audit log entry', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;
    $ledger   = app(CreditLedger::class);

    $before = AdminActionLog::where('action', 'credit_adjustment')->count();

    $ledger->adjust($customer, Money::of(100, 'CZK'), 'Manuální korekce', $admin->id);

    $after = AdminActionLog::where('action', 'credit_adjustment')->count();
    expect($after)->toBe($before + 1);

    $log = AdminActionLog::where('action', 'credit_adjustment')->latest('id')->first();
    expect($log?->admin_user_id)->toBe($admin->id)
        ->and($log?->target_id)->toBe($customer->id);
});

// ── Impersonation triggers audit ──────────────────────────────────────────────

it('impersonate start logs an audit entry', function (): void {
    $admin   = adminUser();
    $user    = customerUser();

    $before = AdminActionLog::where('action', 'impersonate_start')->count();

    $this->actingAs($admin)
        ->get(route('admin.impersonate.start', $user))
        ->assertRedirect();

    $after = AdminActionLog::where('action', 'impersonate_start')->count();
    expect($after)->toBe($before + 1);
});

// ── Admin page ────────────────────────────────────────────────────────────────

it('admin can view audit trail page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.audit-trail.index'))
        ->assertOk()
        ->assertSee('Audit trail');
});

it('non-admin cannot view audit trail', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.audit-trail.index'))
        ->assertStatus(403);
});

it('audit trail can filter by admin_id', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.audit-trail.index', ['admin_id' => $admin->id]))
        ->assertOk();
});

it('audit trail can filter by action', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.audit-trail.index', ['action' => 'credit_adjustment']))
        ->assertOk();
});

it('audit trail shows logged entries', function (): void {
    $admin   = adminUser();
    $service = app(AdminAuditService::class);
    $service->log($admin, 'manual_payment', null, ['invoice_id' => 999]);

    $this->actingAs($admin)
        ->get(route('admin.audit-trail.index'))
        ->assertOk()
        ->assertSee('Ruční platba');
});
