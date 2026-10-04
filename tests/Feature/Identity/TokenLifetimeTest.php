<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Onhost\Domain\Identity\Commands\ApiTokenCommand;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Identity\Tokens\TokenLifetime;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0044 — owner decision R9 of the 2026-10 readiness audit: every new personal API token ends, by default after 365 days,
 * never later than the operator's cap (`onhost.tokens.max_days`), and a token past its end opens nothing. The token was 90 days
 * by default and 365 at most, both written into the handler; the HTTP validation knew only the 365.
 */

/** @return array{token: string, id: string, expires_at: string} */
function tltIssue(User $owner, Organization $org, array $extra = []): array
{
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // a token is a credential: HIGH
    $context = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'tlt-session');

    return app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'tlt-'.Str::ulid(), ['op' => 'create', 'name' => 'ci', 'scopes' => ['services:read']] + $extra), $context);
}

it('gives a new token 365 days when it asks for nothing, and cuts a longer wish to the cap', function () {
    $this->freezeSecond();
    [$owner, $org] = $this->customerWithOrganization();

    expect(tltIssue($owner, $org)['expires_at'])->toBe(now()->addDays(365)->toIso8601String())
        ->and(tltIssue($owner, $org, ['expires_in_days' => 30])['expires_at'])->toBe(now()->addDays(30)->toIso8601String())
        ->and(tltIssue($owner, $org, ['expires_in_days' => 5000])['expires_at'])->toBe(now()->addDays(365)->toIso8601String())
        ->and(PersonalAccessToken::query()->whereNull('expires_at')->exists())->toBeFalse();
});

it('follows the operator\'s cap: a lower cap shortens the default and the HTTP form refuses more', function () {
    $this->freezeSecond();
    config(['onhost.tokens.max_days' => 30, 'onhost.tokens.default_days' => 365]);
    [$owner, $org] = $this->customerWithOrganization();

    expect(TokenLifetime::defaultDays())->toBe(30)
        ->and(tltIssue($owner, $org)['expires_at'])->toBe(now()->addDays(30)->toIso8601String());

    $this->actingAs($owner, 'sanctum')->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => (string) Str::ulid()])
        ->postJson('/v1/tokens', ['name' => 'ci', 'scopes' => ['services:read'], 'expires_in_days' => 31])
        ->assertStatus(422);

    // a nonsense configuration still ends every token
    config(['onhost.tokens.max_days' => 0, 'onhost.tokens.default_days' => -4]);
    expect(TokenLifetime::maxDays())->toBe(1)->and(TokenLifetime::days(null))->toBe(1)->and(TokenLifetime::days('9'))->toBe(1);
});

it('refuses a token whose end has passed, at the door and in the bus', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $issued = tltIssue($owner, $org, ['expires_in_days' => 1]);
    app('auth')->forgetGuards();

    $this->withToken($issued['token'])->getJson('/v1/me', ['X-Organization' => $org->id])->assertOk();

    $this->travel(1)->days();
    $this->travel(1)->minutes();
    app('auth')->forgetGuards();
    $this->withToken($issued['token'])->getJson('/v1/me', ['X-Organization' => $org->id])->assertUnauthorized();

    // a command carried by the expired token's session decides nothing either (IdentityCommandAuthorizer::asToken)
    $context = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'token:'.$issued['id']);
    expect(fn () => app(CommandBus::class)->dispatch(new ApiTokenCommand($org->id, 'tlt-x-'.Str::ulid(), ['op' => 'create', 'name' => 'x', 'scopes' => ['services:read']]), $context))
        ->toThrow(Exception::class);
});

it('never lets a token session ride on a step-up, so a high or critical action through a token is always refused', function () {
    [$owner] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // a grant made without a session

    expect(app(StepUpService::class)->activeGrant($owner, 'token:1'))->toBeNull()
        ->and(app(StepUpService::class)->activeGrant($owner, 'portal-session'))->not->toBeNull();
});
