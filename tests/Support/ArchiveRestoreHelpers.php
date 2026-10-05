<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * Fixtures shared by the archive restore tests (ArchiveRestoreScopeTest, ArchiveRestoreKeyTest). They used to live in the
 * first file, so the second failed when run alone or in another parallel worker.
 *
 * Required by the test files that use them (not autoloaded): `require_once __DIR__.'/../../Support/ArchiveRestoreHelpers.php';`.
 * Every function name starts with `ars` — Pest files share one global function namespace.
 */

/** A finished final archive on the backup disk of a cancelled web service. */
function arsArchive(Service $source): Backup
{
    $set = FinalArchive::PREFIX.'/'.$source->organization_id.'/'.$source->id.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', json_encode(['service' => ['id' => $source->id]]));
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('files', 200));
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $source->id]));

    return Backup::query()->create([
        'service_id' => $source->id, 'organization_id' => $source->organization_id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => 'web', 'parts' => ['service.json', 'site-files.tar.gz'], 'gaps' => []],
    ]);
}

/** A number nobody used before in this process: random e-mail names and remote ids collided now and then on a unique key. */
function arsNextNumber(): int
{
    static $next = 0;

    return ++$next;
}

/** Remote ids start at 5000, clear of the fixed ones the tests write by hand (1042), so a long process never meets them. */
function arsNextRemoteId(): string
{
    return (string) (5000 + arsNextNumber());
}

function arsWebService(Organization $org, string $domain, ?string $projectId = null): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-ars'], ['provider' => 'ispconfig', 'name' => 'ISPConfig ars', 'base_url' => 'https://ars.test:8080', 'secret_ref' => 'env://ISPCONFIG_ARS', 'state' => 'active', 'options' => []]);
    $service = Service::query()->create(['organization_id' => $org->id, 'project_id' => $projectId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web '.$domain, 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => $domain, 'executor' => 'ispconfig'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'web_domain', 'remote_id' => arsNextRemoteId(), 'remote_node' => '1',
        'meta' => ['domain' => $domain, 'system_user' => 'web41'], 'idempotency_key' => 'ars-'.$service->id]);

    return $service->refresh();
}

function arsCancelled(Service $service): void
{
    $service->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
    $service->delete();
}

/** A person with one binding, the shape an invitation (organization/project) or a share (resource) writes. */
function arsPerson(Organization $org, string $role, string $scopeType, ?string $scopeId): User
{
    $user = User::query()->create(['email' => 'person'.arsNextNumber().'@ars.test', 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    $member = $scopeType === 'organization' ? $role : 'guest';
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $member, 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $member, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    if ($scopeType !== 'organization') {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'organization_id' => $org->id]);
    }
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1'); // a restore asks for a fresh step-up; the question here is the scope

    return $user;
}
