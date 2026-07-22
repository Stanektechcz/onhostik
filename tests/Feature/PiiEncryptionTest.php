<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Support\BlindIndex;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Audit 31 — customer phone encrypted at rest, still searchable by exact match
 * through a keyed blind index.
 */

it('stores the phone encrypted at rest but reads it back as plaintext', function (): void {
    $customer = customerUser()->customer;
    $customer->update(['phone' => '+420 777 123 456']);

    // Raw DB value is ciphertext, not the number.
    $raw = DB::table('customers')->where('id', $customer->id)->value('phone');
    expect($raw)->not->toContain('777123456')
        ->and(Crypt::decryptString($raw))->toBe('+420 777 123 456');

    // The model returns plaintext transparently.
    expect($customer->fresh()->phone)->toBe('+420 777 123 456');
});

it('maintains a blind index that matches regardless of formatting', function (): void {
    $customer = customerUser()->customer;
    $customer->update(['phone' => '777 123 456']);

    // Different formatting of the SAME digits resolves to the same customer.
    $found = Customer::wherePhone('777123456')->first();
    expect($found?->id)->toBe($customer->id);

    // A different number does not match.
    expect(Customer::wherePhone('608000111')->exists())->toBeFalse();
});

it('never writes the plaintext phone into the activity log', function (): void {
    $customer = customerUser()->customer;
    $customer->update(['phone' => '777999888', 'company_name' => 'Acme']);

    $logged = DB::table('activity_log')->where('subject_id', $customer->id)->pluck('properties')->implode(' ');
    expect($logged)->not->toContain('777999888');
});

it('global search finds a customer by exact phone but not by substring', function (): void {
    $user = customerUser();
    $user->customer->update(['phone' => '777123456']);

    // Exact match → found.
    $this->actingAs(adminUser())
        ->get(route('admin.search.quick', ['q' => '777123456']))
        ->assertOk()
        ->assertSee($user->customer->email);

    // Substring of the phone → NOT found (encryption trade-off).
    $this->actingAs(adminUser())
        ->get(route('admin.search.quick', ['q' => '77712']))
        ->assertOk()
        ->assertDontSee($user->customer->email);
});

it('reindexes legacy plaintext rows into encrypted + indexed form', function (): void {
    $customer = customerUser()->customer;

    // Simulate a pre-encryption row: plaintext phone, no blind index.
    DB::table('customers')->where('id', $customer->id)->update([
        'phone' => '777000123', 'phone_bidx' => null,
    ]);

    $this->artisan('pii:reindex')->assertSuccessful();

    $raw = DB::table('customers')->where('id', $customer->id)->value('phone');
    expect(Crypt::decryptString($raw))->toBe('777000123')
        ->and(DB::table('customers')->where('id', $customer->id)->value('phone_bidx'))
        ->toBe(BlindIndex::of('777000123'));
});
