<?php

declare(strict_types=1);

it('customer can view active sessions page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('panel.account.sessions'))
         ->assertOk()
         ->assertSee('Aktivní relace');
});

it('sessions page shows current session when session exists in db', function (): void {
    $user = customerUser();

    // Simulate a session entry for this user in the sessions table
    $sessionId = 'test-session-' . uniqid();
    \Illuminate\Support\Facades\DB::table('sessions')->insert([
        'id'            => $sessionId,
        'user_id'       => $user->id,
        'ip_address'    => '127.0.0.1',
        'user_agent'    => 'Test Browser',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    // The page should show the session
    $this->actingAs($user)
         ->get(route('panel.account.sessions'))
         ->assertOk()
         ->assertSee('127.0.0.1');
});

it('customer can revoke another session', function (): void {
    $user = customerUser();

    // Insert a fake other session for this user
    $otherId = 'fake-session-id-' . uniqid();
    \Illuminate\Support\Facades\DB::table('sessions')->insert([
        'id'            => $otherId,
        'user_id'       => $user->id,
        'ip_address'    => '192.168.1.100',
        'user_agent'    => 'Mozilla/5.0 Test Browser',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => time() - 300,
    ]);

    $this->actingAs($user)
         ->delete(route('panel.account.sessions.destroy', $otherId))
         ->assertRedirect();

    expect(\Illuminate\Support\Facades\DB::table('sessions')->where('id', $otherId)->exists())->toBeFalse();
});

it('customer cannot revoke another users session', function (): void {
    adminUser();
    $user1 = customerUser();
    $user2 = customerUser();

    $otherId = 'fake-session-id-' . uniqid();
    \Illuminate\Support\Facades\DB::table('sessions')->insert([
        'id'            => $otherId,
        'user_id'       => $user1->id,
        'ip_address'    => '10.0.0.1',
        'user_agent'    => 'Test',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    // user2 tries to delete user1's session — the where('user_id') guard prevents it
    $this->actingAs($user2)
         ->delete(route('panel.account.sessions.destroy', $otherId))
         ->assertRedirect();

    // session should still exist (user2 can't delete user1's session)
    expect(\Illuminate\Support\Facades\DB::table('sessions')->where('id', $otherId)->exists())->toBeTrue();
});

it('guest is redirected from sessions page', function (): void {
    $this->get(route('panel.account.sessions'))
         ->assertRedirect(route('login'));
});
