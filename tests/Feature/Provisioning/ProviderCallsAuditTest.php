<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Providers\Contracts\Naming;

/*
 * TASK-0020 — did anybody use the TASK-0005 hole before it was closed?
 *
 * Until TASK-0005 a customer could name ANY ISPConfig remote id (shell user, database user, mailbox, alias) and the
 * platform acted on it without checking that the service owned it. `onhost:audit:provider-calls` reads what the
 * platform already logged — the operations, the provider calls and the listings the panel answered — and says for
 * every such action whose record the id was. It never calls a panel and never writes to the database.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** One logged ISPConfig call, in the shape ProviderCallLogger writes it (request summary + the whole vendor answer). */
function pcauditSeedCall(string $function, array $body, mixed $answer, CarbonInterface $at, string $bodyCode = 'ok', ?string $rawRequest = null, ?string $rawResponse = null): string
{
    $id = 'pcall_'.strtolower((string) Str::ulid());
    DB::table('provider_calls')->insert([
        'id' => $id, 'provider' => 'ispconfig', 'instance_key' => 'ispconfig-shared01', 'action' => $function, 'method' => 'POST', 'path' => '/remote/json.php?'.$function,
        'http_status' => 200, 'body_code' => $bodyCode, 'ok' => true, 'duration_ms' => 42, 'operation_id' => null, 'correlation_id' => null, 'actor' => 'worker',
        'request' => $rawRequest ?? json_encode(['method' => 'POST', 'url' => ISP.'/remote/json.php?'.$function, 'query' => [], 'body' => ['session_id' => '[redacted]'] + $body, 'headers' => ['Content-Type', 'Accept']]),
        'response' => $rawResponse ?? json_encode(['code' => $bodyCode, 'message' => $bodyCode === 'ok' ? '' : 'refused', 'response' => $answer]),
        'error' => null, 'created_at' => $at,
    ]);

    return $id;
}

/** A service.action operation as ServiceService::requestAction stores it, with one attempt that ran at `$at`. */
function pcauditSeedOperation(Service $service, string $action, array $params, CarbonInterface $at, string $state = Operation::SUCCEEDED, ?array $error = null, string $actor = 'usr_pcaudit_actor'): Operation
{
    $operation = Operation::query()->create([
        'organization_id' => $service->organization_id, 'service_id' => $service->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'state' => $state,
        'actor_type' => 'user', 'actor_id' => $actor, 'idempotency_key' => 'pcaudit-'.Str::random(12), 'desired' => array_merge($params, ['action' => $action, 'service_id' => $service->id]),
        'error' => $error, 'provider_instance_id' => $service->provider_instance_id, 'queue' => 'default', 'attempts' => 1,
        'queued_at' => $at, 'started_at' => $at, 'finished_at' => $at->copy()->addSeconds(5), 'created_at' => $at, 'updated_at' => $at->copy()->addSeconds(5),
    ]);
    DB::table('operation_attempts')->insert([
        'id' => 'opa_'.strtolower((string) Str::ulid()), 'operation_id' => $operation->id, 'attempt' => 1, 'step' => 1, 'step_label' => 'action',
        'started_at' => $at, 'finished_at' => $at->copy()->addSeconds(5), 'outcome' => $state === Operation::FAILED ? 'fail' : 'ok', 'created_at' => $at, 'updated_at' => $at,
    ]);

    return $operation;
}

/** Another platform service with its own site on the same ISPConfig node (featureWebService must have run first). */
function pcauditNeighbourWebSite(Organization $org, string $siteId): Service
{
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting '.$siteId, 'hostname' => "site{$siteId}.cz", 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'desired_spec' => ['executor' => 'ispconfig', 'family' => 'web', 'domain' => "site{$siteId}.cz"], 'entitlements' => ['ssh' => true], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [], 'health' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "pcaudit:{$service->id}", 'adapter_version' => '1.0.0', 'remote_type' => 'web_domain', 'remote_id' => $siteId, 'remote_node' => '1', 'meta' => ['client_id' => 4]]);

    return $service;
}

