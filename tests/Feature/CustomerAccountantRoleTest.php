<?php

declare(strict_types=1);

use App\Domains\Customer\Enums\CustomerRole;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Accountant sub-account role: a member who may reach billing/payments but not
 * account deletion or member management.
 */

function accountantOf(User $owner): User
{
    $u = User::factory()->create();
    $owner->customer->members()->attach($u->id, ['role' => CustomerRole::Accountant->value]);

    return $u;
}

function plainMemberOf(User $owner): User
{
    $u = User::factory()->create();
    $owner->customer->members()->attach($u->id, ['role' => CustomerRole::Member->value]);

    return $u;
}

it('reports billing access per role', function (): void {
    $owner      = customerUser();
    $accountant = accountantOf($owner);
    $member     = plainMemberOf($owner);

    expect($owner->fresh()->canAccessBilling())->toBeTrue()
        ->and($accountant->fresh()->canAccessBilling())->toBeTrue()
        ->and($member->fresh()->canAccessBilling())->toBeFalse();
});

it('lets an accountant reach billing areas', function (string $route): void {
    $owner      = customerUser();
    $accountant = accountantOf($owner);

    $this->actingAs($accountant)->get(route($route))->assertOk();
})->with([
    'panel.billing.invoices',
    'panel.billing.payments',
    'panel.account.billing',
    'panel.payment-methods.index',
]);

it('still blocks a plain member from billing', function (): void {
    $owner  = customerUser();
    $member = plainMemberOf($owner);

    $this->actingAs($member)->get(route('panel.billing.invoices'))->assertForbidden();
});

it('blocks an accountant from account deletion and member management', function (): void {
    $owner      = customerUser();
    $accountant = accountantOf($owner);

    $this->actingAs($accountant)->get(route('panel.account.delete.create'))->assertForbidden();
    $this->actingAs($accountant)->get(route('panel.account.members.index'))->assertForbidden();
});

it('lets the owner invite an accountant', function (): void {
    \Illuminate\Support\Facades\Notification::fake();
    $owner = customerUser();

    $this->actingAs($owner)->post(route('panel.account.members.invite'), [
        'email' => 'ucetni@firma.cz',
        'role'  => 'accountant',
    ])->assertRedirect();

    $this->assertDatabaseHas('customer_invitations', [
        'customer_id' => $owner->customer->id,
        'email'       => 'ucetni@firma.cz',
        'role'        => 'accountant',
    ]);
});

it('rejects an invalid invite role', function (): void {
    \Illuminate\Support\Facades\Notification::fake();
    $owner = customerUser();

    $this->actingAs($owner)->post(route('panel.account.members.invite'), [
        'email' => 'x@y.cz',
        'role'  => 'owner', // not assignable
    ])->assertSessionHasErrors('role');
});
