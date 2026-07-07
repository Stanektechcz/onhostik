<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminDailyDigestNotification;
use Illuminate\Console\Command;

class SendAdminDailyDigestCommand extends Command
{
    protected $signature   = 'reports:send-admin-daily';
    protected $description = 'Send daily digest email to all admin users.';

    public function handle(): int
    {
        $overdueInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->count();

        $openTickets = SupportTicket::query()
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value])
            ->count();

        $servicesDueIn7Days = Service::query()
            ->whereNull('terminated_at')
            ->whereBetween('next_due_date', [now(), now()->addDays(7)])
            ->count();

        $notification = new AdminDailyDigestNotification(
            overdueInvoices:    $overdueInvoices,
            openTickets:        $openTickets,
            servicesDueIn7Days: $servicesDueIn7Days,
            date:               now(),
        );

        $admins = User::role('admin')->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }

        $this->info("Daily digest sent to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
