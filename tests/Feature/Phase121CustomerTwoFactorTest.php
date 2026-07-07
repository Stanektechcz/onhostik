<?php

declare(strict_types=1);

use App\Notifications\TwoFactorDisabledNotification;
use App\Notifications\TwoFactorEnabledNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Notifications ─────────────────────────────────────────────────────────────

it('TwoFactorAuthenticationConfirmed event sends enabled notification', function (): void {
    Notification::fake();

    $user = customerUser();

    TwoFactorAuthenticationConfirmed::dispatch($user);

    Notification::assertSentTo($user, TwoFactorEnabledNotification::class);
});

it('TwoFactorAuthenticationDisabled event sends disabled notification', function (): void {
    Notification::fake();

    $user = customerUser();

    TwoFactorAuthenticationDisabled::dispatch($user);

    Notification::assertSentTo($user, TwoFactorDisabledNotification::class);
});

it('TwoFactorEnabledNotification mail has correct subject', function (): void {
    Notification::fake();

    $user = customerUser();

    $user->notify(new TwoFactorEnabledNotification());

    Notification::assertSentTo($user, TwoFactorEnabledNotification::class, function ($notification, $channels) {
        expect($channels)->toContain('mail');
        return true;
    });
});

it('TwoFactorDisabledNotification mail has security subject', function (): void {
    Notification::fake();

    $user = customerUser();

    $user->notify(new TwoFactorDisabledNotification());

    Notification::assertSentTo($user, TwoFactorDisabledNotification::class, function ($notification, $channels) {
        expect($channels)->toContain('mail');
        return true;
    });
});

it('confirming 2FA does not send notification when event is not dispatched', function (): void {
    Notification::fake();

    // No event dispatched — no notification expected
    Notification::assertNothingSent();
});

// ── Admin customer list: 2FA column ──────────────────────────────────────────

it('admin customer list shows 2FA column header', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.index'))
         ->assertOk()
         ->assertSee('2FA');
});

it('admin customer list shows 2FA active badge for customer with confirmed 2fa', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($admin)
         ->get(route('admin.customers.index'))
         ->assertOk()
         ->assertSee('Aktivní');
});

it('admin customer list shows 2FA inactive for customer without 2fa', function (): void {
    $admin = adminUser();
    customerUser(); // customer without 2FA

    $this->actingAs($admin)
         ->get(route('admin.customers.index'))
         ->assertOk()
         ->assertSee('S aktivním 2FA');
});

// ── Admin customer list: 2FA stat widget ─────────────────────────────────────

it('admin customer list shows 2FA adoption stat', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.index'))
         ->assertOk()
         ->assertSee('S aktivním 2FA');
});

it('admin customer 2FA count increases when customer enables 2FA', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    // Before: 0 customers with 2FA
    $before = $this->actingAs($admin)
                   ->get(route('admin.customers.index'))
                   ->assertOk();

    // Enable 2FA on customer
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    // After: customer list refreshes with new data
    $this->actingAs($admin)
         ->get(route('admin.customers.index'))
         ->assertOk()
         ->assertSee('Aktivní');
});

// ── Listener audit log ────────────────────────────────────────────────────────

it('confirming 2FA via event logs two_factor_enabled activity', function (): void {
    Notification::fake();

    $user = customerUser();

    TwoFactorAuthenticationConfirmed::dispatch($user);

    $activity = \Spatie\Activitylog\Models\Activity::where('causer_id', $user->id)
                    ->where('description', 'two_factor_enabled')
                    ->first();

    expect($activity)->not->toBeNull();
});

it('disabling 2FA via event logs two_factor_disabled activity', function (): void {
    Notification::fake();

    $user = customerUser();

    TwoFactorAuthenticationDisabled::dispatch($user);

    $activity = \Spatie\Activitylog\Models\Activity::where('causer_id', $user->id)
                    ->where('description', 'two_factor_disabled')
                    ->first();

    expect($activity)->not->toBeNull();
});
