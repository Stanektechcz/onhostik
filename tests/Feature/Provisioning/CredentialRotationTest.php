<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/*
 * Rotating the access to a panel (Brain card H314). A new key is tried against the panel before it replaces the one
 * that works; a key the panel refuses changes nothing. An access belongs to one instance: the stored secret of
 * another instance cannot be pointed at this one. The game panel's two keys are verified each for itself.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
});

function rotationStored(string $key): array
{
    return app(SecretStore::class)->read(SecretRef::parse("db://provider_instances/{$key}"));
}

/** A Proxmox that accepts only tokens whose secret starts with `good-secret-`. */
function rotationPveFake(): void
{
    Http::fake(function (Request $request) {
        $authorized = str_contains((string) $request->header('Authorization')[0], '=good-secret-');
        if (! $authorized) {
            return Http::response(['errors' => 'authentication failure'], 401);
        }
        if (str_ends_with($request->url(), '/api2/json/version')) {
            return Http::response(['data' => ['version' => '8.2.4', 'release' => '8.2']]);
        }

        return Http::response(['data' => [['type' => 'cluster', 'quorate' => 1]]]);
    });
}

it('tries a new access against the panel first and keeps the working one when the panel refuses it', function () {
    rotationPveFake();
    $base = ['key' => 'proxmox-rot', 'provider' => 'proxmox', 'base_url' => 'https://pve-rot.onhost.internal:8006', 'region_code' => 'cz1', 'options' => ['verify_tls' => false]];

    // the first access has no working one to protect: it is stored as given and proven by the probe afterwards
    $this->postJson('/v1/staff/integrations', $base + ['credentials' => ['token_id' => 'onhost@pve!cp', 'token_secret' => 'good-secret-1']])->assertCreated();
    expect(Http::recorded())->toHaveCount(0);
    expect($this->postJson('/v1/staff/integrations/proxmox-rot/probe')->assertOk()->json('up'))->toBeTrue();

    // a mistyped secret: the panel says no, nothing is stored, the instance keeps working
    $refused = $this->putJson('/v1/staff/integrations/proxmox-rot', $base + ['credentials' => ['token_secret' => 'typo-secret']])
        ->assertUnprocessable()->assertJsonPath('error', 'instance_credentials_unverified')->assertJsonPath('credential_keys', ['token_secret']);
    expect($refused->getContent())->not->toContain('typo-secret');
    expect(rotationStored('proxmox-rot')['token_secret'])->toBe('good-secret-1');
    expect($this->postJson('/v1/staff/integrations/proxmox-rot/probe')->assertOk()->json('up'))->toBeTrue();
    // the refusal is on record (the command bus writes it; the handler's own rows roll back with the transaction), without the value
    $failed = AuditEvent::query()->where('result', 'failed')->get()->first(fn (AuditEvent $e) => str_contains((string) json_encode($e->toArray()), 'instance_credentials_unverified'));
    expect($failed)->not->toBeNull()->and((string) json_encode($failed->toArray()))->not->toContain('typo-secret')->not->toContain('good-secret-1');

    // the same values again are not a rotation: the panel is not asked
    $sent = count(Http::recorded());
    $this->putJson('/v1/staff/integrations/proxmox-rot', $base + ['credentials' => ['token_secret' => 'good-secret-1']])->assertCreated();
    expect(count(Http::recorded()))->toBe($sent);

    // a secret the panel accepts replaces the old one
    $this->putJson('/v1/staff/integrations/proxmox-rot', $base + ['credentials' => ['token_secret' => 'good-secret-2']])->assertCreated();
    expect(rotationStored('proxmox-rot'))->toMatchArray(['token_id' => 'onhost@pve!cp', 'token_secret' => 'good-secret-2']);
    expect($this->postJson('/v1/staff/integrations/proxmox-rot/probe')->assertOk()->json('up'))->toBeTrue();

    // a compromised access has to go even when the panel cannot confirm the new one: forced, and recorded as forced
    $this->putJson('/v1/staff/integrations/proxmox-rot', $base + ['credentials' => ['token_secret' => 'unconfirmed-secret'], 'force_credentials' => true])->assertCreated();
    expect(rotationStored('proxmox-rot')['token_secret'])->toBe('unconfirmed-secret');
    $forced = AuditEvent::query()->where('action', 'provider.instance.update')->orderByDesc('created_at')->orderByDesc('id')->get()->first(fn (AuditEvent $e) => str_contains(json_encode($e->toArray()), '"credentials_forced":true'));
    expect($forced)->not->toBeNull();
});

