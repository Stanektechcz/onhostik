<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Net\DnsLookup;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * The status page on the customer's own host (audit §5k-3): the host is set in the settings, verified by its CNAME to
 * the portal host, then served on that host by the middleware; the on-demand TLS ask answers 200 only for verified hosts;
 * a host verified elsewhere cannot be claimed twice.
 */

beforeEach(fn () => Http::preventStrayRequests());

afterEach(fn () => DnsLookup::$resolver = null);

it('verifies the CNAME, serves the page on the custom host and answers the TLS ask', function () {
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $portal = OrganizationStatusService::customCname();
    DnsLookup::$resolver = fn (string $host): array => $host === 'status.shop.cz' ? [$portal.'.'] : ($host === 'status.other.cz' ? ['elsewhere.example.'] : []);

    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $this->withHeaders($h + ['Idempotency-Key' => 'sd-0'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['enabled' => true, 'title' => 'Shop status', 'domain' => 'not a host']])->assertStatus(422);
    $this->withHeaders($h + ['Idempotency-Key' => 'sd-1'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['enabled' => true, 'title' => 'Shop status', 'domain' => 'Status.Shop.cz']])->assertOk()->assertJsonPath('organization.settings.status_page.domain', 'status.shop.cz')->assertJsonPath('organization.settings.status_page.domain_verified_at', null);
    expect($this->withHeaders($h)->getJson('/v1/account/status-page')->assertOk()->json('data.expected_cname'))->toBe($portal);
    $this->getJson('/v1/status/host-check?host=status.shop.cz')->assertNotFound(); // not verified yet
    $this->get('http://status.shop.cz/')->assertStatus(200)->assertDontSee('Shop status'); // the portal answers as usual

    $verified = $this->withHeaders($h + ['Idempotency-Key' => 'sd-2'])->postJson('/v1/account/status-page/verify')->assertOk()->json();
    expect($verified['verified'])->toBeTrue()->and($verified['found'])->toBe([$portal])->and($verified['url'])->toBe('https://status.shop.cz/');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'status_page.domain.verified')->exists())->toBeTrue();
    $this->flushHeaders();
    auth()->forgetGuards();
    $this->getJson('/v1/status/host-check?host=status.shop.cz')->assertOk()->assertJsonPath('allowed', true);
    $this->getJson('/v1/status/host-check?host=evil.example')->assertNotFound();
    $this->get('http://status.shop.cz/')->assertOk()->assertSee('Shop status')->assertSee('Vše v provozu');
    $this->get('http://status.shop.cz/badge.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($this->getJson("/v1/status/org/{$org->slug}")->assertOk()->json('data.domain'))->toBe('status.shop.cz');

    // a wrong CNAME fails; another organization cannot claim a verified host; a changed host starts over
    [$other, $otherOrg] = $this->customerWithOrganization(['email' => 'other@firma.cz']);
    $this->actingAs($other, 'sanctum');
    $oh = ['X-Organization' => $otherOrg->id];
    $this->withHeaders($oh + ['Idempotency-Key' => 'sd-3'])->patchJson("/v1/organizations/{$otherOrg->id}", ['status_page' => ['enabled' => true, 'domain' => 'status.other.cz']])->assertOk();
    expect($this->withHeaders($oh + ['Idempotency-Key' => 'sd-4'])->postJson('/v1/account/status-page/verify')->assertOk()->json('verified'))->toBeFalse();
    $this->withHeaders($oh + ['Idempotency-Key' => 'sd-5'])->patchJson("/v1/organizations/{$otherOrg->id}", ['status_page' => ['domain' => 'status.shop.cz']])->assertOk();
    $this->withHeaders($oh + ['Idempotency-Key' => 'sd-6'])->postJson('/v1/account/status-page/verify')->assertStatus(409)->assertJsonPath('error', 'status_domain_taken');
    expect(app(OrganizationStatusService::class)->verifyPending())->toBe(0); // the pending one is taken, nothing else waits
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'sd-7'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['domain' => 'stav.shop.cz']])->assertOk()->assertJsonPath('organization.settings.status_page.domain_verified_at', null);
    $this->flushHeaders();
    auth()->forgetGuards();
    $this->getJson('/v1/status/host-check?host=status.shop.cz')->assertNotFound();
    $this->artisan('onhost:status:verify-domains')->assertSuccessful();
});
