<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Providers\Contracts\Naming;

/*
 * The prototype's web workbench has tabs for error pages, web server directives, protected folders, database users,
 * shell users, statistics, a custom certificate, a file manager and one-click applications. Each tab is backed by
 * what the panel behind the service really offers (ISPConfig: vhost settings, folders, users, stats, certificate;
 * aaPanel: rewrite rules, site password, file manager, applications) — never named to the customer.
 */

const CERT_PEM = "-----BEGIN CERTIFICATE-----\nMIIBszCCAVmgAwIBAgIUQ0FGRQ==\n-----END CERTIFICATE-----";
const KEY_PEM = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSj\n-----END PRIVATE KEY-----";
const SSH_KEY = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGk0X2p3YkY5cE9xR1pVd1lqU2tXbEt0cUp2ZHVIZlg5 deploy@laptop';

beforeEach(fn () => Http::preventStrayRequests());

it('drives the extended ISPConfig site tabs: error pages, directives, protected folders, database and shell users, statistics and a custom certificate', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $prefix = Naming::prefix($service->id);
    $site = ['domain_id' => 7, 'domain' => 'shop.cz', 'sys_groupid' => 3, 'system_user' => 'web7', 'system_group' => 'client3', 'document_root' => '/var/www/clients/client3/web7', 'ssl' => 'y', 'ssl_letsencrypt' => 'y', 'rewrite_to_https' => 'n', 'subdomain' => 'www', 'errordocs' => '1', 'apache_directives' => 'Header set X-Test "1"', 'nginx_directives' => '', 'stats_type' => 'awstats'];
    $panel = ['folders' => [], 'folder_users' => [], 'shell' => [], 'db_users' => [12 => ['database_user_id' => 12, 'database_user' => "{$prefix}_shop"]], 'databases' => [['database_id' => 4, 'database_name' => "{$prefix}_shop", 'database_user_id' => 12, 'parent_domain_id' => 7]]];
    $updates = [];
    Http::fake(function ($request) use (&$panel, &$updates, $site) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $ok = fn ($response) => Http::response(['code' => 'ok', 'message' => '', 'response' => $response]);
        switch ($function) {
            case 'login': return $ok('sess-1');
            case 'sites_web_domain_get': return $ok(is_array($body['primary_id'] ?? null) ? [$site] : $site);
            case 'sites_web_domain_update': $updates[] = $body['params'];

                return $ok(true);
            case 'monitor_jobqueue_count': return $ok(0);
            case 'sites_web_folder_get': return $ok(array_values($panel['folders']));
            case 'sites_web_folder_add': $panel['folders'][] = ['web_folder_id' => 90, 'path' => $body['params']['path'], 'active' => 'y'];

                return $ok(90);
            case 'sites_web_folder_user_get': return $ok(array_values(array_filter($panel['folder_users'], fn ($u) => $u['web_folder_id'] === (int) $body['primary_id']['web_folder_id'])));
            case 'sites_web_folder_user_add': $panel['folder_users'][] = ['web_folder_user_id' => 91, 'web_folder_id' => (int) $body['params']['web_folder_id'], 'username' => $body['params']['username']];

                return $ok(91);
            case 'sites_database_get': return $ok($panel['databases']);
            case 'sites_database_user_get': return $ok($panel['db_users'][(int) $body['primary_id']] ?? []);
            case 'sites_database_user_add': $panel['db_users'][13] = ['database_user_id' => 13, 'database_user' => $body['params']['database_user']];

                return $ok(13);
            case 'sites_shell_user_get': return $ok(array_values($panel['shell']));
            case 'sites_shell_user_add': $panel['shell'][] = ['shell_user_id' => 30, 'username' => $body['params']['username'], 'ssh_rsa' => $body['params']['ssh_rsa'], 'chroot' => $body['params']['chroot'], 'active' => 'y'];

                return $ok(30);
            case 'sites_shell_user_update': foreach ($panel['shell'] as &$row) {
                if ($row['shell_user_id'] === (int) $body['primary_id']) {
                    $row['ssh_rsa'] = $body['params']['ssh_rsa'];
                }
            }

                return $ok(true);
        }

        return Http::response(['code' => 'remote_fault', 'message' => "unexpected {$function}", 'response' => false]);
    });
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    $f = $features['features'];
    expect($f['errpages']['enabled'])->toBeTrue()->and($f['directives'])->toBe(['enabled' => true, 'options' => ['apache', 'nginx']])->and($f['protected'])->toBe(['enabled' => true, 'options' => 'folders'])
        ->and($f['db_users']['enabled'])->toBeTrue()->and($f['shell'])->toBe(['enabled' => true, 'limit' => 2])->and($f['stats']['enabled'])->toBeTrue()->and($f['ssl_upload']['enabled'])->toBeTrue()
        ->and($f['files']['enabled'])->toBeTrue()->and($f['apps']['enabled'])->toBeFalse()->and($f['db_admin']['enabled'])->toBeFalse() // files through the site's agent user (toolkit)
        ->and($features['actions'])->toContain('errpages.set', 'directives.set', 'folder.protect', 'dbuser.create', 'shell.create', 'stats.set', 'ssl.upload', 'file.save')->not->toContain('app.install')
        ->and(json_encode($features))->not->toMatch('/ispconfig|aapanel/i');

    // listings: vhost settings, database users (derived from the site's databases), shell users, protected folders
    $settings = $this->getJson("/v1/services/{$service->id}/resources/site_settings")->assertOk()->json('data');
    expect($settings)->toMatchArray(['errordocs' => true, 'directives' => ['apache' => 'Header set X-Test "1"', 'nginx' => ''], 'document_root' => '/var/www/clients/client3/web7', 'site_password' => null])->and($settings['stats'])->toBe(['type' => 'awstats', 'url' => 'https://shop.cz/stats/', 'user' => 'admin']);
    expect($this->getJson("/v1/services/{$service->id}/resources/db_users")->assertOk()->json('data'))->toBe([['remote_id' => '12', 'user' => "{$prefix}_shop", 'databases' => ["{$prefix}_shop"]]]);
    expect($this->getJson("/v1/services/{$service->id}/resources/shell_users")->assertOk()->json('data'))->toBe([]);
    expect($this->getJson("/v1/services/{$service->id}/resources/protected_folders")->assertOk()->json('data'))->toBe([]);
    // the file manager needs the site's agent user on the node; without the instance key it is offered but reports the missing agent, never a vendor name
    $files = $this->getJson("/v1/services/{$service->id}/resources/files");
    expect($files->status())->toBeGreaterThanOrEqual(400)->and((string) $files->json('message'))->not->toMatch('/jailkit|ssh -i|authorized_keys/i');

    $run = function (string $action, array $params, string $key) use ($service): Operation {
        $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => $key])->assertAccepted();
        $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
        expect($operation->state)->toBe(Operation::SUCCEEDED, "operation {$action}: ".json_encode($operation->error));

        return $operation;
    };

    $run('errpages.set', ['enabled' => false], 'ep-1');
    expect(end($updates))->toMatchArray(['errordocs' => 0]);

    $run('directives.set', ['kind' => 'apache', 'content' => "Header set X-Frame-Options SAMEORIGIN\r\nRedirect 301 /old /new"], 'dir-1');
    expect(end($updates))->toMatchArray(['apache_directives' => "Header set X-Frame-Options SAMEORIGIN\nRedirect 301 /old /new"]);
    // customers tune their vhost; they never include files, load modules or change handlers
    foreach (['Include /etc/apache2/sites-enabled/other.conf', 'LoadModule evil_module /tmp/x.so', 'php_admin_value open_basedir none', 'SetHandler proxy:unix:/run/x.sock'] as $bad) {
        $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'directives.set', 'params' => ['kind' => 'apache', 'content' => $bad]], ['Idempotency-Key' => 'dir-bad-'.md5($bad)])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    }
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'directives.set', 'params' => ['kind' => 'rewrite', 'content' => 'x']], ['Idempotency-Key' => 'dir-kind'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    $run('folder.protect', ['path' => 'admin', 'user' => 'boss', 'password' => 'Correct-Horse-Battery-9'], 'fp-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sites_web_folder_add') && $r['params']['path'] === '/admin' && $r['params']['parent_domain_id'] === 7);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sites_web_folder_user_add') && $r['params']['username'] === 'boss' && $r['params']['password'] === 'Correct-Horse-Battery-9' && $r['params']['web_folder_id'] === 90);
    expect($this->getJson("/v1/services/{$service->id}/resources/protected_folders?fresh=1")->assertOk()->json('data'))->toBe([['remote_id' => '90', 'path' => '/admin', 'users' => ['boss'], 'active' => true]]);
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'folder.protect', 'params' => ['path' => '../../etc', 'user' => 'boss', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'fp-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    $run('dbuser.create', ['user' => 'ro', 'password' => 'Correct-Horse-Battery-9'], 'dbu-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sites_database_user_add') && $r['params']['database_user'] === "{$prefix}_ro" && $r['params']['database_password'] === 'Correct-Horse-Battery-9');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'dbuser.create', 'params' => ['user' => 'root user', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'dbu-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    $run('shell.create', ['user' => 'deploy', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => SSH_KEY], 'sh-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sites_shell_user_add') && $r['params']['username'] === "{$prefix}_deploy" && $r['params']['chroot'] === 'jailkit' && $r['params']['puser'] === 'web7' && $r['params']['dir'] === '/var/www/clients/client3/web7' && $r['params']['ssh_rsa'] === SSH_KEY);
    expect($this->getJson("/v1/services/{$service->id}/resources/shell_users?fresh=1")->assertOk()->json('data'))->toBe([['remote_id' => '30', 'user' => "{$prefix}_deploy", 'has_key' => true, 'chroot' => true, 'active' => true]]);
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'shell.create', 'params' => ['user' => 'ops', 'password' => 'Correct-Horse-Battery-9', 'ssh_key' => 'not a key']], ['Idempotency-Key' => 'sh-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $run('shell.key', ['remote_id' => '30', 'ssh_key' => ''], 'sh-2');
    expect($this->getJson("/v1/services/{$service->id}/resources/shell_users?fresh=1")->assertOk()->json('data.0.has_key'))->toBeFalse();

    $run('stats.set', ['type' => 'goaccess', 'password' => 'StatsPass-123'], 'st-1');
    expect(end($updates))->toMatchArray(['stats_type' => 'goaccess', 'stats_password' => 'StatsPass-123']);
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'stats.set', 'params' => ['type' => 'piwik']], ['Idempotency-Key' => 'st-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    $operation = $run('ssl.upload', ['cert' => CERT_PEM, 'key' => KEY_PEM], 'ssl-1');
    expect(end($updates))->toMatchArray(['ssl' => 'y', 'ssl_letsencrypt' => 'n', 'ssl_cert' => CERT_PEM, 'ssl_key' => KEY_PEM, 'ssl_bundle' => '', 'ssl_action' => 'save']);
    expect(json_encode($operation->result))->not->toContain('BEGIN PRIVATE KEY'); // the key never lands in the operation record
    expect(DB::table('audit_events')->where('action', 'service.action.ssl.upload')->value('detail'))->not->toContain('BEGIN PRIVATE KEY');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'ssl.upload', 'params' => ['cert' => 'garbage', 'key' => KEY_PEM]], ['Idempotency-Key' => 'ssl-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    expect(json_encode($this->getJson("/v1/services/{$service->id}/operations")->json()))->not->toMatch('/ispconfig|Correct-Horse|StatsPass/');
});

