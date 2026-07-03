<?php

declare(strict_types=1);

use App\Domains\Reseller\Models\ResellerProfile;
use App\Models\User;
use App\Notifications\ResellerApprovedNotification;
use App\Notifications\ResellerRejectedNotification;
use App\Notifications\ResellerSuspendedNotification;
use Database\Factories\ResellerProfileFactory;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

// ────────────────────────────────────────────────────────────────────────
// Index
// ────────────────────────────────────────────────────────────────────────

it('admin can list resellers', function (): void {
    $admin = adminUser();

    ResellerProfile::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.resellers.index'))
        ->assertOk()
        ->assertViewIs('admin.resellers.index')
        ->assertViewHas('resellers');
});

it('non-admin cannot access reseller list', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.resellers.index'))
        ->assertForbidden();
});

it('admin can filter resellers by status', function (): void {
    $admin = adminUser();

    ResellerProfile::factory()->create(['status' => 'pending']);
    ResellerProfile::factory()->active()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.resellers.index', ['status' => 'pending']))
        ->assertOk();

    $resellers = $response->viewData('resellers');
    expect($resellers->total())->toBe(1);
    expect($resellers->first()->status)->toBe('pending');
});

it('admin can search resellers by business name', function (): void {
    $admin = adminUser();

    ResellerProfile::factory()->create(['business_name' => 'Acme Corp']);
    ResellerProfile::factory()->create(['business_name' => 'Globex Inc']);

    $response = $this->actingAs($admin)
        ->get(route('admin.resellers.index', ['search' => 'acme']))
        ->assertOk();

    $resellers = $response->viewData('resellers');
    expect($resellers->total())->toBe(1);
    expect($resellers->first()->business_name)->toBe('Acme Corp');
});

// ────────────────────────────────────────────────────────────────────────
// Show
// ────────────────────────────────────────────────────────────────────────

it('admin can view reseller detail', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['business_name' => 'Test Reseller']);

    $this->actingAs($admin)
        ->get(route('admin.resellers.show', $reseller))
        ->assertOk()
        ->assertViewIs('admin.resellers.show')
        ->assertViewHas('reseller');
});

// ────────────────────────────────────────────────────────────────────────
// Approve / Reject / Suspend
// ────────────────────────────────────────────────────────────────────────

it('admin can approve a pending reseller', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('admin.resellers.approve', $reseller))
        ->assertRedirect();

    expect($reseller->fresh()->status)->toBe('active');
    expect($reseller->fresh()->approved_at)->not->toBeNull();
});

it('admin can reject a reseller', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('admin.resellers.reject', $reseller))
        ->assertRedirect();

    expect($reseller->fresh()->status)->toBe('rejected');
});

it('admin can suspend an active reseller', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs($admin)
        ->post(route('admin.resellers.suspend', $reseller))
        ->assertRedirect();

    expect($reseller->fresh()->status)->toBe('suspended');
});

// ────────────────────────────────────────────────────────────────────────
// Markup update
// ────────────────────────────────────────────────────────────────────────

it('admin can update reseller markup', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create(['markup_percent' => 10.0]);

    $this->actingAs($admin)
        ->put(route('admin.resellers.markup', $reseller), ['markup_percent' => 25.5])
        ->assertRedirect();

    expect((float) $reseller->fresh()->markup_percent)->toBe(25.5);
});

it('markup update rejects values above 100', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs($admin)
        ->put(route('admin.resellers.markup', $reseller), ['markup_percent' => 101])
        ->assertSessionHasErrors('markup_percent');
});

it('markup update rejects negative values', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs($admin)
        ->put(route('admin.resellers.markup', $reseller), ['markup_percent' => -1])
        ->assertSessionHasErrors('markup_percent');
});

// ────────────────────────────────────────────────────────────────────────
// Permission lifecycle
// ────────────────────────────────────────────────────────────────────────

it('approve grants access-reseller permission to the reseller user', function (): void {
    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['status' => 'pending']);

    \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web');

    $this->actingAs($admin)
        ->post(route('admin.resellers.approve', $reseller))
        ->assertRedirect();

    expect($reseller->user->fresh()->hasPermissionTo('access-reseller'))->toBeTrue();
});

it('suspend revokes access-reseller permission from the reseller user', function (): void {
    \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web');

    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();
    $reseller->user->givePermissionTo('access-reseller');

    $this->actingAs($admin)
        ->post(route('admin.resellers.suspend', $reseller))
        ->assertRedirect();

    expect($reseller->user->fresh()->hasPermissionTo('access-reseller'))->toBeFalse();
});

it('revoke removes access-reseller permission and sets status to suspended', function (): void {
    \Spatie\Permission\Models\Permission::findOrCreate('access-reseller', 'web');

    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();
    $reseller->user->givePermissionTo('access-reseller');

    $this->actingAs($admin)
        ->post(route('admin.resellers.revoke', $reseller))
        ->assertRedirect();

    expect($reseller->fresh()->status)->toBe('suspended');
    expect($reseller->user->fresh()->hasPermissionTo('access-reseller'))->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────────
// Notifications
// ────────────────────────────────────────────────────────────────────────

it('approve sends ResellerApprovedNotification to the reseller user', function (): void {
    Notification::fake();

    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('admin.resellers.approve', $reseller))
        ->assertRedirect();

    Notification::assertSentTo($reseller->user, ResellerApprovedNotification::class);
});

it('reject sends ResellerRejectedNotification to the reseller user', function (): void {
    Notification::fake();

    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->post(route('admin.resellers.reject', $reseller))
        ->assertRedirect();

    Notification::assertSentTo($reseller->user, ResellerRejectedNotification::class);
});

it('suspend sends ResellerSuspendedNotification to the reseller user', function (): void {
    Notification::fake();

    $admin    = adminUser();
    $reseller = ResellerProfile::factory()->active()->create();

    $this->actingAs($admin)
        ->post(route('admin.resellers.suspend', $reseller))
        ->assertRedirect();

    Notification::assertSentTo($reseller->user, ResellerSuspendedNotification::class);
});
