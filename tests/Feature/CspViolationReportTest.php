<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

/**
 * Audit C24 — CSP violation reports land somewhere.
 *
 * Report-only mode is only useful if the reports are collected: the point is
 * to learn what enforce would break before flipping the switch.
 */

it('advertises a report endpoint in the policy', function (): void {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy-Report-Only')
        ?? $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('report-uri')
        ->and($csp)->toContain('report-to csp-endpoint')
        // report-to needs this header to name the group.
        ->and($response->headers->get('Reporting-Endpoints'))->toContain('csp-endpoint');
});

it('accepts a legacy csp-report body and logs it', function (): void {
    Log::spy();

    $this->call('POST', '/api/security/csp-report', [], [], [], [
        'CONTENT_TYPE' => 'application/csp-report',
    ], json_encode(['csp-report' => [
        'effective-directive' => 'script-src',
        'blocked-uri'         => 'https://evil.test/x.js',
        'document-uri'        => 'https://onhost.test/panel',
    ]]))->assertNoContent();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $msg, array $ctx): bool => $msg === 'csp.violation'
            && $ctx['directive'] === 'script-src'
            && $ctx['blocked_uri'] === 'https://evil.test/x.js',
    );
});

it('accepts the modern Reporting-API array form', function (): void {
    Log::spy();

    $this->call('POST', '/api/security/csp-report', [], [], [], [
        'CONTENT_TYPE' => 'application/reports+json',
    ], json_encode([[
        'type' => 'csp-violation',
        'body' => ['effectiveDirective' => 'style-src', 'blockedURL' => 'inline'],
    ]]))->assertNoContent();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $msg, array $ctx): bool => $msg === 'csp.violation' && $ctx['directive'] === 'style-src',
    );
});

it('never logs the script sample', function (): void {
    Log::spy();

    // script-sample echoes page content back — it can contain whatever the
    // user typed, so it must not reach the log.
    $this->call('POST', '/api/security/csp-report', [], [], [], [], json_encode(['csp-report' => [
        'effective-directive' => 'script-src',
        'script-sample'       => 'secret-password-typed-by-user',
    ]]))->assertNoContent();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $msg, array $ctx): bool => ! str_contains(json_encode($ctx) ?: '', 'secret-password'),
    );
});

it('shrugs off a malformed or oversized body', function (): void {
    $this->call('POST', '/api/security/csp-report', [], [], [], [], 'not json at all')
        ->assertNoContent();

    // World-writable endpoint: a huge body must not be parsed.
    $this->call('POST', '/api/security/csp-report', [], [], [], [], str_repeat('x', 20_000))
        ->assertNoContent();
});

it('needs no authentication', function (): void {
    // A browser posts these without credentials, and a violation on a
    // logged-out page still matters.
    $this->call('POST', '/api/security/csp-report', [], [], [], [], json_encode(['csp-report' => [
        'effective-directive' => 'img-src',
    ]]))->assertNoContent();
});
