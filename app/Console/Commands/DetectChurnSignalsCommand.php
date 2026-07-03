<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Models\User;
use App\Notifications\ChurnRiskDetectedNotification;
use Illuminate\Console\Command;

/**
 * Detects customers who may be at churn risk and logs them to the
 * activity log so admins can proactively reach out.
 *
 * Churn signals checked:
 *   1. Active service + no login in 45+ days
 *   2. No open support ticket + no payment in 60+ days
 *
 * All signals are idempotent (cached 7 days per customer) so they never
 * fire more than once per week per customer. If any signals are detected,
 * an aggregate ChurnRiskDetectedNotification is dispatched to admins.
 */
class DetectChurnSignalsCommand extends Command
{
    protected $signature   = 'crm:detect-churn';
    protected $description = 'Detect customers at churn risk and notify admins';

    public function handle(): int
    {
        /** @var array<int, array<string, string>> $risks */
        $risks = [];

        // Signal 1: active services but no login in 45 days
        Customer::query()
            ->whereHas('services', fn ($q) => $q->where('status', ServiceStatus::Active->value))
            ->whereHas('user', fn ($q) => $q->where('last_login_at', '<', now()->subDays(45))
                ->orWhereNull('last_login_at'))
            ->with('user')
            ->chunk(100, function ($customers) use (&$risks): void {
                foreach ($customers as $customer) {
                    if ($this->shouldSkip($customer, 'no_login')) {
                        continue;
                    }

                    activity('churn')
                        ->performedOn($customer)
                        ->withProperties([
                            'signal'      => 'no_login_45d',
                            'customer_id' => $customer->id,
                            'email'       => $customer->email,
                            'last_login'  => optional($customer->user?->last_login_at)->toDateString() ?? 'never',
                        ])
                        ->log('churn.signal_detected');

                    cache()->put("churn_skip:{$customer->id}:no_login", true, now()->addWeek());

                    $risks[] = [
                        'customer_id' => (string) $customer->id,
                        'email'       => (string) $customer->email,
                        'signal'      => 'no_login_45d',
                        'detail'      => 'last_login: ' . (optional($customer->user?->last_login_at)->toDateString() ?? 'never'),
                    ];
                }
            });

        // Signal 2: no payment in 60 days
        Customer::query()
            ->whereHas('services', fn ($q) => $q->where('status', ServiceStatus::Active->value))
            ->whereDoesntHave('payments', fn ($q) => $q->where('created_at', '>=', now()->subDays(60)))
            ->chunk(100, function ($customers) use (&$risks): void {
                foreach ($customers as $customer) {
                    if ($this->shouldSkip($customer, 'no_payment')) {
                        continue;
                    }

                    activity('churn')
                        ->performedOn($customer)
                        ->withProperties([
                            'signal'      => 'no_payment_60d',
                            'customer_id' => $customer->id,
                            'email'       => $customer->email,
                        ])
                        ->log('churn.signal_detected');

                    cache()->put("churn_skip:{$customer->id}:no_payment", true, now()->addWeek());

                    $risks[] = [
                        'customer_id' => (string) $customer->id,
                        'email'       => (string) $customer->email,
                        'signal'      => 'no_payment_60d',
                        'detail'      => 'no payment in 60d',
                    ];
                }
            });

        $count = count($risks);

        if ($count > 0) {
            $notification = new ChurnRiskDetectedNotification($risks, $count);

            User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->each(fn (User $admin) => $admin->notify($notification));
        }

        $this->info("Detected {$count} churn signal(s). Review in: /admin/audit?log=churn");

        return self::SUCCESS;
    }

    private function shouldSkip(Customer $customer, string $signal): bool
    {
        return (bool) cache()->get("churn_skip:{$customer->id}:{$signal}");
    }
}
