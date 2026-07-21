<?php

declare(strict_types=1);

use App\Listeners\EnforceConcurrentSessionLimit;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Audit 29 — a user may hold at most N simultaneous sessions.
 *
 * The listener no-ops unless the database session driver is active (only then
 * is there a `sessions` table to count), so every test opts into it explicitly.
 */
beforeEach(function (): void {
    config(['session.driver' => 'database']);
});

function seedSession(User $user, string $id, int $lastActivity): void
{
    DB::table('sessions')->insert([
        'id'            => $id,
        'user_id'       => $user->id,
        'ip_address'    => '127.0.0.1',
        'payload'       => 'x',
        'last_activity' => $lastActivity,
    ]);
}

function fireLogin(User $user): void
{
    app(EnforceConcurrentSessionLimit::class)->handle(new Login('web', $user, false));
}

it('evicts the oldest sessions when a login exceeds the cap', function (): void {
    config(['auth.max_concurrent_sessions' => 2]);
    $user = customerUser();

    // Three prior sessions; the login being processed becomes the would-be 4th.
    seedSession($user, 'oldest', 100);
    seedSession($user, 'middle', 200);
    seedSession($user, 'newest', 300);

    fireLogin($user);

    // Keep cap-1 = 1 most-recent existing row, so the row this request writes
    // lands the total at exactly the cap of 2.
    $remaining = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();

    expect($remaining)->toBe(['newest'])
        ->and($remaining)->not->toContain('oldest')
        ->and($remaining)->not->toContain('middle');
});

it('leaves sessions untouched when under the cap', function (): void {
    config(['auth.max_concurrent_sessions' => 5]);
    $user = customerUser();

    seedSession($user, 'a', 100);
    seedSession($user, 'b', 200);

    fireLogin($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);
});

it('does nothing when the cap is disabled (0)', function (): void {
    config(['auth.max_concurrent_sessions' => 0]);
    $user = customerUser();

    seedSession($user, 'a', 100);
    seedSession($user, 'b', 200);
    seedSession($user, 'c', 300);

    fireLogin($user);

    // 0 = unlimited: the previous behaviour, so nothing is evicted.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(3);
});

it('cycles the remember token so an evicted device cannot return via its cookie', function (): void {
    config(['auth.max_concurrent_sessions' => 1]);
    $user = customerUser();
    $user->forceFill(['remember_token' => 'original-token'])->save();

    seedSession($user, 'a', 100);
    seedSession($user, 'b', 200);

    fireLogin($user);

    // A dropped device holding a remember-me cookie must not be able to walk
    // back in over the cap.
    expect($user->fresh()->remember_token)->not->toBe('original-token');
});

it('never touches another user’s sessions', function (): void {
    config(['auth.max_concurrent_sessions' => 1]);
    $user  = customerUser();
    $other = customerUser();

    seedSession($user, 'mine-1', 100);
    seedSession($user, 'mine-2', 200);
    seedSession($other, 'theirs-1', 100);
    seedSession($other, 'theirs-2', 200);

    fireLogin($user);

    expect(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(2);
});

it('no-ops under a non-database session driver', function (): void {
    config(['session.driver' => 'array', 'auth.max_concurrent_sessions' => 1]);
    $user = customerUser();

    seedSession($user, 'a', 100);
    seedSession($user, 'b', 200);

    fireLogin($user);

    // With no server-side session store the count is meaningless — enforce
    // nothing rather than pretend to.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(2);
});
