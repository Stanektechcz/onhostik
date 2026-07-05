<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->string('q')->toString();

        $customers = collect();
        $orders    = collect();
        $invoices  = collect();
        $services  = collect();
        $tickets   = collect();

        if ($query !== '') {
            $like = "%{$query}%";

            $customers = Customer::query()
                ->where('email', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like))
                ->limit(10)
                ->get();

            $orders = Order::query()
                ->where('uuid', 'like', $like)
                ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', $like))
                ->with('customer')
                ->limit(10)
                ->get();

            $invoices = Invoice::query()
                ->where('number', 'like', $like)
                ->orWhere('variable_symbol', 'like', $like)
                ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', $like))
                ->limit(10)
                ->get();

            $services = Service::query()
                ->where('label', 'like', $like)
                ->orWhere('external_id', 'like', $like)
                ->orWhereHas('customer', fn ($q) => $q->where('email', 'like', $like)->orWhere('company_name', 'like', $like))
                ->with('customer', 'product')
                ->limit(10)
                ->get();

            $tickets = SupportTicket::query()
                ->where('subject', 'like', $like)
                ->orWhere('uuid', 'like', $like)
                ->orWhereHas('customer.user', fn ($q) => $q->where('email', 'like', $like))
                ->with('customer.user')
                ->limit(10)
                ->get();
        }

        $total = $customers->count() + $orders->count() + $invoices->count()
               + $services->count() + $tickets->count();

        return view('admin.search', compact('query', 'customers', 'orders', 'invoices', 'services', 'tickets', 'total'));
    }

    /** Live autocomplete — returns top 5 results per category as JSON. */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $like    = "%{$query}%";
        $results = [];

        Customer::query()
            ->where('email', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->limit(5)
            ->get()
            ->each(function (Customer $c) use (&$results): void {
                $results[] = [
                    'type'  => 'customer',
                    'label' => ($c->company_name ?: $c->email),
                    'sub'   => $c->email,
                    'url'   => route('admin.customers.show', $c),
                ];
            });

        Invoice::query()
            ->where('number', 'like', $like)
            ->limit(5)
            ->get()
            ->each(function (Invoice $i) use (&$results): void {
                $results[] = [
                    'type'  => 'invoice',
                    'label' => $i->number,
                    'sub'   => $i->status->label(),
                    'url'   => route('admin.invoices.show', $i),
                ];
            });

        Service::query()
            ->where('label', 'like', $like)
            ->orWhere('external_id', 'like', $like)
            ->limit(5)
            ->get()
            ->each(function (Service $s) use (&$results): void {
                $results[] = [
                    'type'  => 'service',
                    'label' => ($s->label ?: "Service #{$s->id}"),
                    'sub'   => $s->status->label(),
                    'url'   => route('admin.services.show', $s),
                ];
            });

        SupportTicket::query()
            ->where('subject', 'like', $like)
            ->limit(5)
            ->get()
            ->each(function (SupportTicket $t) use (&$results): void {
                $results[] = [
                    'type'  => 'ticket',
                    'label' => $t->subject,
                    'sub'   => "#{$t->uuid}",
                    'url'   => route('admin.support.show', $t),
                ];
            });

        return response()->json(['results' => array_slice($results, 0, 12)]);
    }
}
