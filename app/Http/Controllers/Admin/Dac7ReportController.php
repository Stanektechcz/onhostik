<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Services\ExchangeRateService;
use App\Domains\Shared\Enums\Currency;
use App\Http\Controllers\Controller;
use Brick\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates a DAC7-compatible CSV export of platform revenue per customer.
 *
 * DAC7 (EU Directive 2021/514) requires platform operators to report
 * seller / service-recipient income to national tax authorities annually.
 * This export produces the data needed to compile the statutory report.
 */
class Dac7ReportController extends Controller
{
    public function export(Request $request, ExchangeRateService $fx): StreamedResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
        ]);

        $year = (int) $validated['year'];

        return response()->streamDownload(function () use ($year, $fx): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'customer_id',
                'jmeno',
                'firma',
                'ulice',
                'mesto',
                'psc',
                'stat',
                'dic',
                'celkem_czk_minor',
                'celkem_eur_minor',
                'celkem_usd_minor',
                'ekvivalent_czk_minor',
            ]);

            // Aggregate paid invoice totals per customer per currency
            // Use invoice snapshot fields (billing details frozen at issue time)
            $rows = DB::table('invoices')
                ->where('status', InvoiceStatus::Paid->value)
                ->whereYear('paid_at', $year)
                ->where('purpose', '!=', 'credit_topup')
                ->whereNotNull('customer_id')
                ->selectRaw('
                    customer_id,
                    MAX(snapshot_name)        AS snapshot_name,
                    MAX(snapshot_company)     AS snapshot_company,
                    MAX(snapshot_street)      AS snapshot_street,
                    MAX(snapshot_city)        AS snapshot_city,
                    MAX(snapshot_zip)         AS snapshot_zip,
                    MAX(snapshot_country_code) AS snapshot_country_code,
                    MAX(snapshot_vat_number)  AS snapshot_vat_number,
                    currency,
                    SUM(total)               AS total_minor
                ')
                ->groupBy('customer_id', 'currency')
                ->orderBy('customer_id')
                ->get();

            // Pivot per-customer (group currencies in memory)
            $pivoted = [];
            foreach ($rows as $row) {
                $id = $row->customer_id;
                if (!isset($pivoted[$id])) {
                    $pivoted[$id] = [
                        'customer_id'  => $id,
                        'full_name'    => $row->snapshot_name ?? '',
                        'company_name' => $row->snapshot_company ?? '',
                        'street'       => $row->snapshot_street ?? '',
                        'city'         => $row->snapshot_city ?? '',
                        'zip'          => $row->snapshot_zip ?? '',
                        'country_code' => $row->snapshot_country_code ?? '',
                        'vat_number'   => $row->snapshot_vat_number ?? '',
                        'CZK'          => 0,
                        'EUR'          => 0,
                        'USD'          => 0,
                    ];
                }
                $pivoted[$id][$row->currency] = (int) $row->total_minor;
            }

            foreach ($pivoted as $customer) {
                // Compute CZK equivalent of all currencies
                $czkEquiv = $customer['CZK'];

                foreach ([Currency::EUR, Currency::USD] as $curr) {
                    $minor = $customer[$curr->value];
                    if ($minor > 0) {
                        $rate = $fx->getLatestRate($curr);
                        if ($rate !== null) {
                            $czkEquiv += (int) round($minor * $rate);
                        }
                    }
                }

                fputcsv($handle, [
                    $customer['customer_id'],
                    $customer['full_name'],
                    $customer['company_name'],
                    $customer['street'],
                    $customer['city'],
                    $customer['zip'],
                    $customer['country_code'],
                    $customer['vat_number'],
                    $customer['CZK'],
                    $customer['EUR'],
                    $customer['USD'],
                    $czkEquiv,
                ]);
            }

            fclose($handle);
        }, "dac7-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
