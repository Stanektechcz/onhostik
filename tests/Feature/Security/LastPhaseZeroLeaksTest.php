<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/*
 * TASK-0046 — the last Phase-0 leaks (permission program P0-08, P0-09; docs/runbooks/breach-register.md "Still open after
 * Phase 0 wave 2", production-readiness-audit row 133, onboarding audit response §4 "Security and authorization").
 *
 *  1. GET /v1/me is open to tokens and answered every current membership of the token's person with the full organization
 *     record (billing e-mail, company and VAT ids, address, settings, role): a token bound to organization A read B (PA-04).
 *  2. `archive.restore`: a global staff binding (`backup.read`/`backup.restore` of a staff role) passed
 *     ServiceArchiveService::assertMayRestore for every organization — on the CUSTOMER's routes, where a member of staff is the
 *     customer they act as. Staff restore on the platform's authority only in staff mode (/v1/staff), asked by StaffActor.
 *  3. The three secondary gates (spec apply, action hooks, Discord) asked `service.manage` for everything (audit §4); TASK-0029
 *     (22dae65) made them ask ServiceActionCommand::permissionFor. Kept from coming back here.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
});

// ── 1. GET /v1/me over a token ────────────────────────────────────────────────────────────────────────────────────────

/** A person in two organizations with distinct billing identities — B joined FIRST — and a token for A (or for none). @return array{0:User,1:Organization,2:Organization,3:string} */
function lpzlTwoOrganizations(bool $bound = true): array
{
    $user = User::factory()->create(['email' => 'lpzl-'.Str::lower(Str::random(6)).'@example.cz']);
    $b = app(OrganizationService::class)->create($user, ['name' => 'Beta Tajná s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK', 'billing_email' => 'fakturace@beta-tajna.cz'], CommandContext::system('test'));
    $b->forceFill(['ico' => '87654321', 'dic' => 'CZ87654321', 'street' => 'Tajná 1', 'city' => 'Brno'])->save();
    $a = app(OrganizationService::class)->create($user, ['name' => 'Alfa s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK', 'billing_email' => 'fakturace@alfa.cz'], CommandContext::system('test'));
    $token = $user->createToken('lpzl', TokenScopes::ALL);
    if ($bound) {
        $token->accessToken->forceFill(['organization_id' => $a->id])->save();
    }

    return [$user, $a, $b, $token->plainTextToken];
}

it('answers GET /v1/me over a token bound to one organization with that organization only (PA-04, P0-09)', function () {
    [, $a, $b, $plain] = lpzlTwoOrganizations();

    $me = $this->withToken($plain)->getJson('/v1/me')->assertOk()->json('data');

    expect(array_column($me['organizations'], 'id'))->toBe([$a->id])
        ->and($me['organization']['id'])->toBe($a->id)
        ->and($me['organization']['billing']['email'])->toBe('fakturace@alfa.cz'); // its own organization in full, as before
    $body = (string) json_encode($me, JSON_UNESCAPED_UNICODE);
    foreach ([$b->id, 'Beta Tajná', 'fakturace@beta-tajna.cz', '87654321', 'Tajná 1'] as $theirs) {
        expect(str_contains($body, $theirs))->toBeFalse("GET /v1/me over A's token still shows B: {$theirs}");
    }
});

