<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view ssl certificate checks', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.ssl-certificate-checks.index'))
        ->assertOk()
        ->assertViewHas('checks');
});

it('admin can manually create an ssl check', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.ssl-certificate-checks.store'), [
            'service_id' => $service->id,
            'domain'     => 'example.cz',
            'status'     => 'valid',
            'checked_at' => now()->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect();

    expect(\App\Models\SslCertificateCheck::count())->toBe(1);
});

it('admin can filter ssl checks by status', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.ssl-certificate-checks.index').'?status=expired')
        ->assertOk();
});

it('guest cannot access ssl certificate checks', function (): void {
    $this->get(route('admin.ssl-certificate-checks.index'))
        ->assertRedirect();
});

it('store validates status enum', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.ssl-certificate-checks.store'), [
            'service_id' => 1,
            'domain'     => 'example.cz',
            'status'     => 'unknown_status',
            'checked_at' => now()->format('Y-m-d H:i:s'),
        ])
        ->assertSessionHasErrors('status');
});
