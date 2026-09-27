<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\Middleware\IdempotencyKey;

/*
 * TASK-0041 — the P0-16 red team's MEDIUM findings on the two replay stores (owning task TASK-0036, permission program IF-12,
 * audit SE-5 / G12).
 *
 *  - The HTTP replay store kept the raw response body for 24 h: the plaintext token of POST /v1/tokens, an action hook's URL
 *    (the token is its path), a generated password. Whoever read `idempotency_keys` (a backup, a support query) read them.
 *  - The bus replay store was scoped to the organization alone: another member of the same organization sending the same key
 *    was answered with the first member's result and nothing ran for them; the same key with another body was silently
 *    answered with the first run.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

function rssMember(Organization $organization, string $role): User
{
    $user = User::factory()->create();
    app(OrganizationService::class)->attachMember($organization, $user, $role, CommandContext::system('test'), true);

    return $user;
}

it('keeps no token of a token creation in the replay store, and answers its replay with already_done instead of the token', function () {
    Http::fake();
    [$user] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $body = ['name' => 'CI', 'scopes' => ['services:read']];

    $created = $this->postJson('/v1/tokens', $body, ['Idempotency-Key' => 'rss-token'])->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $secret = explode('|', (string) $created->json('token'), 2)[1] ?? '';
    expect($secret)->toStartWith('onh_live_');
    expect(DB::table('idempotency_keys')->pluck('result')->implode("\n"))->not->toContain($secret)->not->toContain(substr($secret, 9));

    // the same request again: it was done — the token was shown once and is not shown again, nor is a second one issued
    $replay = $this->postJson('/v1/tokens', $body, ['Idempotency-Key' => 'rss-token'])->assertStatus(409)
        ->assertJsonPath('error', 'already_done')->assertHeader('Idempotent-Replayed', 'true');
    expect($replay->getContent())->not->toContain(substr($secret, 9))
        ->and($user->tokens()->whereNull('revoked_at')->count())->toBe(1);
    // … and another body under the same key is still the contract's 409
    $this->postJson('/v1/tokens', ['name' => 'Jiný', 'scopes' => ['services:read']], ['Idempotency-Key' => 'rss-token'])->assertStatus(409)->assertJsonPath('error', 'idempotency_key_reused');
});

it('treats a hook URL or a generated password in an answer as a secret, and keeps an answer without one as it was', function () {
    $middleware = app(IdempotencyKey::class);
    $send = fn (string $key, array $answer) => $middleware->handle(
        tap(Request::create('/v1/rss', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '198.51.100.7'], '{"a":1}'), fn (Request $r) => $r->headers->set('Idempotency-Key', $key)),
        fn () => response()->json($answer, 201),
    );

    $send('rss-hook', ['hook' => ['id' => 'ahk-1', 'name' => 'deploy'], 'url' => 'https://portal.example/v1/hooks/run/ahk_'.str_repeat('Q', 40)]);
    $generated = implode('-', ['vygenerovane', 'heslo', 'schranky']); // built here, so no secret scanner reads a literal
    $send('rss-password', ['mailbox' => 'info@example.cz', 'generated_password' => $generated]);
    $send('rss-plain', ['id' => 'svc_1', 'state' => 'active', 'totp_enabled' => true]);
    $stored = DB::table('idempotency_keys')->pluck('result')->implode("\n");
    expect($stored)->not->toContain(str_repeat('Q', 40))->not->toContain($generated);

    foreach (['rss-hook', 'rss-password'] as $key) {
        $replay = $send($key, ['never' => 'called']);
        expect($replay->getStatusCode())->toBe(409)->and(json_decode((string) $replay->getContent(), true)['error'] ?? null)->toBe('already_done');
    }
    // an answer that hands out nothing secret is replayed word for word (a flag named like a secret is not one)
    $plain = $send('rss-plain', ['never' => 'called']);
    expect($plain->getStatusCode())->toBe(201)->and(json_decode((string) $plain->getContent(), true))->toBe(['id' => 'svc_1', 'state' => 'active', 'totp_enabled' => true]);
});

it('gives a second member of the organization sending the same bus key a run of their own, and refuses the same key with another body', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = rssMember($org, 'org_admin');
    $bus = app(CommandBus::class);
    $update = fn (User $who, string $name) => $bus->dispatch(new OrganizationCommand($org->id, 'rss-same-key', ['op' => 'update', 'name' => $name]), $this->contextFor($who, $org, 'totp'));

    $update($owner, 'Alfa s.r.o.');
    expect($org->fresh()->name)->toBe('Alfa s.r.o.');
    // another person of the same organization, the same key: their own request runs (it used to be answered with the owner's)
    $update($admin, 'Beta s.r.o.');
    expect($org->fresh()->name)->toBe('Beta s.r.o.');

    // the same person, the same key, another body: refused, not silently answered with the first run
    expect(fn () => $update($owner, 'Gama s.r.o.'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('idempotency_key_reused')->and($e->status)->toBe(409));
    expect($org->fresh()->name)->toBe('Beta s.r.o.');

    // a true retry is still a replay: nothing runs twice (no new succeeded audit row for the owner)
    $rows = fn () => DB::table('audit_events')->where('action', 'organization.update')->where('result', 'succeeded')->where('actor_id', $owner->id)->count();
    $before = $rows();
    $update($owner, 'Alfa s.r.o.');
    expect($org->fresh()->name)->toBe('Beta s.r.o.')->and($rows())->toBe($before);
});

it('leaves the system\'s own bus keys as they were: a retry of a sweep is answered by its first run whatever the body', function () {
    [, $org] = $this->customerWithOrganization();
    $bus = app(CommandBus::class);
    $system = CommandContext::system('test')->withScope($org->id);

    $bus->dispatch(new OrganizationCommand($org->id, 'rss-system', ['op' => 'update', 'name' => 'Systém a.s.']), $system);
    $bus->dispatch(new OrganizationCommand($org->id, 'rss-system', ['op' => 'update', 'name' => 'Jiný a.s.']), $system);
    expect($org->fresh()->name)->toBe('Systém a.s.');
});
