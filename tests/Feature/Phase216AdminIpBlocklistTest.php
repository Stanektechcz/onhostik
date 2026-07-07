<?php

use App\Models\IpBlocklistEntry;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view ip blocklist', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.ip-blocklist.index'))
        ->assertOk()
        ->assertViewHas('entries');
});

it('admin can block an ip address', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.ip-blocklist.store'), [
            'ip_address' => '1.2.3.4',
            'reason'     => 'Spam',
        ])
        ->assertRedirect();

    expect(IpBlocklistEntry::where('ip_address', '1.2.3.4')->exists())->toBeTrue();
});

it('ip_address must be a valid ip', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.ip-blocklist.store'), [
            'ip_address' => 'not-an-ip',
        ])
        ->assertSessionHasErrors('ip_address');
});

it('blocking same ip twice updates the record', function (): void {
    IpBlocklistEntry::create(['ip_address' => '5.5.5.5', 'reason' => 'old']);

    $this->actingAs(adminUser())
        ->post(route('admin.ip-blocklist.store'), [
            'ip_address' => '5.5.5.5',
            'reason'     => 'updated',
        ])
        ->assertRedirect();

    expect(IpBlocklistEntry::where('ip_address', '5.5.5.5')->count())->toBe(1);
    expect(IpBlocklistEntry::where('ip_address', '5.5.5.5')->value('reason'))->toBe('updated');
});

it('admin can remove an ip from blocklist', function (): void {
    $entry = IpBlocklistEntry::create(['ip_address' => '9.9.9.9', 'reason' => 'test']);

    $this->actingAs(adminUser())
        ->delete(route('admin.ip-blocklist.destroy', $entry))
        ->assertRedirect();

    expect(IpBlocklistEntry::find($entry->id))->toBeNull();
});