/** Another platform service with its own mail domain on the same mail server. */
function pcauditNeighbourMailDomain(Organization $org, string $domain, string $remoteId): Service
{
    $instance = ProviderInstance::query()->where('key', 'ispconfig-shared01')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'mail-hosting', 'family' => 'mail', 'name' => 'E-mail '.$domain, 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'desired_spec' => ['executor' => 'ispconfig', 'family' => 'mail', 'domain' => $domain], 'entitlements' => ['mailboxes' => 10], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "pcaudit:{$service->id}", 'adapter_version' => '1.0.0', 'remote_type' => 'mail_domain', 'remote_id' => $remoteId, 'remote_node' => '1', 'meta' => ['client_id' => 4, 'domain' => $domain]]);

    return $service;
}

/** Runs the audit to stdout as JSON: [exit code, decoded report, raw text]. */
function pcauditRun(array $options = []): array
{
    $code = Artisan::call('onhost:audit:provider-calls', array_merge(['--stdout' => true, '--format' => 'json'], $options));
    $text = Artisan::output();

    return [$code, json_decode($text, true), $text];
}

/** Scenario of test 1: service A (site 7) set a key on shell user 21, which the listing of site 8 (service B) holds. */
function pcauditNeighbourShellKey(Organization $orgA, Organization $orgB): array
{
    $mine = featureWebService($orgA, 'ispconfig');
    $theirs = pcauditNeighbourWebSite($orgB, '8');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 8]], [['shell_user_id' => 21, 'parent_domain_id' => 8, 'username' => $theirs->name_prefix.'_deploy']], now()->subDays(10));
    $operation = pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '21', 'ssh_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPcauditAttackerKeyMaterial0123456789 mallory@evil'], now()->subDays(5));
    $write = pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 21, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPcauditAttackerKeyMaterial0123456789 mallory@evil']], 1, now()->subDays(5)->addSecond());

    return [$mine, $theirs, $operation, $write];
}

it('flags a shell key set on a neighbour\'s shell user as CRITICAL', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    [$mine, , $operation, $write] = pcauditNeighbourShellKey($orgA, $orgB);

    [$code, $report] = pcauditRun();

    expect($code)->toBe(1)
        ->and($report['actions'])->toHaveCount(1)
        ->and($report['actions'][0]['severity'])->toBe('CRITICAL')
        ->and($report['actions'][0]['verdict'])->toBe('FOREIGN_PLATFORM')
        ->and($report['actions'][0]['operation_id'])->toBe($operation->id)
        ->and($report['actions'][0]['service_id'])->toBe($mine->id)
        ->and($report['actions'][0]['sent'])->toBe('accepted')
        ->and($report['actions'][0]['provider_call_ids'])->toBe([$write])
        ->and($report['actions'][0]['owner_organization_id'])->toBe($orgB->id)
        ->and($report['summary']['CRITICAL'])->toBe(1);
});

it('treats a write the panel never answered as possibly done', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureWebService($orgA, 'ispconfig');
    $theirs = pcauditNeighbourWebSite($orgB, '8');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 8]], [['shell_user_id' => 21, 'parent_domain_id' => 8, 'username' => $theirs->name_prefix.'_deploy']], now()->subDays(10));
    pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '21'], now()->subDays(5), Operation::FAILED, ['message' => 'ISPConfig sites_shell_user_update returned HTTP 504', 'retryable' => false]);
    $write = pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 21, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAtimeout']], null, now()->subDays(5)->addSecond(), 'ok', null, 'upstream timed out');
    DB::table('provider_calls')->where('id', $write)->update(['http_status' => 504, 'body_code' => null, 'ok' => false]);

    [$code, $report] = pcauditRun();

    expect($code)->toBe(1)
        ->and($report['actions'][0]['sent'])->toBe('uncertain')
        ->and($report['actions'][0]['severity'])->toBe('CRITICAL');
});

it('clears an action on the service\'s own shell user', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 7]], [['shell_user_id' => 20, 'parent_domain_id' => 7, 'username' => $mine->name_prefix.'_deploy']], now()->subDays(3));
    pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '20'], now()->subDays(2));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 20, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAown']], 1, now()->subDays(2)->addSecond());

    [$code, $report] = pcauditRun();
    [, $withClean] = pcauditRun(['--include-clean' => true]);

    expect($code)->toBe(0)
        ->and($report['actions'])->toBe([])
        ->and($withClean['actions'])->toHaveCount(1)
        ->and($withClean['actions'][0]['verdict'])->toBe('OWN')
        ->and($withClean['actions'][0]['severity'])->toBe('CLEAN');
});

