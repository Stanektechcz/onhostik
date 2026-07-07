<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class CustomerLtvReportController extends Controller
{
    public function index(): View
    {
        // Use DB::table() — Invoice has MoneyCast, aggregate on model is unsafe
        $ltvRows = DB::table('invoices')
            ->select('customer_id', DB::raw('SUM(total) as ltv_minor'))
            ->where('status', InvoiceStatus::Paid->value)
            ->groupBy('customer_id')
            ->orderByDesc('ltv_minor')
            ->limit(100)
            ->get()
            ->keyBy('customer_id');

        $customerIds = $ltvRows->keys()->all();
        $customers   = Customer::query()
            ->with('user')
            ->whereIn('id', $customerIds)
            ->get()
            ->keyBy('id');

        $ranked = $ltvRows->map(function (object $row) use ($customers): array {
            $customer = $customers->get($row->customer_id);
            return [
                'customer'  => $customer,
                'ltv_minor' => (int) $row->ltv_minor,
            ];
        })->values();

        // Avg LTV per segment
        $segmentAvg = [];
        foreach (CustomerSegment::cases() as $seg) {
            $segCustomerIds = Customer::query()
                ->where('segment', $seg->value)
                ->pluck('id')
                ->all();

            if (empty($segCustomerIds)) {
                $segmentAvg[$seg->value] = 0;
                continue;
            }

            $avg = DB::table('invoices')
                ->where('status', InvoiceStatus::Paid->value)
                ->whereIn('customer_id', $segCustomerIds)
                ->avg('total');

            $segmentAvg[$seg->value] = (int) round((float) ($avg ?? 0));
        }

        return view('admin.customer-ltv-report', [
            'ranked'     => $ranked,
            'segmentAvg' => $segmentAvg,
            'segments'   => CustomerSegment::cases(),
        ]);
    }
}
