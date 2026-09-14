<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Providers\Contracts\Naming;

beforeEach(fn () => Http::preventStrayRequests());

it('exposes the executor\'s feature catalogue without naming the vendor and lists site resources from aaPanel', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $prefix = Naming::prefix($service->id);
    Http::fake([
        AAP.'/data?action=getData&table=databases' => Http::response(['data' => [['id' => 5, 'name' => "{$prefix}_shop", 'username' => "{$prefix}_shop", 'codeing' => 'utf8mb4'], ['id' => 6, 'name' => 'other_customer', 'username' => 'oc']], 'page' => '']),
        AAP.'/crontab?action=GetCrontab' => Http::response([['id' => 11, 'name' => "onhost:{$service->id}:nightly", 'type' => 'day', 'where_hour' => '3', 'where_minute' => '15', 'sBody' => 'php artisan queue:prune', 'status' => 1], ['id' => 12, 'name' => 'someone else', 'sBody' => 'rm -rf /']]),
        AAP.'/site?action=GetSSL' => Http::response(['status' => true, 'type' => 1, 'httpTohttps' => true, 'cert_data' => ['notAfter' => '2026-12-01', 'issuer' => "Let's Encrypt", 'dns' => ['shop.cz', 'www.shop.cz']]]),
        AAP.'/site?action=GetPHPVersion' => Http::response([['version' => '74', 'name' => 'PHP-7.4'], ['version' => '83', 'name' => 'PHP-8.3'], ['version' => '84', 'name' => 'PHP-8.4'], ['version' => '00', 'name' => 'Static']]),
    ]);
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    expect($features['features']['databases'])->toBe(['enabled' => true, 'limit' => 2])
        ->and($features['features']['ssh']['enabled'])->toBeFalse() // aaPanel offers no shell access
        ->and($features['features']['logs']['enabled'])->toBeTrue()
        ->and($features['features']['restore']['enabled'])->toBeTrue() // restore unpacks the archive over the site through the toolkit
        ->and($features['features']['terminal']['enabled'])->toBeTrue() // the plan carries the ssh entitlement: the toolkit terminal runs on the node
        ->and($features['features']['monitoring'])->toBe(['enabled' => true, 'limit' => 1])
        ->and($features['features']['staging']['enabled'])->toBeFalse()
        ->and($features['actions'])->toContain('database.create', 'cron.create', 'ssl.issue', 'redirect.set', 'subdomain.add')->not->toContain('mailbox.create', 'snapshot')
        ->and(json_encode($features))->not->toContain('aapanel')->not->toContain('aaPanel');

    expect($this->getJson("/v1/services/{$service->id}/resources/databases")->assertOk()->json('data'))->toBe([['remote_id' => '5', 'name' => "{$prefix}_shop", 'user' => "{$prefix}_shop", 'charset' => 'utf8mb4', 'size_bytes' => null]]);
    expect($this->getJson("/v1/services/{$service->id}/resources/cron")->assertOk()->json('data.0'))->toMatchArray(['remote_id' => '11', 'schedule' => '15 3 * * *', 'label' => 'nightly', 'active' => true]);
    expect($this->getJson("/v1/services/{$service->id}/resources/certificate")->assertOk()->json('data'))->toMatchArray(['issued' => true, 'letsencrypt' => true, 'https_forced' => true, 'domains' => ['shop.cz', 'www.shop.cz']]);
    expect($this->getJson("/v1/services/{$service->id}/resources/php")->assertOk()->json('data.versions'))->toBe(['7.4', '8.3', '8.4']);
    $this->getJson("/v1/services/{$service->id}/resources/mailboxes")->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
    $this->getJson("/v1/services/{$service->id}/resources/nope")->assertUnprocessable()->assertJsonPath('error', 'resource_kind_unknown');
});

