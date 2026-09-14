<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ResourceRef;

/*
 * The web toolkit on aaPanel: the panel API for cron edit/run/logs, database access, backup deletion, Node projects
 * and the run path, plus the shell wrapper (ExecShell + polled exit/output files) that the terminal, quotas and
 * tool discovery run through. Tenancy: jobs, databases and projects of other sites on the node are never touched.
 */

function aaToolsAdapter(): AaPanelWebProvider
{
    $_ENV['AAPANEL_MANAGED01_API_KEY'] = 'aa-key-123';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'base_url' => 'https://managed01.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'capabilities' => ['web'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('aapanel', AaPanelWebProvider::class);

    return $registry->forInstance($instance);
}

function aaToolsFake(array &$calls, array $answers): void
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

        return Http::response(['status' => false, 'msg' => "unexpected {$path}"]);
    });
}

it('drives cron edit/run/logs, database access, backup deletion, Node projects and the run path through the aaPanel API', function () {
    $calls = [];
    $prefix = Naming::prefix('srv_tools');
    aaToolsFake($calls, [
        'crontab?action=GetCrontab' => [['id' => 12, 'name' => Naming::cronLabel('srv_tools', 'cache'), 'type' => 'day', 'where_hour' => '3', 'where_minute' => '0', 'sBody' => 'php cron.php'], ['id' => 13, 'name' => 'other:job', 'type' => 'day', 'where_hour' => '1', 'where_minute' => '0', 'sBody' => 'rm -rf /']],
        'crontab?action=modify_crond' => ['status' => true, 'msg' => 'ok'],
        'crontab?action=set_cron_status' => ['status' => true, 'msg' => 'ok'],
        'crontab?action=StartTask' => ['status' => true, 'msg' => 'started'],
        'crontab?action=GetLogs' => ['status' => true, 'msg' => "line one\nline two\nline three"],
        'data?action=getData&table=databases' => ['data' => [['id' => 4, 'name' => $prefix.'_shop', 'username' => $prefix.'_shop', 'codeing' => 'utf8mb4'], ['id' => 5, 'name' => 'other_db', 'username' => 'other', 'codeing' => 'utf8']]],
        'database?action=GetDatabaseAccess' => ['status' => true, 'msg' => ['permission' => '203.0.113.7,localhost']],
        'database?action=SetDatabaseAccess' => ['status' => true, 'msg' => 'ok'],
        'data?action=getData&table=backup' => ['data' => [['id' => 77, 'addtime' => '2026-09-10 02:30:00', 'size' => 123456, 'filename' => '/www/backup/site/shop.cz_20260910.tar.gz']]],
        'site?action=DelBackup' => ['status' => true, 'msg' => 'deleted'],
        'project/nodejs/get_project_list' => ['data' => [['name' => 'shop-api', 'path' => '/www/wwwroot/shop.cz/api', 'project_config' => ['port' => 3000, 'version' => 'v22.1.0', 'domains' => ['api.shop.cz']], 'run' => true], ['name' => 'other', 'path' => '/www/wwwroot/other.cz/app', 'project_config' => ['port' => 3001]]]],
        'project/nodejs/restart_project' => ['status' => true, 'msg' => 'ok'],
        'site?action=GetSiteRunPath' => ['runPath' => '/public'],
    ]);
    $adapter = aaToolsAdapter();
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    expect($adapter->documentRoot($site))->toBe('/www/wwwroot/shop.cz/public');

    expect($adapter->cronLogs($site, '12', 2))->toBe(['line two', 'line three']);
    expect($adapter->runCron($site, '12')->data['started'])->toBeTrue();
    $adapter->updateCron($site, '12', ['schedule' => '30 * * * *', 'command' => 'php worker.php', 'active' => false]);
    $modify = collect($calls)->last(fn ($c) => str_contains($c[0], 'modify_crond'))[1];
    expect($modify)->toMatchArray(['id' => 12, 'type' => 'hour-n', 'minute' => 30, 'sBody' => 'php worker.php'])->and((string) $modify['name'])->toStartWith('onhost:');
    expect(collect($calls)->contains(fn ($c) => str_contains($c[0], 'set_cron_status')))->toBeTrue();
    expect(fn () => $adapter->runCron($site, '13'))->toThrow(ProviderException::class); // another tenant's job on the shared node

    expect($adapter->databaseAccess($site, '4'))->toBe(['remote' => true, 'hosts' => ['203.0.113.7']]);
    $adapter->setDatabaseAccess($site, '4', true, []);
    expect(collect($calls)->last(fn ($c) => str_contains($c[0], 'SetDatabaseAccess'))[1])->toMatchArray(['name' => $prefix.'_shop', 'dataAccess' => '%']);
    $adapter->setDatabaseAccess($site, '4', false);
    expect(collect($calls)->last(fn ($c) => str_contains($c[0], 'SetDatabaseAccess'))[1]['dataAccess'])->toBe('127.0.0.1');
    expect(fn () => $adapter->databaseAccess($site, '5'))->toThrow(ProviderException::class);

    expect($adapter->deleteBackup($site, '77')->data['deleted'])->toBeTrue()->and($adapter->deleteBackup($site, '78')->alreadyExisted)->toBeTrue();
    expect(collect($calls)->last(fn ($c) => str_contains($c[0], 'DelBackup'))[1]['id'])->toBe(77);

    $projects = $adapter->nodeProjects($site);
    expect($projects)->toHaveCount(1)->and($projects[0])->toMatchArray(['remote_id' => 'shop-api', 'name' => 'shop-api', 'path' => '/www/wwwroot/shop.cz/api', 'port' => 3000]);
    $adapter->nodeProjectAction($site, 'shop-api', 'restart');
    expect(collect($calls)->last(fn ($c) => str_contains($c[0], 'restart_project'))[1]['project_name'])->toBe('shop-api');
    expect(fn () => $adapter->nodeProjectAction($site, 'other', 'stop'))->toThrow(ProviderException::class);
});

