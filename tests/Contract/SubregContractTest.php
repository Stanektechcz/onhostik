<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Subreg\SubregErrorMap;
use Onhost\Providers\Subreg\SubregRegistrarProvider;
use Onhost\Providers\Subreg\SubregSoapGateway;

function subregInstance(array $options = []): ProviderInstance
{
    $_ENV['SUBREG_MAIN_LOGIN'] = 'onhost_api';
    $_ENV['SUBREG_MAIN_PASSWORD'] = 'subreg-secret';

    return ProviderInstance::query()->updateOrCreate(['key' => 'subreg-main'], [
        'provider' => 'subreg', 'name' => 'Subreg', 'base_url' => 'https://subreg.cz', 'secret_ref' => 'env://SUBREG_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0', 'options' => $options,
    ]);
}

function subregAdapter(array $options = []): SubregRegistrarProvider
{
    $instance = subregInstance($options);
    app(ProviderRegistry::class)->forget($instance);

    return app(ProviderRegistry::class)->forInstance($instance);
}

beforeEach(function () {
    cache()->forget('onhost:subreg:ssid:subreg-main');
});

it('logs in once per session, injects the ssid into document/literal envelopes and refuses the hosting functions', function () {
    subregFake(fn (string $fn, array $p) => match ($fn) {
        'Login' => ['data' => ['ssid' => 'sess-1']],
        'Check_Domain' => ['data' => ['name' => $p['domain'], 'avail' => $p['domain'] === 'volna.cz' ? 1 : 0, 'price' => ['amount' => '140.00', 'premium' => 0, 'currency' => 'CZK']]],
        default => ['error' => ['unexpected', 505, 1003]],
    });
    $adapter = subregAdapter();
    $result = $adapter->checkAvailability(['volna.cz', 'obsazena.cz']);
    expect($result['volna.cz'])->toMatchArray(['available' => true, 'reason' => 'free'])->and($result['volna.cz']['price'])->toBe(['amount' => '140.00', 'currency' => 'CZK', 'premium' => false])
        ->and($result['obsazena.cz'])->toMatchArray(['available' => false, 'reason' => 'registered']);
    expect(subregCalls())->toBe(['Login', 'Check_Domain', 'Check_Domain']);
    Http::assertSent(function (Request $r) {
        [$fn, $params] = subregRequest($r);

        return $fn === 'Login' && $params === ['login' => 'onhost_api', 'password' => 'subreg-secret'] && $r->hasHeader('SOAPAction', '"http://subreg.cz/wsdl#Login"')
            && str_contains($r->url(), 'subreg.cz/soap/cmd.php?soap_format=1') && str_contains($r->body(), '<ns:Login xmlns:ns="http://subreg.cz/types">');
    });
    expect(subregParams('Check_Domain')[0])->toBe(['ssid' => 'sess-1', 'domain' => 'volna.cz']);

    $gateway = $adapter->gateway();
    expect(fn () => $gateway->call('List_Web_Hostings'))->toThrow(ProviderException::class, 'not allow-listed');
    expect(fn () => $gateway->call('Create_Web_Hosting_Mailbox', ['domain' => 'x.cz']))->toThrow(ProviderException::class, 'not allow-listed');
    expect(fn () => $gateway->order('Certificate_Request', 'x.cz', []))->toThrow(ProviderException::class, 'not allow-listed');
    Http::assertSentCount(3);
});

