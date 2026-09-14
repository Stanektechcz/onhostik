<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerChangeRequest;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * The commission model as a contract term (audit §5m-1): the partner asks finance for the other model instead of flipping a
 * local toggle; finance approves or rejects; an approved change takes effect on the first of the next month, applied by the
 * daily command; the portal reads the request state and the prototype button asks instead of switching.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('asks finance for the other model, applies an approved change next month and shows the state in the portal', function () {
    [$owner, $partnerOrg] = $this->customerWithOrganization(['email' => 'agentura@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.']);
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $partnerOrg->id];

    // the request: the model in force is refused, an unknown one is validated, the second request waits behind the first
    expect($this->withHeaders($h)->getJson('/v1/partner/model')->assertOk()->json('data'))->toMatchArray(['request' => null, 'model' => 'share', 'pending_model' => null]);
    $this->withHeaders($h + ['Idempotency-Key' => 'pm-0'])->postJson('/v1/partner/model', ['model' => 'share'])->assertStatus(409)->assertJsonPath('error', 'partner_model_same');
    $this->withHeaders($h + ['Idempotency-Key' => 'pm-0b'])->postJson('/v1/partner/model', ['model' => 'hybrid'])->assertStatus(422);
    $request = $this->withHeaders($h + ['Idempotency-Key' => 'pm-1'])->postJson('/v1/partner/model', ['model' => 'oneoff', 'note' => 'Chceme jednorázové bonusy.'])->assertStatus(201)->json();
    expect($request)->toMatchArray(['state' => 'requested', 'from' => 'share', 'to' => 'oneoff', 'note' => 'Chceme jednorázové bonusy.', 'effective_from' => null]);
    $this->withHeaders($h + ['Idempotency-Key' => 'pm-2'])->postJson('/v1/partner/model', ['model' => 'oneoff'])->assertStatus(409)->assertJsonPath('error', 'partner_request_pending');
    expect($this->withHeaders($h)->getJson('/v1/partner/model')->assertOk()->json('data.request.state'))->toBe('requested');
    expect(Partner::query()->findOrFail($partner->id)->model)->toBe('share'); // nothing switched locally
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'partner.model.requested')->where('title', 'like', '%share → oneoff%')->exists())->toBeTrue();

    // finance approves: the partner keeps the old model until the first of next month; the daily command flips it then
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    expect($this->getJson('/v1/staff/partners/requests')->assertOk()->json('data'))->toHaveCount(1)->and($this->getJson('/v1/staff/partners/requests')->json('data.0'))->toMatchArray(['id' => $request['id'], 'partner_code' => $partner->code, 'state' => 'requested']);
    $this->withHeader('Idempotency-Key', 'pm-d1')->postJson("/v1/staff/partners/requests/{$request['id']}/decide", ['decision' => 'maybe'])->assertStatus(422);
    $effective = now()->startOfMonth()->addMonth()->toDateString();
    $decided = $this->withHeader('Idempotency-Key', 'pm-d2')->postJson("/v1/staff/partners/requests/{$request['id']}/decide", ['decision' => 'approve', 'note' => 'OK od příštího měsíce'])->assertOk()->json();
    expect($decided)->toMatchArray(['state' => 'approved', 'effective_from' => $effective, 'decision_note' => 'OK od příštího měsíce']);
    $this->withHeader('Idempotency-Key', 'pm-d3')->postJson("/v1/staff/partners/requests/{$request['id']}/decide", ['decision' => 'reject'])->assertStatus(409)->assertJsonPath('error', 'partner_request_decided');
    $partner->refresh();
    expect($partner->model)->toBe('share')->and($partner->pending_model)->toBe('oneoff')->and($partner->model_effective_from?->toDateString())->toBe($effective);
    expect($this->getJson("/v1/staff/partners/{$partner->id}")->assertOk()->json('data'))->toMatchArray(['model' => 'share', 'pending_model' => 'oneoff', 'model_effective_from' => $effective]);
    expect($this->getJson('/v1/staff/partners/requests?state=requested')->json('data'))->toBe([])->and($this->getJson('/v1/staff/partners/requests?state=all')->json('data'))->toHaveCount(1);
    expect($partners->applyPendingModels())->toBe(0); // not yet
    expect(Artisan::call('onhost:partners:apply-models'))->toBe(0)->and(Artisan::output())->toContain('changes applied: 0');
    $this->travelTo(now()->startOfMonth()->addMonth()->addHours(3));
    expect($partners->applyPendingModels())->toBe(1)->and($partners->applyPendingModels())->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    $this->travelBack();
    $partner->refresh();
    expect($partner->model)->toBe('oneoff')->and($partner->pending_model)->toBeNull()->and($partner->model_effective_from)->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.model.approved')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.model.changed')->where('title', 'like', '%oneoff%')->exists())->toBeTrue();

    // a rejected request changes nothing and carries the reason back to the portal
    $this->actingAs($owner, 'sanctum');
    $second = $this->withHeaders($h + ['Idempotency-Key' => 'pm-3'])->postJson('/v1/partner/model', ['model' => 'share'])->assertStatus(201)->json();
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'pm-d4')->postJson("/v1/staff/partners/requests/{$second['id']}/decide", ['decision' => 'reject', 'note' => 'Model se mění nejdřív po roce.'])->assertOk()->assertJsonPath('state', 'rejected');
    expect(Partner::query()->findOrFail($partner->id)->pending_model)->toBeNull()->and(PartnerChangeRequest::query()->count())->toBe(2);
    $this->actingAs($owner, 'sanctum');
    expect($this->withHeaders($h)->getJson('/v1/partner/model')->assertOk()->json('data'))->toMatchArray(['model' => 'oneoff', 'pending_model' => null])->and($this->withHeaders($h)->getJson('/v1/partner/model')->json('data.request'))->toMatchArray(['state' => 'rejected', 'decision_note' => 'Model se mění nejdřív po roce.']);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('event', 'partner.model.rejected')->where('body', 'Model se mění nejdřív po roce.')->exists())->toBeTrue();

    // the prototype's model buttons ask finance through the seam instead of switching; the note reads the request state
    $html = $this->get('/partner')->assertOk()->getContent();
    expect($html)->toContain('if (window.OnhostPartner && window.OnhostPartner.requestModel(this, m[0])) return; this.setState({ model: m[0] });')
        ->toContain('modelNote: (window.OnhostPartner && window.OnhostPartner.modelNote(this)) || modelNote')
        ->toContain('Model provize je smluvní podmínka: o změnu požádáte tlačítkem níže')->not->toContain('Model si můžete přepnout kdykoli');
    $js = (string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js'));
    expect($js)->toContain("'/partner/model'")->toContain('function requestModel(cmp, model)')->toContain('function modelNote(cmp)');
});
