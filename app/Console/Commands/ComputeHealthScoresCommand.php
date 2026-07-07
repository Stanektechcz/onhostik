<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Bi\Actions\CustomerHealthScorer;
use App\Domains\Customer\Models\Customer;
use Illuminate\Console\Command;

class ComputeHealthScoresCommand extends Command
{
    protected $signature   = 'crm:compute-health-scores';
    protected $description = 'Compute and persist health scores for all customers.';

    public function handle(CustomerHealthScorer $scorer): int
    {
        $processed = 0;

        Customer::query()
            ->with(['invoices', 'services', 'supportTickets', 'user'])
            ->each(function (Customer $customer) use ($scorer, &$processed): void {
                $score = $scorer->score($customer);

                $customer->update([
                    'health_score'            => $score,
                    'health_score_updated_at' => now(),
                ]);

                $processed++;
            });

        $this->info("Health scores computed for {$processed} customer(s).");

        return self::SUCCESS;
    }
}
