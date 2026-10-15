<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Onhost\Domain\Content\Models\WaitlistSignup;
use Onhost\Domain\Content\Waitlist\WaitlistService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * E2 (vlna 2 row 15, variant A; owner decision E2): a waitlist with double opt-in through the outbox, the consent version,
 * deletion after 30 days (unconfirmed) and 12 months (confirmed), token and IP stored as hashes, a rate limit, the CommandBus.
 */

beforeEach(function () {
    Mail::fake();
    config(['onhost.outbox.eager' => false]); // the queued mail is read before it leaves
});

/** The raw token from the queued confirmation mail of $email. */
function waitlistMailedToken(string $email): string
{
    $mail = MailOutbox::query()->where('template_key', 'waitlist-confirm')->where('to', $email)->latest('created_at')->firstOrFail();
    preg_match('/potvrdit=([^&"]+)/', (string) $mail->vars['url'], $m);

    return rawurldecode($m[1] ?? '');
}

function waitlistSignupBody(array $overrides = []): array
{
    return $overrides + ['email' => 'Jana@Example.cz', 'product' => 'gpu-server', 'consent' => true, 'consent_version' => WaitlistService::CURRENT_CONSENT];
}

it('takes a signup only with the consent and its current version, and keeps the address pending until confirmed', function () {
    // other addresses: the per-e-mail limit (3 an hour) counts refused attempts too
    $this->postJson('/v1/waitlist', waitlistSignupBody(['email' => 'a@example.cz', 'consent' => false]))->assertUnprocessable()->assertJsonValidationErrors(['consent']);
    $this->postJson('/v1/waitlist', waitlistSignupBody(['email' => 'b@example.cz', 'product' => 'Not A Key!']))->assertUnprocessable();
    $this->postJson('/v1/waitlist', waitlistSignupBody(['email' => 'c@example.cz', 'consent_version' => '2020-01-01']))->assertUnprocessable()->assertJsonPath('error', 'waitlist_consent_outdated');
    expect(WaitlistSignup::query()->count())->toBe(0);

    $this->postJson('/v1/waitlist', waitlistSignupBody())->assertStatus(202)->assertJsonPath('data.state', 'pending_confirmation');
    app(OutboxPublisher::class)->relayPending();

    $signup = WaitlistSignup::query()->sole();
    expect($signup->email)->toBe('jana@example.cz')->and($signup->state)->toBe('pending')->and($signup->consent_version)->toBe(WaitlistService::CURRENT_CONSENT)
        ->and($signup->ip_hash)->toHaveLength(64)->and($signup->ip_hash)->not->toBe('127.0.0.1')
        ->and($signup->toArray())->not->toHaveKeys(['token_hash', 'ip_hash']);

    $token = waitlistMailedToken('jana@example.cz');
    expect(strlen($token))->toBe(48)->and($signup->fresh()->token_hash)->toBe(hash('sha256', $token));
    $mail = MailOutbox::query()->where('template_key', 'waitlist-confirm')->sole();
    expect($mail->vars['souhlas'])->toBe('Chci dostat e-mail, až bude gpu-server k dispozici. Souhlas mohu kdykoli odvolat.');
    // the token never rides the durable outbox and never reaches the audit; neither does the address
    expect((string) DB::table('outbox_messages')->get()->toJson())->not->toContain($token)->not->toContain('jana@example.cz');
    expect((string) AuditEvent::query()->where('action', 'waitlist.signup')->get()->toJson())->toContain('gpu-server')->not->toContain('jana@example.cz');
});

it('confirms by the mailed token, answers the same for a known address, and deletes on withdrawal', function () {
    $this->postJson('/v1/waitlist', waitlistSignupBody())->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    $token = waitlistMailedToken('jana@example.cz');

    $this->postJson('/v1/waitlist/confirm', ['token' => 'nope'])->assertNotFound()->assertJsonPath('error', 'waitlist_token_invalid');
    $this->postJson('/v1/waitlist/confirm', ['token' => $token])->assertOk()->assertJsonPath('data.state', 'confirmed');
    $this->postJson('/v1/waitlist/confirm', ['token' => $token])->assertOk()->assertJsonPath('data.state', 'confirmed'); // a second click is harmless
    expect(WaitlistSignup::query()->sole()->confirmed_at)->not->toBeNull();

    // the same address again: the same answer, no second mail, nothing changes
    $this->postJson('/v1/waitlist', waitlistSignupBody())->assertStatus(202)->assertJsonPath('data.state', 'pending_confirmation');
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'waitlist-confirm')->count())->toBe(1)->and(WaitlistSignup::query()->sole()->state)->toBe('confirmed');

    $this->postJson('/v1/waitlist/withdraw', ['token' => $token])->assertOk()->assertJsonPath('data.withdrawn', true);
    expect(WaitlistSignup::query()->count())->toBe(0);
    $this->postJson('/v1/waitlist/withdraw', ['token' => $token])->assertNotFound();
});

it('does not confirm a signup older than 30 days and prunes after 30 days and 12 months', function () {
    $this->postJson('/v1/waitlist', waitlistSignupBody())->assertStatus(202);
    $this->postJson('/v1/waitlist', waitlistSignupBody(['email' => 'petr@example.cz']))->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    $this->postJson('/v1/waitlist/confirm', ['token' => waitlistMailedToken('petr@example.cz')])->assertOk();
    $stale = waitlistMailedToken('jana@example.cz');

    $this->travel(31)->days();
    $this->postJson('/v1/waitlist/confirm', ['token' => $stale])->assertNotFound();
    $this->artisan('onhost:waitlist:prune')->assertSuccessful();
    expect(WaitlistSignup::query()->pluck('email')->all())->toBe(['petr@example.cz']);

    $this->travel(10)->months(); // ~11 months after the confirmation: kept
    expect(app(WaitlistService::class)->prune())->toBe(['pending' => 0, 'confirmed' => 0]);
    $this->travel(2)->months(); // past 12 months: gone
    expect(app(WaitlistService::class)->prune())->toBe(['pending' => 0, 'confirmed' => 1]);
    expect(WaitlistSignup::query()->count())->toBe(0);
});

it('rate-limits signups per e-mail', function () {
    foreach (['gpu-server', 'gpu-server-2', 'gpu-server-3'] as $product) {
        $this->postJson('/v1/waitlist', waitlistSignupBody(['product' => $product]))->assertStatus(202);
    }
    $this->postJson('/v1/waitlist', waitlistSignupBody(['product' => 'gpu-server-4']))->assertStatus(429);
});
