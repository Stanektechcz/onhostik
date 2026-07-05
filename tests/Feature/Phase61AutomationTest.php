<?php

declare(strict_types=1);

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Automation\Models\AutomationRuleLog;
use App\Domains\Automation\Services\AutomationEngine;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── AutomationRule model ───────────────────────────────────────────────────────

it('AutomationRule matches with no conditions (always true)', function (): void {
    $rule = AutomationRule::create([
        'name'       => 'No conditions',
        'trigger'    => 'ticket.created',
        'action'     => 'log_event',
        'conditions' => null,
        'is_active'  => true,
    ]);

    expect($rule->matches(['anything' => 'goes']))->toBeTrue();
});

it('AutomationRule matches = operator correctly', function (): void {
    $rule = new AutomationRule([
        'conditions' => [['field' => 'priority', 'operator' => '=', 'value' => 'urgent']],
    ]);

    expect($rule->matches(['priority' => 'urgent']))->toBeTrue()
        ->and($rule->matches(['priority' => 'normal']))->toBeFalse();
});

it('AutomationRule matches != operator', function (): void {
    $rule = new AutomationRule([
        'conditions' => [['field' => 'status', 'operator' => '!=', 'value' => 'closed']],
    ]);

    expect($rule->matches(['status' => 'open']))->toBeTrue()
        ->and($rule->matches(['status' => 'closed']))->toBeFalse();
});

it('AutomationRule matches contains operator', function (): void {
    $rule = new AutomationRule([
        'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'urgent']],
    ]);

    expect($rule->matches(['subject' => 'Urgent server issue']))->toBeTrue()
        ->and($rule->matches(['subject' => 'Normal question']))->toBeFalse();
});

it('AutomationRule matches in operator', function (): void {
    $rule = new AutomationRule([
        'conditions' => [['field' => 'status', 'operator' => 'in', 'value' => ['open', 'pending']]],
    ]);

    expect($rule->matches(['status' => 'open']))->toBeTrue()
        ->and($rule->matches(['status' => 'closed']))->toBeFalse();
});

it('AutomationRule with multiple conditions requires all to pass', function (): void {
    $rule = new AutomationRule([
        'conditions' => [
            ['field' => 'priority', 'operator' => '=', 'value' => 'urgent'],
            ['field' => 'status',   'operator' => '=', 'value' => 'open'],
        ],
    ]);

    expect($rule->matches(['priority' => 'urgent', 'status' => 'open']))->toBeTrue()
        ->and($rule->matches(['priority' => 'urgent', 'status' => 'closed']))->toBeFalse();
});

// ── AutomationRuleLog model ────────────────────────────────────────────────────

it('AutomationRuleLog has no updated_at', function (): void {
    $cols = Schema::getColumnListing('automation_rule_logs');
    expect($cols)->toContain('created_at')
        ->and($cols)->not->toContain('updated_at');
});

// ── AutomationEngine ───────────────────────────────────────────────────────────

it('AutomationEngine fires log_event action and logs outcome', function (): void {
    AutomationRule::create([
        'name'      => 'Log all tickets',
        'trigger'   => 'ticket.created',
        'action'    => 'log_event',
        'is_active' => true,
    ]);

    app(AutomationEngine::class)->fire('ticket.created', ['subject' => 'Hello']);

    expect(AutomationRuleLog::where('outcome', 'ok')->count())->toBe(1);
});

it('AutomationEngine skips inactive rules', function (): void {
    AutomationRule::create([
        'name'      => 'Disabled rule',
        'trigger'   => 'ticket.created',
        'action'    => 'log_event',
        'is_active' => false,
    ]);

    app(AutomationEngine::class)->fire('ticket.created', []);

    expect(AutomationRuleLog::count())->toBe(0);
});

it('AutomationEngine logs skip when conditions not met', function (): void {
    AutomationRule::create([
        'name'       => 'Urgent only',
        'trigger'    => 'ticket.created',
        'action'     => 'log_event',
        'conditions' => [['field' => 'priority', 'operator' => '=', 'value' => 'urgent']],
        'is_active'  => true,
    ]);

    app(AutomationEngine::class)->fire('ticket.created', ['priority' => 'normal']);

    expect(AutomationRuleLog::where('outcome', 'skipped')->count())->toBe(1)
        ->and(AutomationRuleLog::where('outcome', 'ok')->count())->toBe(0);
});

it('AutomationEngine increments run_count on success', function (): void {
    $rule = AutomationRule::create([
        'name'      => 'Counter test',
        'trigger'   => 'invoice.paid',
        'action'    => 'log_event',
        'is_active' => true,
    ]);

    app(AutomationEngine::class)->fire('invoice.paid', []);
    app(AutomationEngine::class)->fire('invoice.paid', []);

    expect($rule->fresh()->run_count)->toBe(2);
});

it('AutomationEngine does not affect rules with different triggers', function (): void {
    AutomationRule::create([
        'name'      => 'Invoice rule',
        'trigger'   => 'invoice.paid',
        'action'    => 'log_event',
        'is_active' => true,
    ]);

    app(AutomationEngine::class)->fire('ticket.created', []);

    expect(AutomationRuleLog::count())->toBe(0);
});

// ── Admin CRUD ─────────────────────────────────────────────────────────────────

it('admin can list automation rules', function (): void {
    $admin = adminUser();

    AutomationRule::create(['name' => 'Test rule', 'trigger' => 'ticket.created', 'action' => 'log_event', 'is_active' => true]);

    $this->actingAs($admin)
        ->get(route('admin.automation.index'))
        ->assertOk()
        ->assertSee('Test rule');
});

it('admin can create an automation rule', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.automation.store'), [
            'name'               => 'My rule',
            'trigger'            => 'ticket.created',
            'action'             => 'log_event',
            'is_active'          => '1',
            'conditions_json'    => '',
            'action_params_json' => '',
        ])
        ->assertRedirect(route('admin.automation.index'));

    expect(AutomationRule::where('name', 'My rule')->exists())->toBeTrue();
});

it('admin can toggle a rule active/inactive', function (): void {
    $admin = adminUser();
    $rule  = AutomationRule::create(['name' => 'Toggle me', 'trigger' => 'ticket.created', 'action' => 'log_event', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.automation.toggle', $rule))
        ->assertRedirect();

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('admin can delete a rule', function (): void {
    $admin = adminUser();
    $rule  = AutomationRule::create(['name' => 'Delete me', 'trigger' => 'ticket.created', 'action' => 'log_event', 'is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.automation.destroy', $rule))
        ->assertRedirect(route('admin.automation.index'));

    expect(AutomationRule::find($rule->id))->toBeNull();
});

it('admin can view rule logs', function (): void {
    $admin = adminUser();
    $rule  = AutomationRule::create(['name' => 'Log viewer', 'trigger' => 'ticket.created', 'action' => 'log_event', 'is_active' => true]);

    AutomationRuleLog::create(['automation_rule_id' => $rule->id, 'outcome' => 'ok', 'message' => 'Ran fine']);

    $this->actingAs($admin)
        ->get(route('admin.automation.logs', $rule))
        ->assertOk()
        ->assertSee('Ran fine');
});

it('non-admin cannot access automation routes', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.automation.index'))
        ->assertStatus(403);
});