it('re-logs in once when the session expired and normalises major/minor error codes', function () {
    $checks = 0;
    $current = null; // error returned by Info_Domain / Get_Credit in the second half of the test
    subregFake(function (string $fn, array $p) use (&$checks, &$current) {
        if ($fn === 'Login') {
            return ['data' => ['ssid' => 'sess-'.uniqid()]];
        }
        if ($fn === 'Check_Domain') {
            return ++$checks === 1 ? ['error' => ['You are not logged', 500, 101]] : ['data' => ['name' => $p['domain'], 'avail' => 1]];
        }
        if ($current === 'fault') {
            return ['fault' => 'Internal Error'];
        }

        return ['error' => $current ?? ['unexpected', 505, 1003]];
    });
    $adapter = subregAdapter();
    expect($adapter->checkAvailability(['x.cz'])['x.cz']['available'])->toBeTrue();
    expect(subregCalls())->toBe(['Login', 'Check_Domain', 'Login', 'Check_Domain']);

    foreach ([
        [['Incorrect login or password', 500, 104], ProviderErrorCode::AUTH, SubregErrorMap::PROVIDER_AUTH_ERROR],
        [['Access denied for your IP', 500, 105], ProviderErrorCode::AUTH, SubregErrorMap::IP_NOT_ALLOWED],
        [['You are not allowed for this domain!', 501, 1004], ProviderErrorCode::NOT_FOUND, SubregErrorMap::OBJECT_NOT_FOUND],
        [['Timeout or unknown registry error', 502, 1001], ProviderErrorCode::TRANSIENT, SubregErrorMap::REGISTRY_TEMPORARILY_UNAVAILABLE],
        [['Empty required field period', 505, 1005], ProviderErrorCode::VALIDATION, SubregErrorMap::INVALID_REQUEST],
        [['Object already exists', 507, 1010], ProviderErrorCode::CONFLICT, SubregErrorMap::ALREADY_EXISTS],
        [['New or Pending order of this domain is already exists!', 600, 12345], ProviderErrorCode::CONFLICT, SubregErrorMap::ORDER_ALREADY_PENDING],
        [['Param curExpDate is not equal to exDate', 506, 1008], ProviderErrorCode::CONFLICT, SubregErrorMap::EXPIRY_MISMATCH],
        [['Insufficient credit', 602, 1001], ProviderErrorCode::CAPACITY, SubregErrorMap::INSUFFICIENT_REGISTRAR_CREDIT],
    ] as [$error, $code, $normalized]) {
        $current = $error;
        try {
            $adapter->domainInfo('x.cz');
            $this->fail('expected a ProviderException for '.$error[0]);
        } catch (ProviderException $e) {
            expect($e->errorCode)->toBe($code, $error[0])->and($e->context['normalized'])->toBe($normalized)->and($e->vendorCode)->toBe($error[1].'.'.$error[2]);
        }
    }
    $current = 'fault';
    expect(fn () => $adapter->creditInfo())->toThrow(ProviderException::class, 'SOAP fault: Internal Error');
});

