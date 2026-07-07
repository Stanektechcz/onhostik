<?php

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can rate a closed ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->actingAs($user)
        ->post(route('panel.support.rating.store', $ticket), [
            'csat_score'   => 5,
            'csat_comment' => 'Výborná podpora, rychlé řešení.',
        ])
        ->assertRedirect();

    expect($ticket->fresh()->csat_score)->toBe(5);
    expect($ticket->fresh()->csat_rated_at)->not()->toBeNull();
});

it('cannot rate an open ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    $this->actingAs($user)
        ->post(route('panel.support.rating.store', $ticket), ['csat_score' => 4])
        ->assertStatus(422);
});

it('cannot rate a ticket twice', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id'   => $user->customer->id,
        'status'        => TicketStatus::Closed,
        'csat_score'    => 4,
        'csat_rated_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('panel.support.rating.store', $ticket), ['csat_score' => 3])
        ->assertStatus(422);
});

it('customer cannot rate another customers ticket', function (): void {
    $user    = customerUser();
    $other   = \App\Domains\Customer\Models\Customer::factory()->create();
    $ticket  = SupportTicket::factory()->create([
        'customer_id' => $other->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->actingAs($user)
        ->post(route('panel.support.rating.store', $ticket), ['csat_score' => 5])
        ->assertForbidden();
});

it('csat_score must be between 1 and 5', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->actingAs($user)
        ->post(route('panel.support.rating.store', $ticket), ['csat_score' => 6])
        ->assertSessionHasErrors('csat_score');
});
