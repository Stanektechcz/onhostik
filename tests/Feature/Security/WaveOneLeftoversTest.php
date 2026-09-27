<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelTransport;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * TASK-0041 — the leftovers of the permission program's wave 1 red-team round (PROGRAM-opravneni-2026-09-27 §5/§6):
 *  (a) IF-7 / D11: an aaPanel node the operator left open (one customer, `tenancy.closed` unset) must never silently become
 *      shared — placement refuses a second organization there; closing it (`onhost:aapanel:tenancy --apply`) is the way.
 *  (b) IF-3 / P0-07 follow-up: project memberships granted before the GrantPolicy allow-list existed are listed, read-only.
 *  (c) IF-12 / P0-10 follow-up: the bus key of a domain command is the caller's for one target and one person, and the same
 *      key with another request is refused (409) — as for service actions.
 *  (d) IF-6 / P0-02 follow-up: a move within one game panel builds the copy under the recorded owner, so it runs only when
 *      the panel proves that owner is the organization's own (OWNED) — not merely "not moved".
 *  (e) IF-7 / P0-03 follow-up: a hardlinked file comes back from a download only when it belongs to www or THIS site's own
 *      agent user — not to any `oh*ag`, which is every other site's agent on the node.
 *  (f) P0-07 follow-up: sharing a service compares the rights of the person acted for (onBehalfOfUserId ?? actorId), like
 *      every other grant path (GrantPolicy::grantor).
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

// ── (a) placement on an aaPanel node whose tenancy was left open ────────────────────────────────────────────────────

/** An aaPanel panel with one managed node in its own region, so nothing seeded competes with it. */
function wolAaPanelNode(string $key, array $options = [], int $ramUsedMb = 1000, string $provider = 'aapanel', string $role = 'managed'): Node
{
    Region::query()->firstOrCreate(['code' => 'wol1'], ['name' => 'Laboratoř', 'country' => 'CZ', 'state' => 'active']);
    $instance = ProviderInstance::query()->create(['key' => $key, 'provider' => $provider, 'name' => $key, 'region_code' => 'wol1', 'base_url' => "https://{$key}.mgmt.test:8888", 'secret_ref' => 'env://WOL', 'state' => 'active', 'capabilities' => ['web.create' => true], 'options' => $options, 'adapter_version' => '1.0.0']);

    return Node::query()->create(['provider_instance_id' => $instance->id, 'name' => "{$key}-n1", 'region_code' => 'wol1', 'role' => $role, 'state' => 'active',
        'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => $ramUsedMb, 'disk_used_gb' => 100, 'io_wait_pct' => 1]]);
}

/** A managed web service of an organization, on a node or (null) still waiting to be placed. */
function wolManagedService(Organization $org, ?Node $on = null, string $state = ServiceStateMachine::ACTIVE): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'wordpress', 'family' => 'managed', 'name' => 'Managed WordPress', 'state' => $on === null ? ServiceStateMachine::PAID : $state, 'region_code' => 'wol1',
        'provider_instance_id' => $on?->provider_instance_id, 'node_id' => $on?->id, 'desired_spec' => ['executor' => 'aapanel'], 'entitlements' => ['nvme_gb' => 10], 'sla_class' => 'standard', 'activated_at' => $on === null ? null : now()]);
}

/** @return array<string, mixed> */
function wolManagedWant(string $provider = 'aapanel', string $role = 'managed'): array
{
    return ['role' => $role, 'provider' => $provider, 'region' => 'wol1', 'disk_gb' => 10];
}

it('refuses to put a second organization on an aaPanel node one organization uses while its tenancy is open', function () {
    [, $first] = $this->customerWithOrganization();
    [, $second] = $this->customerWithOrganization();
    $node = wolAaPanelNode('aapanel-wol-open');
    wolManagedService($first, $node);
    $newcomer = wolManagedService($second);

    expect(fn () => app(NodeScheduler::class)->place($newcomer, wolManagedWant()))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capacity_unavailable')->and($e->getMessage())->toContain('aapanel-wol-open')->toContain('onhost:aapanel:tenancy'));
    expect($newcomer->fresh()->node_id)->toBeNull()->and($newcomer->fresh()->provider_instance_id)->toBeNull();
});

