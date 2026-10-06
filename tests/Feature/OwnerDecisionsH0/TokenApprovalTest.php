<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * H0, owner decision H-R1 (permission program S1-05): a HIGH or CRITICAL action through an API token or a service account is no
 * longer refused outright for want of a step-up a token can never take. The token's request opens a request for approval (403
 * `approval_required` with its id); the organization's owner, signed in to the portal with a fresh step-up, approves it; the token
 * repeats the very same request with `approval_ids` and it runs once. Never approved by the token itself, by the person the token
 * belongs to, by somebody else's organization or by staff, never spent by another token, another payload or twice.
 */

function h0TokenPortal(TestCase $test, User $user, Organization $org, bool $stepUp = true): TestCase
{
    app('auth')->forgetGuards();
    $test->flushHeaders();
    if ($stepUp) {
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    }

    return $test->actingAs($user, 'sanctum')->withHeaders(['X-Organization' => $org->id]);
}

function h0TokenBearer(TestCase $test, string $plain, Organization $org): TestCase
{
    app('auth')->forgetGuards();
    $test->flushHeaders();

    return $test->withToken($plain)->withHeaders(['X-Organization' => $org->id]);
}

/** A service account of `$org` with the organization admin's role and the power scope; its first token's plain text and id. */
function h0TokenAccount(TestCase $test, User $owner, Organization $org, string $name = 'GitHub Actions'): array
{
    $created = h0TokenPortal($test, $owner, $org)->postJson('/v1/service-accounts', ['name' => $name, 'role' => 'org_admin', 'scopes' => ['services:read', 'services:power']], ['Idempotency-Key' => (string) Str::ulid()])->assertCreated();

    return [(string) $created->json('token'), (string) $created->json('data.tokens.0.id'), (string) $created->json('data.id')];
}

function h0TokenTerminate(TestCase $test, string $plain, Organization $org, Service $service, array $approvalIds = []): TestResponse
{
    return h0TokenBearer($test, $plain, $org)->postJson("/v1/services/{$service->id}/actions", ['action' => 'terminate', 'params' => []] + ($approvalIds === [] ? [] : ['approval_ids' => $approvalIds]), ['Idempotency-Key' => (string) Str::ulid()]);
}

function h0TokenDecide(TestCase $test, User $user, Organization $org, string $approvalId, string $decision = 'approved', bool $stepUp = true): TestResponse
{
    return h0TokenPortal($test, $user, $org, $stepUp)->postJson("/v1/token-approvals/{$approvalId}/decision", ['decision' => $decision, 'note' => $decision === 'rejected' ? 'not now' : null], ['Idempotency-Key' => (string) Str::ulid()]);
}

it('opens a request for a HIGH action of a service account token, the owner approves it with a step-up and the token repeats it once', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$plain, $tokenId, $accountId] = h0TokenAccount($this, $owner, $org);
    $web = featureWebService($org, 'aapanel');

    // 1. the token asks: refused, but with a request the owner can approve — nothing ran
    $first = h0TokenTerminate($this, $plain, $org, $web)->assertForbidden()->assertJsonPath('error', 'approval_required')->assertJsonPath('requirement', 'approval');
    $approvalId = (string) $first->json('approval_id');
    $approval = Approval::query()->findOrFail($approvalId);
    expect($approval->state)->toBe('pending')
        ->and($approval->requested_by)->toBe($accountId)
        ->and($approval->organization_id)->toBe($org->id)
        ->and((string) data_get($approval->payload, 'token.id'))->toBe($tokenId)
        ->and(Operation::query()->where('service_id', $web->id)->count())->toBe(0)
        ->and($web->refresh()->terminate_at)->toBeNull();
    // asking again hands back the same request instead of a second one
    expect((string) h0TokenTerminate($this, $plain, $org, $web)->assertForbidden()->json('approval_id'))->toBe($approvalId);

    // 2. the owner sees it in the portal and decides it — a fresh step-up first
    $listed = h0TokenPortal($this, $owner, $org)->getJson('/v1/token-approvals')->assertOk();
    expect(collect($listed->json('data'))->pluck('id')->all())->toBe([$approvalId])
        ->and($listed->json('data.0.token.id'))->toBe($tokenId)
        ->and($listed->json('data.0.action'))->toBe('service.terminate');
    app(StepUpService::class)->revokeAll($owner);
    h0TokenDecide($this, $owner, $org, $approvalId, 'approved', false)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    h0TokenDecide($this, $owner, $org, $approvalId)->assertOk()->assertJsonPath('data.state', 'approved');

    // 3. the token repeats the same request with the approval: it runs, the approval is spent
    h0TokenTerminate($this, $plain, $org, $web, [$approvalId])->assertStatus(202);
    expect($approval->refresh()->state)->toBe('consumed')
        ->and(Operation::query()->where('service_id', $web->id)->count())->toBe(1);
    $audit = AuditEvent::query()->where('action', 'service.terminate')->where('result', 'succeeded')->sole();
    expect($audit->actor_type)->toBe('service_account')->and((array) $audit->approval_ids)->toBe([$approvalId]);

    // 4. once only: the same approval again is a new request
    $again = h0TokenTerminate($this, $plain, $org, $web, [$approvalId])->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect((string) $again->json('approval_id'))->not->toBe($approvalId)->and(Operation::query()->where('service_id', $web->id)->count())->toBe(1);
});

