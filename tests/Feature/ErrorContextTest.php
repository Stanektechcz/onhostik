<?php

declare(strict_types=1);

use App\Domains\Shared\Support\ErrorContext;
use App\Domains\Shared\Support\SecretRedactor;

/**
 * Phase J (J141): request correlation and the contents of an error report.
 */

// ── Request id ────────────────────────────────────────────────────────────────

it('stamps every response with a request id', function (): void {
    $response = $this->get('/');

    expect($response->headers->get('X-Request-Id'))->not->toBeNull()->not->toBe('');
});

it('stamps API responses too', function (): void {
    $response = $this->getJson('/api/up');

    expect($response->headers->get('X-Request-Id'))->not->toBeNull()->not->toBe('');
});

it('honours a sane inbound request id so hops can be correlated', function (): void {
    $this->withHeader('X-Request-Id', 'lb-abc123def456')
        ->get('/')
        ->assertHeader('X-Request-Id', 'lb-abc123def456');
});

it('replaces a malformed inbound request id instead of trusting it', function (): void {
    // Accepting this verbatim would let a caller inject fake lines into logs.
    $injection = "abc\ndef ERROR: fabricated log line";

    $response = $this->withHeader('X-Request-Id', $injection)->get('/');

    expect($response->headers->get('X-Request-Id'))->not->toBe($injection)
        ->and($response->headers->get('X-Request-Id'))->not->toContain("\n");
});

it('gives each request its own id', function (): void {
    $first  = $this->get('/')->headers->get('X-Request-Id');
    $second = $this->get('/')->headers->get('X-Request-Id');

    expect($first)->not->toBe($second);
});

// ── Error context ─────────────────────────────────────────────────────────────

it('identifies the user by id only, never by email', function (): void {
    $user = customerUser();

    $this->actingAs($user)->get(route('panel.dashboard'));

    $context = ErrorContext::current();

    expect($context['user_id'])->toBe($user->id)
        ->and(json_encode($context))->not->toContain($user->email);
});

it('reports the route pattern rather than the resolved url', function (): void {
    // Grouping by pattern keeps one broken endpoint as one issue instead of
    // one issue per record id — and keeps ids out of a third party's store.
    $user    = customerUser();
    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $user->customer->id,
    ]);

    $this->actingAs($user)->get(route('panel.billing.invoices.show', $invoice));

    expect(ErrorContext::current()['route'])->toContain('{');
});

it('carries no request body or query string', function (): void {
    $this->get('/?password=hunter2&api_key=live_abc123');

    $encoded = json_encode(ErrorContext::current());

    expect($encoded)->not->toContain('hunter2')
        ->and($encoded)->not->toContain('live_abc123');
});

it('works outside an HTTP request', function (): void {
    // Queue workers and scheduled commands report exceptions too.
    $context = ErrorContext::current();

    expect($context)->toHaveKey('env');
});

// ── Redaction ─────────────────────────────────────────────────────────────────

it('masks secret-bearing keys wherever they are nested', function (): void {
    $redacted = SecretRedactor::redact([
        'user'    => 'petr',
        'password' => 'tajne-heslo',
        'nested'  => [
            'api_key'   => 'live_abc',
            'auth_code' => 'EPP-XYZ',
            'deep'      => ['client_secret' => 'shh'],
        ],
    ]);

    $encoded = json_encode($redacted);

    expect($encoded)->not->toContain('tajne-heslo')
        ->and($encoded)->not->toContain('live_abc')
        ->and($encoded)->not->toContain('EPP-XYZ')
        ->and($encoded)->not->toContain('shh')
        // Non-secret values must survive, or the log is useless.
        ->and($redacted['user'])->toBe('petr');
});

it('catches prefixed variants, not just exact key names', function (): void {
    // The earlier exact-match list let aapanel_api_key and smtp_password past.
    $redacted = SecretRedactor::redact([
        'aapanel_api_key' => 'leak-1',
        'smtp_password'   => 'leak-2',
        'comgate_secret'  => 'leak-3',
    ]);

    expect(json_encode($redacted))->not->toContain('leak-');
});

it('leaves innocent keys alone', function (): void {
    $redacted = SecretRedactor::redact(['domain' => 'example.cz', 'plan' => 'basic']);

    expect($redacted)->toBe(['domain' => 'example.cz', 'plan' => 'basic']);
});

it('does not claim errors are being captured when no tracker is configured', function (): void {
    // Honest default: implying capture that is not happening is how outages
    // go unnoticed for a week.
    expect(config('error-tracking.enabled'))->toBeFalse();
});
