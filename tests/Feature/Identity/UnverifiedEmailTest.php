<?php

declare(strict_types=1);

use App\Http\Support\PublicApiDocs;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\EmailVerificationGuard;
use Onhost\Domain\Identity\Models\EmailVerificationToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Notifications\VerifyEmailNotification;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Owner decision R5 of the 2026-10 readiness audit (TASK-0096): an e-mail that is not verified blocks partner payouts and
 * orders above 5 000 Kč, and there is an endpoint to send the verification mail again. The rule is the server's, asked in
 * the places the money moves (CheckoutService::placeOrder, PartnerPayouts) — never a hidden button.
 *
 * Blocked while unverified: an order above the limit (any door: the panel, a token, the guest checkout), requesting a partner
 * payout (also the automatic one), and paying one out. Kept: sign-in, the profile, reading everything, smaller orders,
 * tickets, and asking for the mail again.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
    config(['onhost.vies.enabled' => false]);
});

function uvmQuote(Organization $org, string $plan = 'start'): Quote
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => $plan]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
}

function uvmOrder(TestCase $test, User $as, Organization $org, ?Quote $quote = null): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->postJson('/v1/orders', [
        'quote_id' => ($quote ?? uvmQuote($org))->id, 'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []], 'payment' => ['mode' => 'bank'],
    ], ['Idempotency-Key' => (string) Str::ulid(), 'X-Organization' => $org->id]);
}

function uvmVerificationToken(User $user): string
{
    $plain = Str::random(48);
    EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'purpose' => 'verify', 'expires_at' => now()->addDay()]);

    return $plain;
}

function uvmPartner(Organization $org, int $payableMinor = 300000): Partner
{
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($org, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    partnerConfirmedPayoutAccount($partner);
    PartnerCommission::query()->create([
        'partner_id' => $partner->id, 'organization_id' => $org->id, 'invoice_id' => 'inv-'.uniqid('', true), 'period' => now()->format('Y-m'), 'kind' => 'share',
        'base_minor' => $payableMinor * 5, 'rate_pct' => 20, 'amount_minor' => $payableMinor, 'currency' => 'CZK', 'state' => 'payable', 'payout_id' => null, 'invoice_paid_at' => now()->subDays(2),
    ]);

    return $partner->fresh();
}

function uvmUserContext(User $user, ?Organization $org = null): CommandContext
{
    return new CommandContext('user', $user->id, $org?->id, null, '127.0.0.1', 'pest', 'uvm-session');
}

// ── orders ──

it('refuses an order above the limit while the e-mail is unverified, with a slug and a way out', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    $total = (int) uvmQuote($org)->total_minor;
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => $total - 1]]);

    $response = uvmOrder($this, $owner, $org)->assertForbidden();
    $response->assertJsonPath('error', 'email_unverified')->assertJsonPath('help', '/dokumentace/api#email-unverified')->assertJsonPath('resend', '/v1/me/email/verification');
    expect($response->json('message'))->toContain('ověř')->and(Order::query()->count())->toBe(0);
});

it('lets an unverified account order up to the limit, and the default limit is 5 000 Kč', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    $total = (int) uvmQuote($org)->total_minor;
    expect($total)->toBeLessThanOrEqual(500000); // the starter plan is under the default limit

    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => $total]]); // exactly the limit is still allowed: "above" is the rule
    uvmOrder($this, $owner, $org)->assertCreated();

    $context = uvmUserContext($owner, $org);
    config(['onhost.identity.unverified_order_limit_minor' => null]);
    EmailVerificationGuard::assertMayPlaceOrder(500000, 'CZK', $context); // 5 000,00 Kč with the default limit
    expect(fn () => EmailVerificationGuard::assertMayPlaceOrder(500001, 'CZK', $context))->toThrow(DomainError::class)
        ->and(fn () => EmailVerificationGuard::assertMayPlaceOrder(20001, 'EUR', $context))->toThrow(DomainError::class);
    EmailVerificationGuard::assertMayPlaceOrder(20000, 'EUR', $context);
});

it('lifts the block the moment the e-mail is verified', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 1]]);
    uvmOrder($this, $owner, $org)->assertForbidden()->assertJsonPath('error', 'email_unverified');

    $this->postJson('/v1/auth/verify-email', ['token' => uvmVerificationToken($owner)])->assertOk()->assertJsonPath('data.verified', true);

    uvmOrder($this, $owner, $org)->assertCreated();
});

it('asks the same of a personal API token, which is the person: through the bus, and a token has no route to orders anyway', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 1]]);

    // the context a token request builds is the token owner's (ApiContext::context): the same rule applies
    expect(fn () => EmailVerificationGuard::assertMayPlaceOrder(500, 'CZK', uvmUserContext($owner, $org)))->toThrow(DomainError::class);

    $plain = $owner->createToken('ci', ['services:read'])->plainTextToken;
    $this->withToken($plain)->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => (string) Str::ulid()])
        ->postJson('/v1/orders', ['quote_id' => uvmQuote($org)->id, 'consents' => [], 'payment' => ['mode' => 'bank']])->assertForbidden();
    expect(Order::query()->count())->toBe(0);
});

