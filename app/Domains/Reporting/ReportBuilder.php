<?php

declare(strict_types=1);

namespace App\Domains\Reporting;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ReportBuilder
{
    public const TYPES = [
        'revenue'        => 'Přehled tržeb',
        'churn'          => 'Odchod zákazníků',
        'cohort_revenue' => 'Kohortní analýza tržeb',
        'new_customers'  => 'Noví zákazníci',
        'services'       => 'Přehled služeb',
        'invoices'       => 'Přehled faktur',
    ];

    /**
     * Build a report and return rows as collection.
     *
     * @param array{type: string, from: string, to: string, group_by?: string, status?: string} $params
     * @return array{title: string, headers: list<string>, rows: Collection<int, mixed>}
     */
    public function build(array $params): array
    {
        $type    = $params['type'];
        $from    = Carbon::parse($params['from'])->startOfDay();
        $to      = Carbon::parse($params['to'])->endOfDay();
        $groupBy = $params['group_by'] ?? 'month';

        return match ($type) {
            'revenue'        => $this->revenueReport($from, $to, $groupBy),
            'churn'          => $this->churnReport($from, $to),
            'cohort_revenue' => $this->cohortRevenueReport($from, $to),
            'new_customers'  => $this->newCustomersReport($from, $to, $groupBy),
            'services'       => $this->servicesReport($from, $to, $params['status'] ?? ''),
            'invoices'       => $this->invoicesReport($from, $to, $params['status'] ?? ''),
            default          => $this->revenueReport($from, $to, $groupBy),
        };
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function revenueReport(Carbon $from, Carbon $to, string $groupBy): array
    {
        $invoices = Invoice::query()
            ->where('status', 'paid')
            ->whereBetween('issue_date', [$from, $to])
            ->get();

        $grouped = $invoices->groupBy(function (Invoice $inv) use ($groupBy): string {
            return match ($groupBy) {
                'day'   => $inv->issue_date->format('Y-m-d'),
                'week'  => $inv->issue_date->format('Y-W'),
                default => $inv->issue_date->format('Y-m'),
            };
        });

        $rows = $grouped->map(function (Collection $group, string $period): array {
            $total = $group->sum(fn (Invoice $i) => $i->total->getMinorAmount()->toInt());
            return [
                'Období'       => $period,
                'Počet faktur' => $group->count(),
                'Celkem (Kč)'  => number_format($total / 100, 2, ',', ' '),
            ];
        })->sortKeys()->values();

        return ['title' => 'Přehled tržeb', 'headers' => ['Období', 'Počet faktur', 'Celkem (Kč)'], 'rows' => $rows];
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function churnReport(Carbon $from, Carbon $to): array
    {
        $terminated = Service::query()
            ->whereBetween('terminated_at', [$from, $to])
            ->with('customer', 'product')
            ->get();

        $rows = $terminated->map(fn (Service $s): array => [
            'Zákazník'       => $s->customer?->company_name ?: $s->customer?->email ?: '—',
            'Služba'         => $s->label ?? "Service #{$s->id}",
            'Produkt'        => $s->product?->name ?: '—',
            'Ukončeno'       => $s->terminated_at?->format('d.m.Y') ?? '—',
            'Důvod'          => $s->cancellation_reason ?? '—',
        ])->values();

        return ['title' => 'Odchod zákazníků', 'headers' => ['Zákazník', 'Služba', 'Produkt', 'Ukončeno', 'Důvod'], 'rows' => $rows];
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function cohortRevenueReport(Carbon $from, Carbon $to): array
    {
        $customers = Customer::query()
            ->whereBetween('created_at', [$from, $to])
            ->with(['invoices' => fn ($q) => $q->where('status', 'paid')])
            ->get();

        $rows = $customers->groupBy(fn (Customer $c) => $c->created_at->format('Y-m'))
            ->map(function (Collection $cohort, string $month): array {
                $totalRevenue = $cohort->sum(fn (Customer $c) => $c->invoices->sum(fn (Invoice $i) => $i->total->getMinorAmount()->toInt()));
                return [
                    'Kohorta (měsíc)'   => $month,
                    'Zákazníků'         => $cohort->count(),
                    'Celkem tržby (Kč)' => number_format($totalRevenue / 100, 2, ',', ' '),
                    'Průměr/zákazník'   => $cohort->count() > 0
                        ? number_format(($totalRevenue / $cohort->count()) / 100, 2, ',', ' ')
                        : '0',
                ];
            })
            ->sortKeys()
            ->values();

        return ['title' => 'Kohortní analýza tržeb', 'headers' => ['Kohorta (měsíc)', 'Zákazníků', 'Celkem tržby (Kč)', 'Průměr/zákazník'], 'rows' => $rows];
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function newCustomersReport(Carbon $from, Carbon $to, string $groupBy): array
    {
        $customers = Customer::query()
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $grouped = $customers->groupBy(function (Customer $c) use ($groupBy): string {
            return match ($groupBy) {
                'day'   => $c->created_at->format('Y-m-d'),
                'week'  => $c->created_at->format('Y-W'),
                default => $c->created_at->format('Y-m'),
            };
        });

        $rows = $grouped->map(fn (Collection $group, string $period): array => [
            'Období'      => $period,
            'Zákazníků'   => $group->count(),
        ])->sortKeys()->values();

        return ['title' => 'Noví zákazníci', 'headers' => ['Období', 'Zákazníků'], 'rows' => $rows];
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function servicesReport(Carbon $from, Carbon $to, string $status): array
    {
        $services = Service::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->with(['customer', 'product'])
            ->get();

        $rows = $services->map(fn (Service $s): array => [
            'Zákazník' => $s->customer?->company_name ?: $s->customer?->email ?: '—',
            'Služba'   => $s->label ?? "Service #{$s->id}",
            'Produkt'  => $s->product?->name ?: '—',
            'Status'   => $s->status->label(),
            'Vytvořeno' => $s->created_at?->format('d.m.Y') ?? '—',
        ])->values();

        return ['title' => 'Přehled služeb', 'headers' => ['Zákazník', 'Služba', 'Produkt', 'Status', 'Vytvořeno'], 'rows' => $rows];
    }

    /** @return array{title: string, headers: list<string>, rows: Collection<int, mixed>} */
    private function invoicesReport(Carbon $from, Carbon $to, string $status): array
    {
        $invoices = Invoice::query()
            ->whereBetween('issue_date', [$from, $to])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->with('customer')
            ->get();

        $rows = $invoices->map(fn (Invoice $i): array => [
            'Číslo'     => $i->number,
            'Zákazník'  => $i->customer?->company_name ?: $i->customer?->email ?: '—',
            'Částka'    => number_format($i->total->getMinorAmount()->toInt() / 100, 2, ',', ' '),
            'Status'    => $i->status->label(),
            'Datum'     => $i->issue_date?->format('d.m.Y') ?? '—',
        ])->values();

        return ['title' => 'Přehled faktur', 'headers' => ['Číslo', 'Zákazník', 'Částka', 'Status', 'Datum'], 'rows' => $rows];
    }

    /** @param array{title: string, headers: list<string>, rows: Collection<int, mixed>} $report */
    public function toCsv(array $report): string
    {
        $lines = [implode(',', array_map(fn ($h) => '"' . str_replace('"', '""', $h) . '"', $report['headers']))];

        foreach ($report['rows'] as $row) {
            $lines[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                array_values((array) $row)
            ));
        }

        return implode("\n", $lines);
    }
}
