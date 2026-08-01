<?php

declare(strict_types=1);

use App\Domains\Customer\Enums\CustomerRole;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Sub-accounts — Batch 3: invited members are kept out of billing, payments,
 * payment methods and account deletion; owners and members' ordinary access are
 * unaffected.
 */

function memberOf(User $owner): User
{
    $member = User::factory()->create();
    $owner->customer->members()->attach($member->id, ['role' => CustomerRole::Member->value]);

    return $member;
}

it('forbids a member from the billing and account-deletion areas', function (string $routeName): void {
    $owner  = customerUser();
    $member = memberOf($owner);

    $this->actingAs($member)->get(route($routeName))->assertForbidden();
})->with([
    'panel.billing.invoices',
    'panel.billing.payments',
    'panel.billing.credits',
    'panel.payment-methods.index',
    'panel.account.billing',
    'panel.account.delete.create',
]);

it('lets the owner reach billing', function (): void {
    $owner = customerUser();

    $this->actingAs($owner)->get(route('panel.billing.invoices'))->assertOk();
    $this->actingAs($owner)->get(route('panel.account.billing'))->assertOk();
});

it('still lets a member use the non-billing panel', function (): void {
    $owner  = customerUser();
    $member = memberOf($owner);

    // Members manage services/domains/tickets — those must stay reachable.
    $this->actingAs($member)->get(route('panel.dashboard'))->assertOk();
    $this->actingAs($member)->get(route('panel.services.index'))->assertOk();
});

it('blocks a member from posting a credit top-up', function (): void {
    $owner  = customerUser();
    $member = memberOf($owner);

    $this->actingAs($member)
        ->post(route('panel.billing.credits.topup'), ['amount' => 500])
        ->assertForbidden();
});
