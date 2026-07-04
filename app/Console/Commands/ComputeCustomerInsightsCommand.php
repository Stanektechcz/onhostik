<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Bi\Actions\ChurnRiskScorer;
use App\Domains\Customer\Models\Customer;
use Illuminate\Console\Command;

class ComputeCustomerInsightsCommand extends Command
{
    protected $signature   = 'bi:compute-insights';
    protected $description = 'Compute churn risk scores and segment labels for all customers';

    public function handle(ChurnRiskScorer $scorer): int
    {
        $processed  = 0;
        $atRisk     = 0;
        $churned    = 0;

        Customer::query()
            ->with(['invoices', 'services', 'supportTickets'])
            ->each(function (Customer $customer) use ($scorer, &$processed, &$atRisk, &$churned): void {
                $score   = $scorer->score($customer);
                $segment = $scorer->segment($customer, $score);

                $customer->update([
                    'churn_risk_score'   => $score,
                    'segment'            => $segment->value,
                    'insights_updated_at' => now(),
                ]);

                $processed++;
                if ($segment->value === 'at_risk') {
                    $atRisk++;
                }
                if ($segment->value === 'churned') {
                    $churned++;
                }
            });

        $this->info("Insights computed for {$processed} customer(s). At-risk: {$atRisk}, Churned: {$churned}.");

        return self::SUCCESS;
    }
}
