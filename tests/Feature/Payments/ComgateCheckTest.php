<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Payments\ComgateCheck;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Payments\Comgate\ComgateMode;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;
use Tests\TestCase;

/*
 * Owner decision H-R8 (2026-10-07, TASK-0129): there is no Comgate test account yet, but the administration must be able to check
 * the gateway — whether the platform holds its credentials, which mode it is in, whether it answers, and a 1 Kč TEST payment — with
 * a staff permission, a step-up, the bus and the audit; the test-mode switch takes a second person. Everything runs against
 * Http::fake (no request reaches Comgate), and no credential ever lands in a response, a log table or a setting.
 */

const COMGATE_CHECK_SECRET = 'cg-Secret-Value-9f3a';

beforeEach(function () {
    Http::preventStrayRequests();
    comgateCheckCredentials(null, null);
});

afterEach(function () {
    comgateCheckCredentials(null, null); // never leak credentials into the next test (the CheckoutTest lesson)
});

function comgateCheckCredentials(?string $merchant, ?string $secret): void
{
    foreach (['COMGATE_MERCHANT' => $merchant, 'COMGATE_SECRET' => $secret] as $key => $value) {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
    config(['onhost.payments.comgate.merchant' => $merchant, 'onhost.payments.comgate.secret_ref' => 'env://COMGATE', 'onhost.payments.comgate.test' => true]);
}

function comgateCheckStaff(string $role, bool $stepUp = true): User
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    if ($stepUp) {
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    }

    return $user;
}

function comgateCheckSend(TestCase $test, User $as, string $method, string $uri, array $body = []): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->withHeader('Idempotency-Key', 'cgc-'.Str::ulid())->json($method, $uri, $body);
}

/** The Comgate API double: payment methods, a test payment and its status. @param array<string,mixed> $gate */
function comgateCheckFake(array &$gate): void
{
    $gate += ['methods_status' => 200, 'created' => [], 'status_calls' => 0];
    Http::fake(function (Request $request) use (&$gate) {
        $url = $request->url();
        if (str_contains($url, 'payments.comgate.cz/v2.0/method.json')) {
            $gate['auth'] = $request->header('Authorization')[0] ?? '';

            return $gate['methods_status'] === 200
                ? Http::response(['methods' => [['id' => 'CARD_CZ_CSOB_2', 'name' => 'Karta'], ['id' => 'BANK_ALL', 'name' => 'Převod']]])
                : Http::response(['code' => 1400, 'message' => 'Unauthorized'], $gate['methods_status']);
        }
        if (str_contains($url, 'payments.comgate.cz/v2.0/payment/transId/')) {
            $gate['status_calls']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'TEST-CHK-0001', 'status' => 'PENDING', 'price' => 100, 'curr' => 'CZK', 'test' => true]);
        }
        if (str_ends_with($url, 'payments.comgate.cz/v2.0/payment')) {
            $gate['created'][] = $request->data();

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'TEST-CHK-0001', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=TEST-CHK-0001']);
        }

        return null;
    });
}

/** No credential anywhere it could be read back. */
function comgateCheckAssertNoSecret(string ...$extra): void
{
    $haystack = implode("\n", array_merge($extra, [
        (string) json_encode(DB::table('provider_calls')->get()), (string) json_encode(DB::table('audit_events')->get()), (string) json_encode(DB::table('system_settings')->get()),
    ]));
    expect($haystack)->not->toContain(COMGATE_CHECK_SECRET)->not->toContain(base64_encode('123456:'.COMGATE_CHECK_SECRET));
}

it('says plainly that the credentials are missing, and sends nothing to Comgate', function () {
    $admin = comgateCheckStaff('infrastructure_admin');
    $status = comgateCheckSend($this, $admin, 'GET', '/v1/staff/payments/comgate')->assertOk();
    expect($status->json('data.configured'))->toBeFalse()->and($status->json('data.credentials'))->toMatchArray(['merchant' => false, 'secret' => false, 'store' => 'env'])
        ->and($status->json('data.test_mode'))->toBeTrue()->and($status->json('data.test_mode_source'))->toBe('env');

    $check = comgateCheckSend($this, $admin, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'connection'])->assertOk();
    expect($check->json('ok'))->toBeFalse()->and($check->json('error'))->toBe('comgate_credentials_missing')
        ->and($check->json('missing'))->toBe(['merchant', 'secret'])->and($check->json('message'))->toContain('Chybí přihlašovací údaje Comgate');
    Http::assertNothingSent();
    expect(app(SettingsStore::class)->get(ComgateCheck::LAST_CHECK)['error'] ?? null)->toBe('comgate_credentials_missing')
        ->and(AuditEvent::query()->where('action', 'payments.comgate.check')->where('result', 'succeeded')->exists())->toBeTrue();
});