it('resolves a database user by its service name prefix', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureWebService($orgA, 'ispconfig');
    $theirs = pcauditNeighbourWebSite($orgB, '8');
    pcauditSeedCall('sites_database_user_get', ['primary_id' => 40], ['database_user_id' => 40, 'database_user' => $theirs->name_prefix.'_shop'], now()->subDays(20));
    pcauditSeedCall('sites_database_user_get', ['primary_id' => 41], ['database_user_id' => 41, 'database_user' => 'c9legacyshop'], now()->subDays(20));
    $password = pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '40'], now()->subDays(6));
    pcauditSeedCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 40, 'params' => ['database_password' => '[redacted]']], 1, now()->subDays(6)->addSecond());
    $delete = pcauditSeedOperation($mine, 'dbuser.delete', ['remote_id' => '41'], now()->subDays(4));
    pcauditSeedCall('sites_database_user_delete', ['primary_id' => 41], 1, now()->subDays(4)->addSecond());

    [$code, $report] = pcauditRun();
    $rows = collect($report['actions'])->keyBy('operation_id');

    expect($code)->toBe(1)
        ->and($rows[$password->id]['verdict'])->toBe('FOREIGN_PLATFORM')
        ->and($rows[$password->id]['severity'])->toBe('CRITICAL')
        ->and($rows[$password->id]['owner_service_id'])->toBe($theirs->id)
        ->and($rows[$delete->id]['verdict'])->toBe('FOREIGN_UNMANAGED')
        ->and($rows[$delete->id]['severity'])->toBe('CRITICAL');
});

it('reads a mailbox\'s owner from the address the panel returned', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureMailService($orgA, 'shop.cz');
    pcauditNeighbourMailDomain($orgB, 'other.cz', '6');
    pcauditSeedCall('mail_user_get', ['primary_id' => ['email' => '%@shop.cz']], [['mailuser_id' => 31, 'email' => 'info@shop.cz']], now()->subDays(9));
    pcauditSeedCall('mail_user_get', ['primary_id' => 32], ['mailuser_id' => 32, 'email' => 'info@other.cz', 'password' => '$1$hash'], now()->subDays(8));
    $foreign = pcauditSeedOperation($mine, 'mailbox.delete', ['remote_id' => '32'], now()->subDays(8));
    pcauditSeedCall('mail_user_delete', ['primary_id' => 32], 1, now()->subDays(8)->addSecond());
    $own = pcauditSeedOperation($mine, 'mailbox.update', ['remote_id' => '31'], now()->subDays(7));
    pcauditSeedCall('mail_user_update', ['client_id' => 3, 'primary_id' => 31, 'params' => ['email' => 'info@shop.cz', 'quota' => 1]], 1, now()->subDays(7)->addSecond());

    [$code, $report, $text] = pcauditRun();
    [, , $markdown] = pcauditRun(['--format' => 'md']);

    expect($code)->toBe(1)
        ->and($report['actions'])->toHaveCount(1)
        ->and($report['actions'][0]['operation_id'])->toBe($foreign->id)
        ->and($report['actions'][0]['severity'])->toBe('CRITICAL')
        ->and($report['actions'][0]['owner_address'])->toBe('i***@other.cz')
        ->and(collect($report['actions'])->pluck('operation_id'))->not->toContain($own->id)
        ->and($text)->not->toContain('info@other.cz')
        ->and($markdown)->toContain('i***@other.cz')
        ->and($markdown)->not->toContain('info@other.cz');
});

it('grades a cross-service action inside one organization as MEDIUM', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    pcauditNeighbourWebSite($org, '9');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 9]], [['shell_user_id' => 25, 'parent_domain_id' => 9, 'username' => 'sibling']], now()->subDays(3));
    pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '25'], now()->subDays(2));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 25, 'params' => ['ssh_rsa' => '']], 1, now()->subDays(2)->addSecond());

    [$code, $report] = pcauditRun();

    expect($code)->toBe(0)
        ->and($report['actions'][0]['severity'])->toBe('MEDIUM')
        ->and($report['actions'][0]['verdict'])->toBe('FOREIGN_PLATFORM');
});

