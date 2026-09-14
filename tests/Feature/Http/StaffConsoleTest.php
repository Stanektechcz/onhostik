<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/*
 * Staff console data (audit §5f-2): the game panel view (nodes, templates with the catalogue mapping, allocations,
 * servers with their services, the provisioning queue), template mapping and port ranges through the bus, the
 * automation rules with their last runs, the renewals ahead and the scheduler — all staff-only.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('shows game panels with nodes, templates, allocations and servers, maps templates and creates port ranges through the bus', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $created = [];
    Http::fake(function (Request $request) use (&$created) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);

        return match (true) {
            str_ends_with($path, '/api/application/nodes') => $list([['id' => 2, 'name' => 'games01', 'memory' => 131072, 'disk' => 2048000, 'allocated_resources' => ['memory' => 8192, 'disk' => 61440], 'maintenance_mode' => false]], 'node'),
            str_ends_with($path, '/api/application/servers') => $list([['id' => 77, 'identifier' => 'e4c1abcd', 'uuid' => 'e4c1-uuid', 'external_id' => 'ord-1', 'name' => 'mc-liga', 'node' => 2, 'user' => 9, 'egg' => 5, 'suspended' => false, 'container' => ['installed' => 1], 'status' => null, 'limits' => ['memory' => 8192, 'disk' => 61440, 'cpu' => 300], 'allocation' => 11]], 'server'),
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list([['id' => 5, 'name' => 'Paper', 'docker_image' => 'x', 'docker_images' => [], 'startup' => 'java', 'config' => ['startup' => ['privileged' => false]]], ['id' => 6, 'name' => 'Forge', 'docker_image' => 'x', 'docker_images' => [], 'startup' => 'java', 'config' => ['startup' => ['privileged' => false]]]], 'egg'),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $request->method() === 'GET' => $list([['id' => 11, 'ip' => '89.187.160.10', 'alias' => null, 'port' => 25566, 'assigned' => true], ['id' => 12, 'ip' => '89.187.160.10', 'alias' => null, 'port' => 25567, 'assigned' => false]], 'allocation'),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $request->method() === 'POST' => (function () use ($request, &$created) {
                $created[] = $request->data();

                return Http::response('', 204);
            })(),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => 'no fake']]], 404),
        };
    });
    $this->actingAs($user, 'sanctum')->getJson('/v1/staff/game')->assertForbidden();
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');

    $game = $this->getJson('/v1/staff/game')->assertOk()->json('data');
    $panel = $game['instances'][0];
    expect($panel['key'])->toBe('pterodactyl-games01')->and($panel['nodes'][0])->toMatchArray(['id' => 2, 'name' => 'games01', 'servers' => 1, 'memory_pct' => 6, 'scheduler_state' => 'active'])
        ->and($panel['allocations'][0])->toMatchArray(['node' => 2, 'total' => 2, 'free' => 1, 'ips' => ['89.187.160.10']])
        ->and($panel['eggs'][0])->toMatchArray(['id' => 5, 'name' => 'Paper', 'servers' => 1, 'mapped_as' => ['minecraft-paper']])->and($panel['eggs'][1]['mapped_as'])->toBe([])
        ->and($panel['servers'][0]['service'])->toMatchArray(['id' => $service->id, 'label' => 'mc-liga', 'organization' => 'Test s.r.o.']);
    expect(json_encode($game))->not->toContain('CLIENTKEY')->not->toContain('APPLICATIONKEY');

    // mapping a template and creating a port range are bus commands: step-up, audit, the instance's options change
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->putJson("/v1/staff/integrations/{$instance->key}/game/eggs", ['key' => 'minecraft-forge', 'nest' => 1, 'egg' => 6])->assertOk()->assertJsonPath('eggs.minecraft-forge.egg', 6);
    expect($instance->fresh()->option('eggs.minecraft-forge'))->toBe(['nest' => 1, 'egg' => 6]);
    $this->putJson("/v1/staff/integrations/{$instance->key}/game/eggs", ['key' => 'minecraft-forge', 'remove' => true])->assertOk();
    expect($instance->fresh()->option('eggs'))->not->toHaveKey('minecraft-forge');
    $this->putJson("/v1/staff/integrations/{$instance->key}/game/eggs", ['key' => 'bad key!', 'nest' => 1, 'egg' => 6])->assertStatus(422);
    $this->postJson("/v1/staff/integrations/{$instance->key}/game/allocations", ['node' => 2, 'ip' => '89.187.160.10', 'ports' => ['25570-25579', 'nope', '25600']])->assertCreated()->assertJsonPath('ports', ['25570-25579', '25600']);
    expect($created[0]['ports'])->toBe(['25570-25579', '25600']);
    $this->postJson("/v1/staff/integrations/{$instance->key}/game/allocations", ['node' => 2, 'ip' => 'not-an-ip', 'ports' => ['25570']])->assertStatus(422);
});

it('lists the automation rules with their last runs, the renewals ahead with credit coverage, and the scheduler', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    $service = featureWebService($org, 'aapanel');
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'month');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $standard['version']->id, 'price_id' => $standard['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 18900,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(3), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    app(WalletService::class)->topup($org, Money::decimal('100', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    app(AutomationLedger::class)->record('usage.watch', ['checked' => 12, 'warned' => 1, 'critical' => 0, 'upgraded' => 0, 'errors' => 0]);
    $service->forceFill(['tags' => ['policy' => ['auto_upgrade' => true], 'usage' => ['level' => 'warn']]])->save();

    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $rules = collect($this->getJson('/v1/staff/automation')->assertOk()->json('data'))->keyBy('key');
    expect($rules->get('usage.watch')['last']['stats']['checked'])->toBe(12)->and($rules->get('usage.watch')['now'])->toMatchArray(['auto_upgrade' => 1, 'warn' => 1])
        ->and($rules->get('renewal.guard')['last'])->toBeNull()->and($rules->get('order.risk')['now']['hold_score'])->toBe(60)->and($rules->count())->toBe(count(AutomationLedger::RULES));

    $renewals = $this->getJson('/v1/staff/renewals?days=14')->assertOk()->json('data');
    expect($renewals)->toHaveCount(1)->and($renewals[0])->toMatchArray(['kind' => 'subscription', 'organization' => 'Test s.r.o.', 'service' => 'shop.cz', 'auto_renew' => true, 'covered' => false])->and($renewals[0]['amount']['minor'])->toBe(18900);

    $jobs = $this->getJson('/v1/staff/jobs')->assertOk()->json('data');
    $usage = collect($jobs['scheduler'])->first(fn ($e) => str_starts_with($e['command'], 'onhost:services:usage-watch'));
    expect($usage)->not->toBeNull()->and($usage['expression'])->toBe('50 * * * *')->and($usage['last']['stats']['checked'])->toBe(12)->and($jobs['queue'])->toHaveKeys(['pending', 'running', 'waiting']);
    $this->actingAs($owner, 'sanctum')->getJson('/v1/staff/automation')->assertForbidden();
});
