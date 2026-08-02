<?php

declare(strict_types=1);

/**
 * /.well-known/security.txt — RFC 9116 disclosure policy (audit 500 #159).
 */

it('serves a valid security.txt', function (): void {
    $response = $this->get('/.well-known/security.txt')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('Contact:')
        ->toContain('Expires:')       // required by RFC 9116
        ->toContain('Canonical:');
});

it('publishes the configured disclosure contact', function (): void {
    config(['security.disclosure_email' => 'abuse@example.cz']);

    $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertSee('mailto:abuse@example.cz');
});
