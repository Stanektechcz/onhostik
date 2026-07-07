<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\Payment;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminWeeklyReportNotification;
use Illuminate\Console\Command;

class SendAdminWeeklyReportCommand extends Command
{
    protected $signature   = 'reports:send-admin-weekly';
    protected $description = 'Send weekly summary report to all admin users.';

    public function handle(): int
    {
        $weekStart = now()->subDays(7)->startOfDay();
        $weekEnd   = now()->endOfDay();

        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $revenueMinor = (int) Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('amount');

        $newTickets = SupportTicket::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $newOrders = Order::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $activeServices = Service::query()
            ->where('status', ServiceStatus::Active->value)
            ->count();

        $notification = new AdminWeeklyReportNotification(
            newCustomers:   $newCustomers,
            revenueMinor:   $revenueMinor,
            newTickets:     $newTickets,
            newOrders:      $newOrders,
            activeServices: $activeServices,
            weekStart:      $weekStart,
            weekEnd:        $weekEnd,
        );

        $admins = User::role('admin')->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }

        $this->info("Weekly report sent to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
