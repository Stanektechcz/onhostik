<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;

/*
 * Game panel bootstrap (audit §5g-1): one run brings a panel into service — probe, nodes into the scheduler, catalogue
 * templates mapped onto the panel's eggs by name (community eggs the panel lacks are named for import), prerequisites,
 * a port range where a node has none, and the plan placement so every game order lands on the panel.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('bootstraps a game panel end to end and maps the catalogue templates by egg name', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $instance->forceFill(['options' => ['eggs' => ['cs2' => ['nest' => 9, 'egg' => 99]]]])->save(); // a stale manual mapping: the egg no longer exists → re-mapped
    $created = [];
    Http::fake(function (Request $request) use (&$created) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $m = $request->method();
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $egg = fn (int $id, string $name, bool $priv = false) => ['id' => $id, 'name' => $name, 'docker_image' => 'ghcr.io/x', 'docker_images' => [], 'startup' => 'x', 'config' => ['startup' => ['privileged' => $priv]]];

        return match (true) {
            str_ends_with($path, '/api/application/nodes') => $list([['id' => 2, 'name' => 'games01', 'memory' => 131072, 'disk' => 2048000, 'allocated_resources' => ['memory' => 0, 'disk' => 0], 'maintenance_mode' => false]], 'node'),
            str_ends_with($path, '/api/application/servers') => $list([], 'server'),
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft'], ['id' => 2, 'name' => 'Source Engine'], ['id' => 4, 'name' => 'Rust']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list([$egg(1, 'Vanilla Minecraft'), $egg(3, 'Paper'), $egg(4, 'Forge Minecraft'), $egg(5, 'Bungeecord')], 'egg'),
            str_ends_with($path, '/api/application/nests/2/eggs') => $list([$egg(7, 'Counter-Strike: Global Offensive'), $egg(8, 'ARK: Survival Evolved'), $egg(9, 'Team Fortress 2'), $egg(15, 'Counter-Strike 2')], 'egg'),
            str_ends_with($path, '/api/application/nests/4/eggs') => $list([$egg(14, 'Rust')], 'egg'),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $m === 'GET' => $list($created === [] ? [] : [['id' => 20, 'ip' => '203.0.113.10', 'alias' => null, 'port' => 25565, 'assigned' => false]], 'allocation'),
            str_ends_with($path, '/api/application/nodes/2/allocations') && $m === 'POST' => (function () use ($request, &$created) {
                $created[] = $request->data();

                return Http::response('', 204);
            })(),
            str_ends_with($path, '/api/client/account') => Http::response(['object' => 'user', 'attributes' => ['id' => 1, 'admin' => true]]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$m} {$path}"]]], 404),
        };
    });
    Node::query()->where('provider_instance_id', $instance->id)->update(['tags' => ['public_ipv4' => '203.0.113.10']]);

    $report = app(GamePanelBootstrap::class)->bootstrap($instance, CommandContext::system('test'));
    expect($report['errors'])->toBe([])->and($report['probe']['up'])->toBeTrue()->and($report['nodes'])->toBe(['games01']);
    expect($report['eggs']['mapped'])->toMatchArray(['minecraft-paper' => ['nest' => 1, 'egg' => 3, 'name' => 'Paper'], 'minecraft-forge' => ['nest' => 1, 'egg' => 4, 'name' => 'Forge Minecraft'], 'cs2' => ['nest' => 2, 'egg' => 15, 'name' => 'Counter-Strike 2'], 'rust' => ['nest' => 4, 'egg' => 14, 'name' => 'Rust'], 'ark' => ['nest' => 2, 'egg' => 8, 'name' => 'ARK: Survival Evolved']])
        ->and(array_keys($report['eggs']['unmapped']))->toBe(['valheim', 'palworld'])->and($report['eggs']['unmapped']['valheim'])->toContain('pelican-eggs');
    $instance->refresh();
    expect($instance->option('eggs.rust'))->toMatchArray(['nest' => 4, 'egg' => 14, 'environment' => ['WORLD_SIZE' => '3000', 'MAX_PLAYERS' => '50']])->and($instance->option('eggs.cs2.egg'))->toBe(15);
    // the node had no free port: the default range was created on its public address
    expect($created)->toHaveCount(1)->and($created[0]['ip'])->toBe('203.0.113.10')->and($created[0]['ports'])->toBe(['25565-25599'])->and($report['allocations'][0])->toMatchArray(['name' => 'games01', 'free' => 1]);
    expect($report['prerequisites']['client_api'])->toBe('ok');
    $placement = PlanPlacement::query()->where('product_key', 'game')->firstOrFail();
    expect($placement->provider_instance_id)->toBe($instance->id)->and($report['placement']['provider_instance_key'])->toBe('pterodactyl-games01');

    // a second run keeps the mappings (nothing re-mapped) and creates no ports; the console endpoints do the same through the bus
    $again = app(GamePanelBootstrap::class)->syncEggs($instance->fresh(), CommandContext::system('test'));
    expect($again['mapped'])->toBe([])->and($again['kept'])->toContain('rust')->toContain('cs2');
    $staff = $this->staff('infrastructure_admin');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->actingAs($staff, 'sanctum')->postJson("/v1/staff/integrations/{$instance->key}/game/eggs/sync", ['force' => true])->assertOk()->assertJsonPath('mapped.rust.egg', 14);
    $this->postJson("/v1/staff/integrations/{$instance->key}/game/bootstrap")->assertOk()->assertJsonPath('errors', [])->assertJsonPath('placement.product_key', 'game');
    expect($created)->toHaveCount(1); // the port range exists now: not created again
});

it('refuses to bootstrap without credentials and names the command to store them', function () {
    $instance = ProviderInstance::query()->create(['key' => 'pterodactyl-empty', 'provider' => 'pterodactyl', 'name' => 'Empty', 'base_url' => 'https://empty.panel.test', 'secret_ref' => 'db://provider_instances/pterodactyl-empty', 'state' => 'active', 'region_code' => null, 'capabilities' => [], 'options' => []]);
    $report = app(GamePanelBootstrap::class)->bootstrap($instance, CommandContext::system('test'));
    expect($report['errors'][0])->toContain('missing credentials: application_key')->toContain('onhost:integrations:secret pterodactyl-empty');
    $this->artisan('onhost:game:bootstrap', ['instance' => 'pterodactyl-empty'])->assertFailed();
});
