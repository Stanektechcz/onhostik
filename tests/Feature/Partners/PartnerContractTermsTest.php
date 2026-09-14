<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Contract changes beyond the model (audit §5n-1): rate locks, payout terms and the white-label scope go through the
 * same request table — the partner asks from the portal, finance decides, money terms apply on the first of next
 * month, the scope at once; monthly / quarterly payout terms request the payable balance for the partner.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('lets a partner ask for any term, applies approved money terms next month and the scope at once, and pays out on terms', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    app(AutomationLedger::class)->setEnabled('partners.auto_approve', false, 'test'); // the manual path here; the rule has its own test (§5o-1)
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $partnerOrg->id];

    // the contract block: every term in force, the values a partner may ask for
    $terms = $this->withHeaders($h)->getJson('/v1/partner/changes')->assertOk()->json('data');
    expect($terms)->toMatchArray(['model' => 'share', 'rate' => 15, 'rate_locked_until' => null, 'payout_terms' => 'on_request', 'whitelabel_scope' => 'basic', 'pending' => [], 'open' => [], 'requests' => []])
        ->and($terms['kinds'])->toBe(PartnerService::CHANGE_VALUES)->and($terms['min_payout']['minor'])->toBe(100000);

    // validation: unknown term, unknown value, the value in force, one open request per term
    $this->withHeaders($h + ['Idempotency-Key' => 'ct-0'])->postJson('/v1/partner/changes', ['kind' => 'discount', 'value' => 'x'])->assertStatus(422);
    $this->withHeaders($h + ['Idempotency-Key' => 'ct-0b'])->postJson('/v1/partner/changes', ['kind' => 'rate_lock', 'value' => '5'])->assertStatus(422)->assertJsonPath('error', 'partner_change_value_invalid');
    $this->withHeaders($h + ['Idempotency-Key' => 'ct-0c'])->postJson('/v1/partner/changes', ['kind' => 'whitelabel_scope', 'value' => 'basic'])->assertStatus(409)->assertJsonPath('error', 'partner_change_same');
    $payout = $this->withHeaders($h + ['Idempotency-Key' => 'ct-1'])->postJson('/v1/partner/changes', ['kind' => 'payout_terms', 'value' => 'monthly', 'note' => 'Ať to chodí samo.'])->assertStatus(201)->json();
    expect($payout)->toMatchArray(['kind' => 'payout_terms', 'from' => 'on_request', 'to' => 'monthly', 'state' => 'requested']);
    $this->withHeaders($h + ['Idempotency-Key' => 'ct-1b'])->postJson('/v1/partner/changes', ['kind' => 'payout_terms', 'value' => 'quarterly'])->assertStatus(409)->assertJsonPath('error', 'partner_request_pending');
    $lock = $this->withHeaders($h + ['Idempotency-Key' => 'ct-2'])->postJson('/v1/partner/changes', ['kind' => 'rate_lock', 'value' => '6'])->assertStatus(201)->json();
    $scope = $this->withHeaders($h + ['Idempotency-Key' => 'ct-3'])->postJson('/v1/partner/changes', ['kind' => 'whitelabel_scope', 'value' => 'full'])->assertStatus(201)->json();
    $model = $this->withHeaders($h + ['Idempotency-Key' => 'ct-4'])->postJson('/v1/partner/changes', ['kind' => 'model', 'value' => 'oneoff'])->assertStatus(201)->json(); // the model goes through the same door
    expect($this->withHeaders($h)->getJson('/v1/partner/changes')->json('data.open'))->toHaveKeys(['payout_terms', 'rate_lock', 'whitelabel_scope', 'model']);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'partner.change.requested')->where('title', 'like', '%výplatní podmínky → měsíčně%')->exists())->toBeTrue()
        ->and(Notification::query()->where('audience', 'internal')->where('event', 'partner.model.requested')->count())->toBe(1);

    // the basic white-label scope keeps own mail / prices / support off and says so
    $wl = $this->withHeaders($h + ['Idempotency-Key' => 'ct-wl'])->putJson('/v1/partner/whitelabel', ['domain' => 'panel.pixel.cz', 'hide_brand' => true, 'own_mail' => true, 'own_support' => true])->assertOk()->json('whitelabel');
    expect($wl)->toMatchArray(['hide_brand' => true, 'own_mail' => false, 'own_support' => false, 'limited' => ['own_mail', 'own_support']]);

    // finance decides: money terms wait for the first of next month, the scope applies at once
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    expect($this->getJson('/v1/staff/partners/requests')->assertOk()->json('data'))->toHaveCount(4);
    $effective = now()->startOfMonth()->addMonth()->toDateString();
    $this->withHeader('Idempotency-Key', 'ct-d1')->postJson("/v1/staff/partners/requests/{$payout['id']}/decide", ['decision' => 'approve'])->assertOk()->assertJsonPath('effective_from', $effective)->assertJsonPath('applied_at', null);
    $this->withHeader('Idempotency-Key', 'ct-d2')->postJson("/v1/staff/partners/requests/{$lock['id']}/decide", ['decision' => 'approve', 'note' => 'Půl roku fix.'])->assertOk();
    $this->withHeader('Idempotency-Key', 'ct-d3')->postJson("/v1/staff/partners/requests/{$scope['id']}/decide", ['decision' => 'approve'])->assertOk()->assertJsonPath('effective_from', now()->toDateString())->assertJsonPath('state', 'approved');
    $this->withHeader('Idempotency-Key', 'ct-d4')->postJson("/v1/staff/partners/requests/{$model['id']}/decide", ['decision' => 'reject', 'note' => 'Model až po roce.'])->assertOk();
    $partner->refresh();
    expect($partner->whitelabel_scope)->toBe('full')->and($partner->payout_terms)->toBe('on_request')->and($partner->rate_locked_until)->toBeNull()->and($partner->model)->toBe('share');
    expect($this->getJson("/v1/staff/partners/{$partner->id}")->assertOk()->json('data'))->toMatchArray(['payout_terms' => 'on_request', 'whitelabel_scope' => 'full']);
    $this->actingAs($owner, 'sanctum');
    $terms = $this->withHeaders($h)->getJson('/v1/partner/changes')->assertOk()->json('data');
    expect($terms['pending'])->toMatchArray(['payout_terms' => ['to' => 'monthly', 'effective_from' => $effective], 'rate_lock' => ['to' => '6', 'effective_from' => $effective]])->and($terms['open'])->toBe([])->and($terms['whitelabel_scope'])->toBe('full');
    $wl = $this->withHeaders($h + ['Idempotency-Key' => 'ct-wl2'])->putJson('/v1/partner/whitelabel', ['domain' => 'panel.pixel.cz', 'own_mail' => true])->assertOk()->json('whitelabel');
    expect($wl)->toMatchArray(['own_mail' => true, 'limited' => []]); // the full scope lets the flags through
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.change.applied')->where('title', 'like', 'rozsah white-labelu%')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.change.approved')->count())->toBe(3)
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.model.rejected')->exists())->toBeTrue();

    // the first of next month: the daily command applies the money terms; the rate lock counts from that day
    expect($partners->applyPendingChanges())->toBe(0);
    $first = now()->startOfMonth()->addMonth()->addHours(3);
    $this->travelTo($first);
    expect(Artisan::call('onhost:partners:apply-models'))->toBe(0)->and(Artisan::output())->toContain('changes applied: 2');
    app(OutboxPublisher::class)->relayPending();
    $this->travelBack();
    $partner->refresh();
    expect($partner->payout_terms)->toBe('monthly')->and($partner->rate_locked_until?->toDateString())->toBe($first->copy()->startOfDay()->addMonths(6)->toDateString());
    expect($partners->terms($partner)['rate_locked_until'])->toBe($first->copy()->startOfDay()->addMonths(6)->toDateString())->and($partners->terms($partner)['pending'])->toBe([]);
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.change.applied')->count())->toBe(3);

    // monthly terms: the payable balance is requested for the partner on the 1st — once, above the minimum, with an IBAN
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $partnerOrg->id, 'invoice_id' => null, 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 15, 'amount_minor' => 150000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()]);
    expect($partners->autoPayouts())->toBe(['requested' => 0, 'skipped' => 1]); // no IBAN yet
    $partner->forceFill(['iban' => 'CZ6508000000192000145399'])->save();
    expect($partners->autoPayouts())->toBe(['requested' => 1, 'skipped' => 0]);
    $payoutRow = PartnerPayout::query()->where('partner_id', $partner->id)->firstOrFail();
    expect($payoutRow->amount_minor)->toBe(150000)->and($payoutRow->state)->toBe('requested');
    expect($partners->autoPayouts())->toBe(['requested' => 0, 'skipped' => 1]); // one open payout at a time
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.payout.auto')->where('body', 'like', '%(monthly)%')->exists())->toBeTrue();
    $partner->forceFill(['payout_terms' => 'quarterly'])->save();
    PartnerPayout::query()->where('partner_id', $partner->id)->update(['state' => 'paid']);
    PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $partnerOrg->id, 'invoice_id' => null, 'period' => now()->format('Y-m'), 'kind' => 'share', 'base_minor' => 800000, 'rate_pct' => 15, 'amount_minor' => 120000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()]); // the first payout allocated the earlier row
    expect($partners->autoPayouts(Carbon::parse('2027-02-01')))->toBe(['requested' => 0, 'skipped' => 0])->and($partners->autoPayouts(Carbon::parse('2027-04-01')))->toBe(['requested' => 1, 'skipped' => 0]); // quarters only
    expect(Artisan::call('onhost:partners:auto-payouts'))->toBe(0)->and(Artisan::output())->toContain('payouts requested: 0');

    // the portal renders the contract block through the seam
    $html = $this->get('/partner')->assertOk()->getContent();
    expect($html)->toContain('window.OnhostPartner.terms(this)')->toContain('{{ termsTitle }}')->toContain('{{ tm.action }}');
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js'));
    expect($js)->toContain("'/partner/changes'")->toContain('function requestChange(cmp, kind)');
});