it('marks an id with no evidence as REVIEW and names the sys_datalog row', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '99'], now()->subDays(2));
    pcauditSeedCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 99, 'params' => ['database_password' => '[redacted]']], 1, now()->subDays(2)->addSecond());

    [$code, $report] = pcauditRun();
    [, , $markdown] = pcauditRun(['--format' => 'md']);

    expect($code)->toBe(0)
        ->and($report['actions'][0]['severity'])->toBe('REVIEW')
        ->and($report['actions'][0]['verdict'])->toBe('UNKNOWN')
        ->and($report['actions'][0]['sys_datalog'])->toBe('web_database_user database_user_id:99')
        ->and($markdown)->toContain('web_database_user')
        ->and($markdown)->toContain('database_user_id:99');
});

it('lists refusals after the fix as probes', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureMailService($org, 'shop.cz');
    foreach (['32', '33', '34'] as $i => $id) {
        pcauditSeedOperation($mine, 'mailbox.update', ['remote_id' => $id], now()->subHours(5 - $i), Operation::FAILED, ['message' => 'Tahle schránka k téhle službě nepatří.', 'retryable' => false, 'detail' => ['not_ours' => $id]], 'usr_prober');
    }

    [$code, $report] = pcauditRun();

    expect($code)->toBe(1)
        ->and($report['probes'])->toHaveCount(1)
        ->and($report['probes'][0]['severity'])->toBe('HIGH')
        ->and($report['probes'][0]['actor'])->toBe('user:usr_prober')
        ->and($report['probes'][0]['count'])->toBe(3)
        ->and($report['probes'][0]['consecutive'])->toBeTrue();
});

it('lists a write that no operation explains', function () {
    [, $org] = $this->customerWithOrganization();
    featureMailService($org, 'shop.cz');
    pcauditSeedCall('mail_user_get', ['primary_id' => 77], ['mailuser_id' => 77, 'email' => 'boss@historic.cz'], now()->subDays(40));
    $write = pcauditSeedCall('mail_user_delete', ['primary_id' => 77], 1, now()->subDays(3));

    [$code, $report] = pcauditRun();

    expect($code)->toBe(1)
        ->and($report['unattributed'])->toHaveCount(1)
        ->and($report['unattributed'][0]['provider_call_id'])->toBe($write)
        ->and($report['unattributed'][0]['verdict'])->toBe('FOREIGN_UNMANAGED')
        ->and($report['unattributed'][0]['severity'])->toBe('HIGH')
        ->and($report['unattributed'][0]['owner_address'])->toBe('b***@historic.cz');
});

it('uses evidence older than the window but judges only calls inside it', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureWebService($orgA, 'ispconfig');
    $theirs = pcauditNeighbourWebSite($orgB, '8');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 8]], [['shell_user_id' => 21, 'parent_domain_id' => 8, 'username' => $theirs->name_prefix.'_deploy']], now()->subDays(120));
    pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '21'], now()->subDays(100));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 21, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAold']], 1, now()->subDays(100)->addSecond());

    [$outsideCode, $outside] = pcauditRun(['--days' => 90]);
    $recent = pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '21'], now()->subDays(30));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 21, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAnew']], 1, now()->subDays(30)->addSecond());
    [$insideCode, $inside] = pcauditRun(['--days' => 90]);

    expect($outsideCode)->toBe(0)
        ->and($outside['actions'])->toBe([])
        ->and($outside['unattributed'])->toBe([])
        ->and($insideCode)->toBe(1)
        ->and($inside['actions'])->toHaveCount(1)
        ->and($inside['actions'][0]['operation_id'])->toBe($recent->id)
        ->and($inside['actions'][0]['severity'])->toBe('CRITICAL');
});

it('changes nothing', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    pcauditNeighbourShellKey($orgA, $orgB);
    Storage::fake('local');
    Http::fake();
    $tables = ['provider_calls', 'operations', 'operation_attempts', 'audit_events', 'outbox_messages', 'services', 'provider_bindings'];
    $before = collect($tables)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();

    $code = Artisan::call('onhost:audit:provider-calls');

    $after = collect($tables)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
    $files = Storage::disk('local')->allFiles();
    expect($code)->toBe(1)
        ->and($after)->toBe($before)
        ->and($files)->toHaveCount(1)
        ->and($files[0])->toStartWith('reports/provider-calls-audit-')
        ->and($files[0])->toEndWith('.md');
    Http::assertNothingSent();
});

