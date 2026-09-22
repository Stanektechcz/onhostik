<?php

declare(strict_types=1);

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Resilience\CircuitBreaker;
use Onhost\Providers\Contracts\ResourceRef;

/*
 * The circuit breaker of a panel stops every call to it for a minute once five calls in a row have failed: a panel that
 * is down is not hammered, and operations wait instead of failing. It judged a call by its HTTP status alone:
 *  - Proxmox answers an ordinary refusal with HTTP 500 — a VM locked by its backup, a VM that is not there — and so does
 *    a node of the cluster that is down; Pterodactyl answers 5xx when the Wings daemon of ONE node does not answer. Five of
 *    those, from one customer retrying an action, shut the panel off for every customer of every node.
 *  - ISPConfig, WEDOS and Subreg report their own failures in the body of an HTTP 200: the success recorded for the
 *    status reset the count first, so however long the panel kept failing, the breaker never tripped. And Subreg counted
 *    an HTTP 5xx twice.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

function breakerReason(int $status, string $reason, string $body = '{"data":null}'): PromiseInterface
{
    return Create::promiseFor(new Psr7Response($status, ['Content-Type' => 'application/json'], $body, '1.1', $reason));
}

function breakerState(string $instanceKey): string
{
    return app(ProviderHttpClient::class)->breaker($instanceKey)->state();
}

/** @param callable(): mixed $call */
function breakerRefused(callable $call): ?ProviderErrorCode
{
    try {
        $call();
    } catch (ProviderException $e) {
        return $e->errorCode;
    }

    return null;
}

it('does not shut a Proxmox cluster off because one VM refuses, one is gone, or one node is down', function () {
    Http::fake(function (Request $r) {
        $path = substr((string) parse_url($r->url(), PHP_URL_PATH), strlen('/api2/json'));

        return match (true) {
            str_contains($path, '/qemu/1042/') => breakerReason(500, 'VM 1042 is locked (backup)'),
            str_contains($path, '/qemu/1043/') => breakerReason(500, "Configuration file 'nodes/prg1-n2/qemu-server/1043.conf' does not exist"),
            str_starts_with($path, '/nodes/prg1-n3/') => breakerReason(595, 'No route to host'), // the API node cannot reach prg1-n3
            str_contains($path, '/qemu/1044/status/start') => Http::response(['data' => 'UPID:prg1-n2:000A1B2E:0004E1F7:66F0AA13:qmstart:1044:onhost@pve!cp:']),
            default => null,
        };
    });
    $adapter = app(ProviderRegistry::class)->forInstance(pveLab());

    // a customer's start retried inside the backup window, a VM somebody deleted, a VM on a node that is down
    foreach (range(1, 6) as $round) {
        expect(breakerRefused(fn () => $adapter->power(new ResourceRef('qemu', '1042', 'prg1-n2'), 'start')))->toBe(ProviderErrorCode::TRANSIENT)
            ->and(breakerRefused(fn () => $adapter->power(new ResourceRef('qemu', '1043', 'prg1-n2'), 'start')))->toBe(ProviderErrorCode::NOT_FOUND)
            ->and(breakerRefused(fn () => $adapter->power(new ResourceRef('qemu', '1050', 'prg1-n3'), 'start')))->toBe(ProviderErrorCode::TRANSIENT);
    }

    // it used to be: open after the fifth — and the next customer's VM on a healthy node refused for a minute
    expect(breakerState('proxmox-cz1'))->toBe(CircuitBreaker::CLOSED)
        ->and($adapter->power(new ResourceRef('qemu', '1044', 'prg1-n2'), 'start')->isAsync())->toBeTrue();
});

