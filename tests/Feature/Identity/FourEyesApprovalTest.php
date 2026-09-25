<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Four eyes (docs/runbooks/approvals.md). The gate stood in the authorizer from the start, but nothing could make an
 * approval — so a critical action was either impossible, or its command called itself "high" and lost the second person:
 * a legal hold was placed and lifted by one member of staff alone.
 */

function fourEyesStaff(TestCase $test, array $roles, string $email): User
{
    $user = User::factory()->staff()->create(['email' => $email]);
    foreach ($roles as $role) {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    }
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    return $user;
}

it('opens a request when a critical action wants a second person, lets somebody else who could do it themselves decide, and spends the approval once', function () {
    [, $org] = $this->customerWithOrganization();
    $legal = fourEyesStaff($this, ['compliance_legal', 'iam_admin'], 'legal@onhost.test'); // may place holds AND decide approvals — still not their own
    $iam = fourEyesStaff($this, ['iam_admin'], 'iam@onhost.test');                           // may decide approvals, may not place a hold
    $owner = fourEyesStaff($this, ['platform_owner'], 'owner@onhost.test');
    $noc = fourEyesStaff($this, ['support_l1'], 'l1@onhost.test');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-1234/2026'];

    // 1. one person alone: refused, and the refusal carries the request it opened (asking twice opens it once)
    $this->actingAs($legal, 'sanctum');
    $refused = $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->assertJsonPath('requirement', 'approval')->assertJsonPath('approval_state', 'pending');
    $id = (string) $refused->json('approval_id');
    expect($this->postJson($url, $body)->assertForbidden()->json('approval_id'))->toBe($id)->and(Approval::query()->count())->toBe(1);
    expect($org->fresh()->settings['legal_hold'] ?? false)->toBeFalse();
    $row = Approval::query()->findOrFail($id);
    expect($row->action)->toBe('compliance.legal_hold')->and($row->requested_by)->toBe($legal->id)->and($row->reason)->toBe($body['reason'])
        ->and(data_get($row->payload, 'permission'))->toBe('compliance.legal_hold.manage')->and((int) round(now()->diffInHours($row->expires_at)))->toBe(24);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'iam.approval.requested')->where('audience', 'internal')->exists())->toBeTrue();

    // 2. who may decide: not the requester, not somebody who could not place the hold themselves, not somebody without the permission at all
    $decide = fn (array $payload) => $this->withHeader('Idempotency-Key', 'fe-'.bin2hex(random_bytes(4)))->postJson("/v1/staff/approvals/{$id}/decision", $payload);
    $decide(['decision' => 'approved'])->assertForbidden()->assertJsonPath('error', 'approval_own_request');
    $this->actingAs($iam, 'sanctum');
    $decide(['decision' => 'approved'])->assertForbidden()->assertJsonPath('error', 'approver_lacks_permission');
    $decide(['decision' => 'rejected'])->assertStatus(422)->assertJsonPath('error', 'note_required'); // a rejection says why
    $this->actingAs($noc, 'sanctum');
    $decide(['decision' => 'approved'])->assertForbidden();
    expect($row->fresh()->state)->toBe('pending');

    // 3. the lists: whoever may decide sees every request, everybody else their own
    $this->getJson('/v1/staff/approvals?state=pending')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.can_decide', false);
    $this->actingAs($iam, 'sanctum');
    $this->getJson('/v1/staff/approvals?state=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.requested_by.id', $legal->id)->assertJsonPath('meta.can_decide', true)->assertJsonPath('meta.second_person_exists', true);

    // 4. the second person approves — behind a fresh step-up of their own
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->revokeAll($owner);
    $decide(['decision' => 'approved'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $decide(['decision' => 'approved', 'note' => 'ověřeno proti žádosti'])->assertOk()->assertJsonPath('state', 'approved')->assertJsonPath('decided_by.id', $owner->id);
    $decide(['decision' => 'rejected', 'note' => 'pozdě'])->assertStatus(409)->assertJsonPath('error', 'approval_not_pending');

    // 5. the requester repeats the SAME request with the approval: it goes through once, for exactly that payload
    $this->actingAs($legal, 'sanctum');
    $fresh = fn () => $this->withHeader('Idempotency-Key', 'fe-'.bin2hex(random_bytes(4))); // the test client keeps a header once set: every request gets its own key
    $fresh()->postJson($url, ['hold' => true, 'reason' => 'Jiný důvod, jiná žádost 12345', 'approval_ids' => [$id]])->assertForbidden()->assertJsonPath('error', 'approval_required'); // another payload is another request
    $fresh()->postJson($url, $body)->assertOk()->assertJsonPath('legal_hold', true); // the console repeats the action as it was: the approval of exactly this command by this person is found (approval_ids may name it, need not)
    expect($row->fresh()->state)->toBe('consumed')->and($row->fresh()->consumed_at)->not->toBeNull();
    $audit = AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->latest('id')->firstOrFail();
    expect($audit->approval_ids)->toBe([$id])->and($audit->step_up_method)->toBe('totp');
    // lifting it is a new critical action: the spent approval does not carry it
    $fresh()->postJson($url, ['hold' => false, 'reason' => 'Řízení ukončeno, hold zrušen.', 'approval_ids' => [$id]])->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect($org->fresh()->settings['legal_hold'] ?? false)->toBeTrue();
});

