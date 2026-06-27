<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->string('q')->toString();

        $customers = collect();
        $orders    = collect();
        $invoices  = collect();

        if ($query !== '') {
            $customers = Customer::query()
                ->where('email', 'like', "%{$query}%")
                ->orWhere('company_name', 'like', "%{$query}%")
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$query}%"))
                ->limit(10)
                ->get();

            $orders = Order::query()
                ->where('uuid', 'like', "%{$query}%")
                ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', "%{$query}%"))
                ->with('customer')
                ->limit(10)
                ->get();

            $invoices = Invoice::query()
                ->where('number', 'like', "%{$query}%")
                ->orWhere('variable_symbol', 'like', "%{$query}%")
                ->orWhereHas('order.customer', fn ($q) => $q->where('email', 'like', "%{$query}%"))
                ->limit(10)
                ->get();
        }

        return view('admin.search', compact('query', 'customers', 'orders', 'invoices'));
    }
}