it('checks the connection with the vault credentials, and keeps no credential in the answer, the log or the setting', function () {
    comgateCheckCredentials('123456', COMGATE_CHECK_SECRET);
    $gate = [];
    comgateCheckFake($gate);
    $admin = comgateCheckStaff('infrastructure_admin');

    $status = comgateCheckSend($this, $admin, 'GET', '/v1/staff/payments/comgate')->assertOk();
    expect($status->json('data.configured'))->toBeTrue()->and($status->json('data.merchant_hint'))->toBe('•••456');
    $check = comgateCheckSend($this, $admin, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'connection'])->assertOk();
    expect($check->json('ok'))->toBeTrue()->and($check->json('methods'))->toBe(2)->and($check->json('http'))->toBe(200)
        ->and($gate['auth'])->toBe('Basic '.base64_encode('123456:'.COMGATE_CHECK_SECRET)); // the credentials went to Comgate, and only there
    comgateCheckAssertNoSecret((string) $status->getContent(), (string) $check->getContent());

    // the gateway refuses the credentials: said so, with the HTTP status, still no secret
    $gate['methods_status'] = 401;
    $refused = comgateCheckSend($this, $admin, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'connection'])->assertOk();
    expect($refused->json('ok'))->toBeFalse()->and($refused->json('error'))->toBe('comgate_credentials_refused')->and($refused->json('message'))->toContain('HTTP 401');
    comgateCheckAssertNoSecret((string) $refused->getContent());

    // the doctor reads the last check
    Artisan::call('onhost:doctor', ['--json' => true]);
    expect(Artisan::output())->toContain('Comgate answered the last administration check');
});

it('creates a 1 Kc test payment in test mode even when the gateway is live, reads it back and writes nothing to the ledger', function () {
    comgateCheckCredentials('123456', COMGATE_CHECK_SECRET);
    app(SettingsStore::class)->set(ComgateMode::SETTING, false); // the gateway is live
    $gate = [];
    comgateCheckFake($gate);
    $admin = comgateCheckStaff('infrastructure_admin');

    $check = comgateCheckSend($this, $admin, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'payment'])->assertOk();
    expect($check->json('ok'))->toBeTrue()->and($check->json('trans_id'))->toBe('TEST-CHK-0001')->and($check->json('status'))->toBe('PENDING')
        ->and($check->json('test_mode'))->toBeFalse()
        ->and($gate['created'])->toHaveCount(1)->and($gate['created'][0])->toMatchArray(['price' => 100, 'curr' => 'CZK', 'test' => true, 'prepareOnly' => true])
        ->and($gate['status_calls'])->toBe(1)
        ->and(PaymentIntent::query()->count())->toBe(0);
    comgateCheckAssertNoSecret((string) $check->getContent());
});

it('runs a check only for staff with the gateway permission and a fresh step-up', function () {
    comgateCheckCredentials('123456', COMGATE_CHECK_SECRET);
    $gate = [];
    comgateCheckFake($gate);

    $noStepUp = comgateCheckStaff('infrastructure_admin', stepUp: false);
    comgateCheckSend($this, $noStepUp, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'connection'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $reader = comgateCheckStaff('sre'); // may read the gateway's state, not act on it
    comgateCheckSend($this, $reader, 'GET', '/v1/staff/payments/comgate')->assertOk();
    comgateCheckSend($this, $reader, 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'connection'])->assertForbidden();
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum')->getJson('/v1/staff/payments/comgate')->assertForbidden();
    comgateCheckSend($this, comgateCheckStaff('infrastructure_admin'), 'POST', '/v1/staff/payments/comgate/check', ['kind' => 'refund'])->assertStatus(422);
    Http::assertNothingSent();
});

it('switches the test mode only with a second person, and the payments follow the switch', function () {
    comgateCheckCredentials('123456', COMGATE_CHECK_SECRET);
    $gate = [];
    comgateCheckFake($gate);
    $one = comgateCheckStaff('infrastructure_admin');
    $two = comgateCheckStaff('platform_owner'); // somebody who could do it and may decide
    $body = ['enabled' => false, 'reason' => 'Spuštění ostrých plateb'];

    $asked = (string) comgateCheckSend($this, $one, 'PUT', '/v1/staff/payments/comgate/test-mode', $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(ComgateMode::test())->toBeTrue();
    comgateCheckSend($this, $two, 'POST', "/v1/staff/approvals/{$asked}/decision", ['decision' => 'approved'])->assertOk();
    comgateCheckSend($this, $one, 'PUT', '/v1/staff/payments/comgate/test-mode', $body)->assertOk();
    expect(ComgateMode::test())->toBeFalse()->and(ComgateMode::source())->toBe('administration')->and(Approval::query()->findOrFail($asked)->state)->toBe('consumed');

    // a real payment is now created live
    app(ComgatePaymentProvider::class)->createPaymentIntent(Money::minor(10000, 'CZK'), ['method' => 'card', 'description' => 'Test', 'reference' => 'r1']);
    expect(end($gate['created'])['test'] ?? null)->toBeFalse();

    // the doctor names the mode the gateway is really in (the row blocks a production deploy while it is in test mode)
    Artisan::call('onhost:doctor', ['--json' => true]);
    expect(Artisan::output())->toContain('card gateway live mode');

    // back to what the deployment says: again a second person
    $back = ['enabled' => null, 'reason' => 'Zpět na COMGATE_TEST'];
    $asked = (string) comgateCheckSend($this, $one, 'PUT', '/v1/staff/payments/comgate/test-mode', $back)->assertForbidden()->json('approval_id');
    comgateCheckSend($this, $two, 'POST', "/v1/staff/approvals/{$asked}/decision", ['decision' => 'approved'])->assertOk();
    comgateCheckSend($this, $one, 'PUT', '/v1/staff/payments/comgate/test-mode', $back)->assertOk();
    expect(ComgateMode::source())->toBe('env')->and(ComgateMode::test())->toBeTrue();
    comgateCheckAssertNoSecret();
});

it('shows the check in the settings page behind the step-up dialog', function () {
    $this->actingAs($this->staff('sre'));
    expect($this->get('/sprava/nastaveni/integrace')->assertOk()->getContent())->toContain('Platební brána Comgate — test')
        ->toContain("'/staff/payments/comgate/check'")->toContain("guarded(function () { return api('PUT', '/staff/payments/comgate/test-mode'");
});
