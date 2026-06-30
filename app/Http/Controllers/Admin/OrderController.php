<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $counts = Order::query()->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        return view('admin.orders', [
            'orders' => Order::query()
                ->with(['customer', 'items'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->whereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"));
                })
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filter'           => $status,
            'search'           => $search,
            'countActive'      => (int) ($counts[OrderStatus::Active->value] ?? 0),
            'countPending'     => (int) ($counts[OrderStatus::Pending->value] ?? 0),
            'countProcessing'  => (int) ($counts[OrderStatus::Processing->value] ?? 0),
            'countCancelled'   => (int) ($counts[OrderStatus::Cancelled->value] ?? 0),
        ]);
    }

    /** Stream orders as CSV. */
    public function export(Request $request): StreamedResponse
    {
        $status   = $request->string('status')->toString();
        $dateFrom = $request->string('from')->toString();
        $dateTo   = $request->string('to')->toString();

        $query = Order::query()
            ->with('customer')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->orderBy('id');

        $filename = 'objednavky-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Status', 'Zákazník', 'E-mail', 'IČO', 'Měna',
                'Základ', 'Celkem', 'Datum vytvoření', 'Datum zaplacení',
            ], ';');

            $query->chunk(200, function ($orders) use ($out): void {
                foreach ($orders as $order) {
                    $minor = 100;
                    fputcsv($out, [
                        $order->id,
                        $order->status->label(),
                        $order->customer->company_name ?: '',
                        $order->customer->email,
                        $order->customer->registration_number ?: '',
                        $order->total->getCurrency()->getCurrencyCode(),
                        number_format($order->subtotal->getMinorAmount()->toInt() / $minor, 2, ',', ''),
                        number_format($order->total->getMinorAmount()->toInt() / $minor, 2, ',', ''),
                        $order->created_at?->format('d.m.Y H:i') ?? '',
                        $order->paid_at?->format('d.m.Y H:i') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['customer.user', 'items.pricingPlan.product', 'invoices.payments']);

        $services = Service::query()
            ->with(['product', 'server'])
            ->whereIn('order_item_id', $order->items->pluck('id'))
            ->get();

        return view('admin.order-show', [
            'order'    => $order,
            'services' => $services,
        ]);
    }
}
