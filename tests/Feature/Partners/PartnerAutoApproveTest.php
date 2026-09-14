<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Term-specific finance rules (audit §5o-1): a short rate lock and a payout-terms change for a partner with a clean year
 * are approved by the rule without finance; longer locks, fresh partners, partners with a rejected payout, the model and
 * the white-label scope still wait; the rule can be switched off.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('approves simple terms by rule and leaves the rest to finance', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    expect(app(AutomationLedger::class)->enabled('partners.auto_approve'))->toBeTrue();
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $partnerOrg->id];

    // a six-month rate lock: approved at once, effective next month, finance only informed
    $effective = now()->startOfMonth()->addMonth()->toDateString();
    $lock = $this->withHeaders($h + ['Idempotency-Key' => 'aa-1'])->postJson('/v1/partner/changes', ['kind' => 'rate_lock', 'value' => '6'])->assertStatus(201)->json();
    expect($lock)->toMatchArray(['state' => 'approved', 'effective_from' => $effective])->and($lock['decision_note'])->toContain('automaticky');
    expect($this->withHeaders($h)->getJson('/v1/partner/changes')->json('data.pending.rate_lock'))->toMatchArray(['to' => '6', 'effective_from' => $effective]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'partner.change.auto_approved')->where('title', 'like', '%zámek sazby → 6 měsíců%')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.change.approved')->count())->toBe(1);

    // a twelve-month lock is above the rule's ceiling; the model and the scope never go through the rule
    expect($partners->requestChange($partner, 'rate_lock', '12', null, CommandContext::system('test'))->state)->toBe(PartnerChangeRequest::REQUESTED);
    expect($this->withHeaders($h + ['Idempotency-Key' => 'aa-2'])->postJson('/v1/partner/changes', ['kind' => 'whitelabel_scope', 'value' => 'full'])->assertStatus(201)->json('state'))->toBe('requested');
    expect($this->withHeaders($h + ['Idempotency-Key' => 'aa-3'])->postJson('/v1/partner/changes', ['kind' => 'model', 'value' => 'oneoff'])->assertStatus(201)->json('state'))->toBe('requested');

    // payout terms: a fresh partner waits; a partner with a clean year is approved; a rejected payout inside the year blocks the rule
    expect($this->withHeaders($h + ['Idempotency-Key' => 'aa-4'])->postJson('/v1/partner/changes', ['kind' => 'payout_terms', 'value' => 'monthly'])->assertStatus(201)->json('state'))->toBe('requested');
    PartnerChangeRequest::query()->where('kind', 'payout_terms')->delete();
    $partner->forceFill(['approved_at' => now()->subMonths(14)])->save();
    expect($partners->autoApproveReason($partner->refresh(), 'payout_terms', 'monthly'))->toContain('čistý rok');
    PartnerPayout::query()->create(['partner_id' => $partner->id, 'number' => 'PO-TEST-1', 'amount_minor' => 100000, 'currency' => 'CZK', 'method' => 'bank_transfer', 'iban' => 'CZ6508000000192000145399', 'state' => 'rejected', 'self_billing' => ['number' => 'SB-TEST-1'], 'requested_at' => now()]);
    expect($partners->autoApproveReason($partner->refresh(), 'payout_terms', 'monthly'))->toBeNull();
    PartnerPayout::query()->delete();
    $terms = $this->withHeaders($h + ['Idempotency-Key' => 'aa-5'])->postJson('/v1/partner/changes', ['kind' => 'payout_terms', 'value' => 'quarterly'])->assertStatus(201)->json();
    expect($terms)->toMatchArray(['state' => 'approved', 'effective_from' => $effective]);

    // §5p-5: the model back to the default joins the rule after a clean year — never the other way round
    PartnerChangeRequest::query()->where('kind', 'model')->delete();
    $partner->forceFill(['model' => 'oneoff'])->save();
    expect($partners->autoApproveReason($partner->refresh(), 'model', 'share'))->toContain('výchozí model')->and($partners->autoApproveReason($partner, 'model', 'oneoff'))->toBeNull();
    $back = $this->withHeaders($h + ['Idempotency-Key' => 'aa-7'])->postJson('/v1/partner/changes', ['kind' => 'model', 'value' => 'share'])->assertStatus(201)->json();
    expect($back)->toMatchArray(['state' => 'approved', 'effective_from' => $effective])->and($partner->refresh()->pending_model)->toBe('share');
    $partner->forceFill(['model' => 'share', 'pending_model' => null, 'model_effective_from' => null])->save();
    PartnerChangeRequest::query()->where('kind', 'model')->delete();
    expect($this->withHeaders($h + ['Idempotency-Key' => 'aa-8'])->postJson('/v1/partner/changes', ['kind' => 'model', 'value' => 'oneoff'])->assertStatus(201)->json('state'))->toBe('requested');

    // the rule off: everything waits for finance again
    app(AutomationLedger::class)->setEnabled('partners.auto_approve', false, 'test');
    PartnerChangeRequest::query()->where('kind', 'rate_lock')->delete();
    expect($this->withHeaders($h + ['Idempotency-Key' => 'aa-6'])->postJson('/v1/partner/changes', ['kind' => 'rate_lock', 'value' => '3'])->assertStatus(201)->json('state'))->toBe('requested');
    expect(collect($this->actingAs($this->staff('platform_owner'), 'sanctum')->getJson('/v1/staff/automation')->assertOk()->json('data'))->firstWhere('key', 'partners.auto_approve'))->toMatchArray(['enabled' => false, 'switchable' => true]);
});