it('creates and deletes a database, a cron job and an FTP account on aaPanel through operations with scoped names', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $prefix = Naming::prefix($service->id);
    // a stateful aaPanel: listings reflect what was added or deleted, like the real panel
    $panel = ['databases' => [], 'cron' => [], 'ftps' => []];
    Http::fake(function ($request) use (&$panel) {
        $url = $request->url();
        $body = $request->data();
        if (str_contains($url, 'table=databases')) {
            return Http::response(['data' => array_values($panel['databases'])]);
        }
        if (str_contains($url, 'AddDatabase')) {
            $panel['databases'][] = ['id' => 9, 'name' => $body['name'], 'username' => $body['db_user'], 'codeing' => $body['codeing'] ?? 'utf8mb4'];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }
        if (str_contains($url, 'DeleteDatabase')) {
            $panel['databases'] = array_values(array_filter($panel['databases'], fn ($d) => $d['id'] !== (int) $body['id']));

            return Http::response(['status' => true, 'msg' => 'ok']);
        }
        if (str_contains($url, 'GetCrontab')) {
            return Http::response(array_values($panel['cron']));
        }
        if (str_contains($url, 'AddCrontab')) {
            $panel['cron'][] = ['id' => 21, 'name' => $body['name'], 'type' => $body['type'], 'where_hour' => (string) $body['hour'], 'where_minute' => (string) $body['minute'], 'sBody' => $body['sBody'], 'status' => 1];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }
        if (str_contains($url, 'table=ftps')) {
            return Http::response(['data' => array_values($panel['ftps'])]);
        }
        if (str_contains($url, 'ftp?action=AddUser')) {
            $panel['ftps'][] = ['id' => 3, 'name' => $body['ftp_username'], 'path' => $body['path'], 'status' => 1];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }

        return Http::response(['status' => false, 'msg' => "unexpected {$url}"]);
    });
    $this->actingAs($user, 'sanctum');

    // database.create: the customer chooses the suffix, the control plane scopes the name and never returns the password
    $created = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'shop', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'db-1'])->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($created->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'AddDatabase') && $r['name'] === "{$prefix}_shop" && $r['db_user'] === "{$prefix}_shop" && $r['password'] === 'Correct-Horse-Battery-9');
    expect(json_encode($operation->result))->not->toContain('Correct-Horse');
    expect(DB::table('audit_events')->where('action', 'service.action.database.create')->value('payload'))->not->toContain('Correct-Horse');

    // limits from the plan: two databases allowed, a second one is fine, a bad name is rejected before any provider call
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'no spaces here', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'db-2'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'shop2', 'password' => 'short']], ['Idempotency-Key' => 'db-3'])->assertUnprocessable()->assertJsonPath('errors.password.0', fn ($m) => str_contains($m, '12'));

    $deleted = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.delete', 'params' => ['remote_id' => '9']], ['Idempotency-Key' => 'db-4'])->assertAccepted();
    expect(driveOperation(Operation::query()->findOrFail($deleted->json('operation_id')))->state)->toBe(Operation::SUCCEEDED);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'DeleteDatabase') && (int) $r['id'] === 9 && $r['name'] === "{$prefix}_shop");

    // cron.create carries the service label so the shared host scheduler can be listed per service
    $cron = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'cron.create', 'params' => ['schedule' => '30 2 * * *', 'command' => '/usr/bin/backup.sh', 'label' => 'backup']], ['Idempotency-Key' => 'cron-1'])->assertAccepted();
    $op = driveOperation(Operation::query()->findOrFail($cron->json('operation_id')));
    expect($op->state)->toBe(Operation::SUCCEEDED);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'AddCrontab') && $r['name'] === "onhost:{$service->id}:backup" && (int) $r['hour'] === 2 && (int) $r['minute'] === 30 && $r['sBody'] === '/usr/bin/backup.sh');
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'cron.create', 'params' => ['schedule' => 'daily', 'command' => 'x']], ['Idempotency-Key' => 'cron-2'])->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    // ftp.create scopes the user name and defaults the path to the site root
    $ftp = $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'ftp.create', 'params' => ['user' => 'deploy', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'ftp-1'])->assertAccepted();
    expect(driveOperation(Operation::query()->findOrFail($ftp->json('operation_id')))->state)->toBe(Operation::SUCCEEDED);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'AddUser') && $r['ftp_username'] === "{$prefix}_deploy" && $r['path'] === '/www/wwwroot/shop.cz');

    // an action the executor does not offer is refused before it is queued
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'mailbox.create', 'params' => ['address' => 'a@shop.cz', 'password' => 'Correct-Horse-Battery-9']], ['Idempotency-Key' => 'mb-1'])->assertUnprocessable()->assertJsonPath('error', 'feature_unavailable');
});

