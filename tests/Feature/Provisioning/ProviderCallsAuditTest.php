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
    'since after until' => [['--since' => '2026-09-01', '--until' => '2026-08-01']],
]);
