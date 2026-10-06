<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Payments\RefundsNotPaidOut;
use Onhost\Platform\Money\Money;

/**
 * H3 (phase H, TASK-0121): read-only list for finance — refunds to the source of payment whose bank payout was cancelled and that no
 * later payout carries, so the customer is still owed the money (it sits in `liability:refund_payable:<provider>`). Exits 1 while
 * anything waits, so a scheduler or a check can raise it; writes nothing. The same list: `GET /v1/staff/payments/refunds/not-paid-out`.
 */
final class BillingRefundsNotPaidOut extends Command
{
    protected $signature = 'onhost:billing:refunds-not-paid-out {--json : machine-readable output}';

    protected $description = 'Read-only: cancelled refund payouts no later payout carries (money still owed from the refund payable)';

    public function handle(RefundsNotPaidOut $report): int
    {
        $result = $report->report();
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $result['rows'] === [] ? self::SUCCESS : self::FAILURE;
        }
        if ($result['rows'] === []) {
            $this->info('no cancelled refund waits for a payout');
        } else {
            $this->table(['credit note', 'order', 'payment', 'organization', 'provider', 'amount', 'cancelled', 'days'], array_map(fn (array $row) => [
                $row['credit_note'], $row['order'], $row['payment_id'], $row['organization_id'], $row['provider'], $row['amount']->format(), $row['cancelled_at'], $row['days'],
            ], $result['rows']));
            foreach ($result['total'] as $currency => $minor) {
                $this->warn('owed in '.$currency.': '.Money::minor($minor, $currency)->format().' — pay it out again (Finance → refunds) or settle it by hand');
            }
        }
        foreach ($result['payable'] as $account => $minor) {
            $this->line($account.' = '.Money::minor($minor, substr($account, (int) strrpos($account, ':') + 1))->format().' (with the payouts still on their way)');
        }

        return $result['rows'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