it('runs commands through the panel shell wrapper and reads tool paths and quotas from the node', function () {
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
            $path = (string) ($body['path'] ?? '');
            if (str_ends_with($path, '.exit')) {
                return Http::response(['status' => true, 'data' => str_contains($lastShell, 'exit 3') ? '3' : '0']);
            }
            if (str_ends_with($path, '.out')) {
                $out = match (true) {
                    str_contains($lastShell, 'du -sb') => "4096000\n321\n",
                    str_contains($lastShell, 'command -v') => "composer=/usr/local/bin/composer\ngit=/usr/bin/git\nnode=\nnpm=\nmysql=/usr/bin/mysql\nmysqldump=/usr/bin/mysqldump\nredis-cli=\nwp=\nwpphar=/www/server/onhost/wp-cli.phar\n",
                    default => "Your request has been recorded. Tips from BT security !!!\nhello from node\n",
                };

                return Http::response(['status' => true, 'data' => $out]);
            }
        }
        if ($q === 'action=GetSitePHPVersion') {
            return Http::response(['phpversion' => '83']);
        }

        return Http::response(['status' => false, 'msg' => "unexpected {$q}"]);
    });
    $adapter = aaToolsAdapter();
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    $result = $adapter->shell($site)->run('echo hello', ['user' => 'www', 'cwd' => '/www/wwwroot/shop.cz']);
    expect($result->ok())->toBeTrue()->and(trim($result->stdout))->toBe('hello from node')->and($result->timedOut)->toBeFalse(); // the panel's security banner line is stripped
    $wrapped = (string) collect($calls)->first(fn ($c) => $c[0] === 'action=ExecShell')[1]['shell'];
    expect($wrapped)->toStartWith('timeout 120s bash -c ')->toContain('su -s /bin/bash ')->toContain('www')->toContain('/www/wwwroot/shop.cz')->toContain('echo hello')->toContain('> /tmp/onhost-')->toContain('echo $? > /tmp/onhost-'); // POSIX quoting on every platform
    expect(collect($calls)->last(fn ($c) => $c[0] === 'action=ExecShell')[1]['shell'])->toStartWith('rm -f /tmp/onhost-'); // temp files are removed afterwards

    expect($adapter->shell($site)->run('exit 3')->exitCode)->toBe(3);

    $paths = $adapter->toolPaths($site);
    expect($paths['php'])->toBe('/www/server/php/83/bin/php')->and($paths['wp'])->toBe('/www/server/onhost/wp-cli.phar')->and($paths['composer'])->toBe('/usr/local/bin/composer')->and($paths['node'])->toBeNull();

    $quotas = $adapter->quotas($site);
    expect($quotas['disk_used_bytes'])->toBe(4096000)->and($quotas['inodes_used'])->toBeGreaterThan(300)->and($quotas['measured_at'])->not->toBeEmpty();
    expect($adapter->shellAvailable($site))->toBeTrue()->and($adapter->siteUser($site))->toBe(Naming::prefix('srv_tools').'ag')->and($adapter->panelLoginUrl($site))->toBeNull();
    $agent = $adapter->ensureAgent($site); // per-site agent user with ACLs, prepared through the root shell
    expect($agent->data['agent'])->toBe(Naming::prefix('srv_tools').'ag')->and(collect($calls)->last(fn ($c) => $c[0] === 'action=ExecShell' && str_contains((string) ($c[1]['shell'] ?? ''), 'useradd'))[1]['shell'])->toContain('setfacl')->toContain('-G www');
});
