<?php

declare(strict_types=1);

use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\TicketRating;

function makeClosedTicket(\App\Models\User $user): SupportTicket
{
    return SupportTicket::factory()->create([
        'customer_id'   => $user->customer->id,
        'status'        => TicketStatus::Closed,
        'priority'      => TicketPriority::Normal,
        'subject'       => 'Test hodnocení ticketu',
        'last_reply_at' => now(),
    ]);
}

it('customer can rate a closed ticket', function (): void {
    $user   = customerUser();
    $ticket = makeClosedTicket($user);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), [
             'score'   => 5,
             'comment' => 'Skvělá podpora!',
         ])
         ->assertRedirect();

    expect(TicketRating::query()->where('support_ticket_id', $ticket->id)->exists())->toBeTrue();
    expect(TicketRating::query()->where('support_ticket_id', $ticket->id)->value('score'))->toBe(5);
});

it('rating is updated on re-submission', function (): void {
    $user   = customerUser();
    $ticket = makeClosedTicket($user);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 3]);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 5]);

    expect(TicketRating::query()->where('support_ticket_id', $ticket->id)->count())->toBe(1);
    expect(TicketRating::query()->where('support_ticket_id', $ticket->id)->value('score'))->toBe(5);
});

it('cannot rate an open ticket', function (): void {
    $user = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id'   => $user->customer->id,
        'status'        => TicketStatus::Open,
        'priority'      => TicketPriority::Normal,
        'subject'       => 'Otevřený ticket',
        'last_reply_at' => now(),
    ]);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 4])
         ->assertSessionHasErrors(['rating']);
});

it('score must be between 1 and 5', function (): void {
    $user   = customerUser();
    $ticket = makeClosedTicket($user);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 6])
         ->assertSessionHasErrors(['score']);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 0])
         ->assertSessionHasErrors(['score']);
});

it('customer cannot rate another customers ticket', function (): void {
    adminUser();
    $owner = customerUser();
    $other = customerUser();
    $ticket = makeClosedTicket($owner);

    $this->actingAs($other)
         ->post(route('panel.support.rate', $ticket), ['score' => 5])
         ->assertForbidden();
});

it('ticket show page displays rating form for closed ticket', function (): void {
    $user   = customerUser();
    $ticket = makeClosedTicket($user);

    $this->actingAs($user)
         ->get(route('panel.support.show', $ticket))
         ->assertOk()
         ->assertSee('hodnotíte');
});
