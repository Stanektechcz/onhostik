<?php

declare(strict_types=1);

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\TicketRating;
use App\Domains\Support\Services\TicketService;
use App\Notifications\TicketRatingRequestNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Notification sent on close ────────────────────────────────────────────────

it('closing a ticket sends TicketRatingRequestNotification to customer', function (): void {
    Notification::fake();

    $admin = adminUser();
    $user  = customerUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    Notification::assertSentTo(
        $user,
        TicketRatingRequestNotification::class,
        fn ($n) => $n->ticket->id === $ticket->id,
    );
});

it('rating request notification not sent when admin is same as ticket customer user', function (): void {
    Notification::fake();

    // Admin user who also has a customer profile (actor == ticket owner → no notification)
    $admin = adminUser();
    $admin->load('customer');

    if ($admin->customer === null) {
        \App\Domains\Customer\Models\Customer::factory()->for($admin)->create(['country_code' => 'CZ']);
        $admin = $admin->fresh(['customer']);
    }

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $admin->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    Notification::assertNotSentTo($admin, TicketRatingRequestNotification::class);
});

it('rating request not sent if ticket already has a rating', function (): void {
    Notification::fake();

    $admin = adminUser();
    $user  = customerUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    TicketRating::create([
        'support_ticket_id' => $ticket->id,
        'score'             => 5,
        'rated_at'          => now(),
    ]);

    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    Notification::assertNotSentTo($user, TicketRatingRequestNotification::class);
});

// ── Panel rate() controller ───────────────────────────────────────────────────

it('customer can submit a rating for a closed ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), [
             'score'   => 4,
             'comment' => 'Skvělá podpora!',
         ])
         ->assertRedirect()
         ->assertSessionHas('status');

    $rating = TicketRating::query()->where('support_ticket_id', $ticket->id)->first();
    expect($rating)->not->toBeNull()
        ->and($rating->score)->toBe(4)
        ->and($rating->comment)->toBe('Skvělá podpora!');
});

it('customer cannot rate an open ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    $this->actingAs($user)
         ->post(route('panel.support.rate', $ticket), ['score' => 3])
         ->assertSessionHasErrors('rating');
});

it('panel ticket show renders rating form for closed unrated ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->actingAs($user)
         ->get(route('panel.support.show', $ticket))
         ->assertOk()
         ->assertSee('Jak hodnotíte vyřízení tohoto ticketu');
});

it('panel ticket show renders existing rating stars', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Closed,
    ]);
    TicketRating::create([
        'support_ticket_id' => $ticket->id,
        'score'             => 5,
        'rated_at'          => now(),
    ]);

    $this->actingAs($user)
         ->get(route('panel.support.show', $ticket))
         ->assertOk()
         ->assertSee('Vaše hodnocení');
});
