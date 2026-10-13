<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Platform\OperationsOverview;
use Onhost\Domain\Platform\ScheduleHealth;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Ops\PlatformBackup;
use Onhost\Platform\Ops\RestoreDrill;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;

/*
 * P16 (phase R): the staff page "Provoz" — a read-only shift overview from the sources the doctor and /metrics read. Each section
 * shows only to whoever holds its permission, the rest is named as hidden; somebody holding none is refused. Only counts, states,
 * times and the names of the platform's own jobs, nodes and panels leave it: no customer, no provider error text, no secret.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC')); // first (TASK-0192)
});

/** A platform with something to look at in every section. */
function opsOverviewWorld(): array
{
    // automation: every job ran just now, except the outbox relay — an hour ago against a budget of minutes, and it failed twice since
    foreach (array_keys(app(ScheduleHealth::class)->budgets()) as $command) {
        DB::table('scheduled_job_runs')->insert(['command' => $command, 'last_success_at' => now(), 'created_at' => now()->subDay(), 'updated_at' => now()]);
    }
    DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->update(['last_success_at' => now()->subHour(), 'failures_in_a_row' => 2]);
    // payments: one unmatched bank line, one open dunning case
    BankStatementLine::query()->create(['account' => 'CZ00', 'amount_minor' => 12100, 'currency' => 'CZK', 'variable_symbol' => '2026000001', 'counterparty' => 'Jana Nováková', 'booked_at' => now(), 'external_id' => 'ops-1', 'state' => 'unmatched']);
    // infrastructure: a panel held on an unverified version whose last probe error carries a secret, a node waiting, an open breaker
    $panel = ProviderInstance::query()->create(['key' => 'ispconfig-ops', 'provider' => 'ispconfig', 'name' => 'ISPConfig ops', 'base_url' => 'https://isp.mgmt.test:8080', 'secret_ref' => 'env://ISP_OPS', 'state' => 'active',
        'version_gate' => ['state' => 'held', 'version' => '3.3.1', 'previous' => '3.2.12']]);
    IntegrationHealth::query()->create(['provider_instance_id' => $panel->id, 'up' => false, 'last_error' => 'login failed for remote-user:hunter2', 'checked_at' => now()]);
    Node::query()->create(['provider_instance_id' => $panel->id, 'name' => 'web-new-01', 'region_code' => 'cz1', 'role' => 'web', 'state' => Node::QUALIFYING, 'capacity' => [], 'usage' => []]);
    app(ProviderHttpClient::class)->breaker('ispconfig-ops')->trip();

    return ['panel' => $panel];
}

it('shows the platform owner every section, from the doctor\'s sources, read only and without a secret or a customer', function () {
    opsOverviewWorld();
    $this->actingAs($this->staff('platform_owner'), 'sanctum');

    $response = $this->getJson('/v1/staff/operations/overview')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $data = $response->json('data');
    expect(array_keys($data['sections']))->toBe(['automation', 'backups', 'payments', 'infrastructure'])->and($data['hidden'])->toBe([]);

    $automation = $data['sections']['automation'];
    expect($automation['scheduled_jobs']['count'])->toBe(count(app(ScheduleHealth::class)->budgets()))
        ->and($automation['scheduled_jobs']['overdue'])->toBe([['command' => 'onhost:outbox:relay', 'age_seconds' => 3600, 'budget_seconds' => app(ScheduleHealth::class)->budgets()['onhost:outbox:relay'], 'failures_in_a_row' => 2]])->and($automation['scheduled_jobs']['overdue_count'])->toBe(1)
        ->and($automation)->toHaveKeys(['scheduler', 'worker', 'backlog', 'dead_letters', 'failed_jobs']);
    expect($data['sections']['payments'])->toMatchArray(['bank' => ['last_line_at' => now()->toIso8601String(), 'unmatched_lines' => 1], 'dunning' => ['open' => 0, 'by_state' => []]])
        ->and($data['sections']['backups']['platform'])->toHaveKeys(['disk', 'last_set', 'verified_at', 'verified_within_26h']);
    $infra = $data['sections']['infrastructure'];
    expect($infra['panels_held'])->toBe(['count' => 1, 'panels' => [['instance' => 'ispconfig-ops', 'provider' => 'ispconfig', 'version' => '3.3.1']]])
        ->and($infra['nodes_qualifying']['count'])->toBe(1)->and($infra['nodes_qualifying']['nodes'][0])->toMatchArray(['name' => 'web-new-01', 'role' => 'web', 'region' => 'cz1'])
        ->and($infra['breakers_open']['instances'])->toBe([['instance' => 'ispconfig-ops', 'provider' => 'ispconfig', 'state' => 'open']])
        ->and($infra['certificates'])->toBe(['expiring_7d' => 0, 'expired' => 0]);

    $body = (string) $response->getContent();
    expect($body)->not->toContain('hunter2')->not->toContain('remote-user')->not->toContain('env://')->not->toContain('Nováková')->not->toContain('2026000001');
});