it('never prints a secret', function () {
    [, $orgA] = $this->customerWithOrganization();
    $mine = featureWebService($orgA, 'ispconfig');
    $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPcauditLegacyKeyMaterial9876543210 mallory@evil';
    // a legacy row, written before the H12 masks: the password and the key are stored in clear
    pcauditSeedCall('sites_database_user_get', [], null, now()->subDays(50), 'ok', null, json_encode(['code' => 'ok', 'message' => '', 'response' => ['database_user_id' => 41, 'database_user' => 'c9legacyshop', 'database_password' => 'Leg4cy-Secret-Pass!']]));
    pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '41', 'password' => 'Leg4cy-Secret-Pass!'], now()->subDays(5));
    pcauditSeedCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 41, 'params' => ['database_password' => 'Leg4cy-Secret-Pass!']], 1, now()->subDays(5)->addSecond());
    pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '66', 'ssh_key' => $key], now()->subDays(4));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 66, 'params' => ['ssh_rsa' => $key]], 1, now()->subDays(4)->addSecond());

    [, , $json] = pcauditRun(['--include-clean' => true]);
    [, , $markdown] = pcauditRun(['--format' => 'md', '--include-clean' => true]);

    foreach ([$json, $markdown] as $text) {
        expect($text)->not->toContain('Leg4cy-Secret-Pass!')
            ->and($text)->not->toContain('PcauditLegacyKeyMaterial')
            ->and($text)->not->toContain('c9legacyshop')
            ->and($text)->toContain(Redactor::fingerprint($key));
    }
});

it('reports coverage and warns when the oldest call is newer than the window start', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'ispconfig');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 7]], [], now()->subDays(30));
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 7]], [], now()->subDays(1));
    pcauditSeedCall('sites_database_user_get', ['primary_id' => 5], null, now()->subDays(2), 'ok', null, '{"code":"ok","response":{"database_user_id":5,"datab…[truncated]');

    [$code, $report] = pcauditRun(['--days' => 90]);
    $coverage = collect($report['coverage'])->keyBy('instance_key')['ispconfig-shared01'];

    expect($code)->toBe(0)
        ->and($coverage['calls'])->toBe(3)
        ->and($coverage['unparseable'])->toBe(1)
        ->and($coverage['oldest'])->toStartWith(now()->subDays(30)->toDateString())
        ->and($coverage['warning'])->toBeString()->not->toBe('');
});

it('refuses bad options', function (array $options) {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'ispconfig');
    featureWebService($org, 'aapanel');

    expect(Artisan::call('onhost:audit:provider-calls', array_merge(['--stdout' => true], $options)))->toBe(2);
})->with([
    'no days' => [['--days' => '0']],
    'too many days' => [['--days' => '4000']],
    'unknown format' => [['--format' => 'xml']],
    'unknown instance' => [['--instance' => ['nope']]],
    'not an ISPConfig instance' => [['--instance' => ['aapanel-managed01']]],
    'output outside reports' => [['--stdout' => false, '--output' => '../escape.md']],
    'a dot segment' => [['--stdout' => false, '--output' => 'reports/../escape.md']],
    'another extension' => [['--stdout' => false, '--output' => 'reports/x.txt']],
    'extension not matching the format' => [['--stdout' => false, '--format' => 'json', '--output' => 'reports/x.md']],
    'since after until' => [['--since' => '2026-09-01', '--until' => '2026-08-01']],
]);

/*
 * Review round 1 (TASK-0020): the cases the first version got wrong or never exercised.
 */

it('does not silently drop an action whose service instance can no longer be resolved', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    [$mine, , $operation] = pcauditNeighbourShellKey($orgA, $orgB);
    Operation::query()->whereKey($operation->id)->update(['provider_instance_id' => null]);
    ProviderBinding::query()->where('service_id', $mine->id)->delete(); // the service was terminated and its bindings cleaned up

    [$code, $report] = pcauditRun();

    expect($code)->toBe(1)
        ->and(collect($report['actions'] ?? [])->pluck('operation_id'))->toContain($operation->id)
        ->and($report['actions'][0]['severity'])->toBe('CRITICAL');
});

