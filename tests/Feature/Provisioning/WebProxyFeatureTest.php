<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;

/*
 * Reverse proxies and default documents: a site on aaPanel forwards a path to an app on a port and chooses its index
 * order through the panel API; on ISPConfig the platform keeps both as a marked block inside the site's web server
 * directives — the customer sees the same feature on either executor and never learns which panel decides that.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('creates, lists and deletes reverse proxies and sets the default documents on an aaPanel site and, through the site directives, on an ISPConfig site', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $proxies = [];
    $index = 'index.php,index.html';
    Http::fake(function ($request) use (&$proxies, &$index) {
        if (! str_starts_with($request->url(), AAP)) {
            return null; // the ISPConfig fake below answers its own node
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();

        return match (true) {
            str_contains($q, 'action=GetProxyList') => Http::response(array_values($proxies)),
            str_contains($q, 'action=CreateProxy') => (function () use (&$proxies, $body) {
                $proxies[$body['proxyname']] = ['proxyname' => $body['proxyname'], 'proxydir' => $body['proxydir'], 'proxysite' => $body['proxysite'], 'type' => 1, 'cache' => (int) $body['cache'], 'todomain' => $body['todomain']];

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=RemoveProxy') => (function () use (&$proxies, $body) {
                unset($proxies[$body['proxyname']]);

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=GetIndex') => Http::response(['status' => true, 'msg' => '"'.$index.'"']), // the live panel quotes the list
            str_contains($q, 'action=SetIndex') => (function () use (&$index, $body) {
                $index = $body['Index'];

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $this->actingAs($user, 'sanctum');

    $features = $this->getJson("/v1/services/{$service->id}/features")->assertOk()->json('data');
    expect($features['features']['proxy']['enabled'])->toBeTrue()->and($features['features']['default_docs']['enabled'])->toBeTrue()->and($features['actions'])->toContain('proxy.create')->toContain('index.set');
    expect($this->getJson("/v1/services/{$service->id}/resources/proxies")->assertOk()->json('data'))->toBe([]);

    // the target must be an upstream URL, the name a short label
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxy.create', 'params' => ['name' => 'api', 'target' => 'ftp://nope', 'path' => '/api']])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');
    $op = Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxy.create', 'params' => ['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => 'api', 'cache' => false]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($op)->state)->toBe(Operation::SUCCEEDED, json_encode($op->fresh()->error));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'action=CreateProxy') && $r['proxyname'] === 'api' && $r['proxysite'] === 'http://127.0.0.1:3000' && $r['proxydir'] === '/api' && $r['todomain'] === '$host');
    $list = $this->getJson("/v1/services/{$service->id}/resources/proxies")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0])->toMatchArray(['remote_id' => 'api', 'name' => 'api', 'path' => '/api', 'target' => 'http://127.0.0.1:3000', 'enabled' => true, 'cache' => false]);

    // creating the same name again changes nothing; deleting it removes it
    expect(driveOperation(Operation::query()->findOrFail($this->withHeader('Idempotency-Key', 'proxy-again')->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxy.create', 'params' => ['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => 'api']])->assertStatus(202)->json('operation_id')))->state)->toBe(Operation::SUCCEEDED);
    $this->flushHeaders();
    expect(collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'action=CreateProxy'))->count())->toBe(1);
    $delete = Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxy.delete', 'params' => ['remote_id' => 'api']])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($delete)->state)->toBe(Operation::SUCCEEDED)->and($this->getJson("/v1/services/{$service->id}/resources/proxies")->assertOk()->json('data'))->toBe([]);

    // the whole list at once (automation): aaPanel gets the difference — one changed, one new, one gone; unchanged ones are left alone
    $seed = Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => [['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => 'api'], ['name' => 'ws', 'target' => 'http://127.0.0.1:4000', 'path' => 'ws']]]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($seed)->state)->toBe(Operation::SUCCEEDED, json_encode($seed->fresh()->error))->and(array_keys($proxies))->toBe(['api', 'ws']);
    $before = collect(Http::recorded())->count();
    $batch = Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => [['name' => 'api', 'target' => 'http://127.0.0.1:3001', 'path' => 'api'], ['name' => 'admin', 'target' => 'http://127.0.0.1:5000', 'path' => 'admin']]]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($batch)->state)->toBe(Operation::SUCCEEDED, json_encode($batch->fresh()->error))->and(array_keys($proxies))->toBe(['api', 'admin'])->and($proxies['api']['proxysite'])->toBe('http://127.0.0.1:3001');
    $calls = collect(Http::recorded())->slice($before)->map(fn ($p) => (string) parse_url($p[0]->url(), PHP_URL_QUERY))->filter(fn ($q) => str_contains($q, 'Proxy'))->values()->all();
    expect($calls)->toBe(['action=GetProxyList', 'action=GetProxyList', 'action=RemoveProxy', 'action=GetProxyList', 'action=CreateProxy', 'action=GetProxyList', 'action=RemoveProxy', 'action=GetProxyList', 'action=CreateProxy']); // api changed (remove + create), ws gone, admin new; the unchanged path costs nothing
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => [['name' => 'api', 'target' => 'http://127.0.0.1:3000'], ['name' => 'API', 'target' => 'http://127.0.0.1:3000']]]])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid');
    expect(driveOperation(Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => []]])->assertStatus(202)->json('operation_id')))->state)->toBe(Operation::SUCCEEDED)->and($proxies)->toBe([]);

    // default documents
    expect($this->getJson("/v1/services/{$service->id}/resources/default_docs")->assertOk()->json('data.names'))->toBe(['index.php', 'index.html']);
    $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'index.set', 'params' => ['names' => ['../etc/passwd', '']]])->assertStatus(422);
    $set = Operation::query()->findOrFail($this->postJson("/v1/services/{$service->id}/actions", ['action' => 'index.set', 'params' => ['names' => ['app.php', 'index.php', 'index.html']]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($set)->state)->toBe(Operation::SUCCEEDED);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'action=SetIndex') && $r['Index'] === 'app.php,index.php,index.html');
    expect($this->getJson("/v1/services/{$service->id}/resources/default_docs")->assertOk()->json('data.names'))->toBe(['app.php', 'index.php', 'index.html']);

    // ISPConfig: the same feature, kept as a managed block in the site's Apache directives (the customer's own line stays)
    $isp = featureWebService($org, 'ispconfig');
    $directives = "Header set X-Test \"1\"\n";
    Http::fake(function ($request) use (&$directives) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $answer = match ($function) {
            'login' => 'sess-proxy',
            'server_get' => ['web' => ['server_type' => 'apache']],
            'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'server_id' => 1, 'apache_directives' => $directives, 'nginx_directives' => ''],
            'sites_web_domain_update' => (function () use (&$directives, $body) {
                $directives = (string) $body['params']['apache_directives'];

                return true;
            })(),
            'sites_web_domain_jobqueue_count', 'server_get_serverid_by_ip', 'jobqueue_count' => 0,
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
    $features = $this->getJson("/v1/services/{$isp->id}/features")->assertOk()->json('data');
    expect($features['features']['proxy']['enabled'])->toBeTrue()->and($features['features']['default_docs']['enabled'])->toBeTrue()->and($features['actions'])->toContain('proxy.create', 'index.set');
    expect($this->getJson("/v1/services/{$isp->id}/resources/proxies")->assertOk()->json('data'))->toBe([])->and($this->getJson("/v1/services/{$isp->id}/resources/default_docs")->assertOk()->json('data.names'))->toBe([]);
    $op = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'proxy.create', 'params' => ['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => 'api']])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($op)->state)->toBe(Operation::SUCCEEDED, json_encode($op->fresh()->error));
    expect($directives)->toStartWith("Header set X-Test \"1\"\n# ONHOST-TOOLS-BEGIN ")->toContain("ProxyPass /api/ http://127.0.0.1:3000/\n")->toContain('# ONHOST-TOOLS-END');
    $list = $this->getJson("/v1/services/{$isp->id}/resources/proxies?fresh=1")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0])->toMatchArray(['remote_id' => 'api', 'path' => '/api', 'target' => 'http://127.0.0.1:3000', 'enabled' => true]);
    $set = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'index.set', 'params' => ['names' => ['app.php', 'index.php']]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($set)->state)->toBe(Operation::SUCCEEDED, json_encode($set->fresh()->error));
    expect($directives)->toContain("DirectoryIndex app.php index.php standard_index.html\n")->and($this->getJson("/v1/services/{$isp->id}/resources/default_docs?fresh=1")->assertOk()->json('data.names'))->toBe(['app.php', 'index.php']);
    $delete = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'proxy.delete', 'params' => ['remote_id' => 'api']])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($delete)->state)->toBe(Operation::SUCCEEDED)->and($directives)->not->toContain('ProxyPass')->toContain('DirectoryIndex app.php index.php');
    // the whole list at once: ISPConfig writes the vhost once whatever the number of proxies
    $writes = fn () => collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'sites_web_domain_update'))->count();
    $beforeBatch = $writes();
    $batchIsp = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => [['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => 'api'], ['name' => 'ws', 'target' => 'http://127.0.0.1:4000', 'path' => 'ws'], ['name' => 'admin', 'target' => 'http://127.0.0.1:5000', 'path' => 'admin']]]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($batchIsp)->state)->toBe(Operation::SUCCEEDED, json_encode($batchIsp->fresh()->error))->and($writes() - $beforeBatch)->toBe(1)
        ->and($directives)->toContain('ProxyPass /api/ http://127.0.0.1:3000/')->toContain('ProxyPass /ws/ http://127.0.0.1:4000/')->toContain('ProxyPass /admin/ http://127.0.0.1:5000/');
    expect(collect($this->getJson("/v1/services/{$isp->id}/resources/proxies?fresh=1")->assertOk()->json('data'))->pluck('name')->all())->toBe(['api', 'ws', 'admin']);
    $clear = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'proxies.set', 'params' => ['items' => []]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($clear)->state)->toBe(Operation::SUCCEEDED)->and($directives)->not->toContain('ProxyPass')->toContain('DirectoryIndex app.php index.php');

    // an empty list restores the web server's default order: the managed block disappears, the customer's line stays
    $reset = Operation::query()->findOrFail($this->postJson("/v1/services/{$isp->id}/actions", ['action' => 'index.set', 'params' => ['names' => []]])->assertStatus(202)->json('operation_id'));
    expect(driveOperation($reset)->state)->toBe(Operation::SUCCEEDED, json_encode($reset->fresh()->error))->and($directives)->toBe('Header set X-Test "1"')->and($this->getJson("/v1/services/{$isp->id}/resources/default_docs?fresh=1")->assertOk()->json('data.names'))->toBe([]);
    expect(json_encode(Http::recorded()))->not->toMatch('/onhost-remote|remote-secret|"secret"/');
});
