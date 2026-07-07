<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DomainWhoisController;

test('domain whois controller exists', function (): void {
    expect(class_exists(DomainWhoisController::class))->toBeTrue();
});

test('domain whois index route exists', function (): void {
    expect(Route::has('admin.domain-whois.index'))->toBeTrue();
});

test('domain whois lookup route exists', function (): void {
    expect(Route::has('admin.domain-whois.lookup'))->toBeTrue();
});

test('domain whois index loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.domain-whois.index'));
    $response->assertOk();
});

test('domain whois lookup validates domain format', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->postJson(route('admin.domain-whois.lookup'), ['domain' => 'not a domain!!']);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('domain');
});