it('reports an action whose instance cannot be determined at all instead of dropping it', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    $operation = pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '44'], now()->subDays(5));
    Operation::query()->whereKey($operation->id)->update(['provider_instance_id' => null]);
    DB::table('services')->where('id', $mine->id)->update(['provider_instance_id' => null]);
    ProviderBinding::query()->where('service_id', $mine->id)->delete();

    [, $report] = pcauditRun();
    $row = collect($report['actions'])->firstWhere('operation_id', $operation->id);

    expect($row)->not->toBeNull()
        ->and($row['severity'])->toBe('REVIEW')
        ->and($row['notes'])->toContain('instance_guessed');
});

it('counts a targeted action on another provider as skipped, not as audited', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'ispconfig');
    $aapanel = featureWebService($org, 'aapanel');
    pcauditSeedOperation($aapanel, 'shell.key', ['remote_id' => '3'], now()->subDays(5));

    [$code, $report] = pcauditRun(['--include-clean' => true]);

    expect($code)->toBe(0)
        ->and($report['actions'])->toBe([])
        ->and($report['meta']['skipped'])->toBe(['not_ispconfig' => 1]);
});

it('ties each guarded mail action to its own write', function (string $action, array $params, string $function, array $body, ?array $decoy, bool $byTime) {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureMailService($orgA, 'shop.cz');
    pcauditNeighbourMailDomain($orgB, 'other.cz', '6');
    pcauditSeedCall('mail_user_get', ['primary_id' => 55], ['mailuser_id' => 55, 'email' => 'boss@other.cz'], now()->subDays(9));
    $operation = pcauditSeedOperation($mine, $action, $params, now()->subDays(3));
    $write = pcauditSeedCall($function, $body, 1, now()->subDays(3)->addSecond());
    if ($decoy !== null) {
        pcauditSeedCall($function, $decoy, 1, now()->subDays(3)->addSeconds(2)); // same function, same moment, another record
    }

    [$code, $report] = pcauditRun();
    $row = collect($report['actions'])->firstWhere('operation_id', $operation->id);

    expect($code)->toBe(1)
        ->and($row)->not->toBeNull()
        ->and($row['sent'])->toBe('accepted')
        ->and($row['provider_call_ids'])->toBe([$write])
        ->and($row['severity'])->toBe('CRITICAL')
        ->and($row['owner_organization_id'])->toBe($orgB->id)
        ->and(in_array('tied_by_time_only', $row['notes'], true))->toBe($byTime);
})->with([
    'mailbox.restore by the backup id' => ['mailbox.restore', ['remote_id' => '55', 'backup_id' => '900'], 'mail_user_backup', ['primary_id' => 900, 'action_type' => 'backup_restore_mail'], ['primary_id' => 901, 'action_type' => 'backup_restore_mail'], false],
    'filter.create by params.mailuser_id' => ['filter.create', ['remote_id' => '55', 'name' => 'x'], 'mail_user_filter_add', ['client_id' => 3, 'params' => ['mailuser_id' => 55, 'rulename' => 'x']], ['client_id' => 3, 'params' => ['mailuser_id' => 56, 'rulename' => 'x']], false],
    'filter.delete by the filter id, judged by the mailbox' => ['filter.delete', ['mailbox_id' => '55', 'remote_id' => '700'], 'mail_user_filter_delete', ['primary_id' => 700], ['primary_id' => 701], false],
    'fetchmail.create by the destination address' => ['fetchmail.create', ['destination' => 'boss@other.cz', 'host' => 'pop.example.net'], 'mail_fetchmail_add', ['client_id' => 3, 'params' => ['destination' => 'boss@other.cz', 'source_server' => 'pop.example.net']], ['client_id' => 3, 'params' => ['destination' => 'info@shop.cz', 'source_server' => 'pop.example.net']], false],
    'autoresponder.set by the mailbox id' => ['autoresponder.set', ['remote_id' => '55'], 'mail_user_update', ['client_id' => 3, 'primary_id' => 55, 'params' => ['autoresponder' => 'y']], ['client_id' => 3, 'primary_id' => 56, 'params' => ['autoresponder' => 'y']], false],
    'spam.policy by time only' => ['spam.policy', ['remote_id' => '55', 'policy_id' => '2'], 'mail_spamfilter_user_update', ['client_id' => 3, 'primary_id' => 12, 'params' => ['policy_id' => 2]], null, true],
]);