it('shows each section only to whoever may read it and names the rest as hidden', function (string $role, array $shown) {
    opsOverviewWorld();
    $this->actingAs($this->staff($role), 'sanctum');
    $data = $this->getJson('/v1/staff/operations/overview')->assertOk()->json('data');

    expect(array_keys($data['sections']))->toBe($shown)
        ->and($data['hidden'])->toBe(array_values(array_diff(array_keys(OperationsOverview::SECTIONS), $shown)));
})->with([
    'SRE' => ['sre', ['automation', 'infrastructure']],
    'backup and DR' => ['backup_dr_admin', ['automation', 'backups', 'infrastructure']],
    'billing operator' => ['billing_operator', ['payments']],
    'support manager' => ['support_manager', ['automation']],
]);

it('refuses staff without any of its permissions, customers and anonymous callers', function () {
    $this->actingAs($this->staff('sales'), 'sanctum');
    $this->getJson('/v1/staff/operations/overview')->assertForbidden();

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->getJson('/v1/staff/operations/overview')->assertForbidden();

    app('auth')->forgetGuards();
    $this->getJson('/v1/staff/operations/overview')->assertUnauthorized();
});

it('opens the page "Provoz" like its navigation item', function () {
    $this->actingAs($this->staff('sre'));
    $this->get('/sprava/nastaveni/provozni-prehled')->assertOk()->assertSee('Provoz: přehled směny')->assertSee('/v1/staff/operations/overview', false);

    $this->actingAs($this->staff('sales'));
    $this->get('/sprava/nastaveni/provozni-prehled')->assertForbidden();
});

it('shows the newest restore drill of the platform backup (P13) without its problem text', function () {
    Storage::fake('backups');
    config()->set('onhost.platform_backup.disk', 'backups');
    config()->set('onhost.platform_backup.files_root', storage_path('framework/testing/no-private-files'));
    $this->actingAs($this->staff('backup_dr_admin'), 'sanctum');
    expect($this->getJson('/v1/staff/operations/overview')->assertOk()->json('data.sections.backups.drill'))->toMatchArray(['at' => null, 'within_35_days' => false]);

    $set = app(PlatformBackup::class)->run()['set'];
    $counts = app(PlatformBackup::class)->counts();
    app(RestoreDrill::class)->record($set, ['migrations' => $counts['migrations'], 'users' => $counts['users'], 'invoices' => $counts['invoices'], 'ledger_postings' => $counts['ledger_postings'],
        'ledger_imbalance' => 3, 'unbalanced_transactions' => 1], 40, 'onhost_drill_20261020100000');
    $response = $this->getJson('/v1/staff/operations/overview')->assertOk();
    expect($response->json('data.sections.backups.drill'))->toMatchArray(['set' => $set, 'ok' => false, 'within_35_days' => true, 'problems' => 2, 'seconds' => 40])
        ->and((string) $response->getContent())->not->toContain('out of balance')->not->toContain('differ by');
});
