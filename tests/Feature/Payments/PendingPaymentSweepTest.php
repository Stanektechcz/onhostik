<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PendingPaymentSweep;
use Onhost\Platform\Errors\DomainError;

/*
 * W6 (TASK-0228): when both the gateway's notification and the payer's return are lost, a card payment stayed pending for good
 * although the money was taken — nothing ever asked the provider again (`PaymentService::reconcile()` has no caller). The sweep
 * re-reads the status of open card intents between a grace period (the webhook and the return go first) and a cut-off, and
 * applies it through the idempotent `syncFromProvider`. A provider without credentials (staging has none for Comgate) is
 * skipped with a warning, never a failed scheduled job.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config([
        'onhost.payments.sweep.providers' => ['comgate', 'gopay', 'stripe'],
        'onhost.payments.sweep.grace_minutes' => 10,
        'onhost.payments.sweep.max_age_hours' => 72,
        'onhost.payments.sweep.batch' => 50,
        'onhost.payments.gopay.secret_ref' => 'env://W6_SWEEP_GOPAY_ABSENT',
        'onhost.payments.stripe.secret_ref' => 'env://W6_SWEEP_STRIPE_ABSENT',
    ]);
    w6SweepComgateCredentials('123456', 'w6-sweep-secret');
});

function w6SweepComgateCredentials(?string $merchant, ?string $secret): void
{
    foreach (['W6SWEEPCG_MERCHANT' => $merchant, 'W6SWEEPCG_SECRET' => $secret] as $key => $value) {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
    config(['onhost.payments.comgate.merchant' => $merchant, 'onhost.payments.comgate.secret_ref' => 'env://W6SWEEPCG', 'onhost.payments.comgate.test' => true]);
}

/** An intent `$minutesOld` minutes old, as the checkout left it. */
function w6SweepIntent(Organization $org, string $provider, string $providerId, int $minutesOld, string $state = 'PENDING_CUSTOMER'): PaymentIntent
{
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => $provider, 'provider_id' => $providerId, 'purpose' => 'topup',
        'amount_minor' => 50000, 'currency' => 'CZK', 'state' => $state, 'method' => 'card', 'idempotency_key' => 'w6-sweep-'.uniqid('', true)]);
    DB::table('payment_intents')->where('id', $intent->id)->update(['created_at' => now()->subMinutes($minutesOld), 'updated_at' => now()->subMinutes($minutesOld)]);

    return $intent->refresh();
}

/**
 * Comgate's status endpoint over Http::fake: `$answers` maps a transId to its status (`PAID`, `PENDING`, …) or to `error` (a
 * vendor error code). Every transId asked about is appended to `$asked`.
 *
 * @param  array<string,string>  $answers
 * @param  list<string>  $asked
 */