it('registers through Make_Order, resolves the order through Info_Order and reads the registry truth back', function () {
    $state = ['async' => true];
    subregRegistryFake($state);
    $adapter = subregAdapter();
    $result = $adapter->register('example.cz', ['period' => 2, 'registrant' => 'G-000001', 'admin' => 'G-000001', 'nsset' => 'NSSET-ONHOST', 'rules' => ['person' => 'Jana']], 'onhost:v4:domain-create:op1');
    expect($result->isAsync())->toBeTrue()->and($result->async)->toBeInstanceOf(AsyncHandle::class)->and($result->async->meta['order_id'])->toBe('100')->and($result->async->kind)->toBe('subreg_order')
        ->and($result->ref?->remoteId)->toBe('example.cz');
    $order = subregParams('Make_Order')[0]['order'];
    expect($order['type'])->toBe('Create_Domain')->and($order['domain'])->toBe('example.cz')
        ->and($order['params']['period'])->toBe('2')->and($order['params']['registrant'])->toBe(['id' => 'G-000001'])->and($order['params']['contacts'])->toBe(['admin' => ['id' => 'G-000001']])->and($order['params']['ns'])->toBe(['nsset' => 'NSSET-ONHOST']);
    expect(subregCalls())->toBe(['Login', 'Make_Order', 'Info_Order']);

    $status = $adapter->awaitStatus($result->async);
    expect($status->state)->toBe(AsyncStatus::SUCCEEDED)->and($status->detail['expires_at'])->toBe('2027-09-06')->and($status->detail['status'])->toBe('active')->and($status->detail['nsset'])->toBe('');
    expect($state['registered'])->toBeTrue();

    $info = $adapter->domainInfo('example.cz');
    expect($info)->toMatchArray(['name' => 'example.cz', 'status' => 'active', 'expires_at' => '2027-09-06', 'registered_at' => '2026-09-06', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'], 'registrant' => 'G-000001', 'admin' => 'G-000001', 'dnssec' => false]);

    // hosts instead of NSSET, and a refused order surfaces as a conflict (never a blind resend)
    $state['registered'] = false;
    $adapter->register('other.com', ['period' => 1, 'registrant' => 'G-000002', 'nameservers' => ['ns1.example.net', 'ns2.example.net']], 'cl-2');
    $ns = subregParams('Make_Order')[1]['order']['params']['ns'];
    expect($ns)->toBe(['hosts' => [['hostname' => 'ns1.example.net'], ['hostname' => 'ns2.example.net']]]);
    $state['registered'] = true;
    expect(fn () => $adapter->register('example.cz', ['period' => 1, 'registrant' => 'G-000001'], 'cl-3'))->toThrow(ProviderException::class, 'Domain is not available');
});

it('renews with curExpDate, changes nameservers via ModifyNS_Domain, publishes DS records via Modify_Domain and creates contacts', function () {
    $state = ['registered' => true, 'expiration' => '2027-01-31'];
    subregRegistryFake($state);
    $adapter = subregAdapter();
    $renew = $adapter->renew('example.cz', 1, 'cl-renew');
    expect($renew->completed)->toBeTrue()->and($state['expiration'])->toBe('2028-01-31');
    expect(subregParams('Make_Order')[0]['order'])->toMatchArray(['type' => 'Renew_Domain', 'domain' => 'example.cz', 'params' => ['period' => '1', 'curExpDate' => '2027-01-31']]);

    $adapter->updateNameservers('example.cz', ['ns1.cust.cz', 'ns2.cust.cz'], null, 'cl-ns');
    expect(subregParams('Make_Order')[1]['order'])->toMatchArray(['type' => 'ModifyNS_Domain', 'params' => ['ns' => ['hosts' => [['hostname' => 'ns1.cust.cz'], ['hostname' => 'ns2.cust.cz']]]]]);
    $adapter->updateNameservers('example.cz', [], 'NSSET-CUSTOM', 'cl-ns2');
    expect(subregParams('Make_Order')[2]['order']['params'])->toBe(['ns' => ['nsset' => 'NSSET-CUSTOM']]);

    $adapter->updateKeyset('example.cz', ['ds' => ['12345 13 2 ABCDEF0123', ['tag' => 2, 'alg' => 8, 'digest_type' => 2, 'digest' => 'FF']]], 'cl-ds');
    $params = subregParams('Make_Order')[3]['order']['params']['params'];
    expect($params['dsdata'])->toBe([['tag' => '12345', 'alg' => '13', 'digest_type' => '2', 'digest' => 'ABCDEF0123'], ['tag' => '2', 'alg' => '8', 'digest_type' => '2', 'digest' => 'FF']]);
    $adapter->updateKeyset('example.cz', ['handle' => 'KEYSET-1'], 'cl-ks');
    expect(subregParams('Make_Order')[4]['order']['params']['params'])->toBe(['param' => 'keyset', 'value' => 'KEYSET-1']);

    $created = $adapter->createContact(['tld' => 'cz', 'handle' => 'ONH-ABC', 'first_name' => 'Jana', 'last_name' => 'Nováková', 'organization' => 'Firma s.r.o.', 'email' => 'jana@example.cz', 'phone' => '+420 777 123 456', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '110 00', 'country' => 'CZ', 'ico' => '12345678', 'dic' => 'CZ12345678', 'disclose' => 0], 'cl-c');
    expect($created['remote_id'])->toBe('G-000001');
    $contact = subregParams('Create_Contact')[0]['contact'];
    expect($contact)->toMatchArray(['name' => 'Jana', 'surname' => 'Nováková', 'org' => 'Firma s.r.o.', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'pc' => '110 00', 'cc' => 'CZ', 'phone' => '+420.777123456', 'email' => 'jana@example.cz'])
        ->and($contact['params'])->toMatchArray(['vat' => 'CZ12345678', 'ident_type' => 'ico', 'ident_number' => '12345678']);
    expect(SubregRegistrarProvider::e164('777123456'))->toBe('+420.777123456')->and(SubregRegistrarProvider::e164('00421 905 111 222', 'SK'))->toBe('+421.905111222')->and(SubregRegistrarProvider::e164('+1.5551234'))->toBe('+1.5551234')->and(SubregRegistrarProvider::e164('+49 30 1234', 'DE'))->toBe('+49.301234');

    $auth = $adapter->sendAuthInfo('example.cz', 'cl-auth');
    expect($auth->data)->toBe(['auth_info' => 'Auth-Secret-1', 'delivery' => 'inline']);
    $nsset = $adapter->createNsset('NSSET-ONHOST', ['ns1.onhost.cz', 'ns2.onhost.cz'], 'G-000001', 'cl-nsset');
    expect($nsset->completed)->toBeTrue()->and($nsset->ref?->remoteId)->toBe('NSSET-ONHOST');
    $obj = subregParams('Make_Order');
    $last = $obj[count($obj) - 1]['order'];
    expect($last)->toMatchArray(['type' => 'Create_Object', 'object' => 'NSSET-ONHOST'])->and($last['params']['type'])->toBe('nsset')->and($last['params']['params']['hosts'])->toBe([['hostname' => 'ns1.onhost.cz'], ['hostname' => 'ns2.onhost.cz']])->and($last['params']['params']['tech'])->toBe(['id' => 'G-000001']);
    expect($adapter->createNsset('NSSET-ONHOST', ['ns1.onhost.cz'], 'G-000001', 'cl-nsset2')->alreadyExisted)->toBeTrue();
});

it('publishes cost prices per TLD, credit, listing, transfer checks and the poll queue', function () {
    $state = ['registered' => true, 'prices' => ['cz' => ['register' => '140.00', 'renew' => '140.00', 'transfer' => '0.00', 'restore' => '700.00'], 'com' => ['register' => '9.56', 'renew' => '9.56', 'transfer' => '9.56', 'restore' => '60']], 'listing' => [['name' => 'example.cz', 'expire' => '2027-09-06', 'autorenew' => 0], ['name' => 'other.com', 'expire' => '2027-01-01', 'autorenew' => 1]], 'queue' => [['date' => '2026-09-07 10:00:00', 'id' => 7, 'orderid' => 100, 'orderstatus' => 'Completed', 'message' => '', 'errorcode' => 0]]];
    subregRegistryFake($state);
    $adapter = subregAdapter();
    expect($adapter->capabilities())->toMatchArray(['pricing' => true, 'test_mode' => false, 'domain.auth_info' => 'inline']);
    $costs = $adapter->costPrices(['cz', 'com', 'xyz']);
    expect(array_keys($costs))->toBe(['cz', 'com'])->and($costs['cz'])->toMatchArray(['currency' => 'CZK', 'register' => '140.00', 'renew' => '140.00', 'transfer' => '0.00', 'restore' => '700.00', 'min_years' => 1, 'max_years' => 10]);
    expect($adapter->creditInfo())->toMatchArray(['balance' => '12500.00', 'currency' => 'CZK']);
    expect($adapter->listDomains())->toBe([['name' => 'example.cz', 'status' => '', 'expires_at' => '2027-09-06', 'registered_at' => null, 'auto_renew' => 0], ['name' => 'other.com', 'status' => '', 'expires_at' => '2027-01-01', 'registered_at' => null, 'auto_renew' => 1]]);
    expect($adapter->tldPeriods('cz'))->toBe(['periods' => [1, 2, 3], 'default' => 1]);
    expect($adapter->transferCheck('example.cz'))->toMatchArray(['transferable' => false])->and($adapter->transferCheck('example.cz')['detail']['own_account'])->toBeTrue();

    $state['orders']['100'] = ['type' => 'Create_Domain', 'domain' => 'example.cz', 'status' => 'Completed'];
    $event = $adapter->pollRequest();
    expect($event)->toMatchArray(['id' => '7', 'kind' => 'create_domain.completed', 'fqdn' => 'example.cz'])->and($event['payload']['order_id'])->toBe('100');
    $adapter->pollAck('7');
    expect(subregParams('POLL_Ack')[0])->toBe(['ssid' => subregParams('POLL_Ack')[0]['ssid'], 'id' => '7']);
    expect($adapter->pollRequest())->toBeNull();
    expect($adapter->health()->healthy)->toBeTrue()->and($adapter->health()->detail['credit'])->toBe('12500.00');
});

it('has no test flag: test-mode registrations need a demoreg sandbox instance', function () {
    $state = [];
    subregRegistryFake($state);
    expect(fn () => subregAdapter()->register('x.cz', ['period' => 1, 'registrant' => 'G-1'], 'cl', true))->toThrow(ProviderException::class, 'demoreg.net');
    Http::assertNothingSent();
    $demo = subregAdapter(['demo' => true]);
    expect($demo->capabilities()['test_mode'])->toBeTrue()->and($demo->gateway()->endpoint())->toBe(SubregSoapGateway::DEMO_ENDPOINT);
    $demo->register('x.cz', ['period' => 1, 'registrant' => 'G-1'], 'cl', true);
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://demoreg.net/soap/cmd.php'));
});
