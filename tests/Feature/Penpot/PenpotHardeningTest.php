<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflows\ProvisionPenpotWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PenpotAccessCommand;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Penpot\PenpotCompose;
use Onhost\Providers\Penpot\PenpotDockerProvider;

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * TASK-0123 security review of PR #119 (M1–M4, L): what the first round left open, each proven here before the fix.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

/** A guest of the organization holding one resource role on the service (the shape ServiceAccessService::bind writes). */
function penpotGuest(Organization $org, Service $service, string $role): User
{
    $user = User::query()->create(['email' => str_replace('_', '-', $role).'@penpot-guest.test', 'name' => $role, 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    $member = str_starts_with($role, 'svc_') ? 'guest' : $role;
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'state' => 'active', 'role_key' => $member, 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $member, 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    if ($member === 'guest') {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'resource', 'scope_id' => $service->id, 'organization_id' => $org->id]);
    }

    return $user;
}

it('M1: never puts an owner password — the throwaway one or the customer\'s — on a command line', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization(['email' => 'm1@studio.test']);
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $stack = PenpotInstances::stackName($service);

    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $chosen = implode('-', ['Vlastni', 'Penpot', 'Heslo', '77']);
    $id = $this->actingAs($user, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => $chosen], ['X-Organization' => $org->id, 'Idempotency-Key' => 'm1-pw'])->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);

    // both passwords went to the node as a file of the stack's own 0750 directory, made 0600 before anything was written into it
    $file = "/srv/onhost-penpot/{$stack}/.owner-password";
    $passwords = array_map(fn (array $w) => rtrim($w[1], "\n"), array_values(array_filter(PenpotFilesDouble::$history, fn (array $w) => $w[0] === $file)));
    expect($passwords)->toHaveCount(2)->and($passwords[1])->toBe($chosen)->and(strlen($passwords[0]))->toBeGreaterThanOrEqual(32);
    expect($node->matching("install -m 0600 /dev/null '{$file}'"))->toHaveCount(2);
    foreach ($node->commands as $command) {
        foreach ($passwords as $password) {
            expect(str_contains($command, $password))->toBeFalse('a password reached a command line');
        }
        expect($command)->not->toContain('--password');
    }
    // the file is read by manage.py on stdin and removed in the same command, whatever the answer
    foreach (array_merge($node->matching('create-profile'), $node->matching('update-profile')) as $command) {
        expect($command)->toContain("< '{$file}'")->toContain("rm -f '{$file}'");
    }
});

it('M2: hardens every container, refuses a node short of disk, and runs the operator\'s storage quota', function () {
    $compose = PenpotCompose::compose('penpot-ab12cd34ef', ['ram_mb' => 4096, 'cpus' => 2]);
    expect(substr_count($compose, 'no-new-privileges:true'))->toBe(5)->and(substr_count($compose, 'pids_limit:'))->toBe(5)->and(substr_count($compose, "cap_drop:\n      - ALL"))->toBe(5);
    // the Penpot images run as the user `penpot`: no capability at all; PostgreSQL and Valkey switch to their own user at start
    expect($compose)->toContain("cap_add:\n      - CHOWN\n      - DAC_OVERRIDE\n      - FOWNER\n      - SETGID\n      - SETUID")
        ->and(substr_count($compose, 'cap_add:'))->toBe(2);

    $node = penpotLab();
    $node->freeKb = 10 * 1024 * 1024; // 10 GB free, the plan wants 20 GB plus the headroom
    [$user, $org] = $this->customerWithOrganization();
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    expect($service->state)->toBe(ServiceStateMachine::FAILED)->and($node->matching(' up -d'))->toBe([])
        ->and((string) (Operation::query()->where('service_id', $service->id)->firstOrFail()->error['message'] ?? ''))->toContain('disk');

    // with the operator's quota helper configured, every new stack gets its quota right after it was made
    $node2 = penpotLab();
    ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail()->forceFill(['options' => array_replace((array) ProviderInstance::query()->where('key', 'penpot-cz1')->value('options'), ['quota_command' => 'sudo /usr/local/sbin/onhost-penpot-quota'])])->save();
    app(ProviderRegistry::class)->forget(ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail()); // the adapter is built again with the new options and node
    [$user2, $org2] = $this->customerWithOrganization(['email' => 'm2@studio.test']);
    $ok = penpotProvisioned($org2, $this->contextFor($user2, $org2));
    expect($ok->state)->toBe(ServiceStateMachine::ACTIVE)->and($node2->matching("sudo /usr/local/sbin/onhost-penpot-quota '".PenpotInstances::stackName($ok)."' '20'"))->toHaveCount(1);
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['every Penpot node limits the storage of a stack']['ok'])->toBeTrue();
    ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail()->forceFill(['options' => array_diff_key((array) ProviderInstance::query()->where('key', 'penpot-cz1')->value('options'), ['quota_command' => 1])])->save();
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['every Penpot node limits the storage of a stack']['ok'])->toBeFalse()->and($rows['every Penpot node limits the storage of a stack']['detail'])->toContain('penpot-cz1');
});

