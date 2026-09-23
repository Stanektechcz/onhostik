<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * The customer's service desk lists services by what they are — web hosting, domains, servers — and never by what is
 * happening to them. A customer with thirty services and one suspended had to open every category to find it, and
 * the API filter (`GET /v1/services?state=`) is not something a panel user ever sees.
 *
 * The desk's categories already have a seam of their own (`OnhostPanelNav.cats` reads `nav.categories`), so the state
 * views arrive the way every other category does — through the payload. A view appears only when it holds something —
 * a customer whose services are all healthy sees exactly the categories they saw before.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The payload the panel reads (`window.ONHOST_PANEL = {...};`). */
function panelPayload(object $test, object $user): array
{
    $js = (string) $test->actingAs($user)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    $from = strpos($js, '{');
    $to = strrpos($js, '}');

    return $from === false || $to === false ? [] : (json_decode(substr($js, $from, $to - $from + 1), true) ?: []);
}

it('offers a view of the services that need attention, and only when there are any', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');

    $clean = panelPayload($this, $user);
    expect(collect($clean['nav']['categories'] ?? [])->pluck('key')->all())->not->toContain('state:attention');

    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => 'payment'])->save();
    $payload = panelPayload($this, $user);

    $cats = collect($payload['nav']['categories'] ?? [])->where('visible', true);
    expect($cats->pluck('key')->all())->toContain('state:attention')
        ->and($cats->firstWhere('key', 'state:attention')['label'])->toMatchArray(['cs' => 'Vyžadují pozornost', 'en' => 'Need attention'])
        ->and(collect($payload['services']['state:attention'] ?? [])->pluck('id')->all())->toBe([$service->id])
        ->and(collect($payload['services']['web'] ?? [])->pluck('id')->all())->toBe([$service->id]); // still in its own category too
});

it('lists a service that is ending in a view of its own', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ending = featureWebService($org, 'aapanel');
    $ending->forceFill(['terminate_at' => now()->addDays(20), 'tags' => array_merge((array) $ending->tags, ['deletion' => ['reason' => 'customer']])])->save();

    $payload = panelPayload($this, $user);

    expect(collect($payload['nav']['categories'] ?? [])->pluck('key')->all())->toContain('state:ending')
        ->and(collect($payload['services']['state:ending'] ?? [])->pluck('id')->all())->toBe([$ending->id]);
});

it('keeps the offer own categories in front of the state views', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => 'payment'])->save();

    $keys = collect(panelPayload($this, $user)['nav']['categories'] ?? [])->pluck('key')->all();
    $states = array_values(array_filter($keys, fn (string $key) => str_starts_with($key, 'state:')));

    expect($keys[0] ?? '')->not->toStartWith('state:')
        ->and($states)->not->toBe([])
        ->and(array_slice($keys, -count($states)))->toBe($states); // the state views come last, after the offer
});

it('serves a panel whose categories come from the payload', function () {
    [$user] = $this->customerWithOrganization();

    $html = (string) $this->actingAs($user)->get('/panel')->assertOk()->getContent();

    expect($html)->toContain('cats: (window.OnhostPanelNav && window.OnhostPanelNav.cats(this, _)) || [')  // the desk's own seam lists them
        ->and($html)->toContain("indexOf('state:')");   // and a state view never counts a service twice
});
