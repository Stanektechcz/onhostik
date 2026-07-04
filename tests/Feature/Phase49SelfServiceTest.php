<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\SshKey;
use App\Domains\Provisioning\Enums\ServiceStatus;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── SshKey model fingerprint ──────────────────────────────────────────────────

it('SshKey computeFingerprint returns null for invalid key', function (): void {
    expect(SshKey::computeFingerprint('not a key'))->toBeNull();
});

it('SshKey computeFingerprint returns string for valid ed25519 key', function (): void {
    // Minimal valid ed25519 public key structure
    $type    = 'ssh-ed25519';
    $payload = base64_encode('placeholder-key-data');
    $key     = "{$type} {$payload} test@example.com";

    expect(SshKey::computeFingerprint($key))->toBeString()->toStartWith('MD5:');
});

// ── Service customer notes ────────────────────────────────────────────────────

it('customer can update note on their own service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.update-note', $service), ['customer_note' => 'My main website server'])
        ->assertRedirect();

    expect($service->refresh()->customer_note)->toBe('My main website server');
});

it('customer can clear their service note', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id'   => $customer->id,
        'status'        => ServiceStatus::Active,
        'customer_note' => 'Old note',
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.update-note', $service), ['customer_note' => null])
        ->assertRedirect();

    expect($service->refresh()->customer_note)->toBeNull();
});

it('customer note is limited to 1000 characters', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->put(route('panel.services.update-note', $service), ['customer_note' => str_repeat('x', 1001)])
        ->assertSessionHasErrors('customer_note');
});

it('customer cannot update note on another customer service', function (): void {
    $owner   = customerUser();
    $other   = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($other)
        ->put(route('panel.services.update-note', $service), ['customer_note' => 'Hack'])
        ->assertForbidden();
});

// ── Auto-renew toggle ─────────────────────────────────────────────────────────

it('customer can toggle auto-renew off', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
        'auto_renew'  => true,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.toggle-auto-renew', $service))
        ->assertRedirect();

    expect($service->refresh()->auto_renew)->toBeFalse();
});

it('customer can toggle auto-renew back on', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $service = Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
        'auto_renew'  => false,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.toggle-auto-renew', $service))
        ->assertRedirect();

    expect($service->refresh()->auto_renew)->toBeTrue();
});

it('customer cannot toggle auto-renew on another customer service', function (): void {
    $owner   = customerUser();
    $other   = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($other)
        ->post(route('panel.services.toggle-auto-renew', $service))
        ->assertForbidden();
});

// ── SSH key management ────────────────────────────────────────────────────────

it('customer can view SSH keys page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.account.ssh-keys.index'))
        ->assertOk()
        ->assertViewIs('panel.account.ssh-keys');
});

it('customer can add a valid SSH key', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $type    = 'ssh-ed25519';
    $payload = base64_encode('placeholder-key-data-that-is-long-enough');
    $pubKey  = "{$type} {$payload} test@example.com";

    $this->actingAs($user)
        ->post(route('panel.account.ssh-keys.store'), [
            'name'       => 'My laptop',
            'public_key' => $pubKey,
        ])
        ->assertRedirect();

    expect($customer->sshKeys()->count())->toBe(1);
    expect($customer->sshKeys()->first()->name)->toBe('My laptop');
});

it('adding SSH key with invalid type is rejected', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.account.ssh-keys.store'), [
            'name'       => 'Bad key',
            'public_key' => 'not-a-valid-key AAAA comment',
        ])
        ->assertSessionHasErrors('public_key');
});

it('customer can delete their SSH key', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $key = SshKey::create([
        'customer_id' => $customer->id,
        'name'        => 'To delete',
        'public_key'  => 'ssh-ed25519 AAAA test',
    ]);

    $this->actingAs($user)
        ->delete(route('panel.account.ssh-keys.destroy', $key))
        ->assertRedirect();

    expect(SshKey::find($key->id))->toBeNull();
});

it('customer cannot delete another customer SSH key', function (): void {
    $owner = customerUser();
    $other = customerUser();

    $key = SshKey::create([
        'customer_id' => $owner->customer->id,
        'name'        => 'Owner key',
        'public_key'  => 'ssh-ed25519 AAAA test',
    ]);

    $this->actingAs($other)
        ->delete(route('panel.account.ssh-keys.destroy', $key))
        ->assertForbidden();

    expect(SshKey::find($key->id))->not->toBeNull();
});

it('SSH key limit is enforced at 20 keys', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    for ($i = 1; $i <= 20; $i++) {
        SshKey::create([
            'customer_id' => $customer->id,
            'name'        => "Key {$i}",
            'public_key'  => "ssh-ed25519 AAAA{$i} test",
        ]);
    }

    $type    = 'ssh-ed25519';
    $payload = base64_encode('yet-another-key');
    $pubKey  = "{$type} {$payload} test@example.com";

    $this->actingAs($user)
        ->post(route('panel.account.ssh-keys.store'), [
            'name'       => 'One too many',
            'public_key' => $pubKey,
        ])
        ->assertSessionHasErrors('public_key');
});
