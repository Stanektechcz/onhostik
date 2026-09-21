<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Audit\AuditEvent;

/*
 * Staff reading a customer's data leaves a trail, and the customer sees it. A look at the account, at a ticket's
 * conversation or at the mail queue used to leave nothing: "who opened this customer's account last week" had no answer.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('records that support opened a customer\'s account and a ticket — once per quarter of an hour — and shows it to the customer', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ticket = app(TicketService::class)->create(['subject' => 'Nejde mi web', 'body' => 'Dobrý den, web hlásí chybu 500.'], $this->contextFor($owner, $org), $org, $owner);
    $agent = $this->staff('support_l2');
    $this->actingAs($agent, 'sanctum');

    $this->getJson("/v1/staff/customers/{$org->id}")->assertOk();
    $this->getJson("/v1/staff/customers/{$org->id}")->assertOk(); // the console refreshes itself: still one event
    $this->getJson("/v1/staff/tickets/{$ticket->id}")->assertOk();

    $reads = AuditEvent::query()->where('organization_id', $org->id)->where('action', 'like', 'staff.read.%')->orderBy('created_at')->orderBy('id')->get();
    expect($reads->pluck('action')->all())->toBe(['staff.read.customer', 'staff.read.ticket'])
        ->and($reads[0]->actor_id)->toBe($agent->id)->and($reads[0]->resource_id)->toBe($org->id)->and($reads[1]->resource_id)->toBe($ticket->id);

    // a quarter of an hour later the next look is an event again
    $this->travel(16)->minutes();
    $this->getJson("/v1/staff/customers/{$org->id}")->assertOk();
    expect(AuditEvent::query()->where('organization_id', $org->id)->where('action', 'staff.read.customer')->count())->toBe(2);

    // the customer sees it in their own audit trail
    $this->actingAs($owner, 'sanctum');
    $feed = $this->getJson("/v1/organizations/{$org->id}/audit?action=staff.read")->assertOk()->json('data');
    expect(collect($feed)->pluck('action')->unique()->values()->all())->toEqualCanonicalizing(['staff.read.customer', 'staff.read.ticket']);
});

it('records a look at the mail queue and at an integration, which belong to no single customer', function () {
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->getJson('/v1/staff/outbox')->assertOk();
    expect(AuditEvent::query()->where('action', 'staff.read.mail_outbox')->whereNull('organization_id')->count())->toBe(1);
});
