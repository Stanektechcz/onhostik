<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Wedos\WedosErrorMap;
use Onhost\Providers\Wedos\WedosRegistrarProvider;

function wedosInstance(): ProviderInstance
{
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';

    return ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], [
        'provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0',
    ]);
}

function wedosAdapter(): WedosRegistrarProvider
{
    return app(ProviderRegistry::class)->forInstance(wedosInstance());
}

/** @param callable(string $command, array $data, array $payload): array $handler returns overrides for the response envelope */
function wapiFake(callable $handler): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use ($handler) {
        $payload = json_decode((string) $request['request'], true)['request'] ?? [];
        $overrides = $handler((string) ($payload['command'] ?? ''), (array) ($payload['data'] ?? []), $payload);

        return Http::response(['response' => array_merge([
            'code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'] ?? null, 'svTRID' => 'sv-'.substr(sha1(json_encode($payload)), 0, 8), 'command' => $payload['command'] ?? null, 'data' => [],
        ], $overrides)]);
    }]);
}

function wapiCommand(Request $request): string
{
    return (string) (json_decode((string) $request['request'], true)['request']['command'] ?? '');
}

it('signs every request with the hourly SHA-1 auth in Europe/Prague, a versioned clTRID and the test flag', function () {
    wapiFake(fn (string $command, array $data) => $command === 'domain-check' ? ['data' => ['domain' => ['name' => $data['name'], 'status' => 'free']]] : []);
    $result = wedosAdapter()->checkAvailability(['example.cz']);
    expect($result['example.cz'])->toBe(['available' => true, 'reason' => 'free']);
    $hour = now()->setTimezone('Europe/Prague')->format('H');
    Http::assertSent(function (Request $r) use ($hour) {
        $p = json_decode((string) $r['request'], true)['request'];

        return $r->isForm() && $p['user'] === 'onhost@onhost.cz' && $p['auth'] === sha1('onhost@onhost.cz'.sha1('wapi-secret').$hour)
            && $p['test'] === 1 && str_starts_with($p['clTRID'], 'onhost:v4:domain-check:') && $p['command'] === 'domain-check' && $p['data']['name'] === 'example.cz';
    });
});

it('refuses commands outside the allow-list or missing required fields before anything is sent', function () {
    wapiFake(fn () => []);
    $gateway = wedosAdapter()->gateway();
    expect(fn () => $gateway->command('domain-delete', ['name' => 'example.cz']))->toThrow(ProviderException::class, 'not allow-listed');
    expect(fn () => $gateway->command('domain-create', ['name' => 'example.cz']))->toThrow(ProviderException::class, 'requires field period');
    expect(fn () => $gateway->command('domain-info', ['name' => 'not a domain']))->toThrow(ProviderException::class, 'invalid domain name');
    Http::assertNothingSent();
});

it('normalises WAPI codes: 3201 => DOMAIN_NOT_AVAILABLE, 4205 => transient with retry-after, 3002 => registrar credit', function () {
    $codes = new ArrayIterator([3201, 4205, 3002]);
    wapiFake(function () use ($codes) {
        $code = $codes->current();
        $codes->next();

        return ['code' => $code, 'result' => "error {$code}"];
    });
    $adapter = wedosAdapter();
    $expectations = [
        [ProviderErrorCode::CONFLICT, WedosErrorMap::DOMAIN_NOT_AVAILABLE, null],
        [ProviderErrorCode::TRANSIENT, WedosErrorMap::REGISTRY_TEMPORARILY_UNAVAILABLE, 600],
        [ProviderErrorCode::CAPACITY, WedosErrorMap::INSUFFICIENT_REGISTRAR_CREDIT, 3600],
    ];
    foreach ($expectations as [$code, $normalized, $retry]) {
        try {
            $adapter->register('example.cz', ['period' => 1, 'registrant' => 'ONH-1', 'admin' => 'ONH-1'], 'onhost:v4:domain-create:op_test');
            $this->fail('expected a provider exception');
        } catch (ProviderException $e) {
            expect($e->errorCode)->toBe($code)->and($e->context['normalized'])->toBe($normalized)->and($e->retryAfterSeconds)->toBe($retry)->and($e->context['clTRID'])->toBe('onhost:v4:domain-create:op_test');
        }
    }
});

it('opens the WAPI circuit after two consecutive auth failures instead of hammering the endpoint (S33)', function () {
    wapiFake(fn () => ['code' => 2001, 'result' => 'Invalid user or authorization']);
    $adapter = wedosAdapter();
    foreach ([1, 2] as $attempt) {
        try {
            $adapter->domainInfo('example.cz');
            $this->fail('expected auth failure');
        } catch (ProviderException $e) {
            expect($e->errorCode)->toBe(ProviderErrorCode::AUTH);
        }
    }
    try {
        $adapter->domainInfo('example.cz');
        $this->fail('expected open circuit');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::CIRCUIT_OPEN);
    }
    Http::assertSentCount(2);
});

