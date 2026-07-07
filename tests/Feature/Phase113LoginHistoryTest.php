<?php

declare(strict_types=1);

use App\Listeners\RecordLoginHistoryEntry;
use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

// ── Listener unit tests ───────────────────────────────────────────────────────

it('listener records login history entry for user', function (): void {
    $user = customerUser()->fresh();

    $request = Request::create('/login', 'POST', [], [], [], [
        'REMOTE_ADDR'     => '1.2.3.4',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
    ]);

    $listener = new RecordLoginHistoryEntry($request);
    $listener->handle(new Login('web', $user, false));

    $entry = UserLoginHistory::query()->where('user_id', $user->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->ip_address)->toBe('1.2.3.4')
        ->and($entry->user_agent)->toBe('TestBrowser/1.0');
});

it('listener records multiple entries per user', function (): void {
    $user = customerUser()->fresh();

    $request = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
    $listener = new RecordLoginHistoryEntry($request);

    $listener->handle(new Login('web', $user, false));
    $listener->handle(new Login('web', $user, false));

    expect(UserLoginHistory::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('listener ignores non-User auth events', function (): void {
    $request  = Request::create('/login');
    $listener = new RecordLoginHistoryEntry($request);
    $fake     = new class {};

    // @phpstan-ignore argument.type
    $listener->handle(new Login('web', $fake, false));

    expect(UserLoginHistory::count())->toBe(0);
});

// ── Admin HTTP tests ──────────────────────────────────────────────────────────

it('admin can view customer login history page', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    UserLoginHistory::create([
        'user_id'    => $user->id,
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0',
    ]);

    $this->actingAs($admin)
         ->get(route('admin.customer-login-history.show', $user->customer))
         ->assertOk()
         ->assertSee('192.168.1.1')
         ->assertSee('Mozilla/5.0');
});

it('admin sees empty state when no login history exists', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    $this->actingAs($admin)
         ->get(route('admin.customer-login-history.show', $user->customer))
         ->assertOk()
         ->assertSee('Žádná přihlášení nebyla zaznamenána');
});

it('guest is redirected from customer login history page', function (): void {
    $user = customerUser();

    $this->get(route('admin.customer-login-history.show', $user->customer))
         ->assertRedirect();
});

it('customer-show page shows login history link for users with account', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.show', $user->customer))
         ->assertOk()
         ->assertSee('Historie přihlášení');
});