it('does not flag two non-consecutive refusals as a probe', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureMailService($org, 'shop.cz');
    foreach (['10', '50'] as $i => $id) {
        pcauditSeedOperation($mine, 'mailbox.update', ['remote_id' => $id], now()->subHours(5 - $i), Operation::FAILED, ['message' => 'Tahle schránka k téhle službě nepatří.', 'retryable' => false, 'detail' => ['not_ours' => $id]], 'usr_typo');
    }

    [$code, $report] = pcauditRun();

    expect($code)->toBe(0)
        ->and($report['probes'])->toHaveCount(1)
        ->and($report['probes'][0]['count'])->toBe(2)
        ->and($report['probes'][0]['consecutive'])->toBeFalse()
        ->and($report['probes'][0]['severity'])->toBe('INFO');
});

it('grades a platform-made record whose owner service is gone as REVIEW with owner_service_unknown', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    pcauditSeedCall('sites_database_user_get', ['primary_id' => 45], ['database_user_id' => 45, 'database_user' => 'ohzzzzzz_shop'], now()->subDays(20)); // a platform prefix no service carries
    $operation = pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '45'], now()->subDays(6));
    pcauditSeedCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 45, 'params' => ['database_password' => '[redacted]']], 1, now()->subDays(6)->addSecond());

    [, $report] = pcauditRun();
    $row = collect($report['actions'])->firstWhere('operation_id', $operation->id);

    expect($row['verdict'])->toBe('FOREIGN_PLATFORM')
        ->and($row['severity'])->toBe('REVIEW')
        ->and($row['owner_service_id'])->toBeNull()
        ->and($row['notes'])->toContain('owner_service_unknown')
        ->and($row['owner_name'])->toBe('ohzzzzzz_s***');
});

it('lets the worst of two disagreeing sightings win and says so', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureWebService($orgA, 'ispconfig');
    $theirs = pcauditNeighbourWebSite($orgB, '8');
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 7]], [['shell_user_id' => 21, 'parent_domain_id' => 7, 'username' => $mine->name_prefix.'_deploy']], now()->subDays(30));
    pcauditSeedCall('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => 8]], [['shell_user_id' => 21, 'parent_domain_id' => 8, 'username' => $theirs->name_prefix.'_deploy']], now()->subDays(10));
    $operation = pcauditSeedOperation($mine, 'shell.key', ['remote_id' => '21'], now()->subDays(5));
    pcauditSeedCall('sites_shell_user_update', ['client_id' => 3, 'primary_id' => 21, 'params' => ['ssh_rsa' => 'ssh-ed25519 AAAAboth']], 1, now()->subDays(5)->addSecond());

    [, $report] = pcauditRun();
    $row = collect($report['actions'])->firstWhere('operation_id', $operation->id);

    expect($row['severity'])->toBe('CRITICAL')
        ->and($row['owner_service_id'])->toBe($theirs->id)
        ->and($row['owner_site'])->toBe(8)
        ->and($row['notes'])->toContain('conflicting_evidence');
});

it('never lets a customer-supplied value carry terminal or HTML control sequences into the report', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureMailService($org, 'shop.cz');
    $destination = "a\e[2J\e[H@evil\e]0;pwned\x07<b>\u{202E}`x`.cz";
    foreach ([1, 2, 3] as $i) {
        pcauditSeedOperation($mine, 'fetchmail.create', ['destination' => $destination], now()->subHours(6 - $i), Operation::FAILED, ['message' => 'refused', 'retryable' => false, 'detail' => ['not_ours' => $destination]], 'usr_ansi');
    }

    [, $report] = pcauditRun();
    [, , $markdown] = pcauditRun(['--format' => 'md']);
    $strings = [];
    array_walk_recursive($report, function ($value) use (&$strings) {
        if (is_string($value)) {
            $strings[] = $value;
        }
    });

    expect($report['probes'])->toHaveCount(1)
        ->and(implode("\n", $strings))->not->toContain("\e")->not->toContain("\x07")->not->toContain("\u{202E}")
        ->and($markdown)->not->toContain("\e")->not->toContain("\x07")->not->toContain("\u{202E}")
        ->and($markdown)->not->toContain('<b>')
        ->and($markdown)->not->toContain('`x`');
});