it('never lets the token, an admin, another organization or staff decide a token request', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$plain] = h0TokenAccount($this, $owner, $org);
    $web = featureWebService($org, 'aapanel');
    $approvalId = (string) h0TokenTerminate($this, $plain, $org, $web)->assertForbidden()->json('approval_id');

    // the token itself: the route is no token's
    h0TokenBearer($this, $plain, $org)->getJson('/v1/token-approvals')->assertForbidden();
    h0TokenBearer($this, $plain, $org)->postJson("/v1/token-approvals/{$approvalId}/decision", ['decision' => 'approved'])->assertForbidden();

    // an organization admin (who holds every right but the owner's own) is not the owner
    $admin = $this->customer(['email' => 'admin-h0@example.cz']);
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);
    h0TokenDecide($this, $admin, $org, $approvalId)->assertForbidden();

    // the owner of another organization does not find it
    [$stranger, $strangerOrg] = $this->customerWithOrganization(['email' => 'stranger-h0@example.cz']);
    h0TokenDecide($this, $stranger, $strangerOrg, $approvalId)->assertNotFound();
    expect(h0TokenPortal($this, $stranger, $strangerOrg)->getJson('/v1/token-approvals')->assertOk()->json('data'))->toBe([]);

    // staff decide staff's four eyes, not a customer's automation
    $staff = $this->staff('platform_owner');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($staff, 'sanctum')->postJson("/v1/staff/approvals/{$approvalId}/decision", ['decision' => 'approved'], ['Idempotency-Key' => (string) Str::ulid()])
        ->assertForbidden()->assertJsonPath('error', 'token_approval_owner_only');

    expect(Approval::query()->findOrFail($approvalId)->state)->toBe('pending');
    h0TokenTerminate($this, $plain, $org, $web, [$approvalId])->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect(Operation::query()->where('service_id', $web->id)->count())->toBe(0);
});

it('binds an approval to the token and the request it was given for', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$plain] = h0TokenAccount($this, $owner, $org);
    [$otherPlain] = h0TokenAccount($this, $owner, $org, 'Terraform');
    $web = featureWebService($org, 'aapanel');
    $second = featureWebService($org, 'ispconfig');
    $approvalId = (string) h0TokenTerminate($this, $plain, $org, $web)->assertForbidden()->json('approval_id');
    h0TokenDecide($this, $owner, $org, $approvalId)->assertOk();

    // another account's token, with the id it learned, is asked for its own approval
    expect((string) h0TokenTerminate($this, $otherPlain, $org, $web, [$approvalId])->assertForbidden()->json('approval_id'))->not->toBe($approvalId);
    // the same token, another service: not what was approved
    expect((string) h0TokenTerminate($this, $plain, $org, $second, [$approvalId])->assertForbidden()->json('approval_id'))->not->toBe($approvalId);
    expect(Approval::query()->findOrFail($approvalId)->state)->toBe('approved')
        ->and(Operation::query()->whereIn('service_id', [$web->id, $second->id])->count())->toBe(0);
});

it('never lets the person behind a personal token approve their own token', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $issued = $owner->createToken('owner-ci', ['services:read', 'services:power']);
    PersonalAccessToken::query()->whereKey($issued->accessToken->getKey())->update(['organization_id' => $org->id, 'expires_at' => now()->addDays(30)]);
    $web = featureWebService($org, 'aapanel');

    $approvalId = (string) h0TokenTerminate($this, $issued->plainTextToken, $org, $web)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(Approval::query()->findOrFail($approvalId)->requested_by)->toBe($owner->id);

    h0TokenDecide($this, $owner, $org, $approvalId)->assertForbidden()->assertJsonPath('error', 'approval_own_request');
    // the owner may still turn their own token's request down
    h0TokenDecide($this, $owner, $org, $approvalId, 'rejected')->assertOk()->assertJsonPath('data.state', 'rejected');
    expect(ServiceAccount::query()->count())->toBe(0);
});

it('keeps a token approval out of the portal\'s four eyes and the portal\'s approvals out of a token', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$plain, $tokenId] = h0TokenAccount($this, $owner, $org);
    $web = featureWebService($org, 'aapanel');
    $approvalId = (string) h0TokenTerminate($this, $plain, $org, $web)->assertForbidden()->json('approval_id');
    // an approval without the token (as a staff four-eyes request would be) is not the token's, whoever decided it
    Approval::query()->whereKey($approvalId)->update(['payload' => json_encode(['command' => [], 'permission' => 'service.delete'])]);
    $forged = Approval::query()->findOrFail($approvalId);
    $forged->forceFill(['state' => 'approved', 'decided_by' => $owner->id, 'decided_at' => now()])->save();

    h0TokenTerminate($this, $plain, $org, $web, [$approvalId])->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect(Approval::query()->findOrFail($approvalId)->state)->toBe('approved')
        ->and(Operation::query()->where('service_id', $web->id)->count())->toBe(0)
        ->and($tokenId)->not->toBe('');
});
