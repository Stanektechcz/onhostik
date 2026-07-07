<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SlaEscalationNotification;
use Illuminate\Console\Command;

class EscalateTicketSlaCommand extends Command
{
    protected $signature   = 'tickets:escalate-sla';
    protected $description = 'Escalate tickets that have breached their SLA deadline';

    public function handle(): int
    {
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->info('No admin users found.');
            return self::SUCCESS;
        }

        $escalated = 0;

        SupportTicket::whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->whereNull('escalated_at')
            ->whereNotIn('status', [TicketStatus::Closed->value])
            ->get()
            ->each(function (SupportTicket $ticket) use ($admins, &$escalated): void {
                foreach ($admins as $admin) {
                    $admin->notify(new SlaEscalationNotification($ticket));
                }
                $ticket->update(['escalated_at' => now()]);
                $escalated++;
            });

        $this->info("Escalated {$escalated} tickets.");

        return self::SUCCESS;
    }
}
