<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\BillingPeriod;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Platform\Money\Money;

/** Finance reports computed from subscriptions, invoices and billing periods — never from UI state (handoff §10). */
final class ReportService
{
    /** @return array<string, array{mrr:Money, arr:Money, subscriptions:int, metered_last_month:Money}> per currency */
    public function mrr(): array
    {
        $out = [];
        $subscriptions = Subscription::query()->whereIn('state', [Subscription::ACTIVE, Subscription::PAST_DUE])->get();
        foreach ($subscriptions->groupBy('currency') as $currency => $rows) {
            $mrr = 0;
            foreach ($rows as $s) {
                $mrr += $s->period === 'year' ? (int) round($s->amount_minor / 12) : (int) $s->amount_minor;
            }
            $lastMonth = (int) BillingPeriod::query()->where('currency', $currency)->whereDate('period_start', now()->subMonth()->startOfMonth()->toDateString())->sum('total_minor');
            $out[$currency] = ['mrr' => Money::minor($mrr, $currency), 'arr' => Money::minor($mrr * 12, $currency), 'subscriptions' => $rows->count(), 'metered_last_month' => Money::minor($lastMonth, $currency)];
        }

        return $out;
    }

    /** Collections and receivables per currency for the last N days, with ageing buckets. */
    public function collections(int $days = 30): array
    {
        $since = now()->subDays($days);
        $out = [];
        foreach (config('onhost.billing.currencies', ['CZK']) as $currency) {
            $collected = (int) Invoice::query()->where('currency', $currency)->where('state', Invoice::PAID)->where('paid_at', '>=', $since)->sum('paid_minor');
            $issued = (int) Invoice::query()->where('currency', $currency)->whereIn('type', ['invoice', 'statement', 'receipt'])->where('issued_at', '>=', $since)->sum('total_minor');
            $open = Invoice::query()->where('currency', $currency)->whereIn('state', [Invoice::ISSUED, Invoice::OVERDUE])->where('type', 'invoice')->get();
            $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_plus' => 0];
            foreach ($open as $invoice) {
                $outstanding = (int) $invoice->total_minor - (int) $invoice->paid_minor;
                $overdue = $invoice->due_at ? (int) $invoice->due_at->diffInDays(now(), false) : 0;
                $key = $overdue <= 0 ? 'current' : ($overdue <= 30 ? '1_30' : ($overdue <= 60 ? '31_60' : '61_plus'));
                $buckets[$key] += $outstanding;
            }
            $out[$currency] = [
                'collected' => Money::minor($collected, $currency), 'issued' => Money::minor($issued, $currency), 'receivables' => Money::minor(array_sum($buckets), $currency), 'ageing' => array_map(fn ($m) => Money::minor($m, $currency), $buckets),
                'overdue_invoices' => $open->where('state', Invoice::OVERDUE)->count(), 'average_document' => Money::minor($issued > 0 ? (int) round($issued / max(1, Invoice::query()->where('currency', $currency)->where('issued_at', '>=', $since)->count())) : 0, $currency),
            ];
        }
        $out['dunning'] = ['open_cases' => DunningCase::query()->whereNotIn('state', [DunningCase::RESOLVED, DunningCase::TERMINATED])->count(), 'suspended' => DunningCase::query()->where('state', DunningCase::SUSPENDED)->count(), 'termination_scheduled' => DunningCase::query()->where('state', DunningCase::TERMINATION_SCHEDULED)->count()];

        return $out;
    }

    /** Monthly churn: subscriptions cancelled in month / active at month start. @return list<array{month:string,active_start:int,cancelled:int,new:int,churn_pct:float}> */
    public function churn(int $months = 6): array
    {
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $activeStart = Subscription::query()->where('created_at', '<', $start)->where(fn ($q) => $q->whereIn('state', [Subscription::ACTIVE, Subscription::PAST_DUE])->orWhere('updated_at', '>=', $start))->count();
            $cancelled = Subscription::query()->whereIn('state', [Subscription::CANCELLED, Subscription::EXPIRED])->whereBetween('updated_at', [$start, $end])->count();
            $new = Subscription::query()->whereBetween('created_at', [$start, $end])->count();
            $out[] = ['month' => $start->format('Y-m'), 'active_start' => $activeStart, 'cancelled' => $cancelled, 'new' => $new, 'churn_pct' => $activeStart > 0 ? round($cancelled / $activeStart * 100, 2) : 0.0];
        }

        return $out;
    }

    /** Revenue by month from the ledger revenue accounts (net of VAT). @return list<array{month:string, currency:string, net:Money}> */
    public function revenueByMonth(int $months = 12): array
    {
        $since = now()->subMonths($months - 1)->startOfMonth();
        $month = match (DB::connection()->getDriverName()) { // the month of a timestamp per dialect: substr() on a timestamp is a SQLite habit PostgreSQL refuses
            'pgsql' => "to_char(ledger_transactions.posted_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "date_format(ledger_transactions.posted_at, '%Y-%m')",
            default => 'substr(ledger_transactions.posted_at, 1, 7)',
        };
        $rows = DB::table('ledger_postings')->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_postings.transaction_id')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_postings.account_id')
            ->where('ledger_accounts.code', 'like', 'revenue:%')->where('ledger_transactions.posted_at', '>=', $since)
            ->selectRaw("{$month} as month, ledger_accounts.currency as currency, sum(case when ledger_postings.direction = 'credit' then ledger_postings.amount_minor else -ledger_postings.amount_minor end) as net")->groupByRaw("{$month}, ledger_accounts.currency")->orderByRaw($month)->get();

        return $rows->map(fn ($r) => ['month' => (string) $r->month, 'currency' => (string) $r->currency, 'net' => Money::minor((int) $r->net, (string) $r->currency)])->all();
    }
}
