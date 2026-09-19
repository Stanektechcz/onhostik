<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;

/*
 * Which organization a request speaks for (Brain cards H03 and H343). The client names it in `X-Organization`; naming
 * somebody else's is refused, and a membership that ended on its date stops counting at that second — for the choice of
 * the organization, for the lists and for the panel's data — whether the scheduled clean-up has run or not.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
});

it('does not let a customer speak for an organization they do not belong to', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum');

    foreach (['/v1/services', '/v1/orders', '/v1/invoices', '/v1/domains', '/v1/tickets', '/v1/dns/zones', '/v1/payments', '/v1/services/archives'] as $list) {
        $this->withHeader('X-Organization', $org->id)->getJson($list)->assertForbidden();
        $this->getJson($list.'?organization='.$org->id)->assertForbidden();
    }
    $this->flushHeaders();
    // their own lists never carry the other organization's rows
    expect((string) $this->getJson('/v1/services')->assertOk()->getContent())->not->toContain($service->id);
    // and the panel's data cannot be asked for in another organization's name
    expect((string) $this->get('/surfaces/onhost-panel.js?organization='.$org->id)->getContent())->not->toContain($service->id)->not->toContain('shop.cz');
});

it('stops counting a membership at the second its access ends, before any clean-up has run', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $lecturer = $this->customer();
    app(OrganizationService::class)->attachMember($org, $lecturer, 'developer', CommandContext::system('test'), true, now()->addDay());
    $this->actingAs($lecturer, 'sanctum');

    // while it lasts
    $this->withHeader('X-Organization', $org->id)->getJson('/v1/services')->assertOk()->assertJsonPath('data.0.id', $service->id);
    expect((string) $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent())->toContain($service->id);
    expect(collect($this->getJson('/v1/organizations')->assertOk()->json('data'))->pluck('id')->all())->toContain($org->id);

    // the date passes; onhost:access:expire has not run (or is switched off)
    $this->travel(2)->days();
    $this->withHeader('X-Organization', $org->id)->getJson('/v1/services')->assertForbidden();
    $this->flushHeaders();
    expect((string) $this->get('/surfaces/onhost-panel.js')->getContent())->not->toContain($service->id);
    expect(collect($this->getJson('/v1/organizations')->assertOk()->json('data'))->pluck('id')->all())->not->toContain($org->id);
    expect(collect($this->getJson('/v1/auth/me')->json('data.organizations') ?? [])->pluck('id')->all())->not->toContain($org->id);
});