it('answers GET /v1/me over a token bound to no organization with only the id and name of the other organizations', function () {
    [, $a, $b, $plain] = lpzlTwoOrganizations(bound: false);

    // no header: the person's first membership (B) is the one acted for, as every other route of an unbound token resolves it
    $me = $this->withToken($plain)->getJson('/v1/me')->assertOk()->json('data');
    expect($me['organization']['id'])->toBe($b->id);
    $byId = collect($me['organizations'])->keyBy('id');
    expect($byId->keys()->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and($byId[$a->id])->toBe(['id' => $a->id, 'name' => 'Alfa s.r.o.'])
        ->and($byId[$b->id]['billing']['email'])->toBe('fakturace@beta-tajna.cz');

    // naming A: A in full, B by name only
    app('auth')->forgetGuards();
    $me = $this->withToken($plain)->withHeader('X-Organization', $a->id)->getJson('/v1/me')->assertOk()->json('data');
    $byId = collect($me['organizations'])->keyBy('id');
    expect($me['organization']['id'])->toBe($a->id)
        ->and($byId[$b->id])->toBe(['id' => $b->id, 'name' => 'Beta Tajná s.r.o.'])
        ->and($byId[$a->id]['billing']['email'])->toBe('fakturace@alfa.cz');
});

it('keeps the full GET /v1/me for the portal session, which is the person and not a token', function () {
    [$user, $a, $b] = lpzlTwoOrganizations();

    $me = $this->actingAs($user, 'sanctum')->getJson('/v1/me')->assertOk()->json('data');

    $byId = collect($me['organizations'])->keyBy('id');
    expect($byId[$a->id]['billing']['email'])->toBe('fakturace@alfa.cz')
        ->and($byId[$b->id]['billing']['email'])->toBe('fakturace@beta-tajna.cz')
        ->and($byId[$b->id]['billing']['ico'])->toBe('87654321');
});

// ── 2. archive.restore through a global staff binding ─────────────────────────────────────────────────────────────────

function lpzlWebService(Organization $org, string $domain): Service
{
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-lpzl'], ['provider' => 'ispconfig', 'name' => 'ISPConfig lpzl', 'base_url' => 'https://lpzl.test:8080', 'secret_ref' => 'env://ISPCONFIG_LPZL', 'state' => 'active', 'options' => []]);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web '.$domain, 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => ['nvme_gb' => 50], 'desired_spec' => ['domain' => $domain, 'executor' => 'ispconfig'], 'sla_class' => 'standard', 'provider_instance_id' => $instance->id]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'web_domain', 'remote_id' => (string) random_int(100, 999), 'remote_node' => '1',
        'meta' => ['domain' => $domain, 'system_user' => 'web41'], 'idempotency_key' => 'lpzl-'.$service->id]);

    return $service->refresh();
}

/** The final archive of a web service that was then cancelled, and a new live service of the same organization to restore it into. @return array{0:Backup,1:Service} */
function lpzlArchiveAndTarget(Organization $org): array
{
    $old = lpzlWebService($org, 'stary-'.Str::lower(Str::random(4)).'.cz');
    $set = FinalArchive::PREFIX.'/'.$org->id.'/'.$old->id.'-20260901-120000';
    Storage::disk('local')->put($set.'/service.json', json_encode(['service' => ['id' => $old->id]]));
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('files', 200));
    Storage::disk('local')->put($set.'/manifest.json', json_encode(['service_id' => $old->id]));
    $archive = Backup::query()->create([
        'service_id' => $old->id, 'organization_id' => $org->id, 'kind' => 'final', 'state' => 'completed', 'protected' => true,
        'started_at' => now()->subDay(), 'finished_at' => now()->subDay(), 'verified_at' => now()->subDay(), 'verify_status' => 'ok', 'size_bytes' => 1024,
        'retention_until' => now()->addDays(60), 'immutable_until' => now()->addDays(60), 'meta' => ['set' => $set, 'family' => 'web', 'parts' => ['service.json', 'site-files.tar.gz'], 'gaps' => []],
    ]);
    $old->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();
    $old->delete();
    $target = lpzlWebService($org, 'novy-'.Str::lower(Str::random(4)).'.cz');
    lpzlPanel($target);

    return [$archive, $target];
}

/** The target's panel: every step of the restore answers (the question here is who may start one, not how it runs). */
function lpzlPanel(Service $service): void
{
    $transport = Mockery::mock(FileTransport::class)->shouldIgnoreMissing();
    $adapter = Mockery::mock(ProviderAdapter::class, InfrastructureProvider::class, WebHostingProvider::class, WebToolsProvider::class, BackupCapable::class)->shouldIgnoreMissing();
    $adapter->shouldReceive('siteFeatures')->andReturn(['backups' => true, 'restore' => true, 'backup_download' => true, 'backup_delete' => true, 'backup_on_demand' => false]);
    $adapter->shouldReceive('listDatabases')->andReturn([]);
    $adapter->shouldReceive('transport')->andReturn($transport);
    $adapter->shouldReceive('listBackups')->andReturn([]);
    $adapter->shouldReceive('getActualState')->andReturn(new ActualState(true, ['domain' => (string) $service->hostname, 'system_user' => 'web41'], 'active', now()->toISOString()));
    $registry = app(ProviderRegistry::class);
    $known = new ReflectionProperty($registry, 'instances');
    $known->setValue($registry, [(string) $service->provider_instance_id => $adapter] + (array) $known->getValue($registry));
}

