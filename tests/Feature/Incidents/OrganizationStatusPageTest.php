<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Platform\Commands\CommandContext;

/*
 * The organization's own status page (audit §5j-5): off by default, switched on in the account settings; shows the
 * customer's monitors by host name, the platform components behind their services, incidents and maintenance that
 * touch them; the badge follows the overall state.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('is off by default, shows monitors, components and incidents when enabled, and serves the page and the badge', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://www.shop.cz/kosik?x=1', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'up', 'last_ms' => 120]);
    $this->getJson("/v1/status/org/{$org->slug}")->assertNotFound();
    $this->get("/stav/{$org->slug}")->assertNotFound();

    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $mine = $this->withHeaders($h)->getJson('/v1/account/status-page')->assertOk()->json('data');
    expect($mine['settings']['enabled'])->toBeFalse()->and($mine['url'])->toEndWith("/stav/{$org->slug}")->and($mine['preview'])->toBeNull();
    $this->withHeaders($h + ['Idempotency-Key' => 'sp-1'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['enabled' => true, 'title' => 'Moje weby']])->assertOk()->assertJsonPath('organization.settings.status_page.enabled', true);
    $this->flushHeaders();

    $page = $this->getJson("/v1/status/org/{$org->slug}")->assertOk()->json('data');
    expect($page['title'])->toBe('Moje weby')->and($page['overall'])->toBe('operational')->and($page['monitors'][0])->toMatchArray(['host' => 'www.shop.cz', 'state' => 'up', 'ms' => 120])
        ->and(collect($page['components'])->pluck('key')->all())->toContain('web-cz1')->and(json_encode($page))->not->toContain('kosik'); // host names only
    $this->get("/stav/{$org->slug}")->assertOk()->assertSee('Moje weby')->assertSee('www.shop.cz')->assertSee('Vše v provozu');
    $this->get("/stav/{$org->slug}/badge.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('Vše v provozu');

    // an incident on the customer's service shows up and turns the overall state; a monitor down degrades it
    $incident = app(IncidentService::class)->open(['title' => 'Výpadek webového uzlu', 'severity' => 'p1', 'components' => ['web-cz1'], 'affected_services' => [$service->id]], CommandContext::system('test'));
    $page = $this->getJson("/v1/status/org/{$org->slug}")->assertOk()->json('data');
    expect($page['overall'])->toBe('major_outage')->and($page['incidents'][0]['number'])->toBe($incident->number);
    $this->get("/stav/{$org->slug}/badge.svg")->assertOk()->assertSee('Výpadek');
    app(IncidentService::class)->resolve($incident, 'opraveno', CommandContext::system('test'));
    UptimeMonitor::query()->where('organization_id', $org->id)->update(['state' => 'down']);
    expect($this->getJson("/v1/status/org/{$org->slug}")->assertOk()->json('data.overall'))->toBe('degraded');

    // monitors can be hidden; switching the page off hides it again
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'sp-2'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['show_monitors' => false]])->assertOk();
    $this->flushHeaders();
    expect($this->getJson("/v1/status/org/{$org->slug}")->assertOk()->json('data.monitors'))->toBe([]);
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'sp-3'])->patchJson("/v1/organizations/{$org->id}", ['status_page' => ['enabled' => false]])->assertOk();
    $this->flushHeaders();
    $this->getJson("/v1/status/org/{$org->slug}")->assertNotFound();
});