it('still gives a Proxmox cluster a rest when the cluster itself is failing', function () {
    Http::fake(fn () => breakerReason(500, 'cluster not ready - no quorum?'));
    $adapter = app(ProviderRegistry::class)->forInstance(pveLab());

    foreach (range(1, 5) as $round) {
        breakerRefused(fn () => $adapter->power(new ResourceRef('qemu', '1042', 'prg1-n2'), 'start'));
    }

    expect(breakerState('proxmox-cz1'))->toBe(CircuitBreaker::OPEN)
        ->and(breakerRefused(fn () => $adapter->power(new ResourceRef('qemu', '1044', 'prg1-n2'), 'start')))->toBe(ProviderErrorCode::CIRCUIT_OPEN);
});

it('does not shut the game panel off because the daemon of one node does not answer', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    Http::fake([
        // the panel answers; Wings on node 2 does not (Pterodactyl passes the daemon's failure on as a 5xx)
        PTERO.'/api/client/servers/e4c1abcd/power' => Http::response(['errors' => [['code' => 'DaemonConnectionException', 'status' => '504', 'detail' => 'An error was encountered while processing this request.']]], 504),
        PTERO.'/api/client/servers/f5d2bcde/power' => Http::response('', 204),
    ]);
    $adapter = app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail());

    foreach (range(1, 6) as $round) {
        expect(breakerRefused(fn () => $adapter->power(new ResourceRef('server', '77', '2', ['identifier' => 'e4c1abcd']), 'start')))->toBe(ProviderErrorCode::TRANSIENT);
    }

    // it used to be: open — every game server on every other node refused while one node was down
    expect(breakerState('pterodactyl-games01'))->toBe(CircuitBreaker::CLOSED)
        ->and($adapter->power(new ResourceRef('server', '78', '3', ['identifier' => 'f5d2bcde']), 'start')->completed)->toBeTrue();
});

it('gives ISPConfig a rest when its own database keeps failing, although it answers HTTP 200', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    Http::fake([
        ISP.'/remote/json.php?login' => Http::response(['code' => 'ok', 'message' => '', 'response' => 'sess-breaker']),
        ISP.'/remote/json.php?*' => Http::response(['code' => 'remote_fault', 'message' => 'Database connection failed: Too many connections', 'response' => false]),
    ]);
    $adapter = app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->findOrFail($service->provider_instance_id));

    foreach (range(1, 5) as $round) {
        expect(breakerRefused(fn () => $adapter->getActualState(new ResourceRef('web_domain', '7', '1'))))->toBe(ProviderErrorCode::TRANSIENT);
    }

    // it used to be: every HTTP 200 reset the count first, so the breaker of a failing panel never tripped
    expect(breakerState('ispconfig-shared01'))->toBe(CircuitBreaker::OPEN);
});

it('counts a Subreg server error once, not twice', function () {
    $_ENV['SUBREG_MAIN_LOGIN'] = 'onhost_api';
    $_ENV['SUBREG_MAIN_PASSWORD'] = 'subreg-secret';
    $instance = ProviderInstance::query()->updateOrCreate(['key' => 'subreg-main'], ['provider' => 'subreg', 'name' => 'Subreg', 'base_url' => 'https://subreg.cz', 'secret_ref' => 'env://SUBREG_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0', 'options' => []]);
    Http::fake(['subreg.cz/*' => fn (Request $r) => subregRequest($r)[0] === 'Login' ? null : Http::response('Internal Server Error', 500)]);
    subregFake(fn (string $function) => ['data' => ['ssid' => 'sess-breaker']]);
    $adapter = app(ProviderRegistry::class)->forInstance($instance);

    foreach (range(1, 4) as $round) {
        // it used to be: the third already found the breaker open — every 5xx was counted by the client and again by the gateway
        expect(breakerRefused(fn () => $adapter->domainInfo('shop.cz')))->toBe(ProviderErrorCode::TRANSIENT);
    }
    expect(breakerState('subreg-main'))->toBe(CircuitBreaker::CLOSED);
    breakerRefused(fn () => $adapter->domainInfo('shop.cz'));
    expect(breakerState('subreg-main'))->toBe(CircuitBreaker::OPEN);
});
