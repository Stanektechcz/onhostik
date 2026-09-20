<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\UptimeMonitor as Monitor;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Services\Web\WordPressService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\EgressGuard;
use Tests\FakeHostResolver;

/*
 * A destination a CUSTOMER names is a request made from inside the management network. An uptime monitor on
 * `http://10.0.0.5:8888/` probed the panel every minute and reported status, timing and whether a keyword was in the
 * answer; a webhook and an import URL could be pointed the same way. Public addresses only, pinned, no redirects.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('lets through public destinations only, whatever the name says', function () {
    $guard = app(EgressGuard::class);
    FakeHostResolver::$hosts = [
        'panel.internal.example' => ['10.0.0.5'], 'rebind.example' => ['93.184.216.34', '127.0.0.1'], // one private answer is enough
        'v6.internal.example' => ['fd00::5'], 'nowhere.example' => [],
    ];
    foreach ([
        'http://127.0.0.1:8006/api2/json', 'http://localhost:8888/', 'https://[::1]/', 'http://10.0.0.5:8888/system', 'http://172.16.4.2/', 'http://192.168.1.1/',
        'http://169.254.169.254/latest/meta-data/', 'http://100.64.0.1/', 'http://0.0.0.0/', 'http://[::ffff:10.0.0.5]/', 'http://[fe80::1]/', 'http://224.0.0.1/',
        'https://panel.internal.example/', 'https://rebind.example/', 'https://v6.internal.example/', 'https://nowhere.example/',
        'ftp://example.com/x', 'gopher://example.com/', 'https://user:pass@example.com/', 'not a url',
    ] as $url) {
        expect(fn () => $guard->check($url))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('destination_not_allowed', $url));
    }
    foreach (['http://intranet/', 'https://pve.mgmt/', 'https://panel.corp.internal/', 'https://db.local:3306/'] as $local) { // refused by name, whatever they resolve to
        expect(fn () => $guard->check($local))->toThrow(DomainError::class);
    }

    // a public name is pinned to the address that was checked, and redirects are off
    $options = $guard->options('https://hooks.example.com:8443/in');
    expect($options['allow_redirects'])->toBeFalse()->and($options['curl'][CURLOPT_RESOLVE])->toBe(['hooks.example.com:8443:93.184.216.34']);
    expect($guard->check('https://93.184.216.34/')['ip'])->toBe('93.184.216.34');

    // the operator's own public management range, and a lab on private addresses
    config(['onhost.egress.deny_cidrs' => ['93.184.216.0/24']]);
    expect(fn () => $guard->check('https://hooks.example.com/'))->toThrow(DomainError::class);
    config(['onhost.egress.deny_cidrs' => [], 'onhost.egress.allow_cidrs' => ['10.20.0.0/16']]);
    expect($guard->check('http://10.20.3.4/')['ip'])->toBe('10.20.3.4');
    expect(fn () => $guard->check('http://10.21.3.4/'))->toThrow(DomainError::class);
});

it('keeps uptime checks, webhooks and imports out of the management network', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $this->actingAs($owner, 'sanctum');
    $sent = [];
    Http::fake(function (Request $request) use (&$sent) {
        $sent[] = $request->url();

        return Http::response('ok', 200);
    });

    // an uptime monitor pointed inwards is refused when it is saved …
    $this->putJson("/v1/services/{$service->id}/monitoring", ['url' => 'http://10.0.0.5:8888/system?action=GetSystemTotal', 'keyword' => 'cpu'])->assertStatus(422)->assertJsonPath('error', 'destination_not_allowed');
    $this->putJson("/v1/services/{$service->id}/monitoring", ['url' => 'http://127.0.0.1:8006/api2/json/version'])->assertStatus(422);
    // … and a saved one whose name starts pointing inwards later makes no request at all
    $monitor = Monitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://status.shop-example.cz/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'consecutive_failures' => 0]);
    FakeHostResolver::$hosts['status.shop-example.cz'] = ['10.0.0.5'];
    $sample = app(UptimeMonitor::class)->check($monitor);
    expect($sample->ok)->toBeFalse()->and((string) $sample->error)->toContain('cannot be used')->and($sent)->toBe([]);

    // a webhook endpoint
    $this->postJson('/v1/webhooks', ['url' => 'https://127.0.0.1:8888/hook'])->assertStatus(422)->assertJsonPath('error', 'destination_not_allowed');
    $created = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.shop-example.cz/in'])->assertCreated()->json('data');
    FakeHostResolver::$hosts['hooks.shop-example.cz'] = ['192.168.10.10']; // rebinding after the endpoint was accepted
    $delivery = WebhookDelivery::query()->create(['endpoint_id' => $created['id'], 'event' => 'service.activated', 'payload' => ['x' => 1], 'state' => 'pending', 'attempts' => 0]);
    expect(app(WebhookDispatcher::class)->deliver($delivery, WebhookEndpoint::query()->findOrFail($created['id'])))->toBe('failed')->and((string) $delivery->fresh()->last_error)->toContain('cannot be used')->and($sent)->toBe([]);

    // an import from a URL
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    foreach (['http://10.0.0.5:8888/backup.tar.gz' => 'destination_not_allowed', 'http://169.254.169.254/latest/user-data' => 'destination_not_allowed', 'httpx://example.com/a.zip' => 'action_param_invalid'] as $source => $error) {
        $this->withHeader('Idempotency-Key', 'imp-'.md5($source))->postJson("/v1/services/{$service->id}/actions", ['action' => 'import.run', 'params' => ['kind' => 'url', 'source' => $source]])->assertStatus(422)->assertJsonPath('error', $error);
    }
    expect($sent)->toBe([]);
});

it('lets a reverse proxy point at the customer\'s own app on the node, not at the node\'s panel or the network behind it', function () {
    $guard = app(EgressGuard::class);
    foreach (['http://127.0.0.1:3000', 'http://localhost:8000/api', 'https://upstream.example.com/'] as $ok) {
        $guard->checkUpstream($ok);
    }
    foreach (['http://127.0.0.1:8888/', 'http://127.0.0.1:8080', 'http://localhost:8006/api2/json', 'http://127.0.0.1:3306', 'http://127.0.0.1/', 'http://127.0.0.1:443',
        'http://10.0.0.5:8888', 'http://192.168.1.10:3000', 'http://169.254.169.254/', 'http://pve.mgmt:8006'] as $refused) {
        expect(fn () => $guard->checkUpstream($refused))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('destination_not_allowed', $refused));
    }
});

it('collects mail only from a public server and redirects a site only to an http(s) address', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org, 'posta-example.cz');
    $web = featureWebService($org, 'ispconfig');
    $this->actingAs($owner, 'sanctum');
    Queue::fake(); // the parameters are judged before anything is queued; nothing here reaches a panel
    FakeHostResolver::$hosts = ['imap.panel.example' => ['10.0.0.5'], 'imap.seznam.cz' => ['77.75.78.48']];
    $act = fn (string $service, string $action, array $params) => $this->postJson("/v1/services/{$service}/actions", ['action' => $action, 'params' => $params], ['Idempotency-Key' => 'b34-'.md5($action.json_encode($params))]);

    // the mail node connects wherever the customer says — four mail ports of the management network are still the management network
    $fetch = fn (string $host) => ['type' => 'imapssl', 'host' => $host, 'user' => 'jana', 'password' => 'Correct-Horse-Battery-9', 'destination' => 'jana@posta-example.cz'];
    foreach (['10.0.0.5', '127.0.0.1', 'imap.panel.example', 'mailstore.internal', 'mailhost'] as $host) {
        $act($mail->id, 'fetchmail.create', $fetch($host))->assertStatus(422)->assertJsonPath('error', 'destination_not_allowed');
    }
    $act($mail->id, 'fetchmail.create', $fetch('imap.seznam.cz'))->assertAccepted();
    expect(fn () => app(EgressGuard::class)->checkHost(''))->toThrow(DomainError::class)->and(fn () => app(EgressGuard::class)->checkHost('[::1]'))->toThrow(DomainError::class);
    app(EgressGuard::class)->checkHost('93.184.216.34');

    // a redirect target is written into the vhost by the panel: http(s), no credentials, URL characters only
    foreach ([
        'javascript://x%0aalert(1)', 'file:///etc/passwd', 'mailto:jana@shop.cz', 'ftp://files.shop.cz/', 'https://user:pass@new.shop.cz/', 'https://new.shop.cz:8443@evil.example/',
        'https://new.shop.cz/;}location=/x{return', 'https://new.shop.cz/$document_root', 'https://new.shop.cz/"x"', "https://new.shop.cz/'x'", 'https://new.shop.cz/\\x', 'https://new.shop.cz/`id`',
        'https://new.shop.cz/a b', "https://new.shop.cz/\nproxy_pass", '//new.shop.cz/', '/relative', 'https://'.str_repeat('a', 500).'.cz/',
    ] as $target) {
        $act($web->id, 'redirect.set', ['target' => $target])->assertStatus(422)->assertJsonPath('error', 'action_param_invalid')->assertJsonPath('field', 'target');
    }
    foreach (['https://new.shop.cz/', 'http://new.shop.cz:8080/cesta/index.php?a=1&b=2#kotva', 'https://xn--pklad-zsa96e.cz/akce/(2026)/', ''] as $target) {
        $act($web->id, 'redirect.set', ['target' => $target, 'type' => '302'])->assertAccepted();
        Operation::query()->where('service_id', $web->id)->delete(); // queued, never run here: the next action would wait for it
    }
});

it('follows the redirects of a customer\'s site only to public destinations when it checks the site after an update', function () {
    Http::fake([
        'https://shop-cz-staging.web.onhost.cz/cs/' => Http::response('<html>Vítejte</html>', 200),
        'https://shop-cz-staging.web.onhost.cz/' => Http::response('', 302, ['Location' => '/cs/']),
        'https://zly-cz-staging.web.onhost.cz/' => Http::response('', 302, ['Location' => 'http://10.0.0.5:8888/system?action=GetSystemTotal']),
        'https://kruh-cz-staging.web.onhost.cz/*' => Http::response('', 301, ['Location' => 'https://kruh-cz-staging.web.onhost.cz/dal']),
        'https://rozbity-cz-staging.web.onhost.cz/' => Http::response('<p>There has been a critical error on this website.</p>', 200),
    ]);
    $wp = app(WordPressService::class);

    expect($wp->healthCheck('https://shop-cz-staging.web.onhost.cz/'))->toBe(['ok' => true, 'status' => 200, 'error' => null]);
    $inward = $wp->healthCheck('https://zly-cz-staging.web.onhost.cz/');
    expect($inward['ok'])->toBeFalse()->and((string) $inward['error'])->toContain('cannot be used');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '10.0.0.5'));
    expect($wp->healthCheck('https://kruh-cz-staging.web.onhost.cz/'))->toMatchArray(['ok' => false, 'error' => 'the site keeps redirecting']);
    expect($wp->healthCheck('https://rozbity-cz-staging.web.onhost.cz/'))->toMatchArray(['ok' => false, 'error' => 'the site shows a WordPress error page']);
});
