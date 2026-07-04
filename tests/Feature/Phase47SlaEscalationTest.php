<?php

declare(strict_types=1);

use App\Console\Commands\EscalateBreachedSlaTicketsCommand;
use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Notifications\SlaBreachNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── TicketPriority enum extensions ───────────────────────────────────────────

it('TicketPriority slaHours returns correct hours per level', function (): void {
    expect(TicketPriority::Urgent->slaHours())->toBe(4);
    expect(TicketPriority::High->slaHours())->toBe(8);
    expect(TicketPriority::Normal->slaHours())->toBe(24);
    expect(TicketPriority::Low->slaHours())->toBe(48);
});

it('TicketPriority escalated returns next higher level', function (): void {
    expect(TicketPriority::Low->escalated())->toBe(TicketPriority::Normal);
    expect(TicketPriority::Normal->escalated())->toBe(TicketPriority::High);
    expect(TicketPriority::High->escalated())->toBe(TicketPriority::Urgent);
});

it('TicketPriority Urgent escalated returns Urgent', function (): void {
    expect(TicketPriority::Urgent->escalated())->toBe(TicketPriority::Urgent);
});

// ── TicketService auto-sets sla_deadline on open ──────────────────────────────

it('TicketService open auto-sets sla_deadline based on priority', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = app(TicketService::class)->open(
        $customer,
        $user,
        'Test SLA ticket',
        'Message body',
        TicketPriority::High,
    );

    expect($ticket->sla_deadline)->not->toBeNull();
    // High priority = 8 hours; check deadline is ~8h from now (within 5min slack)
    $diffHours = now()->diffInMinutes($ticket->sla_deadline) / 60;
    expect($diffHours)->toBeGreaterThan(7.9)->toBeLessThan(8.1);
});

it('TicketService open sets Urgent sla_deadline to 4 hours', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = app(TicketService::class)->open(
        $customer,
        $user,
        'Urgent ticket',
        'Help!',
        TicketPriority::Urgent,
    );

    $diffHours = now()->diffInMinutes($ticket->sla_deadline) / 60;
    expect($diffHours)->toBeGreaterThan(3.9)->toBeLessThan(4.1);
});

it('TicketService open sets Low sla_deadline to 48 hours', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = app(TicketService::class)->open(
        $customer,
        $user,
        'Low priority ticket',
        'No rush.',
        TicketPriority::Low,
    );

    $diffHours = now()->diffInMinutes($ticket->sla_deadline) / 60;
    expect($diffHours)->toBeGreaterThan(47.9)->toBeLessThan(48.1);
});

// ── EscalateBreachedSlaTicketsCommand ─────────────────────────────────────────

it('escalate-sla command escalates priority of breached tickets', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    // Ticket with past deadline, not yet notified
    $ticket = SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::Normal,
        'sla_deadline'           => now()->subHour(),
        'sla_breach_notified_at' => null,
    ]);

    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->assertSuccessful();

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::High);
    expect($ticket->sla_breach_notified_at)->not->toBeNull();
});

it('escalate-sla command is idempotent — does not re-escalate already breached tickets', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::High,
        'sla_deadline'           => now()->subHours(2),
        'sla_breach_notified_at' => now()->subHour(),
    ]);

    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->assertSuccessful();

    // Priority must not change — ticket already has sla_breach_notified_at
    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::High);
});

it('escalate-sla command skips closed tickets', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Closed,
        'priority'               => TicketPriority::Normal,
        'sla_deadline'           => now()->subHour(),
        'sla_breach_notified_at' => null,
    ]);

    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_breach_notified_at)->toBeNull();
    expect($ticket->priority)->toBe(TicketPriority::Normal);
});

it('escalate-sla command keeps Urgent at Urgent level', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $ticket = SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::Urgent,
        'sla_deadline'           => now()->subHour(),
        'sla_breach_notified_at' => null,
    ]);

    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->assertSuccessful();

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::Urgent);
    expect($ticket->sla_breach_notified_at)->not->toBeNull();
});

it('escalate-sla command sends SlaBreachNotification to admins', function (): void {
    Notification::fake();

    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::Low,
        'sla_deadline'           => now()->subHour(),
        'sla_breach_notified_at' => null,
    ]);

    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->assertSuccessful();

    Notification::assertSentTo($admin, SlaBreachNotification::class);
});

it('escalate-sla command outputs success when no breaches', function (): void {
    $this->artisan(EscalateBreachedSlaTicketsCommand::class)
        ->expectsOutput('No SLA breaches detected.')
        ->assertSuccessful();
});

// ── Admin SLA monitor view ────────────────────────────────────────────────────

it('admin can view SLA monitor page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.support.sla-monitor'))
        ->assertOk()
        ->assertViewIs('admin.support.sla-monitor')
        ->assertViewHas('breached')
        ->assertViewHas('atRisk');
});

it('customer cannot access SLA monitor page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.support.sla-monitor'))
        ->assertForbidden();
});

it('SLA monitor shows breached tickets', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::High,
        'sla_deadline'           => now()->subHours(3),
        'sla_breach_notified_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.support.sla-monitor'))
        ->assertOk();

    expect($response->viewData('breached'))->toHaveCount(1);
});

it('SLA monitor shows at-risk tickets within 2 hours', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::Normal,
        'sla_deadline'           => now()->addHour(),
        'sla_breach_notified_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.support.sla-monitor'))
        ->assertOk();

    expect($response->viewData('atRisk'))->toHaveCount(1);
});

it('SLA monitor does not show ticket with deadline outside 2-hour window', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    SupportTicket::factory()->create([
        'customer_id'            => $customer->id,
        'status'                 => TicketStatus::Open,
        'priority'               => TicketPriority::Low,
        'sla_deadline'           => now()->addHours(5),
        'sla_breach_notified_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.support.sla-monitor'))
        ->assertOk();

    expect($response->viewData('atRisk'))->toHaveCount(0);
});
