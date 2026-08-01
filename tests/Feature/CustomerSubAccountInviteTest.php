<?php

declare(strict_types=1);

use App\Domains\Customer\Enums\CustomerRole;
use App\Domains\Customer\Models\CustomerInvitation;
use App\Models\User;
use App\Notifications\CustomerInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Create a pending invitation directly and return [invitation, rawToken]. */
function pendingInvite(int $customerId, string $email = 'invitee@firma.cz'): array
{
    $raw = Str::random(64);

    $invitation = CustomerInvitation::create([
        'customer_id' => $customerId,
        'email'       => $email,
        'role'        => CustomerRole::Member->value,
        'token_hash'  => hash('sha256', $raw),
        'expires_at'  => now()->addDays(7),
    ]);

    return [$invitation, $raw];
}

// ── owner: managing members ─────────────────────────────────────────────────────

it('lets the owner open the members page', function (): void {
    $owner = customerUser();

    $this->actingAs($owner)->get(route('panel.account.members.index'))
        ->assertOk()
        ->assertSee($owner->email);
});

it('forbids a member from managing members', function (): void {
    $owner  = customerUser();
    $member = User::factory()->create();
    $owner->customer->members()->attach($member->id, ['role' => CustomerRole::Member->value]);

    $this->actingAs($member)->get(route('panel.account.members.index'))->assertForbidden();
    $this->actingAs($member)->post(route('panel.account.members.invite'), ['email' => 'x@y.cz'])->assertForbidden();
});

it('lets the owner invite a member and emails the invitation', function (): void {
    Notification::fake();
    $owner = customerUser();

    $this->actingAs($owner)
        ->post(route('panel.account.members.invite'), ['email' => 'New.Member@Firma.cz'])
        ->assertRedirect();

    $this->assertDatabaseHas('customer_invitations', [
        'customer_id' => $owner->customer->id,
        'email'       => 'new.member@firma.cz', // normalised
    ]);
    Notification::assertSentOnDemand(CustomerInvitationNotification::class);
});

it('re-inviting the same email replaces the pending invitation', function (): void {
    Notification::fake();
    $owner = customerUser();

    $this->actingAs($owner)->post(route('panel.account.members.invite'), ['email' => 'dup@firma.cz']);
    $this->actingAs($owner)->post(route('panel.account.members.invite'), ['email' => 'dup@firma.cz']);

    expect(CustomerInvitation::where('email', 'dup@firma.cz')->count())->toBe(1);
});

it('lets the owner remove a member but never the owner', function (): void {
    $owner  = customerUser();
    $member = User::factory()->create();
    $owner->customer->members()->attach($member->id, ['role' => CustomerRole::Member->value]);

    $this->actingAs($owner)->delete(route('panel.account.members.remove', $member))->assertRedirect();
    expect($owner->customer->members()->where('users.id', $member->id)->exists())->toBeFalse();

    // The owner cannot remove themselves.
    $this->actingAs($owner)->delete(route('panel.account.members.remove', $owner))->assertForbidden();
    expect($owner->customer->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});

it('lets the owner revoke a pending invitation', function (): void {
    $owner            = customerUser();
    [$invitation]     = pendingInvite($owner->customer->id);

    $this->actingAs($owner)->delete(route('panel.account.members.invitations.revoke', $invitation))->assertRedirect();
    $this->assertDatabaseMissing('customer_invitations', ['id' => $invitation->id]);
});

// ── accepting an invitation ─────────────────────────────────────────────────────

it('shows an invalid page for a bad token', function (): void {
    $this->get(route('invitation.accept', ['token' => 'nope']))->assertOk()->assertSee('není platná');
});

it('creates an account and grants access when a new invitee accepts', function (): void {
    $owner        = customerUser();
    [$inv, $raw]  = pendingInvite($owner->customer->id, 'fresh@firma.cz');

    $this->get(route('invitation.accept', ['token' => $raw]))->assertOk();

    $this->post(route('invitation.accept.store', ['token' => $raw]), [
        'name'                  => 'Fresh Member',
        'password'              => 'Str0ng-Pass-123',
        'password_confirmation' => 'Str0ng-Pass-123',
    ])->assertRedirect(route('panel.dashboard'));

    $user = User::where('email', 'fresh@firma.cz')->first();
    expect($user)->not->toBeNull()
        ->and($owner->customer->members()->where('users.id', $user->id)->exists())->toBeTrue()
        ->and($inv->fresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('grants access when an existing user, signed in as the invited email, accepts', function (): void {
    $owner    = customerUser();
    $existing = User::factory()->create(['email' => 'known@firma.cz']);
    [$inv, $raw] = pendingInvite($owner->customer->id, 'known@firma.cz');

    $this->actingAs($existing)
        ->post(route('invitation.accept.store', ['token' => $raw]))
        ->assertRedirect(route('panel.dashboard'));

    expect($owner->customer->members()->where('users.id', $existing->id)->exists())->toBeTrue();
});

it('rejects accepting while signed in as a different email', function (): void {
    $owner    = customerUser();
    $other    = customerUser(); // different email, signed in
    [$inv, $raw] = pendingInvite($owner->customer->id, 'someoneelse@firma.cz');

    $this->actingAs($other)
        ->post(route('invitation.accept.store', ['token' => $raw]))
        ->assertForbidden();

    expect($owner->customer->members()->where('users.id', $other->id)->exists())->toBeFalse();
});

it('cannot reuse an accepted invitation', function (): void {
    $owner       = customerUser();
    [$inv, $raw] = pendingInvite($owner->customer->id, 'once@firma.cz');
    $inv->forceFill(['accepted_at' => now()])->save();

    $this->post(route('invitation.accept.store', ['token' => $raw]), [
        'name'                  => 'Too Late',
        'password'              => 'Str0ng-Pass-123',
        'password_confirmation' => 'Str0ng-Pass-123',
    ])->assertNotFound();
});
