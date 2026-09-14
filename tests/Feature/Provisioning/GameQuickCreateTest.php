<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflows\ProvisionGameServerWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;

/*
 * Spigot on the game panel and the staff quick action (audit §5o): the Spigot template maps onto a Spigot egg when the
 * panel has one and onto the Paper egg (Spigot jar through DL_PATH) when it does not; `{version}` in the template follows
 * the version the order chose; staff create a game server for a customer without an order from the console or the terminal.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function fakeEggs(array $minecraftEggs): void
{
    Http::fake(function (Request $request) use ($minecraftEggs) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);
        $egg = fn (int $id, string $name) => ['id' => $id, 'name' => $name, 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => [], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => false]]];

        return match (true) {
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list(array_map(fn ($e) => $egg($e[0], $e[1]), $minecraftEggs), 'egg'),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
}

it('maps Spigot onto the Paper egg when the panel lacks it and resolves the version into the jar path', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();

    // no Spigot egg: Paper carries it, the preset's environment travels with the mapping
    fakeEggs([[1, 'Vanilla Minecraft'], [3, 'Paper']]);
    $report = app(GamePanelBootstrap::class)->syncEggs($instance, CommandContext::system('test'), true);
    expect($report['mapped']['minecraft-spigot'])->toBe(['nest' => 1, 'egg' => 3, 'name' => 'Paper', 'via_fallback' => true])->and($report['mapped']['minecraft-paper'])->toBe(['nest' => 1, 'egg' => 3, 'name' => 'Paper']);
    $mapped = $instance->refresh()->option('eggs.minecraft-spigot');
    expect($mapped)->toMatchArray(['nest' => 1, 'egg' => 3, 'via_fallback' => true])->and($mapped['environment']['SERVER_JARFILE'])->toBe('spigot-{version}.jar')->and($mapped['environment'])->not->toHaveKey('DL_PATH'); // §5q: no jar download site — the preset's startup builds Spigot with BuildTools on the first start
    expect((string) config('onhost.game.eggs.minecraft-spigot.startup'))->toBe('bash onhost-start.sh')->and((string) config('onhost.game.eggs.minecraft-spigot.startup_script'))->toContain('BuildTools.jar --rev "$VER"')->toContain('--final-name "$JAR"')->and(config('onhost.game.eggs.minecraft-spigot.docker_image'))->toBe('ghcr.io/pterodactyl/yolks:java_21');

    // the version chosen by the order lands in every `{version}` placeholder
    $env = ProvisionGameServerWorkflow::withVersion(['MINECRAFT_VERSION' => '1.21.8', 'SERVER_JARFILE' => 'spigot-{version}.jar', 'DL_PATH' => 'https://download.getbukkit.org/spigot/spigot-{version}.jar', 'BUILD_NUMBER' => 'latest']);
    expect($env)->toMatchArray(['SERVER_JARFILE' => 'spigot-1.21.8.jar', 'DL_PATH' => 'https://download.getbukkit.org/spigot/spigot-1.21.8.jar', 'MINECRAFT_VERSION' => '1.21.8']);
    expect(ProvisionGameServerWorkflow::withVersion(['SERVER_JARFILE' => 'spigot-{version}.jar'])['SERVER_JARFILE'])->toBe('spigot-latest.jar');
});

it('lets staff create a Spigot server for a customer without an order, from the console and the terminal', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'hrac@firma.cz'], ['name' => 'Herní klub s.r.o.']);
    featureGameService($org); // brings the lab panel instance, node and placement
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $instance->forceFill(['options' => ['eggs' => ['minecraft-spigot' => ['nest' => 1, 'egg' => 3, 'name' => 'Paper', 'via_fallback' => true, 'environment' => ['MINECRAFT_VERSION' => '1.21.8', 'SERVER_JARFILE' => 'spigot-{version}.jar', 'DL_PATH' => 'https://download.getbukkit.org/spigot/spigot-{version}.jar']]]]])->save();

    // the console's quick action: the same create path an order takes — a PAID service with a queued provisioning operation
    $this->actingAs($this->staff('game_admin'), 'sanctum');
    $created = $this->withHeader('Idempotency-Key', 'qc-1')->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'game', 'plan_key' => 'game-8', 'config' => ['egg' => 'minecraft-spigot', 'version' => '1.21.8', 'label' => 'Spigot liga', 'region' => 'cz1'], 'reason' => 'zkušební server'])->assertCreated()->json();
    expect($created['service'])->toMatchArray(['product_key' => 'game', 'label' => 'Spigot liga'])->and($created['operation_id'])->not->toBeNull()->and($created['operation_state'])->toBe(Operation::PENDING);
    $service = Service::query()->findOrFail($created['service']['id']);
    expect($service->desired_spec['egg'])->toBe('minecraft-spigot')->and($service->desired_spec['environment'])->toMatchArray(['MINECRAFT_VERSION' => '1.21.8'])->and($service->entitlements['ram_mb'])->toBe(8192)->and($service->organization_id)->toBe($org->id);
    expect(Operation::query()->where('service_id', $service->id)->value('kind'))->toBe('provision.game');
    $this->withHeader('Idempotency-Key', 'qc-2')->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'nope'])->assertNotFound();
    $this->withHeader('Idempotency-Key', 'qc-3')->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'game', 'plan_key' => 'game-999'])->assertNotFound();
    $this->actingAs($this->staff('support_agent'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'qc-4')->postJson("/v1/staff/customers/{$org->id}/services", ['product_key' => 'game', 'plan_key' => 'game-8'])->assertStatus(403);

    // the terminal twin: the organization by owner e-mail, the same command on the bus
    expect(Artisan::call('onhost:game:create', ['organization' => 'hrac@firma.cz', '--plan' => 'game-16', '--egg' => 'minecraft-spigot', '--game-version' => '1.21.8', '--label' => 'Spigot druhý']))->toBe(0);
    expect(Artisan::output())->toContain('Spigot druhý')->toContain('Herní klub s.r.o.')->toContain('operation ');
    expect(Service::query()->where('organization_id', $org->id)->where('label', 'Spigot druhý')->value('entitlements')['ram_mb'] ?? null)->toBe(16384);
    expect(Artisan::call('onhost:game:create', ['organization' => 'nobody@firma.cz']))->toBe(1);
});
