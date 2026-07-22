<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const MAX_PER_TYPE = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like    = '%' . $q . '%';
        $results = [];

        // Customers — phone is encrypted at rest (audit 31), so it can only be
        // matched EXACTLY via its blind index, not by LIKE/substring.
        Customer::query()
            ->where(fn ($w) => $w
                ->where('company_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone_bidx', \App\Domains\Shared\Support\BlindIndex::of($q)))
            ->limit(self::MAX_PER_TYPE)
            ->get()
            ->each(function (Customer $c) use (&$results): void {
                $results[] = [
                    'type'     => 'customer',
                    'icon'     => 'user',
                    'title'    => $c->company_name ?: $c->email,
                    'subtitle' => $c->email,
                    'url'      => route('admin.customers.show', $c),
                ];
            });

        // Services
        Service::query()
            ->where(fn ($w) => $w
                ->where('label', 'like', $like)
                ->orWhere('external_id', 'like', $like))
            ->limit(self::MAX_PER_TYPE)
            ->get()
            ->each(function (Service $s) use (&$results): void {
                $subtitle = $s->status->label();
                if ($s->external_id !== null) {
                    $subtitle .= ' · ' . $s->external_id;
                }
                $results[] = [
                    'type'     => 'service',
                    'icon'     => 'server',
                    'title'    => $s->label ?? "Service #{$s->id}",
                    'subtitle' => $subtitle,
                    'url'      => route('admin.services.show', $s),
                ];
            });

        // Invoices
        Invoice::query()
            ->where('number', 'like', $like)
            ->limit(self::MAX_PER_TYPE)
            ->get()
            ->each(function (Invoice $i) use (&$results): void {
                $results[] = [
                    'type'     => 'invoice',
                    'icon'     => 'file-text',
                    'title'    => "Faktura {$i->number}",
                    'subtitle' => $i->status->label(),
                    'url'      => route('admin.invoices.show', $i),
                ];
            });

        // Orders (by UUID prefix)
        Order::query()
            ->where('uuid', 'like', $like)
            ->limit(self::MAX_PER_TYPE)
            ->get()
            ->each(function (Order $o) use (&$results): void {
                $results[] = [
                    'type'     => 'order',
                    'icon'     => 'shopping-cart',
                    'title'    => "Objednávka #{$o->id}",
                    'subtitle' => mb_substr($o->uuid, 0, 8) . '…',
                    'url'      => route('admin.orders.show', $o),
                ];
            });

        // Tickets
        SupportTicket::query()
            ->where('subject', 'like', $like)
            ->limit(self::MAX_PER_TYPE)
            ->get()
            ->each(function (SupportTicket $t) use (&$results): void {
                $results[] = [
                    'type'     => 'ticket',
                    'icon'     => 'message-circle',
                    'title'    => $t->subject,
                    'subtitle' => $t->status->label(),
                    'url'      => route('admin.support.show', $t),
                ];
            });

        return response()->json(['results' => $results]);
    }
}