it('lets a request run out: an approval nobody gave in a day cannot be given any more', function () {
    [, $org] = $this->customerWithOrganization();
    $legal = fourEyesStaff($this, ['compliance_legal'], 'legal2@onhost.test');
    $owner = fourEyesStaff($this, ['platform_owner'], 'owner2@onhost.test');
    $this->actingAs($legal, 'sanctum');
    $id = (string) $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", ['hold' => true, 'reason' => 'Žádost soudu 45 T 12/2026'])->assertForbidden()->json('approval_id');

    $this->travel(25)->hours();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'fe-late')->postJson("/v1/staff/approvals/{$id}/decision", ['decision' => 'approved'])->assertStatus(409)->assertJsonPath('error', 'approval_expired');
    Approval::query()->whereKey($id)->update(['state' => 'pending']);
    expect(app(ApprovalService::class)->expire())->toBe(1)->and(Approval::query()->whereKey($id)->value('state'))->toBe('expired');
});

it('runs with one operator when the server says so: the step-up stays, the audit says why nobody else signed', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $solo = User::factory()->staff()->create(['email' => 'solo@onhost.test']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $solo->id, 'role_key' => 'platform_owner', 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    $this->actingAs($solo, 'sanctum');
    $body = ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-99/2026'];

    $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", $body)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/staff/customers/{$org->id}/legal-hold", $body)->assertOk()->assertJsonPath('legal_hold', true);
    expect(Approval::query()->count())->toBe(0);
    expect(AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->latest('id')->firstOrFail()->approval_ids)->toBe(['waived:single-operator']);
    $this->getJson('/v1/staff/approvals')->assertOk()->assertJsonPath('meta.four_eyes', false);
});

it('shows staff the approvals page and nobody else', function () {
    $this->get('/sprava/nastaveni/schvalovani')->assertRedirect();
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum')->get('/sprava/nastaveni/schvalovani')->assertRedirect('/panel');
    $this->actingAs($this->staff('iam_admin'), 'sanctum')->get('/sprava/nastaveni/schvalovani')->assertOk()->assertSee('Schvalování')->assertSee('/staff/approvals', false);
});

it('spends an approval once even when two requests read it as unused at the same moment', function () {
    [, $org] = $this->customerWithOrganization();
    $legal = fourEyesStaff($this, ['compliance_legal'], 'legal-race@onhost.test');
    $owner = fourEyesStaff($this, ['platform_owner'], 'owner-race@onhost.test');
    $url = "/v1/staff/customers/{$org->id}/legal-hold";
    $body = ['hold' => true, 'reason' => 'Žádost PČR č. j. KRPA-4321/2026'];
    $this->actingAs($legal, 'sanctum');
    $id = (string) $this->withHeader('Idempotency-Key', 'fe-race-1')->postJson($url, $body)->assertForbidden()->json('approval_id');
    secondPersonApproves($id, $owner);

    // another request (another Idempotency-Key) read the same approval as unused a moment earlier and spent it first
    $spent = false;
    Approval::retrieved(function (Approval $approval) use ($id, &$spent) {
        if (! $spent && $approval->id === $id && $approval->state === 'approved') {
            $spent = true;
            Approval::query()->whereKey($id)->update(['state' => 'consumed', 'consumed_at' => now()]);
        }
    });
    $this->withHeader('Idempotency-Key', 'fe-race-2')->postJson($url, $body + ['approval_ids' => [$id]])->assertForbidden()->assertJsonPath('error', 'approval_required');
    Approval::flushEventListeners();

    expect($spent)->toBeTrue()->and($org->fresh()->settings['legal_hold'] ?? false)->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'compliance.legal_hold')->where('result', 'succeeded')->exists())->toBeFalse();
});