it('still places the same organization, the first organization on an empty node and anyone on a node closed as shared', function () {
    [, $first] = $this->customerWithOrganization();
    [, $second] = $this->customerWithOrganization();
    $scheduler = app(NodeScheduler::class);

    $open = wolAaPanelNode('aapanel-wol-mine');
    wolManagedService($first, $open);
    $again = wolManagedService($first);
    expect($scheduler->place($again, wolManagedWant())['node']->id)->toBe($open->id); // its own customer's second site

    $open->providerInstance->forceFill(['options' => ['tenancy' => ['closed' => true]]])->save(); // `--apply` closed it
    $newcomer = wolManagedService($second);
    expect($scheduler->place($newcomer, wolManagedWant())['node']->id)->toBe($open->id);
});

it('takes an empty open node for a new organization rather than one another organization uses, even when that one scores better', function () {
    [, $first] = $this->customerWithOrganization();
    [, $second] = $this->customerWithOrganization();
    $used = wolAaPanelNode('aapanel-wol-used', ramUsedMb: 1000);
    $empty = wolAaPanelNode('aapanel-wol-empty', ramUsedMb: 60000); // busier: the soft score alone would pick $used
    wolManagedService($first, $used);

    $pick = app(NodeScheduler::class)->place(wolManagedService($second), wolManagedWant());

    expect($pick['node']->id)->toBe($empty->id)->and($pick['candidates'])->toHaveCount(1);
});

it('counts only services that still live on the node, and leaves panels that isolate their sites alone', function () {
    [, $first] = $this->customerWithOrganization();
    [, $second] = $this->customerWithOrganization();
    $scheduler = app(NodeScheduler::class);
    $aapanel = wolAaPanelNode('aapanel-wol-gone');
    wolManagedService($first, $aapanel, ServiceStateMachine::TERMINATED);
    expect($scheduler->place(wolManagedService($second), wolManagedWant())['node']->id)->toBe($aapanel->id);

    // ISPConfig gives every site its own system user: two customers on one ISPConfig node are its normal case
    $isp = wolAaPanelNode('ispconfig-wol', provider: 'ispconfig', role: 'web');
    wolManagedService($first, $isp);
    expect($scheduler->place(wolManagedService($second), wolManagedWant('ispconfig', 'web'))['node']->id)->toBe($isp->id);
});

// ── (b) project memberships outside the allow-list ─────────────────────────────────────────────────────────────────

it('lists project memberships whose role the project allow-list no longer grants, and changes nothing', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $project = Project::query()->create(['organization_id' => $org->id, 'name' => 'E-shop', 'slug' => 'wol-eshop', 'tags' => []]);
    $allowed = ProjectMembership::query()->create(['project_id' => $project->id, 'user_id' => $this->customer()->id, 'role_key' => 'developer']);
    $admin = ProjectMembership::query()->create(['project_id' => $project->id, 'user_id' => $this->customer(['email' => 'wol-admin@example.cz'])->id, 'role_key' => 'org_admin']);
    $unknown = ProjectMembership::query()->create(['project_id' => $project->id, 'user_id' => $this->customer()->id, 'role_key' => 'no_such_role']);
    // the permissions come from the project-scope binding: one without its membership row counts all the same
    $bindingOnly = PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $this->customer(['email' => 'wol-billing@example.cz'])->id, 'role_key' => 'billing_admin', 'scope_type' => 'project', 'scope_id' => $project->id, 'organization_id' => $org->id]);
    $before = ProjectMembership::query()->orderBy('id')->get(['id', 'role_key', 'expires_at'])->toArray();
    $bindingsBefore = PolicyBinding::query()->orderBy('id')->get(['id', 'role_key', 'scope_id', 'expires_at'])->toArray();
    expect(in_array('org_admin', GrantPolicy::PROJECT_ROLES, true))->toBeFalse();

    expect(Artisan::call('onhost:projects:role-audit', ['--dry-run' => true]))->toBe(0);
    $listed = Artisan::output(); // one table row carries several of these: PendingCommand matches one expectation per written line
    expect($listed)->toContain($admin->id)->toContain('wol-admin@example.cz')->toContain('org_admin')->toContain($unknown->id)->toContain('no_such_role (unknown role)')
        ->toContain($bindingOnly->id)->toContain('wol-billing@example.cz')->toContain('3 project memberships')->toContain('Nothing was changed')
        ->not->toContain($allowed->id);
    $this->artisan('onhost:projects:role-audit --apply')->expectsOutputToContain('no --apply')->assertExitCode(1);
    $this->artisan('onhost:projects:role-audit --organization='.$org->id)->expectsOutputToContain($admin->id)->assertExitCode(0);
    $this->artisan('onhost:projects:role-audit --organization=org_nobody')->expectsOutputToContain('0 project memberships')->assertExitCode(0);

    expect(ProjectMembership::query()->orderBy('id')->get(['id', 'role_key', 'expires_at'])->toArray())->toBe($before)
        ->and(PolicyBinding::query()->orderBy('id')->get(['id', 'role_key', 'scope_id', 'expires_at'])->toArray())->toBe($bindingsBefore);
});

