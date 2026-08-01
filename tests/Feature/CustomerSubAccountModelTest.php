<?php

declare(strict_types=1);

use App\Domains\Customer\Enums\CustomerRole;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Customer sub-accounts — Batch 1: the membership data model and the resolver
 * that lets an invited member use the panel without touching the ~120 sites
 * that read $user->customer.
 */

it('records the account owner as an owner-role membership on creation', function (): void {
    $owner  = customerUser();
    $member = $owner->customer->members()->where('users.id', $owner->id)->first();

    expect($member)->not->toBeNull()
        ->and($member->getAttribute('pivot')->getAttribute('role'))->toBe('owner');
});

it('resolves an invited member to the customer they were invited to', function (): void {
    $owner  = customerUser();
    $member = User::factory()->create();

    $owner->customer->members()->attach($member->id, ['role' => CustomerRole::Member->value]);
    $member->refresh();

    expect($member->ownsCustomer())->toBeFalse()
        ->and($member->accessibleCustomer()?->id)->toBe($owner->customer->id)
        ->and($member->customerRoleFor($owner->customer))->toBe(CustomerRole::Member)
        ->and($member->isCustomerOwner($owner->customer))->toBeFalse();
});

it('treats the owner as the owner of their account', function (): void {
    $owner = customerUser();

    expect($owner->ownsCustomer())->toBeTrue()
        ->and($owner->isCustomerOwner())->toBeTrue()
        ->and($owner->customerRoleFor($owner->customer))->toBe(CustomerRole::Owner);
});

it('lets a member use the panel — $user->customer resolves to the invited account', function (): void {
    $owner  = customerUser();
    $member = User::factory()->create();
    $owner->customer->members()->attach($member->id, ['role' => CustomerRole::Member->value]);

    // The dashboard reads $request->user()->customer; for a member that is null
    // until the resolver middleware binds their membership account.
    $this->actingAs($member)->get(route('panel.dashboard'))->assertOk();
});

it('does not resolve a customer for a user with neither ownership nor membership', function (): void {
    $loner = User::factory()->create();

    expect($loner->accessibleCustomer())->toBeNull()
        ->and($loner->ownsCustomer())->toBeFalse();
});