it('drives ISPConfig site features through the remote API: PHP version, HTTPS, subdomains, FTP and redirects', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $site = ['domain_id' => 7, 'domain' => 'shop.cz', 'sys_groupid' => 3, 'system_user' => 'web7', 'system_group' => 'client3', 'document_root' => '/var/www/clients/client3/web7', 'ssl' => 'y', 'ssl_letsencrypt' => 'y', 'rewrite_to_https' => 'n', 'subdomain' => 'www', 'redirect_type' => '', 'redirect_path' => '', 'hd_quota' => 51200];
    $calls = [];
    Http::fake(function ($request) use (&$calls, $site) {
        $function = substr((string) parse_url($request->url(), PHP_URL_QUERY), 0);
        $calls[] = $function;
        $body = $request->data();

        return Http::response(match ($function) {
            'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-1'],
            'sites_web_domain_get' => ['code' => 'ok', 'message' => '', 'response' => is_array($body['primary_id'] ?? null) ? [$site] : $site],
            'sites_web_subdomain_get' => ['code' => 'ok', 'message' => '', 'response' => [['domain_id' => 71, 'domain' => 'blog.shop.cz', 'redirect_path' => '/blog/']]],
            'sites_web_aliasdomain_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            'sites_web_aliasdomain_add' => ['code' => 'ok', 'message' => '', 'response' => 72],
            'sites_ftp_user_get' => ['code' => 'ok', 'message' => '', 'response' => []],
            'sites_ftp_user_add' => ['code' => 'ok', 'message' => '', 'response' => 55],
            'sites_web_domain_update' => ['code' => 'ok', 'message' => '', 'response' => true],
            'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'remote_fault', 'message' => "unexpected {$function}", 'response' => false],
        });
    });
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data.features');
    expect($features['ssh']['enabled'])->toBeTrue()->and($features['logs']['enabled'])->toBeFalse()->and($features['restore']['enabled'])->toBeTrue()->and($features['mail'])->toBe(['enabled' => true, 'limit' => 10]);
    expect($this->getJson("/v1/services/{$service->id}/resources/subdomains")->assertOk()->json('data'))->toBe([['remote_id' => 'sub:71', 'domain' => 'blog.shop.cz', 'path' => '/blog/']]);
    expect($this->getJson("/v1/services/{$service->id}/resources/certificate")->assertOk()->json('data'))->toMatchArray(['issued' => true, 'letsencrypt' => true, 'https_forced' => false, 'domains' => ['shop.cz', 'www.shop.cz']]);

    foreach ([
        ['php.set', ['version' => '8.4'], fn ($r) => ($r['params']['fastcgi_php_version'] ?? null) === 'PHP 8.4:/usr/bin/php-fpm8.4:/etc/php/8.4/fpm'],
        ['https.force', ['enabled' => true], fn ($r) => ($r['params']['rewrite_to_https'] ?? null) === 'y'],
        ['redirect.set', ['target' => 'https://new.shop.cz/', 'type' => '301'], fn ($r) => ($r['params']['redirect_type'] ?? null) === 'R=301,L' && ($r['params']['redirect_path'] ?? null) === 'https://new.shop.cz/'],
        ['subdomain.add', ['domain' => 'eshop.cz'], fn ($r) => ($r['params']['domain'] ?? null) === 'eshop.cz' && ($r['params']['parent_domain_id'] ?? null) === 7],
        ['ftp.create', ['user' => 'upload', 'password' => 'Correct-Horse-Battery-9', 'path' => 'web/uploads'], fn ($r) => str_ends_with((string) ($r['params']['username'] ?? ''), '_upload') && ($r['params']['dir'] ?? null) === '/var/www/clients/client3/web7/web/uploads' && ($r['params']['uid'] ?? null) === 'web7'],
    ] as [$action, $params, $check]) {
        $response = $this->postJson("/v1/services/{$service->id}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => "isp-{$action}"])->assertAccepted();
        $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
        expect($operation->state)->toBe(Operation::SUCCEEDED, "operation {$action}: ".json_encode($operation->error));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'json.php?') && is_array($r['params'] ?? null) && $check($r));
    }
    expect($service->fresh()->desired_spec['php_version'])->toBe('8.4');
    expect(json_encode($this->getJson("/v1/services/{$service->id}")->json()))->not->toContain('ispconfig');
});