it('does not ask staff, a service account or a verified person', function () {
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 1]]);
    [$verified, $org] = $this->customerWithOrganization();
    $staff = User::factory()->staff()->unverified()->create();

    EmailVerificationGuard::assertMayPlaceOrder(9_000_000, 'CZK', uvmUserContext($verified, $org));
    EmailVerificationGuard::assertMayPlaceOrder(9_000_000, 'CZK', uvmUserContext($staff, $org));
    EmailVerificationGuard::assertMayPlaceOrder(9_000_000, 'CZK', CommandContext::system('test'));
    EmailVerificationGuard::assertMayPlaceOrder(9_000_000, 'CZK', new CommandContext('service_account', 'sa_1', $org->id, null, '127.0.0.1', 'pest', null));
    expect(true)->toBeTrue();
});

it('asks guest checkout too: the account is made, the order is not, and the verification mail goes out', function () {
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 100]]);
    $payload = ['customer' => ['email' => 'host@firma.cz', 'name' => 'Petr Host', 'country' => 'CZ'], 'items' => [['product_key' => 'web-hosting', 'plan_key' => 'start']], 'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []], 'payment' => ['mode' => 'bank'], 'terms' => true];

    $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'uvm-guest'])->postJson('/v1/checkout/guest', $payload)->assertForbidden()->assertJsonPath('error', 'email_unverified');

    $user = User::query()->where('email', 'host@firma.cz')->firstOrFail();
    expect(Order::query()->count())->toBe(0)->and($user->email_verified_at)->toBeNull()
        ->and(EmailVerificationToken::query()->where('user_id', $user->id)->where('purpose', 'verify')->exists())->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('treats a set-password link as proof of the mailbox', function () {
    $user = User::factory()->unverified()->create();
    $plain = Str::random(48);
    EmailVerificationToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'purpose' => 'reset', 'expires_at' => now()->addHour()]);

    $this->postJson('/v1/auth/password/reset/confirm', ['token' => $plain, 'password' => 'Brand-New-Passw0rd-77'])->assertOk();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

// ── partner payouts ──

it('refuses a payout request while the partner owner\'s e-mail is unverified, and takes it once verified', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    $partner = uvmPartner($org);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $headers = ['X-Organization' => $org->id];

    $this->withHeaders($headers + ['Idempotency-Key' => 'uvm-p1'])->postJson('/v1/partner/payouts', ['amount' => 1500])->assertForbidden()->assertJsonPath('error', 'email_unverified')->assertJsonPath('help', '/dokumentace/api#email-unverified');
    expect(PartnerPayout::query()->count())->toBe(0)->and(PartnerCommission::query()->where('state', 'payable')->count())->toBe(1);

    $this->postJson('/v1/auth/verify-email', ['token' => uvmVerificationToken($owner)])->assertOk();

    $this->withHeaders($headers + ['Idempotency-Key' => 'uvm-p2'])->postJson('/v1/partner/payouts', ['amount' => 1500])->assertCreated();
    expect(PartnerPayout::query()->count())->toBe(1);
    expect($partner->id)->not->toBeEmpty();
});

it('skips the automatic payout of an unverified partner and does not pay a payout out', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    $partner = uvmPartner($org);
    $partner->forceFill(['payout_terms' => 'monthly'])->save();

    $stats = app(PartnerService::class)->autoPayouts();
    expect($stats['requested'])->toBe(0)->and(PartnerPayout::query()->count())->toBe(0);

    $payout = PartnerPayout::query()->create([
        'partner_id' => $partner->id, 'number' => 'PO-UVM-1', 'amount_minor' => 150000, 'currency' => 'CZK', 'method' => 'bank_transfer', 'iban' => 'CZ6508000000192000145399', 'state' => 'approved',
        'self_billing' => ['number' => 'PO-UVM-1'], 'requested_at' => now()->subDay(),
    ]);
    $staff = $this->steppedUpStaff();
    expect(fn () => app(PartnerService::class)->markPayoutPaid($payout, 'BANK-1', $this->staffContextFor($staff)))->toThrow(DomainError::class, 'ověř');
    expect($payout->fresh()->state)->toBe('approved');
});

// ── what stays ──

it('keeps sign-in, the profile, reading, tickets and the resend of the mail for an unverified account', function () {
    [$owner, $org] = $this->customerWithOrganization(['email_verified_at' => null]);

    $this->postJson('/v1/auth/login', ['email' => $owner->email, 'password' => 'Correct-Horse-Battery-9'])->assertOk();
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $this->withHeaders($h)->getJson('/v1/me')->assertOk();
    $this->withHeaders($h + ['Idempotency-Key' => (string) Str::ulid()])->patchJson('/v1/me', ['name' => 'Nové Jméno'])->assertOk();
    $this->withHeaders($h)->getJson('/v1/orders')->assertOk();
    $this->withHeaders($h)->getJson('/v1/services')->assertOk();
    $this->withHeaders($h)->getJson('/v1/invoices')->assertOk();
    $this->withHeaders($h + ['Idempotency-Key' => (string) Str::ulid()])->postJson('/v1/me/email/verification')->assertOk()->assertJsonPath('data.sent', true);
});

