<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\GameServerPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePreset(array $overrides = []): GameServerPreset
{
    return GameServerPreset::create(array_merge([
        'name'               => 'Minecraft Java Edition',
        'game_slug'          => 'minecraft-java',
        'nest_id'            => 1,
        'egg_id'             => 2,
        'default_memory_mb'  => 2048,
        'default_disk_mb'    => 10240,
        'default_cpu_limit'  => 200,
        'default_swap_mb'    => 0,
        'default_io_weight'  => 500,
        'is_active'          => true,
        'sort_order'         => 0,
    ], $overrides));
}

it('stores a preset with basic resource defaults', function (): void {
    $preset = makePreset();

    expect($preset->name)->toBe('Minecraft Java Edition')
        ->and($preset->game_slug)->toBe('minecraft-java')
        ->and($preset->nest_id)->toBe(1)
        ->and($preset->egg_id)->toBe(2)
        ->and($preset->default_memory_mb)->toBe(2048)
        ->and($preset->is_active)->toBeTrue();
});

it('game_slug must be unique', function (): void {
    makePreset(['game_slug' => 'cs2']);

    expect(fn () => makePreset(['game_slug' => 'cs2']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('toResourceArray includes all required Pterodactyl fields', function (): void {
    $preset = makePreset([
        'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
        'startup'      => 'java -jar server.jar',
        'environment'  => ['SERVER_JARFILE' => 'server.jar', 'MC_VERSION' => 'latest'],
    ]);

    $resources = $preset->toResourceArray();

    expect($resources)->toHaveKey('nest_id')
        ->toHaveKey('egg_id')
        ->toHaveKey('memory_mb')
        ->toHaveKey('disk_mb')
        ->toHaveKey('cpu_limit')
        ->toHaveKey('docker_image')
        ->toHaveKey('startup')
        ->toHaveKey('environment');

    expect($resources['memory_mb'])->toBe(2048)
        ->and($resources['docker_image'])->toBe('ghcr.io/pterodactyl/yolks:java_21')
        ->and($resources['environment'])->toBe(['SERVER_JARFILE' => 'server.jar', 'MC_VERSION' => 'latest']);
});

it('toResourceArray omits docker_image and startup when null', function (): void {
    $preset = makePreset(['docker_image' => null, 'startup' => null]);

    $resources = $preset->toResourceArray();

    expect($resources)->not->toHaveKey('docker_image')
        ->not->toHaveKey('startup');
});

it('toResourceArray omits environment when empty', function (): void {
    $preset = makePreset(['environment' => null]);

    $resources = $preset->toResourceArray();

    expect($resources)->not->toHaveKey('environment');
});

it('environment is cast as array', function (): void {
    $preset = makePreset(['environment' => ['KEY' => 'value', 'NUM' => '42']]);

    $preset->refresh();

    expect($preset->environment)->toBeArray()
        ->toHaveKey('KEY')
        ->toHaveKey('NUM');
});

it('is_active defaults to true', function (): void {
    $preset = GameServerPreset::create([
        'name'              => 'Valheim',
        'game_slug'         => 'valheim',
        'nest_id'           => 1,
        'egg_id'            => 5,
        'default_memory_mb' => 4096,
        'default_disk_mb'   => 20480,
        'default_cpu_limit' => 100,
    ]);

    expect($preset->is_active)->toBeTrue();
});