// ── (c) domain command keys ─────────────────────────────────────────────────────────────────────────────────────────

/** Two domains of one organization, an owner and an org_admin who both manage them. @return array{0: User, 1: User, 2: Domain, 3: Domain} */
function wolDomains(Organization $org, User $owner, User $admin): array
{
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);

    return [$owner, $admin, graceDomain($org, 'wol-jedna.cz', DomainStateMachine::ACTIVE, now()->addDays(200)), graceDomain($org, 'wol-dva.cz', DomainStateMachine::ACTIVE, now()->addDays(200))];
}

it('keeps a domain key to the person and the domain it was used for: another member or another domain is not answered with the first run', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $admin, $one, $two] = wolDomains($org, $owner, $this->customer());

    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'wol-dom-1')->postJson("/v1/domains/{$one->id}/auto-renew", ['enabled' => false])->assertOk()->assertJsonPath('domain_id', $one->id);
    $this->flushHeaders();
    // another member, the same header (a shared CI script, a copied request): the HTTP replay is per person and let it through
    $this->actingAs($admin, 'sanctum');
    $this->withHeader('Idempotency-Key', 'wol-dom-1')->postJson("/v1/domains/{$two->id}/auto-renew", ['enabled' => false])->assertOk()->assertJsonPath('domain_id', $two->id);
    $this->flushHeaders();

    expect($one->fresh()->auto_renew)->toBeFalse()->and($two->fresh()->auto_renew)->toBeFalse();
});

it('refuses the same domain key with another request once the HTTP layer kept nothing, and still replays a true retry', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, , $one] = wolDomains($org, $owner, $this->customer());
    $this->actingAs($owner, 'sanctum');
    $forgetHttp = fn () => DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete(); // a crash after the commit, a 5xx: no stored answer

    $this->withHeader('Idempotency-Key', 'wol-dom-2')->postJson("/v1/domains/{$one->id}/auto-renew", ['enabled' => false])->assertOk();
    $this->flushHeaders();
    $forgetHttp();
    $this->withHeader('Idempotency-Key', 'wol-dom-2')->postJson("/v1/domains/{$one->id}/auto-renew", ['enabled' => true])->assertStatus(409)->assertJsonPath('error', 'idempotency_key_reused');
    $this->flushHeaders();
    expect($one->fresh()->auto_renew)->toBeFalse();

    $forgetHttp();
    $this->withHeader('Idempotency-Key', 'wol-dom-2')->postJson("/v1/domains/{$one->id}/auto-renew", ['enabled' => false])->assertOk()->assertJsonPath('auto_renew', false);
    $this->flushHeaders();
    expect(DB::table('audit_events')->where('action', 'domain.auto_renew')->where('resource_id', $one->id)->count())->toBe(1); // replayed, not run twice
});

// ── (d) same-panel game migration needs an OWNED verdict ──────────────────────────────────────────────────────────

dataset('panel owners that are not the organization\'s own', [
    'another organization\'s account' => [['external_id' => 'org_somebody_else', 'root_admin' => false], 'foreign'],
    'an account the platform never made (no external id)' => [['external_id' => null, 'root_admin' => false], 'unmarked'],
    'a panel administrator' => [['external_id' => null, 'root_admin' => true], 'administrator'],
]);

