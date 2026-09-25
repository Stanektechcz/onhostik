<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Deployment;
use Onhost\Domain\Services\Models\DeploySource;
use Onhost\Domain\Services\Models\UptimeIncident;
use Onhost\Domain\Services\Models\UptimeMonitor as MonitorModel;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * The web toolkit as the customer meets it: the feature catalogue, the guarded terminal whose output comes back in the
 * operation, PHP settings, uptime monitoring with outage notifications, the backup schedule within the plan, staged
 * uploads, git deploy with the signed push webhook and releases on the node, and the staff panel login. The node is a
 * scripted shell; the panel API is faked; the vendor is never named.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

it('runs the toolkit on an aaPanel-backed site: terminal, PHP settings, monitoring, backup schedule, uploads, git deploy', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $shell = new ScriptedShell([
        '/ls -la/' => [0, "total 8\nindex.php\nwp-config.php\n"], // runs behind the site PHP on PATH
        '/php.* -m/' => [0, "[PHP Modules]\nCore\ncurl\nmbstring\n"], // the site's own PHP binary, quoted per platform
        '/git clone/' => [0, "abcdef0123456789abcdef0123456789abcdef01\nInitial commit\n"],
        '/^composer install/' => [0, 'Nothing to install, update or remove'],
    ]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;
    $runPath = '/';
    Http::fake(function ($request) use (&$runPath) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null; // other hosts (the monitored site) are stubbed separately
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        if (str_contains($q, 'SetSiteRunPath')) {
            $runPath = (string) ($body['runPath'] ?? '');

            return Http::response(['status' => true, 'msg' => 'ok']);
        }

        return match (true) {
            str_contains($q, 'GetSiteRunPath') => Http::response(['runPath' => $runPath]),
            str_contains($q, 'GetSitePHPVersion') => Http::response(['phpversion' => '83']),
            str_contains($q, 'GetDirUserINI') => Http::response(['runPath' => ['dirName' => '/www/wwwroot/shop.cz', 'runPath' => $runPath], 'userini' => true, 'logs' => true]),
            str_contains($q, 'GetFileBody') => Http::response(['status' => false, 'msg' => 'file does not exist']),
            str_contains($q, 'table=sites') => Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1']], 'page' => '']),
            str_contains($q, 'table=databases'), str_contains($q, 'table=backup') => Http::response(['data' => [], 'page' => '']),
            str_contains($q, 'GetCrontab') => Http::response([]),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    Http::fake(['shop.cz/*' => Http::sequence()->push('', 500)->push('', 500)->push('', 500)->push('<html>Shop is up</html>', 200)]);
    $this->actingAs($user, 'sanctum');
    $base = "/v1/services/{$service->id}";
    $action = function (string $action, array $params, string $key) use ($base) {
        return $this->postJson("{$base}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => $key]);
    };

    // 1. the catalogue: what the plan and the executor allow, never the vendor
    $features = $this->getJson("{$base}/features")->assertOk()->json('data');
    $f = $features['features'];
    expect($f['terminal']['enabled'])->toBeTrue()->and($f['php_settings']['enabled'])->toBeTrue()->and($f['security']['enabled'])->toBeTrue()->and($f['http3']['enabled'])->toBeTrue()->and($f['deploy']['enabled'])->toBeTrue()
        ->and($f['staging']['enabled'])->toBeFalse()->and($f['cdn']['enabled'])->toBeFalse()->and($f['monitoring'])->toBe(['enabled' => true, 'limit' => 1])->and($f['backup_schedule']['options']['frequency'])->toBe('daily')
        ->and($f['files_advanced']['enabled'])->toBeTrue()->and($f['backup_download']['enabled'])->toBeTrue()->and($f['wordpress']['enabled'])->toBeTrue()->and($f['panel_login']['enabled'])->toBeFalse()
        ->and($features['actions'])->toContain('command.run', 'php.settings', 'security.set', 'http3.set', 'deploy.run', 'deploy.rollback', 'file.rename', 'file.chmod', 'database.export', 'database.import', 'backup.delete', 'wp.update', 'import.run', 'node.create')
        ->not->toContain('staging.create', 'cdn.enable', 'mailbox.create')
        ->and(json_encode($features))->not->toMatch('/aapanel|ispconfig/i');

    // 2. the terminal: guarded at the API, executed as the site user in the document root, output in the operation
    $action('command.run', ['command' => 'sudo ls'], 'term-bad')->assertUnprocessable()->assertJsonPath('error', 'command_forbidden');
    $action('command.run', ['command' => 'php worker.php &'], 'term-bg')->assertUnprocessable()->assertJsonPath('error', 'command_invalid');
    $response = $action('command.run', ['command' => 'ls -la'], 'term-1')->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error));
    $row = collect($this->getJson("{$base}/operations")->assertOk()->json('data'))->firstWhere('id', $operation->id);
    expect($row['action'])->toBe('command.run')->and($row['result']['exit_code'])->toBe(0)->and($row['result']['output'])->toContain('index.php');
    $terminal = collect($shell->calls)->first(fn ($c) => str_contains($c['command'], 'ls -la'));
    expect($terminal['options']['user'])->toBe(Naming::prefix($service->id).'ag')->and($terminal['options']['cwd'])->toBe('/www/wwwroot/shop.cz')->and($terminal['command'])->toStartWith('export PATH='); // the site's agent user, never www (blocked by the node hardening)

    // 3. PHP settings: the site's php.ini keys the plan allows, extensions from the node
    $php = $this->getJson("{$base}/resources/php_settings")->assertOk()->json('data');
    expect($php['editable'])->toContain('memory_limit', 'upload_max_filesize')->and($php['extensions'])->toContain('curl', 'mbstring');
    $action('php.settings', ['settings' => ['disable_functions' => 'exec']], 'php-bad')->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $tools = $this->getJson("{$base}/resources/tools")->assertOk()->json('data');
    expect($tools['shell'])->toBeTrue()->and($tools['user'])->toBe(Naming::prefix($service->id).'ag')->and($tools['document_root'])->toBe('/www/wwwroot/shop.cz')->and(json_encode($tools))->not->toMatch('/aapanel/i');

    // 4. monitoring: a default check on the site, the plan limit, alerts on outage and recovery
    $monitoring = $this->getJson("{$base}/monitoring")->assertOk()->json('data');
    expect($monitoring['monitors'])->toHaveCount(1)->and($monitoring['monitors'][0]['url'])->toBe('https://shop.cz/')->and($monitoring['limit'])->toBe(1)->and($monitoring['threshold'])->toBe(3);
    expect($this->putJson("{$base}/monitoring", ['url' => 'https://shop.cz/health'])->status())->toBeGreaterThanOrEqual(400); // one check on this plan
    $monitorId = $monitoring['monitors'][0]['id'];
    $this->putJson("{$base}/monitoring", ['id' => $monitorId, 'keyword' => 'Shop', 'notify' => true])->assertOk();
    $monitor = MonitorModel::query()->findOrFail($monitorId);
    $checker = app(UptimeMonitor::class);
    foreach ([1, 2, 3] as $i) {
        $checker->check($monitor->fresh());
    }
    expect($monitor->fresh()->state)->toBe('down')->and(UptimeIncident::query()->where('monitor_id', $monitorId)->whereNull('resolved_at')->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'monitoring.down')->exists())->toBeTrue()
        ->and(Notification::query()->where('audience', 'customer')->where('title', 'Web neodpovídá')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'site-down')->exists())->toBeTrue();
    // the alarm says which service it is about, in every form it takes (H14)
    expect(OutboxMessage::query()->where('name', 'monitoring.down')->sole()->aggregate_id)->toBe($monitor->service_id)
        ->and(Notification::query()->where('title', 'Web neodpovídá')->sole()->ref_id)->toBe($monitor->service_id)
        ->and(MailOutbox::query()->where('template_key', 'site-down')->sole()->ref_id)->toBe($monitor->service_id);
    $checker->check($monitor->fresh());
    expect($monitor->fresh()->state)->toBe('up')->and(UptimeIncident::query()->where('monitor_id', $monitorId)->whereNull('resolved_at')->exists())->toBeFalse();
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'site-up')->exists())->toBeTrue();
    $status = $this->getJson("{$base}/monitoring")->assertOk()->json('data');
    expect($status['monitors'][0]['windows']['24h']['checks'])->toBe(4)->and($status['monitors'][0]['incidents'])->toHaveCount(1);

    // 5. backup schedule within the plan
    $this->putJson("{$base}/backups/schedule", ['frequency' => '15m'])->assertUnprocessable()->assertJsonPath('error', 'backup_frequency_above_plan');
    // 3 days / 2 generations is fewer than the plan's 7/7: the next tick prunes to it, so it takes a fresh step-up (TASK-0029 review round 2)
    $this->putJson("{$base}/backups/schedule", ['frequency' => 'daily', 'days' => 3, 'generations' => 2], ['Idempotency-Key' => 'wt-sched-nostepup'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $schedule = $this->putJson("{$base}/backups/schedule", ['frequency' => 'daily', 'days' => 3, 'generations' => 2, 'offsite' => true])->assertOk()->json('schedule');
    expect($schedule)->toMatchArray(['frequency' => 'daily', 'days' => 3, 'generations' => 2, 'offsite' => false, 'offsite_available' => false]);
    expect(BackupPolicy::query()->where('service_id', $service->id)->value('retention'))->toBe(['days' => 3, 'generations' => 2]);

    // 6. staged uploads and one-time downloads
    $upload = $this->post("{$base}/uploads", ['file' => UploadedFile::fake()->create('dump.sql', 5)])->assertOk()->json('data');
    expect($upload['upload_id'])->toMatch('/^up_[a-z0-9]{20}(\.[a-z0-9.]+)?$/')->and($upload['name'])->toBe('dump.sql'); // the id keeps the extension so imports can tell zip from tar
    $this->get("{$base}/downloads/dl_abcdefghijklmnopqrstuvwx")->assertStatus(410);
    $action('database.import', ['remote_id' => '4', 'upload_id' => 'up_nope'], 'imp-bad')->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');

    // 7. git deploy: connect, push webhook (HMAC), release on the node, rollback material, disconnect
    $connected = $this->putJson("{$base}/deploy", ['repository' => 'onhost/site', 'branch' => 'main', 'build_command' => 'composer install --no-dev'])->assertOk()->json();
    expect($connected['source']['repository'])->toBe('onhost/site')->and($connected['public_key'])->toStartWith('ssh-ed25519 ')->and(strlen((string) $connected['webhook_secret']))->toBeGreaterThanOrEqual(32);
    $secret = (string) $connected['webhook_secret'];
    $sourceId = (string) $connected['source']['id'];
    $deploy = $this->getJson("{$base}/deploy")->assertOk()->json('data');
    expect($deploy['configured'])->toBeTrue()->and($deploy['webhook_url'])->toContain("/v1/hooks/deploy/{$sourceId}")->and($deploy['strategy'])->toBe('run_path')->and($deploy['deployments'])->toBe([]);
    $this->putJson("{$base}/deploy", ['repository' => 'onhost/site', 'build_command' => 'sudo make me a sandwich'])->assertUnprocessable();
    // what is stored has the length of its column: a build command of 2 000 characters was accepted for a column of 500 — a 500 on PostgreSQL
    $this->putJson("{$base}/deploy", ['repository' => 'onhost/site', 'build_command' => 'npm run build -- '.str_repeat('x', 490)])->assertUnprocessable();
    $this->putJson("{$base}/deploy", ['repository' => 'https://git.example.com/'.str_repeat('r', 240).'.git'])->assertUnprocessable();

    $payload = json_encode(['ref' => 'refs/heads/main', 'after' => 'abcdef0123456789abcdef0123456789abcdef01', 'head_commit' => ['message' => 'Release 1.2', 'author' => ['name' => 'Jana']]]);
    $sign = fn (string $s) => 'sha256='.hash_hmac('sha256', $payload, $s);
    $this->call('POST', "/v1/hooks/deploy/{$sourceId}", [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sign('wrong-secret'), 'HTTP_X_GITHUB_EVENT' => 'push', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(401);
    $other = json_encode(['ref' => 'refs/heads/feature', 'after' => 'ffff']);
    $this->call('POST', "/v1/hooks/deploy/{$sourceId}", [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $other, $secret), 'HTTP_X_GITHUB_EVENT' => 'push', 'CONTENT_TYPE' => 'application/json'], $other)->assertOk()->assertJsonPath('accepted', false);
    $hook = $this->call('POST', "/v1/hooks/deploy/{$sourceId}", [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sign($secret), 'HTTP_X_GITHUB_EVENT' => 'push', 'CONTENT_TYPE' => 'application/json'], $payload)->assertAccepted();
    expect($hook->json('accepted'))->toBeTrue();
    driveOperations();
    $deployment = Deployment::query()->where('service_id', $service->id)->latest('created_at')->orderByDesc('id')->firstOrFail();
    expect($deployment->state)->toBe('succeeded', (string) $deployment->log)->and($deployment->triggered_by)->toBe('webhook')->and($deployment->sha)->toBe('abcdef0123456789abcdef0123456789abcdef01')->and($deployment->message)->toBe('Release 1.2')->and($deployment->author)->toBe('Jana')
        ->and($deployment->release)->not->toBeEmpty()->and($deployment->log)->toContain('composer install --no-dev');
    expect($runPath)->toContain('/.onhost/releases/'.$deployment->release); // the site now serves the release folder
    expect($shell->ran('git clone --quiet'))->toBeTrue()->and(collect($shell->calls)->first(fn ($c) => str_contains($c['command'], 'git clone'))['command'])->toContain('git@github.com:onhost/site.git')->toContain('deploy_key');
    expect(DeploySource::query()->findOrFail($sourceId)->last_deployment_id)->toBe($deployment->id);
    $listed = $this->getJson("{$base}/deploy")->assertOk()->json('data');
    expect($listed['deployments'][0]['state'])->toBe('succeeded')->and($listed['last_deployment']['id'])->toBe($deployment->id);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('title', 'Deploy dokončen')->exists())->toBeTrue();

    $rotated = $this->postJson("{$base}/deploy/rotate-secret")->assertOk()->json('webhook_secret');
    expect($rotated)->not->toBe($secret);
    $this->call('POST', "/v1/hooks/deploy/{$sourceId}", [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sign($secret), 'HTTP_X_GITHUB_EVENT' => 'push', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(401);
    $this->deleteJson("{$base}/deploy")->assertOk();
    expect($this->getJson("{$base}/deploy")->assertOk()->json('data.configured'))->toBeFalse();

    // 8. staff single sign-on into the panel: not available on this executor, and never for customers
    $this->getJson("/v1/staff/services/{$service->id}/panel-login")->assertStatus(403);
    $staff = $this->staff();
    $this->actingAs($staff, 'sanctum')->getJson("/v1/staff/services/{$service->id}/panel-login")->assertStatus(403)->assertJsonPath('error', 'step_up_required'); // a login into the customer's panel (TASK-0030 WP-B)
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->getJson("/v1/staff/services/{$service->id}/panel-login")->assertStatus(409)->assertJsonPath('error', 'panel_login_unavailable');
});