it('treats 1001 as accepted-async and resolves the outcome through domain-info, never by resending create (S34)', function () {
    $infoCalls = 0;
    wapiFake(function (string $command) use (&$infoCalls) {
        return match ($command) {
            'domain-create' => ['code' => 1001, 'result' => 'Accepted, pending registry'],
            'domain-info' => ['data' => ['domain' => ['name' => 'example.cz', 'status' => ++$infoCalls < 2 ? 'pending' : 'active', 'expiration' => '2027-09-06', 'created' => '2026-09-06', 'dns' => [['name' => 'ns1.onhost.cz'], ['name' => 'ns2.onhost.cz']], 'owner_c' => 'ONH-1', 'nsset' => 'NSSET-ONHOST']]],
            default => [],
        };
    });
    $adapter = wedosAdapter();
    $result = $adapter->register('example.cz', ['period' => 1, 'registrant' => 'ONH-1', 'admin' => 'ONH-1', 'nsset' => 'NSSET-ONHOST', 'rules' => ['person' => 'Jana Nováková']], 'onhost:v4:domain-create:op_1');
    expect($result->isAsync())->toBeTrue()->and($result->async->kind)->toBe('wapi_async')->and($result->async->handle)->toBe('example.cz');
    expect($adapter->awaitStatus($result->async)->state)->toBe(AsyncStatus::RUNNING);
    $status = $adapter->awaitStatus($result->async);
    expect($status->state)->toBe(AsyncStatus::SUCCEEDED)->and($status->detail['expires_at'])->toBe('2027-09-06')->and($status->detail['nameservers'])->toHaveCount(2);
    expect(collect(Http::recorded())->filter(fn (array $pair) => wapiCommand($pair[0]) === 'domain-create'))->toHaveCount(1);
    Http::assertSent(function (Request $r) {
        $p = json_decode((string) $r['request'], true)['request'];

        return $p['command'] === 'domain-create' && $p['data']['owner_c'] === 'ONH-1' && $p['data']['nsset'] === 'NSSET-ONHOST' && $p['data']['rules']['person'] === 'Jana Nováková' && ! isset($p['data']['dns']);
    });
});

it('keeps a reserve of the domain-family quota for critical registrar work', function () {
    config()->set('onhost.wapi.limits', ['all_per_hour' => 1000, 'domain_family_per_hour' => 2, 'reserve' => 0.5]);
    wapiFake(fn (string $command, array $data) => ['data' => ['domain' => ['name' => $data['name'] ?? 'x', 'status' => 'free']]]);
    $adapter = wedosAdapter();
    expect($adapter->checkAvailability(['a.cz'])['a.cz']['available'])->toBeTrue();
    try {
        $adapter->checkAvailability(['b.cz']);
        $this->fail('expected the non-critical quota to be exhausted');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::RATE_LIMIT);
    }
    expect($adapter->register('c.cz', ['period' => 1, 'registrant' => 'ONH-1', 'admin' => 'ONH-1'], 'onhost:v4:domain-create:op_c')->completed)->toBeTrue();
    $quota = $adapter->gateway()->quota();
    expect($quota['used_domain'])->toBe(2)->and($quota['remaining_domain'])->toBe(0);
});

it('reads credit, lists account movements and consumes the poll queue with explicit acks', function () {
    $queue = [['id' => 'n-1', 'type' => 'domain_transfer_out', 'name' => 'example.cz'], null];
    wapiFake(function (string $command, array $data) use (&$queue) {
        return match ($command) {
            'credit-info' => ['data' => ['credit' => '25000.50', 'currency' => 'czk']],
            'account-list' => ['data' => ['account' => [['id' => 1, 'amount' => '-179.00', 'note' => 'example.cz']]]],
            'poll-req' => ($event = array_shift($queue)) === null ? ['data' => []] : ['data' => ['event' => $event]],
            'poll-ack' => $data['id'] === 'n-1' ? [] : ['code' => 2101, 'result' => 'unknown id'],
            default => [],
        };
    });
    $adapter = wedosAdapter();
    expect($adapter->creditInfo())->toBe(['balance' => '25000.50', 'currency' => 'CZK']);
    expect($adapter->accountMovements('2026-09-01', '2026-09-06'))->toHaveCount(1);
    $event = $adapter->pollRequest();
    expect($event['id'])->toBe('n-1')->and($event['kind'])->toBe('domain_transfer_out')->and($event['fqdn'])->toBe('example.cz');
    $adapter->pollAck('n-1');
    expect($adapter->pollRequest())->toBeNull();
    Http::assertSent(fn (Request $r) => wapiCommand($r) === 'poll-ack' && json_decode((string) $r['request'], true)['request']['data']['id'] === 'n-1');
});
