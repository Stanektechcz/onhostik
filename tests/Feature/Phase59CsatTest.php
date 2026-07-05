<?php

declare(strict_types=1);

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\TicketRating;
use App\Models\User;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── TicketRating model ─────────────────────────────────────────────────────────

it('TicketRating has no updated_at column', function (): void {
    $cols = Schema::getColumnListing('ticket_ratings');
    expect($cols)->toContain('created_at')
        ->and($cols)->not->toContain('updated_at');
});

it('TicketRating label() returns correct Czech label for each score', function (): void {
    $map = [5 => 'Výborný', 4 => 'Dobrý', 3 => 'Průměrný', 2 => 'Slabý', 1 => 'Špatný'];
    foreach ($map as $score => $label) {
        $rating = new TicketRating(['score' => $score]);
        expect($rating->label())->toBe($label);
    }
});

it('SupportTicket has rating HasOne relation', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    TicketRating::create([
        'support_ticket_id' => $ticket->id,
        'score'             => 4,
        'comment'           => 'Dobré',
        'rated_at'          => now(),
    ]);

    expect($ticket->refresh()->rating)->toBeInstanceOf(TicketRating::class)
        ->and($ticket->rating->score)->toBe(4);
});

it('ticket_ratings enforces unique support_ticket_id', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    TicketRating::create(['support_ticket_id' => $ticket->id, 'score' => 3, 'rated_at' => now()]);

    expect(fn () => TicketRating::create(['support_ticket_id' => $ticket->id, 'score' => 5, 'rated_at' => now()]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// ── Panel: rate route ──────────────────────────────────────────────────────────

it('panel can rate a closed ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->actingAs($user)
        ->post(route('panel.support.rate', $ticket), ['score' => 5, 'comment' => 'Skvělé!'])
        ->assertRedirect();

    expect(TicketRating::where('support_ticket_id', $ticket->id)->first())
        ->not->toBeNull()
        ->and(TicketRating::where('support_ticket_id', $ticket->id)->value('score'))->toBe(5);
});

it('rating a closed ticket twice updates the rating (updateOrCreate)', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->actingAs($user)->post(route('panel.support.rate', $ticket), ['score' => 2, 'comment' => 'Špatné']);
    $this->actingAs($user)->post(route('panel.support.rate', $ticket), ['score' => 4, 'comment' => 'Opraveno']);

    expect(TicketRating::where('support_ticket_id', $ticket->id)->count())->toBe(1)
        ->and(TicketRating::where('support_ticket_id', $ticket->id)->value('score'))->toBe(4);
});

it('cannot rate an open ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->for($user->customer)->create(); // Open status

    $response = $this->actingAs($user)
        ->post(route('panel.support.rate', $ticket), ['score' => 5]);

    $response->assertSessionHasErrors('rating');
    expect(TicketRating::count())->toBe(0);
});

it('rate endpoint validates score range 1-5', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->actingAs($user)
        ->post(route('panel.support.rate', $ticket), ['score' => 6])
        ->assertSessionHasErrors('score');

    $this->actingAs($user)
        ->post(route('panel.support.rate', $ticket), ['score' => 0])
        ->assertSessionHasErrors('score');
});

it('rate endpoint rejects comment over 1000 chars', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->actingAs($user)
        ->post(route('panel.support.rate', $ticket), ['score' => 3, 'comment' => str_repeat('x', 1001)])
        ->assertSessionHasErrors('comment');
});

it('cannot rate another customer\'s ticket', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $ticket  = SupportTicket::factory()->closed()->for($user1->customer)->create();

    $this->actingAs($user2)
        ->post(route('panel.support.rate', $ticket), ['score' => 5])
        ->assertStatus(403);
});

it('guest cannot access rate endpoint', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->post(route('panel.support.rate', $ticket), ['score' => 5])
        ->assertRedirect(route('login'));
});

// ── Panel: show includes rating form ──────────────────────────────────────────

it('panel support show renders rating form for closed unrated ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();

    $this->actingAs($user)
        ->get(route('panel.support.show', $ticket))
        ->assertOk()
        ->assertSee('hodnotit');
});

it('panel support show renders existing rating (no form) for already-rated ticket', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();
    TicketRating::create(['support_ticket_id' => $ticket->id, 'score' => 5, 'rated_at' => now()]);

    $this->actingAs($user)
        ->get(route('panel.support.show', $ticket))
        ->assertOk()
        ->assertSee('Vaše hodnocení');
});

// ── Admin: CSAT dashboard ──────────────────────────────────────────────────────

it('admin CSAT dashboard is accessible to admins', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.support.csat'))
        ->assertOk()
        ->assertSee('CSAT');
});

it('admin CSAT dashboard is forbidden for regular users', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.support.csat'))
        ->assertStatus(403);
});

it('admin CSAT dashboard shows correct avg score', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $ticket1 = SupportTicket::factory()->closed()->for($user->customer)->create();
    $ticket2 = SupportTicket::factory()->closed()->for($user->customer)->create();

    TicketRating::create(['support_ticket_id' => $ticket1->id, 'score' => 5, 'rated_at' => now()]);
    TicketRating::create(['support_ticket_id' => $ticket2->id, 'score' => 3, 'rated_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.support.csat'))
        ->assertOk()
        ->assertSee('4.00'); // avg of 5+3=4
});

it('admin CSAT dashboard shows score distribution', function (): void {
    $admin  = adminUser();
    $user   = customerUser();

    foreach ([5, 5, 4, 3, 1] as $i => $score) {
        $ticket = SupportTicket::factory()->closed()->for($user->customer)->create();
        TicketRating::create(['support_ticket_id' => $ticket->id, 'score' => $score, 'rated_at' => now()]);
    }

    $this->actingAs($admin)
        ->get(route('admin.support.csat'))
        ->assertOk()
        ->assertSee('Rozložení hodnocení');
});

it('admin CSAT dashboard works with no ratings (empty state)', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.support.csat'))
        ->assertOk()
        ->assertSee('0');
});

it('admin CSAT shows recent ratings table with ticket subject', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $ticket = SupportTicket::factory()->closed()->for($user->customer)->create(['subject' => 'Test ticket CSAT']);
    TicketRating::create(['support_ticket_id' => $ticket->id, 'score' => 4, 'comment' => 'Rychlá odezva', 'rated_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.support.csat'))
        ->assertOk()
        ->assertSee('Rychlá odezva');
});
