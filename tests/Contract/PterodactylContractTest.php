<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Pterodactyl\PterodactylGameProvider;

function pteroAdapter(): PterodactylGameProvider
{
    $_ENV['PTERODACTYL_GAMES01_APPLICATION_KEY'] = 'ptla_APPLICATIONKEY1234567890';
    $_ENV['PTERODACTYL_GAMES01_CLIENT_KEY'] = 'ptlc_CLIENTKEY1234567890';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'pterodactyl-games01'], ['provider' => 'pterodactyl', 'name' => 'Panel', 'base_url' => 'https://panel.test', 'secret_ref' => 'env://PTERODACTYL_GAMES01', 'state' => 'active']);
    $registry = app(ProviderRegistry::class);
    $registry->register('pterodactyl', PterodactylGameProvider::class);

    return $registry->forInstance($instance);
}

function egg(bool $privileged = false): array
{
    return ['object' => 'egg', 'attributes' => ['id' => 5, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21', 'docker_images' => ['Java 21' => 'ghcr.io/pterodactyl/yolks:java_21'], 'startup' => 'java -jar {{SERVER_JARFILE}}', 'config' => ['startup' => ['privileged' => $privileged]],
        'relationships' => ['variables' => ['data' => [['attributes' => ['env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar', 'rules' => 'required|string', 'user_editable' => true]], ['attributes' => ['env_variable' => 'MINECRAFT_VERSION', 'default_value' => 'latest', 'rules' => 'nullable|string', 'user_editable' => true]]]]]]];
}

it('creates a server with the external id, picks free allocations and waits for the install', function () {
    Http::fake([
        'panel.test/api/application/servers/external/*' => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => 'not found']]], 404),
        'panel.test/api/application/nests/1/eggs/5*' => Http::response(egg()),
        'panel.test/api/application/nodes/2/allocations*' => Http::response(['data' => [['attributes' => ['id' => 10, 'ip' => '89.187.160.10', 'port' => 25565, 'assigned' => true]], ['attributes' => ['id' => 11, 'ip' => '89.187.160.10', 'port' => 25566, 'assigned' => false, 'alias' => null]]], 'meta' => ['pagination' => ['total_pages' => 1]]]),
        'panel.test/api/application/servers' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'user' => 9, 'node' => 2, 'allocation' => 11, 'status' => 'installing', 'container' => ['installed' => 0]]], 201),
        'panel.test/api/application/servers/77' => Http::sequence()
            ->push(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'status' => 'installing', 'container' => ['installed' => 0]]])
            ->push(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'e4c1-uuid', 'identifier' => 'e4c1abcd', 'status' => null, 'container' => ['installed' => 1]]]),
    ]);
    $adapter = pteroAdapter();
    expect($adapter->freeAllocations(2))->toBe([['id' => 11, 'ip' => '89.187.160.10', 'port' => 25566, 'alias' => null]]);
    $result = $adapter->provision(new ResourceSpec('srv_g1', 'game_server', 'ord-9:provision.game:v1', ['nest_id' => 1, 'egg_id' => 5, 'ptero_user_id' => 9, 'allocation_id' => 11, 'name' => 'mc-liga', 'entitlements' => ['ram_mb' => 8192, 'nvme_gb' => 60, 'backups' => 5, 'allocations' => 2, 'databases' => 2], 'limits' => ['cpu_pct' => 300]]));
    expect($result->isAsync())->toBeTrue()->and($result->ref->meta['identifier'])->toBe('e4c1abcd');
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::RUNNING)->and($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::SUCCEEDED);
    Http::assertSent(fn ($r) => $r->url() === 'https://panel.test/api/application/servers' && $r['external_id'] === 'ord-9:provision.game:v1' && $r['limits']['memory'] === 8192 && $r['limits']['disk'] === 61440 && $r['environment']['SERVER_JARFILE'] === 'server.jar' && $r['allocation']['default'] === 11 && $r->hasHeader('Authorization', 'Bearer ptla_APPLICATIONKEY1234567890'));
    expect(DB::table('provider_calls')->where('action', 'servers.create')->value('request'))->not->toContain('APPLICATIONKEY');
});

it('refuses privileged eggs and maps 422/429 correctly', function () {
    Http::fake([
        'panel.test/api/application/servers/external/*' => Http::response(['errors' => []], 404),
        'panel.test/api/application/nests/1/eggs/5*' => Http::response(egg(true)),
        'panel.test/api/client/servers/e4c1abcd/power' => Http::response(['errors' => [['code' => 'TooManyRequestsHttpException', 'detail' => 'Too Many Attempts.']]], 429, ['Retry-After' => '12']),
    ]);
    $adapter = pteroAdapter();
    expect(fn () => $adapter->provision(new ResourceSpec('srv_g2', 'game_server', 'k', ['nest_id' => 1, 'egg_id' => 5, 'ptero_user_id' => 9, 'allocation_id' => 11])))->toThrow(ProviderException::class, 'privileged');
    try {
        $adapter->power(new ResourceRef('server', '77', '2', ['identifier' => 'e4c1abcd']), 'reboot');
        $this->fail('expected rate limit');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::RATE_LIMIT)->and($e->retryAfterSeconds)->toBe(12);
    }
});
