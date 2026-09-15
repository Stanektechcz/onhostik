<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Provisioning\GameConfigurator;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * What a game template needs before a server exists (audit §5s): the egg sync records the required variables without a
 * default; passwords are generated, the customer's inputs (a Steam token) are asked for by the order and checked by
 * the quote, operator-held values (a Steam account) come from the secret store or the template is not offered; the
 * RAM floor holds at the quote; a template the panel lost leaves the offer and operations hear about it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** A panel with Paper, CS2 (token + RCON password) and DayZ (operator Steam account); `$gone` drops CS2 from the panel. */
function templatesPanelFake(bool &$gone): void
{
    Http::fake(function (Request $request) use (&$gone) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $egg = fn (int $id, string $name) => ['id' => $id, 'name' => $name, 'docker_image' => 'ghcr.io/x', 'docker_images' => [], 'startup' => 'x', 'config' => ['startup' => ['privileged' => false]]];
        $variable = fn (string $env, string $default, string $rules, bool $editable = true) => ['object' => 'egg_variable', 'attributes' => ['env_variable' => $env, 'default_value' => $default, 'rules' => $rules, 'user_editable' => $editable]];
        $detail = fn (int $id, string $name, array $vars) => Http::response(['object' => 'egg', 'attributes' => $egg($id, $name) + ['relationships' => ['variables' => ['object' => 'list', 'data' => $vars]]]]);

        return match (true) {
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft'], ['id' => 5, 'name' => 'Onhost Gamehosting']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list([$egg(1, 'Paper')], 'egg'),
            str_ends_with($path, '/api/application/nests/5/eggs') => $list(array_values(array_filter([$gone ? null : $egg(17, 'Counter-Strike 2'), $egg(18, 'DayZ')])), 'egg'),
            str_ends_with($path, '/api/application/nests/1/eggs/1') => $detail(1, 'Paper', [$variable('MINECRAFT_VERSION', 'latest', 'required|string|max:20')]),
            str_ends_with($path, '/api/application/nests/5/eggs/17') => $detail(17, 'Counter-Strike 2', [$variable('STEAM_GSLT', '', 'required|string|alpha_num|size:32'), $variable('RCON_PASSWORD', '', 'required|alpha_dash|between:1,30'), $variable('MAP', 'de_dust2', 'required|string')]),
            str_ends_with($path, '/api/application/nests/5/eggs/18') => $detail(18, 'DayZ', [$variable('STEAM_USER', '', 'required|string|not_in:anonymous', false), $variable('STEAM_PASS', '', 'required|string', false)]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
}

it('sorts template requirements, gates the offer and the quote, fills passwords and operator values, and drops a template the panel lost', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $instance->forceFill(['options' => ['eggs' => []]])->save();
    $gone = false;
    templatesPanelFake($gone);

    $sync = app(GamePanelBootstrap::class)->syncEggs($instance, CommandContext::system('test'));
    expect(array_keys($sync['mapped']))->toContain('minecraft-paper')->toContain('cs2')->toContain('dayz');
    $templates = app(GameTemplates::class);
    expect($templates->requirements('minecraft-paper'))->toBe([])
        ->and($templates->requirements('cs2'))->toBe([['env' => 'STEAM_GSLT', 'rules' => 'required|string|alpha_num|size:32', 'editable' => true, 'kind' => 'input'], ['env' => 'RCON_PASSWORD', 'rules' => 'required|alpha_dash|between:1,30', 'editable' => true, 'kind' => 'generated']])
        ->and($templates->inputs('cs2'))->toBe([['env' => 'STEAM_GSLT', 'rules' => 'required|string|alpha_num|size:32']]);

    // availability: DayZ waits for the operator's Steam account, Valheim is not on the panel, CS2 and Paper are fine
    expect($templates->availability('dayz'))->toBe(['available' => false, 'reason' => 'operator_variables_missing'])
        ->and($templates->availability('valheim')['reason'])->toBe('not_on_panel')->and($templates->availability('cs2')['available'])->toBeTrue()->and($templates->availability('nope')['reason'])->toBe('unknown_template');
    $offer = collect((fn () => $this->panelCatalog('cs'))->call(app(SurfaceDataController::class)))->firstWhere('key', 'game');
    expect(collect($offer['eggs'])->pluck('key')->all())->toContain('cs2')->toContain('minecraft-paper')->not->toContain('dayz')->not->toContain('valheim')
        ->and(collect($offer['eggs'])->firstWhere('key', 'cs2')['inputs'][0]['env'])->toBe('STEAM_GSLT');

    // the quote: unknown / unavailable template, RAM floor, missing or malformed input
    $product = Product::query()->where('key', 'game')->firstOrFail();
    $refused = function (array $config, array $ent = ['ram_mb' => 8192]) use ($templates, $product) {
        try {
            $templates->assertOrderable($product, $config, $ent);
        } catch (DomainError $e) {
            return [$e->error, $e->extra];
        }

        return null;
    };
    expect($refused(['egg' => 'ark'])[0])->toBe('game_template_unknown')
        ->and($refused(['egg' => 'dayz'])[0])->toBe('game_template_unavailable')
        ->and($refused(['egg' => 'cs2'], ['ram_mb' => 2048]))->toMatchArray([0 => 'game_template_ram_too_low', 1 => ['field' => 'items', 'template' => 'cs2', 'min_ram_mb' => 4096, 'plan_ram_mb' => 2048]])
        ->and($refused(['egg' => 'cs2'])[0])->toBe('game_template_input_required')
        ->and($refused(['egg' => 'cs2', 'environment' => ['STEAM_GSLT' => 'short']])[1]['inputs'][0]['env'])->toBe('STEAM_GSLT')
        ->and($refused(['egg' => 'cs2', 'environment' => ['STEAM_GSLT' => str_repeat('A1', 16)]]))->toBeNull()
        ->and($refused(['egg' => 'minecraft-paper']))->toBeNull();

    // provisioning fills a rule-shaped password and keeps what the customer typed; operator values once stored
    $env = $templates->fill('cs2', ['STEAM_GSLT' => str_repeat('B2', 16)]);
    expect($env['STEAM_GSLT'])->toBe(str_repeat('B2', 16))->and(GameTemplates::satisfies((string) $env['RCON_PASSWORD'], 'required|alpha_dash|between:1,30'))->toBeTrue()->and(strlen((string) $env['RCON_PASSWORD']))->toBe(20);
    expect(strlen(GameTemplates::secretFor('required|string|max:20|min:8')))->toBe(20)->and(strlen(GameTemplates::secretFor('required|size:32')))->toBe(32)->and(strlen(GameTemplates::secretFor('max:12')))->toBe(12);
    app(SecretStore::class)->write(SecretRef::parse('db://game/operator-variables'), ['STEAM_USER' => 'onhost-steam', 'STEAM_PASS' => 'x-secret']);
    $fresh = app()->make(GameTemplates::class);
    expect($fresh->availability('dayz')['available'])->toBeTrue()->and($fresh->fill('dayz', []))->toBe(['STEAM_USER' => 'onhost-steam', 'STEAM_PASS' => 'x-secret']);

    // drift: CS2 disappears from the panel → mapping dropped, operations told, no longer offered
    $gone = true;
    $report = $fresh->verify($instance->fresh(), CommandContext::system('test'));
    expect($report['missing'])->toBe(['cs2'])->and($report['refreshed'])->toBe(3)->and($instance->fresh()->option('eggs.cs2'))->toBeNull();
    expect(app()->make(GameTemplates::class)->availability('cs2')['reason'])->toBe('not_on_panel');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'game.template.missing')->first()?->title)->toContain('cs2');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-order.api.js')))->toContain('chosen.inputs');
});

