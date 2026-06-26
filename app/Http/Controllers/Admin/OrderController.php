<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
