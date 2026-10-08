<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * L-15 (docs/legal/LEGAL_REVIEW_2026-10.md): a complaint (reklamace) about a digital service is a ticket the law watches. The
 * consumer gets a confirmation of receipt on a durable medium (§ 19 (1) of the Consumer Protection Act) with the date and the
 * deadline, the complaint is decided within 30 days (§ 19 (3)), and the decision — the date and how it was handled, or why it
 * was refused — is confirmed again. Support sees the deadline coming and the day it is missed.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-02 09:00:00', 'UTC'));
});

it('confirms a complaint the customer files, with the 30-day deadline, by a mandatory mail', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');

    $id = $this->postJson('/v1/tickets', ['subject' => 'Reklamace: web nefunguje', 'body' => 'Uplatňuji reklamaci, služba neodpovídá smlouvě.', 'category' => 'reklamace'])->assertCreated()->json('data.id');
    app(OutboxPublisher::class)->relayPending();

    $ticket = Ticket::query()->findOrFail($id);
    expect($ticket->category)->toBe('reklamace')->and($ticket->complaint_received_at?->toIso8601String())->toBe('2026-11-02T09:00:00+00:00')
        ->and($ticket->complaint_due_at?->toDateString())->toBe('2026-12-02')
        ->and($ticket->resolution_due_at->lessThanOrEqualTo($ticket->complaint_due_at))->toBeTrue();
    expect(NotificationService::TEMPLATE_KINDS['complaint-received'])->toBe('legal.notice')
        ->and(OutboxMessage::query()->where('name', 'ticket.complaint.received')->count())->toBe(1);
    $mail = MailOutbox::query()->where('template_key', 'complaint-received')->sole();
    expect($mail->vars['cislo'])->toBe($ticket->number)->and($mail->vars['lhuta'])->toBe('2. 12. 2026');
});

it('lets support mark a ticket as a complaint once, from the day the customer sent it, and confirms the decision', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $id = $this->postJson('/v1/tickets', ['subject' => 'Pomalý web', 'body' => 'Web je pomalý už týden, chci slevu.'])->assertCreated()->json('data.id');
    $this->travel(3)->days();

    $support = $this->staff('support_l2');
    $this->actingAs($support, 'sanctum');
    $this->postJson("/v1/staff/tickets/{$id}/complaint")->assertOk()->assertJsonPath('complaint.due_at', '2026-12-02T09:00:00+00:00'); // 30 days from the customer's message
    $this->postJson("/v1/staff/tickets/{$id}/complaint")->assertOk(); // once: no second confirmation
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'ticket.complaint.received')->count())->toBe(1);

    $this->postJson("/v1/staff/tickets/{$id}/complaint/resolve", ['outcome' => 'maybe', 'resolution' => 'Nevím.'])->assertStatus(422);
    $done = $this->postJson("/v1/staff/tickets/{$id}/complaint/resolve", ['outcome' => 'accepted', 'resolution' => 'Uznáno: přesunuli jsme web na rychlejší uzel a poskytujeme slevu 20 % z ceny měsíce.'])->assertOk();
    expect($done->json('complaint.outcome'))->toBe('accepted')->and($done->json('complaint.resolved_at'))->not->toBeNull();
    $this->postJson("/v1/staff/tickets/{$id}/complaint/resolve", ['outcome' => 'rejected', 'resolution' => 'Podruhé se nerozhoduje.'])->assertStatus(409)->assertJsonPath('error', 'complaint_already_resolved');
    app(OutboxPublisher::class)->relayPending();

    $mail = MailOutbox::query()->where('template_key', 'complaint-resolved')->sole();
    expect(NotificationService::TEMPLATE_KINDS['complaint-resolved'])->toBe('legal.notice')
        ->and($mail->vars['vysledek'])->toBe('uznána')->and($mail->vars['zpusob'])->toContain('slevu 20 %');
});

it('refuses to resolve a ticket that is not a complaint, and keeps customers out', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $id = $this->postJson('/v1/tickets', ['subject' => 'Dotaz', 'body' => 'Jak nastavit DNS?'])->assertCreated()->json('data.id');
    $this->postJson("/v1/staff/tickets/{$id}/complaint")->assertForbidden();

    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson("/v1/staff/tickets/{$id}/complaint/resolve", ['outcome' => 'rejected', 'resolution' => 'Nejde o reklamaci vůbec.'])->assertStatus(409)->assertJsonPath('error', 'ticket_not_a_complaint');
});

it('tells support when the 30 days are running out and when they ran out, once each', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/tickets', ['subject' => 'Reklamace e-mailu', 'body' => 'Reklamuji nedoručování pošty.', 'category' => 'reklamace'])->assertCreated();
    $tickets = app(TicketService::class);

    $this->travel(26)->days();
    $tickets->tick();
    $tickets->tick();
    expect(OutboxMessage::query()->where('name', 'ticket.complaint.due_soon')->count())->toBe(1)->and(OutboxMessage::query()->where('name', 'ticket.complaint.overdue')->count())->toBe(0);

    $this->travel(5)->days();
    $tickets->tick();
    $tickets->tick();
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'ticket.complaint.overdue')->count())->toBe(1)
        ->and(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Reklamace po lhůtě%')->exists())->toBeTrue();
});