it('prices the game configurator per game from its floors and validates the configured sizes in the quote (audit §5v)', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 1], 'terraria' => ['nest' => 2, 'egg' => 5], 'cs2' => ['nest' => 5, 'egg' => 17]]]])->save();
    $cfg = app(GameConfigurator::class);
    expect(Plan::query()->whereIn('key', ['game-4', 'game-64'])->exists())->toBeFalse();

    // from-prices: base 49 Kč covers 1 GB / 1 vCPU / 10 GB; Paper needs 2 GB (+35), CS2 4 GB, 2 vCPU, 60 GB (+105 +60 +75)
    expect($cfg->minimumOptions('minecraft-paper'))->toMatchArray(['ram_gb' => 2, 'vcpu' => 1, 'nvme_gb' => 10])
        ->and($cfg->fromPrice('minecraft-paper')->toDecimal())->toBe('84.00')->and($cfg->fromPrice('terraria')->toDecimal())->toBe('49.00')
        ->and($cfg->fromPrice('cs2')->toDecimal())->toBe('289.00')
        ->and($cfg->price('minecraft-paper', ['ram_gb' => 8, 'vcpu' => 2, 'nvme_gb' => 35, 'databases' => 1])->toDecimal())->toBe('418.00') // 49 + 7×35 + 60 + 30×1.5 (35 GB snaps to 40) + 19
        ->and($cfg->clamp('cs2', ['ram_gb' => 1, 'vcpu' => 99]))->toMatchArray(['ram_gb' => 4, 'vcpu' => 8])
        ->and(app(GameTemplates::class)->slots('minecraft-paper', 4096))->toBe(27);
    $offer = $cfg->offer('cs');
    expect(collect($offer['games'])->pluck('key')->all())->toBe(['minecraft-paper', 'cs2', 'terraria'])->and(collect($offer['options'])->pluck('key')->all())->toBe(['ram_gb', 'vcpu', 'nvme_gb', 'backups', 'allocations', 'databases']);

    // the quote clamps to the floors and prices like the configurator; a fixed plan ignores the sliders
    $quote = app(QuoteService::class)->quote([['product_key' => 'game', 'plan_key' => 'game-custom', 'config' => ['egg' => 'minecraft-paper', 'options' => ['ram_gb' => 1, 'vcpu' => 2]]]], 'CZK', ['country' => 'CZ']);
    $line = $quote['lines'][0];
    expect($line['config']['options'])->toMatchArray(['ram_gb' => 2, 'vcpu' => 2])->and($line['unit_net'])->toBe(14400);
    $fixed = app(QuoteService::class)->quote([['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-paper', 'options' => ['ram_gb' => 1]]]], 'CZK', ['country' => 'CZ']);
    expect($fixed['lines'][0]['unit_net'])->toBe(34900);

    // provisioning: the sliders become the entitlements (GB → MB) and the CPU limit follows the vCPU
    $version = Plan::query()->where('key', 'game-custom')->firstOrFail()->versions()->first();
    $ent = app(ServiceService::class)->entitlementsFor($version, ['ram_gb' => 6, 'vcpu' => 3, 'nvme_gb' => 40], Product::query()->where('key', 'game')->firstOrFail());
    expect($ent)->toMatchArray(['ram_mb' => 6144, 'vcpu' => 3, 'nvme_gb' => 40]);
});

