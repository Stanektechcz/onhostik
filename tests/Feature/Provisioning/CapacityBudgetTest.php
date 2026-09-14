<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CapacityBudget;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * The monthly budget cap on vendor node orders (audit §5q-5): the vendor's monthly price of every ordered node counts
 * against `ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR` (or the console's setting); an automatic order that would cross it
 * waits for a person, a person crossing it must override with a note, finance hears about it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function budgetPool(): ProviderInstance
{
    [$user, $org] = test()->customerWithOrganization();
    featureGameService($org, [], 77, 'e4c1abc0');
    featureGameService($org, [], 78, 'e4c1abc1');
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $hot = Node::query()->where('provider_instance_id', $instance->id)->where('name', 'games01')->firstOrFail();
    $hot->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 40, 'ram_used_mb' => 45000, 'disk_used_gb' => 100], 'last_seen_at' => now()])->save();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 16, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 1024, 'disk_used_gb' => 10], 'last_seen_at' => now()]);
    for ($h = 7 * 24; $h >= 1; $h--) {
        NodeUsageSample::query()->create(['node_id' => $hot->id, 'sampled_at' => now()->subHours($h), 'cpu_pct' => 40, 'ram_used_mb' => (int) round(30000 + (7 * 24 - $h) * (15000 / (7 * 24))), 'disk_used_gb' => 100, 'source' => 'snapshot']);
    }
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['node_order' => ['driver' => 'hetzner', 'secret_ref' => 'env://HETZNER_CZ1', 'image' => 'debian-12', 'location' => 'fsn1', 'ssh_keys' => ['ops'], 'user_data' => '#cloud-config']])])->save();
    app(SecretStore::class)->write(SecretRef::parse('env://HETZNER_CZ1'), ['token' => 'hz-test-token']);

    return $instance;
}

