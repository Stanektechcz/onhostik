<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Dns\Enums\DnsRecordType;
use App\Domains\Dns\Enums\DnsZoneStatus;
use App\Domains\Dns\Models\DnsRecord;
use App\Domains\Dns\Models\DnsZone;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── DnsZone model ─────────────────────────────────────────────────────────────

it('DnsZoneStatus returns correct label and color', function (): void {
    expect(DnsZoneStatus::Active->label())->toBe('Aktivní');
    expect(DnsZoneStatus::Active->color())->toBe('success');
    expect(DnsZoneStatus::Pending->color())->toBe('warning');
    expect(DnsZoneStatus::Suspended->color())->toBe('danger');
});

it('DnsRecordType hasPriority is true only for MX and SRV', function (): void {
    expect(DnsRecordType::MX->hasPriority())->toBeTrue();
    expect(DnsRecordType::SRV->hasPriority())->toBeTrue();
    expect(DnsRecordType::A->hasPriority())->toBeFalse();
    expect(DnsRecordType::TXT->hasPriority())->toBeFalse();
});

// ── Customer panel — DNS zones ────────────────────────────────────────────────

it('customer can view DNS manager index', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.dns-manager.index'))
        ->assertOk()
        ->assertViewIs('panel.dns-manager.index');
});

it('customer can add a new DNS zone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $this->actingAs($user)
        ->post(route('panel.dns-manager.store'), ['domain' => 'example.cz'])
        ->assertRedirect();

    expect($customer->dnsZones()->where('domain', 'example.cz')->exists())->toBeTrue();
});

it('customer cannot add a duplicate DNS zone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $customer->dnsZones()->create([
        'domain'   => 'duplicate.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->post(route('panel.dns-manager.store'), ['domain' => 'duplicate.cz'])
        ->assertSessionHasErrors('domain');
});

it('invalid domain name is rejected', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.dns-manager.store'), ['domain' => 'not_a_valid_domain'])
        ->assertSessionHasErrors('domain');
});

it('customer can view their DNS zone detail', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'mysite.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->get(route('panel.dns-manager.show', $zone))
        ->assertOk()
        ->assertViewIs('panel.dns-manager.show');
});

it('customer cannot view another customer DNS zone', function (): void {
    $owner = customerUser();
    $other = customerUser();

    $zone = $owner->customer->dnsZones()->create([
        'domain'   => 'owner-zone.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($other)
        ->get(route('panel.dns-manager.show', $zone))
        ->assertForbidden();
});

it('customer can delete their DNS zone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'to-delete.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->delete(route('panel.dns-manager.destroy', $zone))
        ->assertRedirect(route('panel.dns-manager.index'));

    expect(DnsZone::find($zone->id))->toBeNull();
});

// ── DNS records ───────────────────────────────────────────────────────────────

it('customer can add an A record to their zone', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'mysite.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->post(route('panel.dns-manager.records.store', $zone), [
            'type'    => 'A',
            'name'    => '@',
            'content' => '192.168.1.1',
            'ttl'     => 3600,
        ])
        ->assertRedirect();

    expect($zone->records()->where('type', 'A')->exists())->toBeTrue();
});

it('customer can add an MX record with priority', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'mailzone.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->post(route('panel.dns-manager.records.store', $zone), [
            'type'     => 'MX',
            'name'     => '@',
            'content'  => 'mail.mailzone.cz.',
            'ttl'      => 3600,
            'priority' => 10,
        ])
        ->assertRedirect();

    $record = $zone->records()->where('type', 'MX')->first();
    expect($record)->not->toBeNull();
    expect($record->priority)->toBe(10);
});

it('invalid DNS record type is rejected', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'badtype.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($user)
        ->post(route('panel.dns-manager.records.store', $zone), [
            'type'    => 'INVALID',
            'name'    => '@',
            'content' => '1.2.3.4',
            'ttl'     => 3600,
        ])
        ->assertSessionHasErrors('type');
});

it('customer can delete a DNS record', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'delrecord.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $record = $zone->records()->create([
        'type'    => DnsRecordType::TXT,
        'name'    => '@',
        'content' => 'v=spf1 ~all',
        'ttl'     => 3600,
    ]);

    $this->actingAs($user)
        ->delete(route('panel.dns-manager.records.destroy', [$zone, $record]))
        ->assertRedirect();

    expect(DnsRecord::find($record->id))->toBeNull();
});

it('customer cannot delete record from another customer zone', function (): void {
    $owner = customerUser();
    $other = customerUser();

    $zone = $owner->customer->dnsZones()->create([
        'domain'   => 'ownerzone.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $record = $zone->records()->create([
        'type'    => DnsRecordType::A,
        'name'    => '@',
        'content' => '1.2.3.4',
        'ttl'     => 3600,
    ]);

    $this->actingAs($other)
        ->delete(route('panel.dns-manager.records.destroy', [$zone, $record]))
        ->assertForbidden();

    expect(DnsRecord::find($record->id))->not->toBeNull();
});

// ── Admin DNS ─────────────────────────────────────────────────────────────────

it('admin can view DNS zones list', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dns.index'))
        ->assertOk()
        ->assertViewIs('admin.dns.index');
});

it('admin can view DNS zone detail', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    assert($customer instanceof Customer);

    $zone = $customer->dnsZones()->create([
        'domain'   => 'admin-view.cz',
        'status'   => DnsZoneStatus::Active,
        'provider' => 'mock',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dns.show', $zone))
        ->assertOk()
        ->assertViewIs('admin.dns.show');
});
