<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Carbon;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Commands\CommandContext;

/*
 * TASK-0133: a ticket's SLA clocks start at the instant the ticket is created. The due dates were computed from their own `now()`
 * before Eloquent stamped `created_at` on save; when a second boundary fell between the two, the first-response window was 59 minutes
 * and some seconds instead of 60 (SupportTest:39 failed on CI, 2026-10-07, run 37680034288). Here every call of the clock moves it on
 * by a second, so the old code always shows the gap.
 */

beforeEach(fn () => $this->seed(LegalEntitySeeder::class));

afterEach(fn () => Carbon::setTestNow());

it('starts the SLA clocks at the instant the ticket is created, however long the request takes', function () {
    [$user, $org] = $this->customerWithOrganization();
    $tick = Carbon::parse('2026-10-07 10:00:00');
    Carbon::setTestNow(function () use (&$tick) {
        $tick = $tick->copy()->addSecond();

        return $tick;
    });

    $ticket = app(TicketService::class)->create(['subject' => 'Nejde mi nastavit MX záznam v DNS zóně', 'body' => 'Pošta stále nechodí.', 'priority' => 'vysoka'], CommandContext::system('sla start'), $org, $user);
    Carbon::setTestNow();

    $ticket->refresh();
    expect($ticket->created_at->diffInSeconds($ticket->first_response_due_at))->toBe(60.0 * 60)
        ->and($ticket->created_at->diffInSeconds($ticket->resolution_due_at))->toBe(1440.0 * 60)
        ->and($ticket->last_customer_message_at->equalTo($ticket->created_at))->toBeTrue();
});