it('refuses to move a game server within its panel unless the panel proves the owner is the organization\'s own', function (array $owner, string $verdict) {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    Node::query()->create(['provider_instance_id' => $instance->id, 'name' => 'games02', 'region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '3']);
    $calls = [];
    Http::fake(function (Request $request) use (&$calls, $owner) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $calls[] = $request->method().' '.$path;

        return match (true) {
            $path === '/api/application/servers/77' && $request->method() === 'GET' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'user' => 9, 'node' => 2, 'identifier' => 'e4c1abcd']]),
            $path === '/api/application/users/9' && $request->method() === 'GET' => Http::response(['object' => 'user', 'attributes' => ['id' => 9, 'email' => 'someone@example.cz', 'username' => 'someone'] + $owner]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => 'no fake']]], 404),
        };
    });

    $this->actingAs($this->steppedUpStaff('infrastructure_admin'), 'sanctum');
    $started = $this->withHeader('Idempotency-Key', 'wol-gmig-'.$verdict)->postJson("/v1/staff/services/{$service->id}/migrate", ['target_node_id' => 'games02', 'reason' => 'údržba uzlu games01'])->assertStatus(202)->json();
    $this->flushHeaders();
    $operation = driveOperation(Operation::query()->findOrFail($started['id']));

    expect($operation->state)->toBe(Operation::FAILED)->and($operation->error['message'])->toContain('support has to review')
        ->and(json_encode($operation->error))->toContain($verdict);
    // nothing past the check: no collaborators read, no stop, no copy made under somebody else's account
    expect($calls)->toBe(['GET /api/application/servers/77', 'GET /api/application/users/9'])
        ->and($service->primaryBinding()->remote_id)->toBe('77');
})->with('panel owners that are not the organization\'s own');

// ── (e) the hardlink exception of a download ───────────────────────────────────────────────────────────────────────

/** A site transport on an open node whose own agent user is `$agent`; the chunk comes back as `hello`. */
function wolTransport(ScriptedShell $shell, ?Closure $agent): AaPanelTransport
{
    $post = fn (string $path, array $params, string $action, bool $critical, array $files) => str_contains($path, 'GetFileBody') ? ['status' => true, 'data' => base64_encode('hello')] : ['status' => true];

    return $agent === null
        ? new AaPanelTransport($post, $shell, '/www/backup/site', 'www')
        : new AaPanelTransport($post, $shell, '/www/wwwroot/shop.cz', 'www', fn (): ?bool => false, $agent);
}

function wolDownloadScript(AaPanelTransport $transport, ScriptedShell $shell): string
{
    $local = (string) tempnam(sys_get_temp_dir(), 'wol');
    try {
        $transport->download('site.tar.gz', $local);
        expect(file_get_contents($local))->toBe('hello');
    } finally {
        @unlink($local);
    }

    return (string) collect($shell->commands())->first(fn (string $c) => str_contains($c, 'exec 3<'));
}

it('lets a file with a second name come back only when it is www\'s or this site\'s own agent\'s, never another site\'s agent', function () {
    $shell = new ScriptedShell(['/exec 3</' => "SIZE 5\n"]);
    $script = wolDownloadScript(wolTransport($shell, fn (): string => 'oh1a2b3cag'), $shell);
    expect($script)->toContain("in 'www'|'oh1a2b3cag') ;;")->not->toContain('oh*ag');

    // a panel folder's transport (backups, dumps) has no site user of its own: only www
    $shell = new ScriptedShell(['/exec 3</' => "SIZE 5\n"]);
    expect(wolDownloadScript(wolTransport($shell, null), $shell))->toContain("in 'www') ;;")->not->toContain('oh*ag');

    // the site's agent could not be made ready: stricter, not looser — only www, and an ordinary file still downloads
    $shell = new ScriptedShell(['/exec 3</' => "SIZE 5\n"]);
    $broken = fn (): string => throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'agent not ready');
    expect(wolDownloadScript(wolTransport($shell, $broken), $shell))->toContain("in 'www') ;;")->not->toContain('oh*ag');
});

// ── (f) sharing compares the rights of the person acted for ────────────────────────────────────────────────────────

it('shares a service with the rights of the person acted for, not of whoever carries the request', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $viewer = $this->customer();
    app(OrganizationService::class)->attachMember($org, $viewer, 'viewer', CommandContext::system('test'), true);
    $service = featureWebService($org, 'ispconfig');
    $access = app(ServiceAccessService::class);

    // acting for a viewer: the viewer holds no console, so it is not handed out — whoever carries the request
    $forViewer = new CommandContext('user', $owner->id, $org->id, onBehalfOfUserId: $viewer->id);
    expect(fn () => $access->share($org, $service, 'wol-friend@example.cz', ['console'], $forViewer))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capability_above_own'));
    expect(ServiceAccessGrant::query()->where('service_id', $service->id)->count())->toBe(0);

    // acting for the owner: the owner's rights decide, and the owner is recorded as the one who granted it
    $forOwner = new CommandContext('user', $this->customer()->id, $org->id, onBehalfOfUserId: $owner->id);
    $grant = $access->share($org, $service, 'wol-friend@example.cz', ['view'], $forOwner);
    expect($grant->granted_by)->toBe($owner->id);
});
