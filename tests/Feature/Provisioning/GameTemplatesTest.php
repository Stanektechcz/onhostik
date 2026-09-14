<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
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
