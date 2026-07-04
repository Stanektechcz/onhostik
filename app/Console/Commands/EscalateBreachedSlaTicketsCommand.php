<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SlaBreachNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Detects tickets that have passed their SLA deadline and escalates their
 * priority by one level. Each ticket is only escalated once (idempotent via
 * sla_breach_notified_at). All admins receive an aggregate notification.
 */
class EscalateBreachedSlaTicketsCommand extends Command
{
    protected $signature   = 'support:escalate-sla';
    protected $description = 'Escalate priority of SLA-breached tickets and notify admins';

    public function handle(): int
    {
        /** @var Collection<int, SupportTicket> $breached */
        $breached = SupportTicket::query()
            ->whereNotIn('status', [TicketStatus::Closed->value])
            ->where('sla_deadline', '<', now())
            ->whereNull('sla_breach_notified_at')
            ->with('customer.user')
            ->get();

        $count = $breached->count();

        if ($count === 0) {
            $this->info('No SLA breaches detected.');
            return self::SUCCESS;
        }

        foreach ($breached as $ticket) {
            $previousPriority = $ticket->priority;
            $newPriority      = $previousPriority->escalated();

            $ticket->update([
                'priority'               => $newPriority,
                'sla_breach_notified_at' => now(),
            ]);

            activity('support')
                ->performedOn($ticket)
                ->withProperties([
                    'previous_priority' => $previousPriority->value,
                    'new_priority'      => $newPriority->value,
                    'sla_deadline'      => $ticket->sla_deadline?->format('Y-m-d H:i'),
                ])
                ->log('support.sla_breach_escalated');
        }

        User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->each(fn (User $admin) => $admin->notify(new SlaBreachNotification($count)));

        $this->info("Escalated {$count} SLA-breached ticket(s). Admins notified.");

        return self::SUCCESS;
    }
}
