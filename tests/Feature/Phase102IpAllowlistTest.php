<?php

declare(strict_types=1);

use App\Models\AdminIpAllowlist;

// ── Unit: containsIp CIDR logic ───────────────────────────────────────────────

it('containsIp returns true for exact /32 match', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '1.2.3.4/32', 'is_active' => true]);
    expect($entry->containsIp('1.2.3.4'))->toBeTrue();
});

it('containsIp returns false for /32 non-match', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '1.2.3.4/32', 'is_active' => true]);
    expect($entry->containsIp('1.2.3.5'))->toBeFalse();
});

it('containsIp returns true for IP in /24 subnet', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '192.168.1.0/24', 'is_active' => true]);
    expect($entry->containsIp('192.168.1.200'))->toBeTrue();
    expect($entry->containsIp('192.168.2.1'))->toBeFalse();
});

it('containsIp returns true for IP in /8 subnet', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '10.0.0.0/8', 'is_active' => true]);
    expect($entry->containsIp('10.255.255.255'))->toBeTrue();
    expect($entry->containsIp('11.0.0.1'))->toBeFalse();
});

it('containsIp handles CIDR without slash (defaults to /32)', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '5.5.5.5', 'is_active' => true]);
    expect($entry->containsIp('5.5.5.5'))->toBeTrue();
    expect($entry->containsIp('5.5.5.6'))->toBeFalse();
});

it('containsIp returns false for invalid IP input', function (): void {
    $entry = new AdminIpAllowlist(['cidr' => '192.168.0.0/24', 'is_active' => true]);
    expect($entry->containsIp('not-an-ip'))->toBeFalse();
    expect($entry->containsIp(''))->toBeFalse();
});

// ── Middleware: fail-open when no active entries ───────────────────────────────

it('admin panel is accessible when allowlist has no active entries', function (): void {
    AdminIpAllowlist::query()->delete();

    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.ip-allowlist.index'))
        ->assertOk();
});

// ── HTTP: CRUD routes ─────────────────────────────────────────────────────────

it('admin can view ip-allowlist index page', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.ip-allowlist.index'))
        ->assertOk()
        ->assertSee('Allowlist');
});

it('admin can add a CIDR entry', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->post(route('admin.ip-allowlist.store'), [
            'cidr'  => '10.20.30.0/24',
            'label' => 'Test network',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('admin_ip_allowlist', [
        'cidr'      => '10.20.30.0/24',
        'label'     => 'Test network',
        'is_active' => 1,
    ]);
});

it('store validates CIDR format', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->post(route('admin.ip-allowlist.store'), [
            'cidr' => 'not valid!',
        ])
        ->assertSessionHasErrors(['cidr']);
});

it('admin can toggle an entry inactive', function (): void {
    $admin = adminUser();
    $entry = AdminIpAllowlist::create([
        'cidr'       => '99.99.99.0/24',
        'is_active'  => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.ip-allowlist.update', $entry), [
            'is_active' => '0',
            'label'     => $entry->label,
        ])
        ->assertRedirect();

    expect($entry->fresh()->is_active)->toBeFalse();
});

it('admin can delete an entry', function (): void {
    $admin = adminUser();
    $entry = AdminIpAllowlist::create([
        'cidr'       => '77.77.77.77/32',
        'is_active'  => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.ip-allowlist.destroy', $entry))
        ->assertRedirect();

    $this->assertDatabaseMissing('admin_ip_allowlist', ['id' => $entry->id]);
});

it('customer cannot access ip-allowlist routes', function (): void {
    $customer = customerUser();
    $this->actingAs($customer)
        ->get(route('admin.ip-allowlist.index'))
        ->assertForbidden();
});
