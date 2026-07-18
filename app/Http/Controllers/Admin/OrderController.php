<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\RecordManualPaymentAction;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status   = $request->string('status')->toString();
        $search   = $request->string('q')->toString();
        $dateFrom = $request->string('from')->toString();
        $dateTo   = $request->string('to')->toString();

        $counts = Order::query()->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        return view('admin.orders', [
            'orders' => Order::query()
                ->with(['customer', 'items'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->whereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"));
                })
                ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filter'           => $status,
            'search'           => $search,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
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

    /**
     * Accept the order: record a manual (admin-confirmed) payment against its
     * open proforma. That fires InvoicePaid, which transitions the order and
     * queues provisioning — the same path a gateway payment takes.
     */
    public function accept(Order $order, RecordManualPaymentAction $recordPayment): RedirectResponse
    {
        $order->load('invoices');

        $invoice = $order->invoices->first(fn (Invoice $i): bool => $i->status->isOpen());

        if ($invoice === null) {
            return back()->withErrors(['order' => 'Objednávka nemá žádnou otevřenou fakturu k akceptaci.']);
        }

        try {
            $recordPayment->execute($invoice, 'Ručně akceptováno administrátorem');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        activity('order')
            ->performedOn($order)
            ->causedBy($this->actor())
            ->log('order.accepted_by_admin');

        return back()->with('status', 'Objednávka byla akceptována — platba zaznamenána a služby se zřizují.');
    }

    /** Cancel the order. */
    public function cancel(Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::Cancelled) {
            return back()->withErrors(['order' => 'Objednávka je již zrušena.']);
        }

        $order->update([
            'status'       => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        activity('order')
            ->performedOn($order)
            ->causedBy($this->actor())
            ->withProperties(['transition' => 'cancelled'])
            ->log('order.cancelled_by_admin');

        return back()->with('status', 'Objednávka byla zrušena.');
    }

    /** Re-run provisioning for the order's services that are not yet live. */
    public function provision(Order $order): RedirectResponse
    {
        $order->load('items');

        $services = Service::query()
            ->whereIn('order_item_id', $order->items->pluck('id'))
            ->get();

        $provisionable = [
            ProvisioningDriver::AAPanel,
            ProvisioningDriver::Proxmox,
            ProvisioningDriver::Pterodactyl,
        ];

        $dispatched = 0;

        foreach ($services as $service) {
            if ($service->status !== ServiceStatus::Active
                && in_array($service->provisioning_driver, $provisionable, true)) {
                ProvisionHostingServiceJob::dispatch($service->id);
                $dispatched++;
            }
        }

        if ($dispatched === 0) {
            return back()->with('status', 'Žádné služby nevyžadují opětovné zřízení.');
        }

        return back()->with('status', "Zřizování bylo znovu spuštěno pro {$dispatched} služeb.");
    }

    /** Edit order notes / status and the per-item domain (before provisioning). */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'notes'          => ['nullable', 'string', 'max:2000'],
            'status'         => ['nullable', 'in:pending,processing,active,cancelled,fraud'],
            'item_domain'    => ['nullable', 'array'],
            'item_domain.*'  => ['nullable', 'string', 'max:253'],
        ]);

        $data = ['notes' => $validated['notes'] ?? null];

        if (! empty($validated['status'])) {
            $data['status'] = OrderStatus::from($validated['status']);

            if ($data['status'] === OrderStatus::Cancelled && $order->cancelled_at === null) {
                $data['cancelled_at'] = now();
            }
        }

        $order->update($data);

        $order->load('items');

        foreach ($validated['item_domain'] ?? [] as $itemId => $domain) {
            $item = $order->items->firstWhere('id', (int) $itemId);

            if ($item === null) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $config = $item->config ?? [];
            $trimmed = is_string($domain) ? mb_strtolower(trim($domain)) : '';

            if ($trimmed !== '') {
                $config['domain'] = $trimmed;
            } else {
                unset($config['domain']);
            }

            $item->update(['config' => $config]);
        }

        activity('order')
            ->performedOn($order)
            ->causedBy($this->actor())
            ->log('order.updated_by_admin');

        return back()->with('status', 'Objednávka byla upravena.');
    }

    private function actor(): ?\App\Models\User
    {
        $user = auth()->user();

        return $user instanceof \App\Models\User ? $user : null;
    }
}