it('prices orders from the vendor catalogue, holds automatic orders over the cap and lets a person override it with a note', function () {
    $instance = budgetPool();
    $types = ['server_types' => [
        ['name' => 'cx22', 'cores' => 2, 'memory' => 4, 'disk' => 40, 'prices' => [['location' => 'fsn1', 'price_monthly' => ['net' => '3.2900', 'gross' => '3.9151']]]],
        ['name' => 'cx42', 'cores' => 16, 'memory' => 64, 'disk' => 1000, 'prices' => [['location' => 'nbg1', 'price_monthly' => ['net' => '31.0000']], ['location' => 'fsn1', 'price_monthly' => ['net' => '30.4900', 'gross' => '36.2831']]]],
        ['name' => 'cx52', 'cores' => 32, 'memory' => 128, 'disk' => 2000, 'deprecated' => true, 'prices' => []],
    ]];
    Http::fake([
        'https://api.hetzner.cloud/v1/server_types*' => Http::response($types, 200),
        'https://api.hetzner.cloud/v1/servers' => Http::sequence()
            ->push(['server' => ['id' => 4711, 'name' => 'cz1-game03', 'public_net' => ['ipv4' => ['ip' => '203.0.113.20']]]], 201)
            ->push(['server' => ['id' => 4712, 'name' => 'cz1-game04', 'public_net' => ['ipv4' => ['ip' => '203.0.113.21']]]], 201),
    ]);
    config()->set('onhost.provisioning.capacity_budget.monthly_minor', 5000); // 50 EUR a month
    $planner = app(CapacityPlanner::class);
    $budget = app(CapacityBudget::class);
    expect($budget->status())->toMatchArray(['monthly_minor' => 5000, 'currency' => 'EUR', 'spent_minor' => 0, 'remaining_minor' => 5000, 'orders' => 0, 'source' => 'config']);

    // the first order fits: the smallest fitting type is priced from the catalogue (the location's price) and counted
    $planner->run();
    $first = CapacityRequest::query()->firstOrFail();
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'bud-1')->postJson("/v1/staff/capacity/requests/{$first->id}/decide", ['decision' => 'approve'])->assertOk()->assertJsonPath('state', 'ordered')->assertJsonPath('cost.minor', 3049)->assertJsonPath('cost.currency', 'EUR');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.hetzner.cloud/v1/servers' && $r['server_type'] === 'cx42');
    expect($budget->status())->toMatchArray(['spent_minor' => 3049, 'remaining_minor' => 1951, 'orders' => 1]);
    expect($this->getJson('/v1/staff/capacity/requests?state=all')->assertOk()->json('budget.spent_minor'))->toBe(3049)->and($this->getJson('/v1/staff/capacity')->assertOk()->json('data.budget.remaining_minor'))->toBe(1951);

    // a second request the same month: the automatic rule holds it (finance hears), a person is refused without the override
    $second = CapacityRequest::query()->create(['role' => 'game', 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'state' => CapacityRequest::PROPOSED, 'wanted_ram_mb' => 65536, 'wanted_cpu_cores' => 16, 'wanted_disk_gb' => 1000, 'meta' => ['vendor' => 'hetzner']]);
    app(AutomationLedger::class)->setEnabled(CapacityPlanner::RULE, true, 'test');
    $planner->decide($second, 'approve', 'automatic: capacity.auto_order', CommandContext::system('capacity.auto_order'));
    $second->refresh();
    expect($second->state)->toBe(CapacityRequest::APPROVED)->and(data_get($second->meta, 'budget_hold.cost_minor'))->toBe(3049)->and(Node::query()->where('name', 'cz1-game04')->exists())->toBeFalse();
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('event', 'capacity.budget.exceeded')->firstOrFail();
    expect($note->title)->toBe('Rozpočet kapacity vyčerpán: game cz1')->and($note->severity)->toBe('hot')->and($note->body)->toContain('objednávka uzlu za 30,49 €')->toContain('limit 50 €')->and($note->kind)->toBe('finance');
    expect(CapacityPlanner::present($second->refresh())['budget_hold']['cost_minor'])->toBe(3049);
    $this->withHeader('Idempotency-Key', 'bud-2')->postJson("/v1/staff/capacity/requests/{$second->id}/decide", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'capacity_budget_exceeded')->assertJsonPath('cost_minor', 3049);
    $this->withHeader('Idempotency-Key', 'bud-3')->postJson("/v1/staff/capacity/requests/{$second->id}/decide", ['decision' => 'approve', 'override_budget' => true])->assertStatus(422); // the note names who approved the spend
    $this->withHeader('Idempotency-Key', 'bud-4')->postJson("/v1/staff/capacity/requests/{$second->id}/decide", ['decision' => 'approve', 'override_budget' => true, 'note' => 'schválila CFO 14. 9.'])->assertOk()->assertJsonPath('state', 'ordered')->assertJsonPath('node_name', 'cz1-game04')->assertJsonPath('budget_hold', null);
    expect(data_get($second->refresh()->meta, 'budget_override'))->toBeTrue()->and($budget->status())->toMatchArray(['spent_minor' => 6098, 'remaining_minor' => 0, 'orders' => 2]);

    // the console sets the cap (a setting over the config) and clears it back to the config; the next month starts from zero
    $this->withHeader('Idempotency-Key', 'bud-5')->putJson('/v1/staff/capacity/budget', ['monthly_minor' => 20000])->assertOk()->assertJsonPath('monthly_minor', 20000)->assertJsonPath('source', 'setting')->assertJsonPath('remaining_minor', 13902);
    $this->getJson('/v1/staff/capacity/budget')->assertOk()->assertJsonPath('data.monthly_minor', 20000);
    $this->withHeader('Idempotency-Key', 'bud-6')->putJson('/v1/staff/capacity/budget', ['monthly_minor' => null])->assertOk()->assertJsonPath('monthly_minor', 5000)->assertJsonPath('source', 'config');
    $this->travelTo(now()->addMonth()->startOfMonth()->addDay());
    expect($budget->status())->toMatchArray(['spent_minor' => 0, 'orders' => 0]);
    config()->set('onhost.provisioning.capacity_budget.monthly_minor', 0);
    expect($budget->status()['remaining_minor'])->toBeNull()->and($budget->allows(999999, 'EUR'))->toBeTrue();
    $this->actingAs($this->customerWithOrganization(['email' => 'x@y.cz'])[0], 'sanctum');
    $this->getJson('/v1/staff/capacity/budget')->assertForbidden();
});
