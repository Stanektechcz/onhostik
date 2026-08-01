<?php

declare(strict_types=1);

use App\Domains\Customer\Enums\CustomerRole;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Active-account switcher: a user who belongs to several accounts (owns one and
 * is a member of another) can switch which one the panel acts on.
 */

it('stores the chosen account in the session', function (): void {
    $owner      = customerUser();          // owns account A
    $otherOwner = customerUser();          // account B
    $otherOwner->customer->members()->attach($owner->id, ['role' => CustomerRole::Member->value]);

    $this->actingAs($owner)
        ->post(route('panel.account.switch', $otherOwner->customer))
        ->assertRedirect(route('panel.dashboard'))
        ->assertSessionHas('active_customer_id', $otherOwner->customer->id);
});

it('cannot switch to an account the user does not belong to', function (): void {
    $user    = customerUser();
    $foreign = customerUser();

    $this->actingAs($user)
        ->post(route('panel.account.switch', $foreign->customer))
        ->assertForbidden();

    expect(session('active_customer_id'))->toBeNull();
});

it('honours the session selection when resolving the customer', function (): void {
    $owner      = customerUser();
    $otherOwner = customerUser();
    $otherOwner->customer->members()->attach($owner->id, ['role' => CustomerRole::Member->value]);

    // With B selected in session, a panel request resolves B (not the owned A).
    $this->actingAs($owner)
        ->withSession(['active_customer_id' => $otherOwner->customer->id])
        ->get(route('panel.dashboard'))
        ->assertOk();

    // Resolver bound account B onto the request user.
    expect($this->app['auth']->user()->customer->id)->toBe($otherOwner->customer->id);
});

it('ignores a stale session selection for an account the user lost access to', function (): void {
    $owner   = customerUser();
    $foreign = customerUser();

    $this->actingAs($owner)
        ->withSession(['active_customer_id' => $foreign->customer->id])
        ->get(route('panel.dashboard'))
        ->assertOk();

    // Falls back to the owned account; the stale selection does not leak access.
    expect($this->app['auth']->user()->customer->id)->toBe($owner->customer->id);
});

it('shows the switcher only for users with more than one account', function (): void {
    $solo = customerUser();
    $this->actingAs($solo)->get(route('panel.dashboard'))->assertOk()->assertDontSee('Aktivní účet');

    $multi = customerUser();
    $other = customerUser();
    $other->customer->members()->attach($multi->id, ['role' => CustomerRole::Member->value]);

    $this->actingAs($multi)->get(route('panel.dashboard'))->assertOk()->assertSee('Aktivní účet');
});