function w6SweepComgateFake(array $answers, array &$asked): void
{
    Http::fake(['payments.comgate.cz/v2.0/payment/transId/*' => function (Request $request) use ($answers, &$asked) {
        $transId = rawurldecode((string) substr((string) parse_url($request->url(), PHP_URL_PATH), strlen('/v2.0/payment/transId/')));
        $asked[] = $transId;
        $status = $answers[$transId] ?? 'PENDING';
        if ($status === 'error') {
            return Http::response(['code' => 1400, 'message' => 'Chyba databáze']);
        }

        return Http::response(['code' => 0, 'message' => 'OK', 'transId' => $transId, 'status' => $status, 'price' => 50000, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2']);
    }]);
}

it('settles a card payment the provider was paid for once the grace period is over', function () {
    [, $org] = $this->customerWithOrganization();
    $intent = w6SweepIntent($org, 'comgate', 'W6S1-PAID-0001', 45);
    $intent->forceFill(['raw' => ['redirect' => 'https://payments.comgate.cz/client/instructions/index?id=W6S1-PAID-0001']])->saveQuietly(); // the checkout's own payload
    $asked = [];
    w6SweepComgateFake(['W6S1-PAID-0001' => 'PAID'], $asked);

    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 1, settled 1, failed 0, open 0, errors 0')
        ->assertExitCode(0);

    expect($asked)->toBe(['W6S1-PAID-0001'])
        ->and($intent->refresh()->state)->toBe('SUCCEEDED')
        ->and($intent->paid_at)->not->toBeNull();
    // idempotent: the next run has nothing left to ask
    $this->artisan('onhost:payments:sweep')->expectsOutputToContain('checked 0')->assertExitCode(0);
    expect($asked)->toBe(['W6S1-PAID-0001']);
});

it('leaves intents inside the grace period, past the cut-off, of the bank and already final ones alone', function () {
    [, $org] = $this->customerWithOrganization();
    $fresh = w6SweepIntent($org, 'comgate', 'W6S2-FRESH-001', 5);
    $old = w6SweepIntent($org, 'comgate', 'W6S2-OLD-0001', 73 * 60);
    $bank = w6SweepIntent($org, 'bank', 'w6s2-bank-0001', 60);
    $paid = w6SweepIntent($org, 'comgate', 'W6S2-PAID-0001', 60, 'SUCCEEDED');
    $canceled = w6SweepIntent($org, 'comgate', 'W6S2-CANC-0001', 60, 'CANCELED');
    // a paid amount that differed went to finance already: asking again would open a reconciliation item every ten minutes
    $mismatch = w6SweepIntent($org, 'comgate', 'W6S2-MISM-0001', 60, 'PROCESSING');
    $mismatch->forceFill(['raw' => ['amount_mismatch' => ['minor' => 40000, 'currency' => 'CZK']]])->saveQuietly();
    $asked = [];
    w6SweepComgateFake(['W6S2-FRESH-001' => 'PAID', 'W6S2-OLD-0001' => 'PAID', 'W6S2-PAID-0001' => 'PAID', 'W6S2-CANC-0001' => 'PAID', 'W6S2-MISM-0001' => 'PAID'], $asked);

    $this->artisan('onhost:payments:sweep')->expectsOutputToContain('checked 0')->assertExitCode(0);

    expect($asked)->toBe([])
        ->and($fresh->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($old->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($bank->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($paid->refresh()->state)->toBe('SUCCEEDED')
        ->and($canceled->refresh()->state)->toBe('CANCELED')
        ->and($mismatch->refresh()->state)->toBe('PROCESSING');
});

it('asks about every open state, closes what the provider cancelled and caps the batch oldest first', function () {
    [, $org] = $this->customerWithOrganization();
    config(['onhost.payments.sweep.batch' => 3]);
    $created = w6SweepIntent($org, 'comgate', 'W6S3-CREA-0001', 600, 'CREATED');
    $auth = w6SweepIntent($org, 'comgate', 'W6S3-AUTH-0001', 500, 'AUTHENTICATION_REQUIRED');
    $processing = w6SweepIntent($org, 'comgate', 'W6S3-PROC-0001', 400, 'PROCESSING');
    $newest = w6SweepIntent($org, 'comgate', 'W6S3-NEWE-0001', 30);
    $asked = [];
    w6SweepComgateFake(['W6S3-CREA-0001' => 'CANCELLED', 'W6S3-AUTH-0001' => 'PENDING', 'W6S3-PROC-0001' => 'PAID', 'W6S3-NEWE-0001' => 'PAID'], $asked);

    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 3, settled 1, failed 1, open 1, errors 0')
        ->assertExitCode(0);

    expect($asked)->toBe(['W6S3-CREA-0001', 'W6S3-AUTH-0001', 'W6S3-PROC-0001'])
        ->and($created->refresh()->state)->toBe('CANCELED')
        ->and($processing->refresh()->state)->toBe('SUCCEEDED')
        ->and($newest->refresh()->state)->toBe('PENDING_CUSTOMER');
    $this->artisan('onhost:payments:sweep', ['--limit' => 10])->assertExitCode(0);
    expect($newest->refresh()->state)->toBe('SUCCEEDED');
});

it('goes on after one intent fails at the provider, logs only the class and the intent, and exits 0', function () {
    [, $org] = $this->customerWithOrganization();
    $broken = w6SweepIntent($org, 'comgate', 'W6S4-ERR-00001', 90);
    $paid = w6SweepIntent($org, 'comgate', 'W6S4-PAID-0001', 60);
    $asked = [];
    w6SweepComgateFake(['W6S4-ERR-00001' => 'error', 'W6S4-PAID-0001' => 'PAID'], $asked);
    Log::spy();

    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 2, settled 1, failed 0, open 0, errors 1')
        ->assertExitCode(0);

    expect($asked)->toBe(['W6S4-ERR-00001', 'W6S4-PAID-0001'])
        ->and($broken->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($paid->refresh()->state)->toBe('SUCCEEDED');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'payments sweep: intent not re-read'
        && $context === ['intent' => $broken->id, 'provider' => 'comgate', 'error' => DomainError::class]);
});

it('skips a provider without credentials with a warning and still sweeps the others', function () {
    [, $org] = $this->customerWithOrganization();
    $gopay = w6SweepIntent($org, 'gopay', '3000123456', 60);
    $stripe = w6SweepIntent($org, 'stripe', 'cs_test_w6sweep', 50);
    $comgate = w6SweepIntent($org, 'comgate', 'W6S5-PAID-0001', 40);
    $asked = [];
    w6SweepComgateFake(['W6S5-PAID-0001' => 'PAID'], $asked);
    Log::spy();

    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 1, settled 1, failed 0, open 0, errors 0, skipped gopay stripe')
        ->assertExitCode(0);

    expect($gopay->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($stripe->refresh()->state)->toBe('PENDING_CUSTOMER')
        ->and($comgate->refresh()->state)->toBe('SUCCEEDED');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'payments sweep: provider skipped, no credentials'
        && $context === ['provider' => 'gopay', 'error' => 'absent']);
});

it('runs green on a deployment with no gateway credentials at all, as staging is today', function () {
    [, $org] = $this->customerWithOrganization();
    w6SweepComgateCredentials(null, null);
    $intent = w6SweepIntent($org, 'comgate', 'W6S6-PEND-0001', 60);

    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 0, settled 0, failed 0, open 0, errors 0, skipped comgate gopay stripe')
        ->assertExitCode(0);
    expect($intent->refresh()->state)->toBe('PENDING_CUSTOMER'); // Http::preventStrayRequests: nothing reached a gateway
});

it('treats a Comgate merchant without its secret as no credentials, not as one error per intent', function () {
    [, $org] = $this->customerWithOrganization();
    w6SweepComgateCredentials('123456', null);
    w6SweepIntent($org, 'comgate', 'W6S7-PEND-0001', 60);

    $summary = app(PendingPaymentSweep::class)->run();

    expect($summary['checked'])->toBe(0)->and($summary['errors'])->toBe(0)->and($summary['skipped'])->toContain('comgate');
});

it('is scheduled every ten minutes on one server without overlapping', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'onhost:payments:sweep'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/10 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