it('groups the game offer (one Minecraft Java card with server types), lists every game on the home page and serves the artwork from our origin (audit §5w)', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail()->forceFill(['options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 1], 'minecraft-bungeecord' => ['nest' => 1, 'egg' => 2], 'minecraft-bedrock' => ['nest' => 1, 'egg' => 30], 'cs2' => ['nest' => 5, 'egg' => 17]]]])->save();
    $offer = app(GameConfigurator::class)->offer('cs');
    $groups = collect($offer['groups'])->keyBy('key');
    expect($groups->keys()->all())->toBe(['minecraft-java', 'minecraft-bedrock', 'cs2'])
        ->and($groups['minecraft-java']['label'])->toBe('Minecraft Java Edition')->and($groups['minecraft-java']['eggs'])->toBe(['minecraft-paper', 'minecraft-bungeecord'])
        ->and($groups['minecraft-java']['from'])->toBe(49.0)->and($groups['minecraft-java']['art'])->toBeNull()
        ->and($groups['cs2']['art'])->toBe('/surfaces/game-art/cs2.jpg')->and($groups['cs2']['category'])->toBe('action')
        ->and(collect($offer['games'])->firstWhere('key', 'minecraft-bungeecord')['variant'])->toBe('BungeeCord')
        ->and(collect($offer['categories'])->pluck('key')->all())->toBe(['minecraft', 'action']);

    $rows = collect((fn () => $this->catalogRows('cs'))->call(app(SurfaceDataController::class)))->where('key', 'game');
    expect($rows->where('sub', true)->pluck('cs')->map(fn ($c) => $c[0])->values()->all())->toBe(['Minecraft Java Edition', 'Minecraft Bedrock Edition', 'Counter-Strike 2'])
        ->and($rows->firstWhere('sub', null)['cs'][0])->toBe('Herní servery')->and($rows->firstWhere('group', 'cs2')['egg'])->toBe('cs2');

    Storage::fake('local');
    Http::fake(['cdn.cloudflare.steamstatic.com/*' => Http::response("\xFF\xD8".str_repeat('x', 2048), 200, ['Content-Type' => 'image/jpeg'])]);
    $this->get('/surfaces/game-art/cs2.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->get('/surfaces/game-art/cs2.jpg')->assertOk(); // the second request is served from the disk
    Http::assertSentCount(1);
    $this->get('/surfaces/game-art/minecraft-java.jpg')->assertNotFound(); // no artwork source: the page draws its own tile
    $this->get('/surfaces/game-art/nope.jpg')->assertNotFound();
});
