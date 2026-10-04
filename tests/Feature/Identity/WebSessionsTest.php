<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\SessionKill;
use Onhost\Domain\Identity\WebSessions;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0070 (audit 2026-10, package C11): "Sessions and devices" lists the person's open web sessions — not only the browser
 * looking at it — and ends one of them, or every other one, through the bus. Production keeps sessions in Redis, so the list is
 * a table of our own (web_sessions) and WebSessionGate logs out a session whose row was ended on its next request.
 */

/** A browser of `$person` that signed in now: the Login listener opens its row and keeps the id in the session. */
function wsesSignIn(User $person, string $agent = 'Mozilla/5.0 Firefox/131.0'): string
{
    $request = Request::create('/panel', 'GET', [], [], [], ['HTTP_USER_AGENT' => $agent, 'REMOTE_ADDR' => '198.51.100.7']);
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    event(new Login('web', $person, false));

    return (string) app('session.store')->get(WebSessions::SESSION_KEY);
}

/** The portal as the browser sends it: stateful (Referer of the app), so the API request carries the session. */
function wsesPortal($test)
{
    return $test->withHeader('Referer', 'http://localhost/panel');
}

it('lists the person\'s open web sessions with the current one marked, and nobody else\'s', function () {
    $person = User::factory()->create();
    $other = User::factory()->create();
    $phone = app(WebSessions::class)->open($person->id, '203.0.113.9', 'Mozilla/5.0 (iPhone) Safari/605.1');
    $foreign = app(WebSessions::class)->open($other->id, '203.0.113.50', 'curl/8');
    $current = wsesSignIn($person);

    $rows = wsesPortal($this->actingAs($person, 'web'))->getJson('/v1/me/sessions')->assertOk()->json('data');

    expect(array_column($rows, 'id'))->toContain($current, $phone)->not->toContain($foreign);
    $mine = collect($rows)->firstWhere('id', $current);
    expect($mine['current'])->toBeTrue()->and($mine['user_agent'])->toContain('Firefox')->and($mine['ip'])->toBe('198.51.100.7')
        ->and(collect($rows)->firstWhere('id', $phone)['current'])->toBeFalse();
});

it('ends another session of the person through the bus, and that browser is logged out on its next request', function () {
    $person = User::factory()->create();
    $phone = app(WebSessions::class)->open($person->id, '203.0.113.9', 'Mozilla/5.0 (iPhone) Safari/605.1');
    wsesSignIn($person);
    $remembered = $person->fresh()->remember_token;

    wsesPortal($this->actingAs($person, 'web'))->deleteJson("/v1/me/sessions/{$phone}")->assertOk()->assertJsonPath('ended', 1);

    expect(DB::table('web_sessions')->where('id', $phone)->value('ended_reason'))->toBe(WebSessions::ENDED_BY_USER)
        ->and($person->fresh()->remember_token)->not->toBe($remembered) // the phone cannot sign itself back in with "remember me"
        ->and(AuditEvent::query()->where('action', 'identity.web_session.end')->where('result', 'succeeded')->exists())->toBeTrue();

    // the phone's next request: its session names the ended row
    Auth::guard('web')->logout();
    app('session.store')->put(WebSessions::SESSION_KEY, $phone);
    Auth::guard('web')->setUser($person);
    expect(Auth::guard('web')->user())->toBeNull();
});

it('refuses to end another person\'s session (not found, untouched) and the current session (sign out instead)', function () {
    $person = User::factory()->create();
    $other = User::factory()->create();
    $theirs = app(WebSessions::class)->open($other->id, '203.0.113.50', 'Mozilla/5.0 Chrome/130.0');
    $current = wsesSignIn($person);

    wsesPortal($this->actingAs($person, 'web'))->deleteJson("/v1/me/sessions/{$theirs}")->assertNotFound();
    expect(DB::table('web_sessions')->where('id', $theirs)->value('ended_at'))->toBeNull();

    wsesPortal($this->actingAs($person, 'web'))->deleteJson("/v1/me/sessions/{$current}")->assertStatus(422)->assertJsonPath('error', 'web_session_current');
    expect(DB::table('web_sessions')->where('id', $current)->value('ended_at'))->toBeNull();

    // a session carrying another person's row id gets a row of its own: the other person's row is neither used nor touched
    $before = (array) DB::table('web_sessions')->where('id', $theirs)->first();
    $this->travel(10)->minutes();
    Auth::guard('web')->logout();
    app('session.store')->put(WebSessions::SESSION_KEY, $theirs);
    Auth::guard('web')->setUser($person);
    $own = app('session.store')->get(WebSessions::SESSION_KEY);
    expect($own)->not->toBe($theirs)
        ->and(Auth::guard('web')->user()?->id)->toBe($person->id)
        ->and(DB::table('web_sessions')->where('id', $own)->value('user_id'))->toBe($person->id)
        ->and((array) DB::table('web_sessions')->where('id', $theirs)->first())->toBe($before);
});

