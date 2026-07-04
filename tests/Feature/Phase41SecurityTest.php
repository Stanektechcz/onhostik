<?php

declare(strict_types=1);

use App\Listeners\TrackSecurityEvent;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\NewIpLoginNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── SecurityEvent model ───────────────────────────────────────────────────────

it('SecurityEvent has correct label and severity for login', function (): void {
    $ev = new SecurityEvent(['event_type' => 'login', 'ip_address' => '1.2.3.4']);
    expect($ev->label())->toBe('Přihlášení');
    expect($ev->severityClass())->toBe('success');
});

it('SecurityEvent has correct label and severity for login_failed', function (): void {
    $ev = new SecurityEvent(['event_type' => 'login_failed', 'ip_address' => '1.2.3.4']);
    expect($ev->label())->toBe('Neúspěšný pokus o přihlášení');
    expect($ev->severityClass())->toBe('danger');
});

it('SecurityEvent has correct label and severity for new_ip_login', function (): void {
    $ev = new SecurityEvent(['event_type' => 'new_ip_login', 'ip_address' => '1.2.3.4']);
    expect($ev->label())->toBe('Přihlášení z nové IP adresy');
    expect($ev->severityClass())->toBe('warning');
});

// ── TrackSecurityEvent listener ───────────────────────────────────────────────

it('TrackSecurityEvent records login event', function (): void {
    $user = customerUser();

    $listener = app(TrackSecurityEvent::class);
    $listener->handleLogin(new Login('web', $user, false));

    expect(SecurityEvent::where('user_id', $user->id)->where('event_type', 'login')->count())->toBe(1);
});

it('TrackSecurityEvent records failed login event', function (): void {
    $listener = app(TrackSecurityEvent::class);
    $listener->handleFailed(new Failed('web', null, ['email' => 'attacker@example.com', 'password' => 'wrong']));

    expect(SecurityEvent::where('event_type', 'login_failed')->where('email', 'attacker@example.com')->count())->toBe(1);
});

it('TrackSecurityEvent records logout event', function (): void {
    $user = customerUser();

    $listener = app(TrackSecurityEvent::class);
    $listener->handleLogout(new Logout('web', $user));

    expect(SecurityEvent::where('user_id', $user->id)->where('event_type', 'logout')->count())->toBe(1);
});

it('TrackSecurityEvent records new_ip_login when IP differs from last_login_ip', function (): void {
    Notification::fake();

    $user = customerUser();
    $user->update(['last_login_ip' => '10.0.0.1']);
    $user = $user->fresh();

    $listener = app(TrackSecurityEvent::class);
    $listener->handleLogin(new Login('web', $user, false));

    // The request IP from the test environment (127.0.0.1) differs from 10.0.0.1
    expect(SecurityEvent::where('user_id', $user->id)->where('event_type', 'new_ip_login')->count())->toBe(1);
    Notification::assertSentTo($user, NewIpLoginNotification::class);
});

it('TrackSecurityEvent does not send new_ip notification on first login (no previous IP)', function (): void {
    Notification::fake();

    $user = customerUser();
    // last_login_ip is null → first login, no notification
    $user->update(['last_login_ip' => null]);
    $user = $user->fresh();

    $listener = app(TrackSecurityEvent::class);
    $listener->handleLogin(new Login('web', $user, false));

    expect(SecurityEvent::where('user_id', $user->id)->where('event_type', 'login')->count())->toBe(1);
    Notification::assertNothingSent();
});

// ── Panel security page ───────────────────────────────────────────────────────

it('security page renders with securityEvents variable', function (): void {
    $user = customerUser();

    SecurityEvent::create([
        'user_id'    => $user->id,
        'event_type' => 'login',
        'ip_address' => '127.0.0.1',
        'email'      => $user->email,
    ]);

    $this->actingAs($user)
        ->get(route('panel.account.security'))
        ->assertOk()
        ->assertViewHas('securityEvents')
        ->assertSee('Aktivita přihlášení');
});

it('security page shows empty state when no events exist', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.account.security'))
        ->assertOk()
        ->assertViewHas('securityEvents')
        ->assertSee('Zatím žádná zaznamenaná aktivita.');
});

// ── Admin security audit page ─────────────────────────────────────────────────

it('admin security dashboard returns 200', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.security.index'))
        ->assertOk()
        ->assertViewIs('admin.security')
        ->assertViewHas('failedCount')
        ->assertViewHas('suspiciousIps')
        ->assertViewHas('recentEvents')
        ->assertViewHas('topAttackers');
});

it('admin security dashboard shows correct failed count in 24h', function (): void {
    $admin = adminUser();

    // 3 failed logins in last 24h
    SecurityEvent::create(['event_type' => 'login_failed', 'ip_address' => '5.5.5.5', 'email' => 'x@test.com']);
    SecurityEvent::create(['event_type' => 'login_failed', 'ip_address' => '5.5.5.5', 'email' => 'y@test.com']);
    SecurityEvent::create(['event_type' => 'login_failed', 'ip_address' => '6.6.6.6', 'email' => 'z@test.com']);
    // 1 old failed login (older than 24h — should NOT be counted)
    $old = SecurityEvent::create(['event_type' => 'login_failed', 'ip_address' => '5.5.5.5', 'email' => 'old@test.com']);
    DB::table('security_events')->where('id', $old->id)->update(['created_at' => now()->subDays(2)]);

    $response = $this->actingAs($admin)->get(route('admin.security.index'));
    expect($response->viewData('failedCount'))->toBe(3);
    expect($response->viewData('suspiciousIps'))->toBe(2);
});

it('customer cannot access admin security dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.security.index'))
        ->assertForbidden();
});
