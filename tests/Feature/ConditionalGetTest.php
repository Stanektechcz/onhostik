<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Conditional GET / ETag on API responses (audit 500 #47/#48): unchanged
 * responses answer 304 so the body isn't re-transferred.
 */

it('adds an ETag to a successful API GET', function (): void {
    $token = customerUser()->createToken('etag')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/profile')->assertOk();

    expect($response->headers->get('ETag'))->not->toBeNull();
});

it('returns 304 when the client sends a matching If-None-Match', function (): void {
    $token = customerUser()->createToken('etag')->plainTextToken;

    $etag = $this->withToken($token)->getJson('/api/v1/profile')->assertOk()->headers->get('ETag');

    $this->withToken($token)
        ->withHeaders(['If-None-Match' => $etag])
        ->getJson('/api/v1/profile')
        ->assertStatus(304);
});

it('returns 200 with a fresh body when If-None-Match does not match', function (): void {
    $token = customerUser()->createToken('etag')->plainTextToken;

    $this->withToken($token)
        ->withHeaders(['If-None-Match' => '"stale-etag"'])
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('does not add an ETag to the public health probe when empty', function (): void {
    // /up returns JSON content, so it should carry an ETag too (sanity: header present).
    $response = $this->getJson('/api/up')->assertOk();

    expect($response->headers->get('ETag'))->not->toBeNull();
});
