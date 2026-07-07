<?php

declare(strict_types=1);

use App\Domains\Support\Models\SupportTicket;
use App\Notifications\SlaEscalationNotification;
use Illuminate\Support\Facades\Notification;

it('sla escalation command escalates breached tickets', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id'  => $customer->customer->id,
        'status'       => 'open',
        'sla_deadline' => now()->subHours(2),
        'escalated_at' => null,
    ]);

    $this->artisan('tickets:escalate-sla')->assertSuccessful();

    Notification::assertSentTo($admin, SlaEscalationNotification::class);
    expect($ticket->fresh()->escalated_at)->not->toBeNull();
});

it('sla escalation command does not re-escalate already escalated tickets', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    SupportTicket::factory()->create([
        'customer_id'  => $customer->customer->id,
        'status'       => 'open',
        'sla_deadline' => now()->subHours(2),
        'escalated_at' => now()->subHour(),
    ]);

    $this->artisan('tickets:escalate-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('sla escalation command ignores closed tickets', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    SupportTicket::factory()->create([
        'customer_id'  => $customer->customer->id,
        'status'       => 'closed',
        'sla_deadline' => now()->subHours(2),
        'escalated_at' => null,
    ]);

    $this->artisan('tickets:escalate-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('sla escalation command does not escalate future sla deadlines', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    SupportTicket::factory()->create([
        'customer_id'  => $customer->customer->id,
        'status'       => 'open',
        'sla_deadline' => now()->addHours(2),
        'escalated_at' => null,
    ]);

    $this->artisan('tickets:escalate-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('sla escalation notification references the ticket', function (): void {
    $customer = customerUser();

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->customer->id,
    ]);

    $notification = new SlaEscalationNotification($ticket);
    $mail         = $notification->toMail(adminUser());

    expect($mail)->not->toBeNull();
});