it('raises a write on a neighbour\'s record to REVIEW when another organization\'s action ran next to it', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization();
    $mine = featureMailService($orgA, 'shop.cz');
    pcauditNeighbourMailDomain($orgB, 'other.cz', '6');
    pcauditSeedCall('mail_user_get', ['primary_id' => 32], ['mailuser_id' => 32, 'email' => 'info@other.cz'], now()->subDays(20));
    pcauditSeedCall('mail_user_get', ['primary_id' => 33], ['mailuser_id' => 33, 'email' => 'sales@other.cz'], now()->subDays(20));
    $near = pcauditSeedOperation($mine, 'mailbox.password', ['remote_id' => '31'], now()->subDays(3)); // an action the tie did not catch
    $suspect = pcauditSeedCall('mail_user_delete', ['primary_id' => 32], 1, now()->subDays(3)->addSeconds(10));
    $quiet = pcauditSeedCall('mail_user_delete', ['primary_id' => 33], 1, now()->subDays(10)); // nothing ran near it

    [$code, $report] = pcauditRun();
    [, $withClean] = pcauditRun(['--include-clean' => true]);
    $all = collect($withClean['unattributed'])->keyBy('provider_call_id');

    expect($code)->toBe(0)
        ->and($report['unattributed'])->toHaveCount(1)
        ->and($report['unattributed'][0]['provider_call_id'])->toBe($suspect)
        ->and($report['unattributed'][0]['verdict'])->toBe('PLATFORM')
        ->and($report['unattributed'][0]['severity'])->toBe('REVIEW')
        ->and($report['unattributed'][0]['notes'])->toContain('nearest_op_other_org')
        ->and($report['unattributed'][0]['nearest_operation']['operation_id'])->toBe($near->id)
        ->and($all[$quiet]['severity'])->toBe('INFO')
        ->and($all[$quiet]['notes'])->toBe([]);
});

it('does not clear an action on a name whose prefix several services share', function () {
    [, $org] = $this->customerWithOrganization();
    $mine = featureWebService($org, 'ispconfig');
    $other = pcauditNeighbourWebSite($org, '9');
    $shared = Naming::prefix($other->id);
    DB::table('services')->where('id', $other->id)->update(['name_prefix' => null]); // a legacy row: its prefix is derived from the id
    DB::table('services')->where('id', $mine->id)->update(['name_prefix' => $shared]);
    pcauditSeedCall('sites_database_user_get', ['primary_id' => 46], ['database_user_id' => 46, 'database_user' => $shared.'_shop'], now()->subDays(20));
    $operation = pcauditSeedOperation($mine, 'dbuser.password', ['remote_id' => '46'], now()->subDays(6));
    pcauditSeedCall('sites_database_user_update', ['client_id' => 3, 'primary_id' => 46, 'params' => ['database_password' => '[redacted]']], 1, now()->subDays(6)->addSecond());

    [, $report] = pcauditRun();
    $row = collect($report['actions'])->firstWhere('operation_id', $operation->id);

    expect($row)->not->toBeNull()
        ->and($row['severity'])->toBe('REVIEW')
        ->and($row['notes'])->toContain('shared_prefix');
});

it('files the report only under a matching extension and never over an earlier report', function () {
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'ispconfig');
    Storage::fake('local');
    Storage::disk('local')->put('reports/earlier.md', 'incident 1');

    $overwrite = Artisan::call('onhost:audit:provider-calls', ['--output' => 'reports/earlier.md']);
    $kept = Storage::disk('local')->get('reports/earlier.md');
    $forced = Artisan::call('onhost:audit:provider-calls', ['--output' => 'reports/earlier.md', '--force' => true]);
    $dots = Artisan::call('onhost:audit:provider-calls', ['--output' => 'reports/report..final.md']);

    expect($overwrite)->toBe(2)
        ->and($kept)->toBe('incident 1')
        ->and($forced)->toBe(0)
        ->and(Storage::disk('local')->get('reports/earlier.md'))->toStartWith('# Provider calls audit')
        ->and($dots)->toBe(0)
        ->and(Storage::disk('local')->exists('reports/report..final.md'))->toBeTrue();
});