it('drives the aaPanel file manager, site password, rewrite rules, certificate upload and one-click applications', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $files = ['/www/wwwroot/shop.cz/wp-config.php' => "<?php\ndefine('DB_NAME', 'shop');\n"];
    $calls = [];
    Http::fake(function ($request) use (&$files, &$calls) {
        $url = $request->url();
        $body = $request->data();
        $calls[] = [substr($url, strlen(AAP)), $body];
        if (str_contains($url, 'files?action=GetDir')) {
            return Http::response(['DIR' => ['themes;4096;1725000000;755;www;www;'], 'FILES' => ['index.php;28;1725000001;644;www;www;', '.htaccess;120;1725000002;644;www;www;'], 'PATH' => $body['path']]);
        }
        if (str_contains($url, 'files?action=GetFileBody')) {
            return isset($files[$body['path']]) ? Http::response(['status' => true, 'data' => $files[$body['path']], 'encoding' => 'utf-8']) : Http::response(['status' => false, 'msg' => 'File not found']);
        }
        if (str_contains($url, 'files?action=SaveFileBody')) {
            $files[$body['path']] = $body['data'];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }
        if (str_contains($url, 'deployment?action=GetList')) {
            return Http::response(['list' => [['name' => 'WordPress', 'version' => '6.6', 'title' => 'WordPress'], ['name' => 'Joomla', 'version' => '5.1']]]);
        }
        if (str_contains($url, 'site?action=GetSSL')) {
            return Http::response(['status' => true, 'type' => 0, 'httpTohttps' => false, 'cert_data' => ['notAfter' => '2027-01-01', 'issuer' => 'Sectigo', 'dns' => ['shop.cz']]]);
        }
        if (str_contains($url, 'action=GetPHPVersion')) {
            return Http::response([['version' => '83', 'name' => 'PHP-8.3']]);
        }

        return Http::response(['status' => true, 'msg' => 'ok']);
    });
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    $f = $features['features'];
    expect($f['files']['enabled'])->toBeTrue()->and($f['apps']['enabled'])->toBeTrue()->and($f['directives'])->toBe(['enabled' => true, 'options' => ['rewrite']])->and($f['protected'])->toBe(['enabled' => true, 'options' => 'site'])
        ->and($f['ssl_upload']['enabled'])->toBeTrue()->and($f['shell']['enabled'])->toBeFalse()->and($f['errpages']['enabled'])->toBeFalse()->and($f['db_users']['enabled'])->toBeFalse()->and($f['stats']['enabled'])->toBeFalse()
        ->and($features['actions'])->toContain('file.save', 'file.mkdir', 'file.delete', 'app.install', 'folder.protect', 'directives.set', 'ssl.upload')->not->toContain('errpages.set', 'shell.create', 'dbuser.create', 'stats.set');

    // file manager: listings stay inside the site root, entries are typed and sized
    $listing = $this->getJson("/v1/services/{$service->id}/resources/files?path=wp-content")->assertOk()->json('data');
    expect($listing['path'])->toBe('/wp-content')->and(array_column($listing['entries'], 'name'))->toBe(['themes', '.htaccess', 'index.php'])
        ->and($listing['entries'][0])->toMatchArray(['type' => 'dir', 'size' => null])->and($listing['entries'][2])->toMatchArray(['type' => 'file', 'size' => 28])->and($listing['entries'][2]['modified'])->toStartWith('2024-08-30');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'GetDir') && $r['path'] === '/www/wwwroot/shop.cz/wp-content');
    $this->getJson("/v1/services/{$service->id}/resources/files?path=../other-customer")->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $this->getJson("/v1/services/{$service->id}/resources/files?path=wp-content/../../etc")->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    // downloads stream through the control plane (never a panel URL); the inline editor gets JSON
    $download = $this->get("/v1/services/{$service->id}/files/download?path=wp-config.php")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="wp-config.php"')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($download->getContent())->toBe("<?php\ndefine('DB_NAME', 'shop');\n");
    expect($this->getJson("/v1/services/{$service->id}/files/download?json=1&path=wp-config.php")->assertOk()->json('data'))->toMatchArray(['name' => 'wp-config.php', 'size' => 33]);
    $this->get("/v1/services/{$service->id}/files/download?path=../../etc/passwd")->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $this->get("/v1/services/{$service->id}/files/download?path=missing.txt")->assertNotFound();

    $run = function (string $action, array $params, string $key) use ($service): Operation {
        $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => $key])->assertAccepted();
        $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
        expect($operation->state)->toBe(Operation::SUCCEEDED, "operation {$action}: ".json_encode($operation->error));

        return $operation;
    };

    $run('file.save', ['path' => 'robots.txt', 'content' => "User-agent: *\nDisallow: /wp-admin/\n"], 'f-1');
    expect($files['/www/wwwroot/shop.cz/robots.txt'])->toBe("User-agent: *\nDisallow: /wp-admin/\n");
    $run('file.mkdir', ['path' => 'backup/2026'], 'f-2');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'CreateDir') && $r['path'] === '/www/wwwroot/shop.cz/backup/2026');
    $run('file.delete', ['path' => 'old.zip'], 'f-3');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'DeleteFile') && $r['path'] === '/www/wwwroot/shop.cz/old.zip');
    $run('file.delete', ['path' => 'cache', 'directory' => true], 'f-4');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'DeleteDir') && $r['path'] === '/www/wwwroot/shop.cz/cache');
    foreach ([['file.save', ['path' => '../../index.php', 'content' => 'x']], ['file.delete', ['path' => '']], ['file.mkdir', ['path' => 'a/./b']]] as [$action, $params]) {
        $this->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => 'f-bad-'.md5(json_encode($params))])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    }
    // the site root itself is never deleted, even with a fully valid request
    $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'file.delete', 'params' => ['path' => '/', 'directory' => true]], ['Idempotency-Key' => 'f-root'])->assertUnprocessable();

    // site-wide password: the panel stores it, the platform remembers the user so the listing can show it
    $run('folder.protect', ['user' => 'preview', 'password' => 'Correct-Horse-Battery-9'], 'pw-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'SetHasPwd') && (int) $r['id'] === 41 && $r['username'] === 'preview' && $r['password'] === 'Correct-Horse-Battery-9');
    expect($service->fresh()->desired_spec['site_password']['user'])->toBe('preview');
    expect($this->getJson("/v1/services/{$service->id}/resources/protected_folders?fresh=1")->assertOk()->json('data'))->toBe([['remote_id' => 'site', 'path' => '/', 'users' => ['preview'], 'active' => true]]);
    expect($this->getJson("/v1/services/{$service->id}/resources/site_settings?fresh=1")->assertOk()->json('data'))->toMatchArray(['site_password' => 'preview', 'document_root' => '/www/wwwroot/shop.cz', 'directives' => ['rewrite' => '']]);
    $run('folder.unprotect', ['remote_id' => 'site'], 'pw-2');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'CloseHasPwd') && (int) $r['id'] === 41);
    expect($service->fresh()->desired_spec)->not->toHaveKey('site_password');

    // rewrite rules are the only directives this panel offers; they land in the site's rewrite file and nginx reloads
    $run('directives.set', ['kind' => 'rewrite', 'content' => 'rewrite ^/old$ /new permanent;'], 'rw-1');
    expect($files['/www/server/panel/vhost/rewrite/shop.cz.conf'])->toBe('rewrite ^/old$ /new permanent;');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'ServiceAdmin') && $r['name'] === 'nginx' && $r['type'] === 'reload');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'directives.set', 'params' => ['kind' => 'apache', 'content' => 'x']], ['Idempotency-Key' => 'rw-bad'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'directives.set', 'params' => ['kind' => 'rewrite', 'content' => 'include /etc/nginx/other.conf;']], ['Idempotency-Key' => 'rw-bad2'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    $run('ssl.upload', ['cert' => CERT_PEM, 'key' => KEY_PEM, 'chain' => CERT_PEM], 'ssl-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'SetSSL') && (int) $r['type'] === 0 && $r['siteName'] === 'shop.cz' && $r['key'] === KEY_PEM && $r['csr'] === CERT_PEM."\n".CERT_PEM);

    // one-click applications
    expect($this->getJson("/v1/services/{$service->id}/resources/apps")->assertOk()->json('data'))->toBe([['name' => 'WordPress', 'version' => '6.6', 'title' => 'WordPress'], ['name' => 'Joomla', 'version' => '5.1', 'title' => null]]);
    $run('app.install', ['name' => 'WordPress', 'php_version' => '8.3'], 'app-1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'SetupPackage') && $r['dname'] === 'WordPress' && $r['site_name'] === 'shop.cz' && (string) $r['php_version'] === '83');

    // tabs the panel does not offer are refused before anything is queued
    foreach ([['errpages.set', ['enabled' => true]], ['shell.create', ['user' => 'x', 'password' => 'Correct-Horse-Battery-9']], ['stats.set', ['type' => 'awstats']]] as [$action, $params]) {
        $this->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => 'na-'.$action])->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
    }
    expect(json_encode($calls))->not->toContain('errpages');
});
