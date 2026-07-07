<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view ssl certificate monitoring dashboard', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.ssl-monitoring.index'))
        ->assertOk()
        ->assertViewHas('statusStats');
});

it('monitoring shows expiring count', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    \App\Models\SslCertificateCheck::create([
        'service_id' => $service->id,
        'domain'     => 'a.cz',
        'status'     => 'expiring_soon',
        'checked_at' => now(),
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.ssl-monitoring.index'))
        ->assertOk();

    expect($response->viewData('expiringCount'))->toBeGreaterThanOrEqual(1);
});

it('monitoring shows expired count', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    \App\Models\SslCertificateCheck::create([
        'service_id' => $service->id,
        'domain'     => 'expired.cz',
        'status'     => 'expired',
        'checked_at' => now(),
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.ssl-monitoring.index'))
        ->assertOk();

    $response->assertViewHas('expiredCount');
});

it('guest cannot access ssl monitoring', function (): void {
    $this->get(route('admin.ssl-monitoring.index'))
        ->assertRedirect();
});

it('monitoring shows recent checks', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.ssl-monitoring.index'))
        ->assertOk()
        ->assertViewHas('recentChecks');
});