it('M3: lets the owner, an organization admin or a console holder set the Penpot password — never a svc_manage guest', function () {
    penpotLab();
    [$owner, $org] = $this->customerWithOrganization();
    $service = penpotProvisioned($org, $this->contextFor($owner, $org));
    $scope = CommandScope::resource($service->id, $service->organization_id, $service->project_id);
    expect(PenpotAccessCommand::PERMISSION)->toBe('service.console');

    $manage = penpotGuest($org, $service, 'svc_manage');
    app(StepUpService::class)->grant($manage, 'totp', null, '127.0.0.1');
    $this->actingAs($manage, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => implode('-', ['Svc', 'Manage', 'Heslo', '1'])], ['X-Organization' => $org->id, 'Idempotency-Key' => 'm3-manage'])
        ->assertForbidden();
    $this->actingAs($manage, 'sanctum')->getJson("/v1/services/{$service->id}/penpot", ['X-Organization' => $org->id])->assertOk(); // the card is still theirs to read

    foreach (['svc_console', 'org_admin'] as $role) {
        $person = penpotGuest($org, $service, $role);
        expect(app(Authorizer::class)->can($person, PenpotAccessCommand::PERMISSION, $scope))->toBeTrue($role);
    }
    expect(app(Authorizer::class)->can($owner, PenpotAccessCommand::PERMISSION, $scope))->toBeTrue();
    $console = User::query()->where('email', 'svc-console@penpot-guest.test')->firstOrFail();
    app(StepUpService::class)->grant($console, 'totp', null, '127.0.0.1');
    $this->actingAs($console, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => implode('-', ['Svc', 'Console', 'Heslo', '1'])], ['X-Organization' => $org->id, 'Idempotency-Key' => 'm3-console'])->assertStatus(202);
});

it('M4: refuses a Penpot node without the SSH host key fingerprint, and the doctor names it', function () {
    $node = penpotLab();
    $instances = app(ProviderInstanceService::class);
    $ctx = CommandContext::system('test');
    $input = ['key' => 'penpot-cz2', 'provider' => 'penpot', 'name' => 'Penpot cz2', 'region_code' => 'cz1', 'base_url' => 'https://penpot02.example.test', 'options' => ['ssh_host' => '198.51.100.21']];

    try {
        $instances->upsert($input, $ctx);
        $this->fail('a Penpot node without a host key fingerprint was registered');
    } catch (DomainError $e) {
        expect($e->error)->toBe('instance_ssh_fingerprint_required')->and($e->extra['field'])->toBe('options.ssh_fingerprint');
    }
    expect(ProviderInstance::query()->where('key', 'penpot-cz2')->exists())->toBeFalse();
    $made = $instances->upsert(array_replace_recursive($input, ['options' => ['ssh_fingerprint' => 'SHA256:'.str_repeat('B', 43)]]), $ctx);
    expect($made->provider)->toBe('penpot')->and($made->supports('penpot.stack'))->toBeTrue();

    // one that got in another way is refused by the adapter before any SSH, and the doctor says which
    ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail()->forceFill(['options' => ['ssh_host' => '198.51.100.20']])->save();
    app(ProviderRegistry::class)->forget(ProviderInstance::query()->where('key', 'penpot-cz1')->firstOrFail());
    $health = app(ProviderRegistry::class)->forKey('penpot-cz1')->health();
    expect($health->healthy)->toBeFalse()->and((string) $health->error)->toContain('fingerprint')->and($node->commands)->toBe([]);
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['every Penpot node pins its SSH host key']['ok'])->toBeFalse()->and($rows['every Penpot node pins its SSH host key']['detail'])->toContain('penpot-cz1');
});

it('L: allocates the port under a node lock, never resets the password of a running instance, and pins the images', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization(['email' => 'l@studio.test']);
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $stack = PenpotInstances::stackName($service);
    $alloc = $node->matching('.ports.lock');
    expect($alloc)->toHaveCount(1)->and($alloc[0])->toContain('flock -w 60 9')->and($alloc[0])->toContain("/{$stack}/.port");
    expect(array_filter(PenpotFilesDouble::$history, fn (array $w) => str_ends_with($w[0], '/.port')))->toBe([]); // the port file is the lock holder's, not an SFTP write

    // the owner set a password; a provisioning run again over the ACTIVE service (repair) leaves the account alone
    $tags = (array) $service->tags;
    $tags['penpot']['owner_password_set'] = true;
    $service->forceFill(['tags' => $tags])->save();
    $before = count($node->commands);
    $again = app(OperationService::class)->start(ProvisionPenpotWorkflow::class, 'repair:'.$service->id, array_merge((array) $service->desired_spec, ['service_id' => $service->id]), CommandContext::system('test'), $service->id, $service->organization_id, null, $service->provider_instance_id);
    $again = driveOperation($again);
    expect($again->state)->toBe(Operation::SUCCEEDED, (string) json_encode($again->error));
    $after = array_slice($node->commands, $before);
    expect(array_filter($after, fn (string $c) => str_contains($c, 'create-profile') || str_contains($c, 'update-profile')))->toBe([])
        ->and(array_filter(PenpotFilesDouble::$history, fn (array $w) => str_ends_with($w[0], '.owner-password')))->toHaveCount(1); // the first, throwaway one only
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // images: every one by digest, in the stack's env file too
    foreach ((array) config('penpot.images') as $name => $image) {
        expect($image)->toMatch('/^[a-z0-9.\/-]+:[A-Za-z0-9._-]+@sha256:[a-f0-9]{64}$/', $name);
    }
    $env = PenpotFilesDouble::$files["/srv/onhost-penpot/{$stack}/.env"];
    expect($env)->toContain('PENPOT_BACKEND_IMAGE='.config('penpot.images.backend'))->toContain('ONHOST_POSTGRES_IMAGE='.config('penpot.images.postgres'));
    expect(PenpotCompose::compose($stack, ['ram_mb' => 4096, 'cpus' => 2]))->toContain('image: "${PENPOT_FRONTEND_IMAGE}"')->not->toContain('${PENPOT_VERSION}');
    expect(collect(app(PenpotHealth::class)->checks())->keyBy('check')['Penpot images are pinned by digest']['ok'])->toBeTrue();
});