it('gives the terminal the same answer: a refused value is not stored, --force stores it and says so', function () {
    rotationPveFake();
    $this->postJson('/v1/staff/integrations', ['key' => 'proxmox-cli', 'provider' => 'proxmox', 'base_url' => 'https://pve-cli.onhost.internal:8006', 'region_code' => 'cz1', 'options' => ['verify_tls' => false], 'credentials' => ['token_id' => 'onhost@pve!cp', 'token_secret' => 'good-secret-1']])->assertCreated();
    $prompt = 'Value for token_secret (blank keeps the stored value)';

    $this->artisan('onhost:integrations:secret', ['instance' => 'proxmox-cli', 'key' => 'token_secret'])->expectsQuestion($prompt, 'typo-secret')
        ->expectsOutputToContain('Nothing was stored')->assertExitCode(1);
    expect(rotationStored('proxmox-cli')['token_secret'])->toBe('good-secret-1');
    // outside the command bus the refusal is the service's own audit row: which keys, why, never the value
    $row = AuditEvent::query()->where('action', 'provider.instance.credentials.rotate')->where('result', 'failed')->firstOrFail();
    expect((string) json_encode($row->toArray()))->toContain('token_secret')->not->toContain('typo-secret');

    $this->artisan('onhost:integrations:secret', ['instance' => 'proxmox-cli', 'key' => 'token_secret'])->expectsQuestion($prompt, 'good-secret-2')->assertExitCode(0);
    expect(rotationStored('proxmox-cli')['token_secret'])->toBe('good-secret-2');

    $this->artisan('onhost:integrations:secret', ['instance' => 'proxmox-cli', 'key' => 'token_secret', '--force' => true])->expectsQuestion($prompt, 'unconfirmed-secret')->assertExitCode(0);
    expect(rotationStored('proxmox-cli')['token_secret'])->toBe('unconfirmed-secret');
});

it('never lets one instance use the stored access of another, whatever the two are called', function () {
    Http::fake(['*' => Http::response(['data' => ['version' => '8.2.4']])]);
    $a = ['key' => 'proxmox-a', 'provider' => 'proxmox', 'base_url' => 'https://pve-a.onhost.internal:8006', 'region_code' => 'cz1', 'credentials' => ['token_id' => 'onhost@pve!a', 'token_secret' => 'secret-of-a']];
    $this->postJson('/v1/staff/integrations', $a)->assertCreated();

    // a second instance, a look-alike key, pointed at the first one's secret: its credentials would travel to another host
    $b = ['key' => 'proxmox-a2', 'provider' => 'proxmox', 'base_url' => 'https://pve-elsewhere.example.net:8006', 'region_code' => 'cz1', 'secret_ref' => 'db://provider_instances/proxmox-a'];
    $this->postJson('/v1/staff/integrations', $b)->assertUnprocessable()->assertJsonPath('error', 'instance_secret_ref_foreign');
    expect(ProviderInstance::query()->where('key', 'proxmox-a2')->exists())->toBeFalse();

    // and an existing instance cannot be re-pointed either
    $this->postJson('/v1/staff/integrations', ['key' => 'proxmox-b', 'provider' => 'proxmox', 'base_url' => 'https://pve-b.onhost.internal:8006', 'region_code' => 'cz1', 'credentials' => ['token_id' => 'onhost@pve!b', 'token_secret' => 'secret-of-b']])->assertCreated();
    $this->putJson('/v1/staff/integrations/proxmox-b', ['key' => 'proxmox-b', 'provider' => 'proxmox', 'base_url' => 'https://pve-b.onhost.internal:8006', 'secret_ref' => 'db://provider_instances/proxmox-a'])->assertUnprocessable()->assertJsonPath('error', 'instance_secret_ref_foreign');
    expect(ProviderInstance::query()->where('key', 'proxmox-b')->value('secret_ref'))->toBe('db://provider_instances/proxmox-b')
        ->and(rotationStored('proxmox-b')['token_secret'])->toBe('secret-of-b');
});

it('verifies the two keys of the game panel each for itself', function () {
    Http::fake(function (Request $request) {
        $bearer = (string) $request->header('Authorization')[0];
        if (str_contains($request->url(), '/api/application/')) {
            return $bearer === 'Bearer ptla_good' ? Http::response(['data' => [['attributes' => ['id' => 1, 'name' => 'node-1', 'memory' => 65536, 'disk' => 500000, 'maintenance_mode' => false, 'allocated_resources' => ['memory' => 0, 'disk' => 0]]]], 'meta' => ['pagination' => ['total_pages' => 1, 'current_page' => 1]]]) : Http::response(['errors' => [['code' => 'AuthenticationException']]], 401);
        }

        return in_array($bearer, ['Bearer ptlc_good', 'Bearer ptlc_new'], true) ? Http::response(['attributes' => ['id' => 7, 'email' => 'svc@onhost.cz']]) : Http::response(['errors' => [['code' => 'AuthenticationException']]], 401);
    });
    $base = ['key' => 'ptero-rot', 'provider' => 'pterodactyl', 'base_url' => 'https://gamepanel-rot.onhost.internal', 'region_code' => 'cz1'];
    $this->postJson('/v1/staff/integrations', $base + ['credentials' => ['application_key' => 'ptla_good', 'client_key' => 'ptlc_good']])->assertCreated();

    // the application key still works, so the panel is "up" — but the new client key is refused: it must not be stored
    $this->putJson('/v1/staff/integrations/ptero-rot', $base + ['credentials' => ['client_key' => 'ptlc_wrong']])
        ->assertUnprocessable()->assertJsonPath('error', 'instance_credentials_unverified')->assertJsonPath('credential_keys', ['client_key']);
    expect(rotationStored('ptero-rot'))->toMatchArray(['application_key' => 'ptla_good', 'client_key' => 'ptlc_good']);

    $this->putJson('/v1/staff/integrations/ptero-rot', $base + ['credentials' => ['client_key' => 'ptlc_new']])->assertCreated();
    expect(rotationStored('ptero-rot'))->toMatchArray(['application_key' => 'ptla_good', 'client_key' => 'ptlc_new']);
});