// ── resend ──

it('sends the verification mail again with a fresh token that works, and replaces the older ones', function () {
    $this->freezeSecond();
    $user = User::factory()->unverified()->create();
    $old = uvmVerificationToken($user);
    EmailVerificationToken::query()->where('user_id', $user->id)->update(['created_at' => now()->subMinutes(10)]);
    $this->actingAs($user, 'sanctum');

    $this->postJson('/v1/me/email/verification', [], ['Idempotency-Key' => (string) Str::ulid()])->assertOk()->assertJsonPath('data.sent', true);

    $sent = null;
    Notification::assertSentTo($user, VerifyEmailNotification::class, function ($n) use (&$sent) {
        $sent = $n->token;

        return true;
    });
    $this->postJson('/v1/auth/verify-email', ['token' => $old])->assertUnprocessable()->assertJsonPath('error', 'verify_token_invalid');
    $this->postJson('/v1/auth/verify-email', ['token' => $sent])->assertOk();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('throttles the resend: one a minute and five an hour, and says when to come back', function () {
    $this->freezeSecond();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user, 'sanctum');
    $send = fn () => $this->postJson('/v1/me/email/verification', [], ['Idempotency-Key' => (string) Str::ulid()]);

    $send()->assertOk();
    $send()->assertStatus(429)->assertJsonPath('error', 'email_verification_throttled')->assertJsonPath('retry_after', 60);
    foreach (range(1, 4) as $i) {
        $this->travel(61)->seconds();
        $send()->assertOk();
    }
    $this->travel(61)->seconds();
    $send()->assertStatus(429)->assertJsonPath('error', 'email_verification_throttled');
    $this->travel(61)->minutes();
    $send()->assertOk();
});

it('has nothing to resend for a verified address, and needs a signed-in person', function () {
    $this->postJson('/v1/me/email/verification')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/v1/me/email/verification', [], ['Idempotency-Key' => (string) Str::ulid()])->assertStatus(409)->assertJsonPath('error', 'email_already_verified');
});

it('answers the new slugs in the public error index', function () {
    $slugs = PublicApiDocs::scanErrorSlugs();
    expect($slugs)->toHaveKeys(['email_unverified', 'email_verification_throttled', 'email_already_verified'])
        ->and($slugs['email_unverified']['statuses'])->toBe([403])->and($slugs['email_unverified']['message'])->not->toBe('')
        ->and($slugs['email_verification_throttled']['statuses'])->toBe([429]);
});

// ── security review follow-ups ──

it('fails closed for an actor it cannot resolve, and for a partner without an owner', function () {
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 1]]);
    expect(fn () => EmailVerificationGuard::assertMayPlaceOrder(500, 'CZK', new CommandContext('user', 'usr_missing', null, null, '127.0.0.1', 'pest', null)))->toThrow(DomainError::class)
        ->and(fn () => EmailVerificationGuard::assertMayPlaceOrder(500, 'CZK', new CommandContext('user', null, null, null, '127.0.0.1', 'pest', null)))->toThrow(DomainError::class);

    [, $org] = $this->customerWithOrganization();
    $org->owner_user_id = null; // the column is NOT NULL; the guard still never assumes it
    expect(fn () => EmailVerificationGuard::assertMayReceivePayout($org))->toThrow(DomainError::class, 'majitele');
});

it('caps the verification mail at five an hour whoever asks, guest checkout included', function () {
    $user = User::factory()->unverified()->create();
    foreach (range(1, 5) as $i) {
        EmailVerificationGuard::issue($user);
    }
    expect(fn () => EmailVerificationGuard::issue($user))->toThrow(DomainError::class, 'několikrát');
    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 5);
});

it('tells a refused guest how to get in', function () {
    config(['onhost.identity.unverified_order_limit_minor' => ['CZK' => 100]]);
    $payload = ['customer' => ['email' => 'host2@firma.cz', 'name' => 'Petr Host', 'country' => 'CZ'], 'items' => [['product_key' => 'web-hosting', 'plan_key' => 'start']], 'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []], 'payment' => ['mode' => 'bank'], 'terms' => true];
    $response = $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'uvm-guest-2'])->postJson('/v1/checkout/guest', $payload)->assertForbidden();
    expect($response->json('message'))->toContain('Zapomenuté heslo');
});

it('tells the partner why the automatic payout did not come', function () {
    [, $org] = $this->customerWithOrganization(['email_verified_at' => null]);
    $partner = uvmPartner($org);
    $partner->forceFill(['payout_terms' => 'monthly'])->save();

    app(PartnerService::class)->autoPayouts();
    app(OutboxPublisher::class)->relayPending();

    $note = Onhost\Domain\Notifications\Models\Notification::query()->where('organization_id', $org->id)->where('event', 'partner.payout.auto_skipped')->first();
    expect($note)->not->toBeNull()->and($note->body)->toContain('ověří svůj e-mail');
});