it('ends every other session of the person and keeps the current one and other people\'s', function () {
    $person = User::factory()->create();
    $other = User::factory()->create();
    $a = app(WebSessions::class)->open($person->id, '203.0.113.1', 'Mozilla/5.0 Chrome/130.0');
    $b = app(WebSessions::class)->open($person->id, '203.0.113.2', 'Mozilla/5.0 Safari/605.1');
    $theirs = app(WebSessions::class)->open($other->id, '203.0.113.3', 'Mozilla/5.0 Chrome/130.0');
    $current = wsesSignIn($person);

    wsesPortal($this->actingAs($person, 'web'))->postJson('/v1/me/sessions/end-others', [])->assertOk()->assertJsonPath('ended', 2);

    expect(DB::table('web_sessions')->whereIn('id', [$a, $b])->whereNotNull('ended_at')->count())->toBe(2)
        ->and(DB::table('web_sessions')->where('id', $current)->value('ended_at'))->toBeNull()
        ->and(DB::table('web_sessions')->where('id', $theirs)->value('ended_at'))->toBeNull();
    $listed = wsesPortal($this->actingAs($person, 'web'))->getJson('/v1/me/sessions')->assertOk()->json('data');
    expect(array_column($listed, 'id'))->toBe([$current]);
});

it('keeps the sessions page and its actions away from API tokens', function () {
    [$person, $org] = $this->customerWithOrganization();
    $other = app(WebSessions::class)->open($person->id, '203.0.113.1', 'Mozilla/5.0 Chrome/130.0');
    $token = $person->createToken('wses', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $org->id])->save();

    $this->withToken($token->plainTextToken)->getJson('/v1/me/sessions')->assertForbidden();
    $this->withToken($token->plainTextToken)->deleteJson("/v1/me/sessions/{$other}")->assertForbidden();
    $this->withToken($token->plainTextToken)->postJson('/v1/me/sessions/end-others', [])->assertForbidden();
    expect(DB::table('web_sessions')->where('id', $other)->value('ended_at'))->toBeNull();
});

it('ends the row of a session that signs out, of every session on SessionKill and of the others on a password change', function () {
    $person = User::factory()->create(['password' => 'Correct-Horse-Battery-9']);
    $current = wsesSignIn($person);
    Auth::guard('web')->setUser($person);
    Auth::guard('web')->logout();
    expect(DB::table('web_sessions')->where('id', $current)->value('ended_reason'))->toBe(WebSessions::SIGNED_OUT);

    $a = app(WebSessions::class)->open($person->id, null, null);
    app(SessionKill::class)->end($person, 'mfa_reset', null, CommandContext::system('wses'));
    expect(DB::table('web_sessions')->where('id', $a)->value('ended_reason'))->toBe(WebSessions::ENDED_ALL);

    $this->travel(2)->seconds();
    $b = app(WebSessions::class)->open($person->id, null, null);
    $now = wsesSignIn($person);
    wsesPortal($this->actingAs($person, 'web'))->postJson('/v1/me/password', ['current_password' => 'Correct-Horse-Battery-9', 'password' => 'Another-Long-Secret-77x'])->assertOk();
    expect(DB::table('web_sessions')->where('id', $b)->value('ended_reason'))->toBe(WebSessions::PASSWORD_CHANGED)
        ->and(DB::table('web_sessions')->where('id', $now)->value('ended_at'))->toBeNull();
});

it('gives a session opened before this release its row on its next request, so it is listed', function () {
    $person = User::factory()->create();
    $request = Request::create('/panel');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
    app('session.store')->forget(WebSessions::SESSION_KEY);

    Auth::guard('web')->setUser($person);

    $id = app('session.store')->get(WebSessions::SESSION_KEY);
    expect($id)->toBeString()->and(Auth::guard('web')->user()?->id)->toBe($person->id)
        ->and(DB::table('web_sessions')->where('id', $id)->where('user_id', $person->id)->whereNull('ended_at')->exists())->toBeTrue();
});