/** Both customer doors to one archive restore: the archive endpoint and the generic action endpoint. @return list<Closure(): Illuminate\Testing\TestResponse> */
function lpzlCustomerDoors(object $test, Organization $org, Backup $archive, Service $target): array
{
    return [
        fn () => $test->withHeaders(['Idempotency-Key' => (string) Str::ulid(), 'X-Organization' => $org->id])->postJson("/v1/services/archives/{$archive->id}/restore", ['service_id' => $target->id]),
        fn () => $test->withHeaders(['Idempotency-Key' => (string) Str::ulid(), 'X-Organization' => $org->id])->postJson("/v1/services/{$target->id}/actions", ['action' => 'archive.restore', 'params' => ['backup_id' => $archive->id]]),
    ];
}

it('refuses on the customer routes an archive restore that only a global staff binding allows (IF-4 archive.restore, P0-08)', function () {
    [, $org] = $this->customerWithOrganization();
    [$archive, $target] = lpzlArchiveAndTarget($org);
    $backupAdmin = $this->steppedUpStaff('backup_dr_admin'); // backup.read + backup.restore globally, a member of nothing

    foreach (lpzlCustomerDoors($this, $org, $archive, $target) as $door) {
        $this->actingAs($backupAdmin, 'sanctum');
        expect($door()->status())->toBeIn([403, 404]);
        app('auth')->forgetGuards();
        $this->flushHeaders();
    }
    expect(Operation::query()->where('service_id', $target->id)->count())->toBe(0)
        ->and(data_get($archive->fresh()->meta, 'download.waived'))->toBeNull(); // nor did the attempt make the download free
});

it('lets a member of staff restore an archive in staff mode on the platform\'s authority, and a member of the organization as its member', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$archive, $target] = lpzlArchiveAndTarget($org);

    // the staff path of Phase 0: /v1/staff, a staff role that carries the restore
    $backupAdmin = $this->steppedUpStaff('backup_dr_admin');
    $this->actingAs($backupAdmin, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/staff/services/{$target->id}/actions", ['action' => 'archive.restore', 'params' => ['backup_id' => $archive->id], 'reason' => 'ticket 4711'])->assertStatus(202);
    app('auth')->forgetGuards();
    $this->flushHeaders();

    // a member of staff who is ALSO the organization's admin restores there as that admin, on the customer route
    [$archive2, $target2] = lpzlArchiveAndTarget($org);
    $staffMember = $this->steppedUpStaff('auditor_read_only');
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $staffMember->id, 'state' => 'active', 'role_key' => 'org_admin', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $staffMember->id, 'role_key' => 'org_admin', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    $this->actingAs($staffMember, 'sanctum');
    lpzlCustomerDoors($this, $org, $archive2, $target2)[0]()->assertOk()->assertJsonPath('service_id', $target2->id); // the archive endpoint answers 200 with the operation
    app('auth')->forgetGuards();
    $this->flushHeaders();

    // and the owner, as always
    [$archive3, $target3] = lpzlArchiveAndTarget($org);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    lpzlCustomerDoors($this, $org, $archive3, $target3)[1]()->assertStatus(202);
});

it('refuses a staff-mode archive restore to a member of staff whose staff role carries no restore', function () {
    [, $org] = $this->customerWithOrganization();
    [$archive, $target] = lpzlArchiveAndTarget($org);
    $support = $this->steppedUpStaff('support_l2'); // staff.service.manage, no backup key
    $this->actingAs($support, 'sanctum')->withHeader('Idempotency-Key', (string) Str::ulid())
        ->postJson("/v1/staff/services/{$target->id}/actions", ['action' => 'archive.restore', 'params' => ['backup_id' => $archive->id], 'reason' => 'ticket 4712'])->assertForbidden();
    expect(Operation::query()->where('service_id', $target->id)->count())->toBe(0);
});

// ── 3. the secondary gates ask the action map ────────────────────────────────────────────────────────────────────────

it('keeps the spec apply, action hook and Discord gates on the action map, never on a hard-coded service.manage (audit §4)', function () {
    foreach (['domains/Services/ServiceSpecService.php', 'domains/Integrations/ActionHookService.php', 'domains/Integrations/DiscordService.php'] as $file) {
        $literals = array_filter(token_get_all((string) file_get_contents(base_path($file))), fn ($t) => is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && trim($t[1], '\'"') === 'service.manage');
        expect($literals)->toBeEmpty("{$file} asks a hard-coded service.manage again: ask ServiceActionCommand::permissionFor");
        expect((string) file_get_contents(base_path($file)))->toContain('ServiceActionCommand');
    }
});
