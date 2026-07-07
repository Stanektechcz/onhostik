<?php

declare(strict_types=1);

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Models\NpsResponse;
use App\Notifications\NpsSurveyNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Migration table ────────────────────────────────────────────────────────────

it('nps_responses table exists with expected columns', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'test-token-abc',
        'notified_at'  => now(),
    ]);

    expect($nps->score)->toBeNull()
        ->and($nps->submitted_at)->toBeNull()
        ->and($nps->survey_token)->toBe('test-token-abc');
});

// ── NPS sent on ticket close ───────────────────────────────────────────────────

it('sends NPS survey notification when admin closes ticket', function (): void {
    Notification::fake();

    $customer = customerUser();
    $admin    = adminUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    Notification::assertSentTo($customer, NpsSurveyNotification::class);

    $nps = NpsResponse::first();
    expect($nps)->not->toBeNull()
        ->and($nps->ticket_id)->toBe($ticket->id)
        ->and($nps->notified_at)->not->toBeNull();
});

it('does not send NPS survey when customer closes own ticket', function (): void {
    Notification::fake();

    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    // Customer closes their own ticket — actor is the same user
    app(TicketService::class)->changeStatus($ticket, $user, TicketStatus::Closed);

    Notification::assertNotSentTo($user, NpsSurveyNotification::class);
});

it('does not send duplicate NPS survey when ticket is re-closed', function (): void {
    Notification::fake();

    $customer = customerUser();
    $admin    = adminUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    // Reopen then close again
    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Open);
    app(TicketService::class)->changeStatus($ticket, $admin, TicketStatus::Closed);

    // Should only have 1 NPS record for this ticket
    expect(NpsResponse::where('ticket_id', $ticket->id)->count())->toBe(1);
    Notification::assertSentToTimes($customer, NpsSurveyNotification::class, 1);
});

// ── Survey form ────────────────────────────────────────────────────────────────

it('NPS survey form is accessible via public token URL', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'public-token-123',
        'notified_at'  => now(),
    ]);

    $response = $this->get(route('nps.show', $nps->survey_token));

    $response->assertStatus(200)
        ->assertSee('Doporučili byste nás');
});

it('NPS survey form returns 404 for unknown token', function (): void {
    $this->get(route('nps.show', 'nonexistent-token'))->assertStatus(404);
});

it('already-submitted survey redirects without re-showing form', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'done-token',
        'notified_at'  => now(),
        'submitted_at' => now(),
        'score'        => 9,
    ]);

    $response = $this->get(route('nps.show', $nps->survey_token));
    $response->assertRedirect(route('front.home'));
});

// ── Survey submission ──────────────────────────────────────────────────────────

it('can submit NPS score and comment', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'submit-token',
        'notified_at'  => now(),
    ]);

    $response = $this->post(route('nps.submit', $nps->survey_token), [
        'score'   => 8,
        'comment' => 'Velmi spokojeni',
    ]);

    $response->assertRedirect(route('front.home'));

    $fresh = $nps->fresh();
    expect($fresh->score)->toBe(8)
        ->and($fresh->comment)->toBe('Velmi spokojeni')
        ->and($fresh->submitted_at)->not->toBeNull();
});

it('validates score is between 0 and 10', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'validate-token',
        'notified_at'  => now(),
    ]);

    $this->post(route('nps.submit', $nps->survey_token), ['score' => 11])
        ->assertSessionHasErrors('score');

    $this->post(route('nps.submit', $nps->survey_token), ['score' => -1])
        ->assertSessionHasErrors('score');
});

it('cannot submit survey twice', function (): void {
    $user = customerUser();

    $nps = NpsResponse::create([
        'customer_id'  => $user->customer->id,
        'survey_token' => 'double-submit-token',
        'notified_at'  => now(),
        'score'        => 9,
        'submitted_at' => now(),
    ]);

    $this->post(route('nps.submit', $nps->survey_token), ['score' => 3])
        ->assertRedirect(route('front.home'));

    // Score unchanged
    expect($nps->fresh()->score)->toBe(9);
});

// ── NPS model helpers ─────────────────────────────────────────────────────────

it('NpsResponse correctly classifies promoter passive detractor', function (): void {
    $user = customerUser();

    $promoter  = NpsResponse::create(['customer_id' => $user->customer->id, 'survey_token' => 'p1', 'score' => 10]);
    $passive   = NpsResponse::create(['customer_id' => $user->customer->id, 'survey_token' => 'p2', 'score' => 7]);
    $detractor = NpsResponse::create(['customer_id' => $user->customer->id, 'survey_token' => 'p3', 'score' => 5]);

    expect($promoter->isPromoter())->toBeTrue()
        ->and($promoter->isPassive())->toBeFalse()
        ->and($promoter->isDetractor())->toBeFalse()
        ->and($passive->isPassive())->toBeTrue()
        ->and($detractor->isDetractor())->toBeTrue()
        ->and($promoter->categoryLabel())->toBe('Promotér')
        ->and($passive->categoryLabel())->toBe('Pasivní')
        ->and($detractor->categoryLabel())->toBe('Kritik');
});

// ── Admin NPS dashboard ────────────────────────────────────────────────────────

it('admin can access NPS dashboard', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.support.nps'))
        ->assertStatus(200)
        ->assertSee('Net Promoter Score');
});

it('admin NPS dashboard shows correct promoter detractor counts', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    NpsResponse::create(['customer_id' => $customer->customer->id, 'survey_token' => 't1', 'score' => 10, 'submitted_at' => now()]);
    NpsResponse::create(['customer_id' => $customer->customer->id, 'survey_token' => 't2', 'score' => 9,  'submitted_at' => now()]);
    NpsResponse::create(['customer_id' => $customer->customer->id, 'survey_token' => 't3', 'score' => 7,  'submitted_at' => now()]);
    NpsResponse::create(['customer_id' => $customer->customer->id, 'survey_token' => 't4', 'score' => 3,  'submitted_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.support.nps'));

    $response->assertStatus(200)
        ->assertSee('Promotéři')
        ->assertSee('Kritici');
});

it('customer cannot access admin NPS dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.support.nps'))
        ->assertStatus(403);
});
