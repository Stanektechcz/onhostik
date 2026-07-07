<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\Payment;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ActivityFeedController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $customer = $this->customer($request);
        $filter   = $request->string('filter')->toString();
        $page     = max(1, (int) $request->get('page', 1));

        $events = $this->collectEvents($customer, $filter)
            ->sortByDesc('date')
            ->values();

        $paginator = new LengthAwarePaginator(
            $events->forPage($page, self::PER_PAGE)->values(),
            $events->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('panel.activity-feed', [
            'events' => $paginator,
            'filter' => $filter,
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function collectEvents(Customer $customer, string $filter): Collection
    {
        $events = collect();

        if ($filter === '' || $filter === 'invoices') {
            $this->collectInvoiceEvents($customer, $events);
        }
        if ($filter === '' || $filter === 'payments') {
            $this->collectPaymentEvents($customer, $events);
        }
        if ($filter === '' || $filter === 'orders') {
            $this->collectOrderEvents($customer, $events);
        }
        if ($filter === '' || $filter === 'tickets') {
            $this->collectTicketEvents($customer, $events);
        }
        if ($filter === '' || $filter === 'services') {
            $this->collectServiceEvents($customer, $events);
        }
        if ($filter === '' || $filter === 'credit') {
            $this->collectCreditEvents($customer, $events);
        }

        return $events;
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectInvoiceEvents(Customer $customer, Collection $events): void
    {
        $customer->invoices()
            ->latest('id')
            ->limit(100)
            ->get()
            ->each(function (Invoice $invoice) use ($events): void {
                $formatted = MoneyFormatter::format($invoice->total);

                $events->push([
                    'type'        => 'invoice_created',
                    'icon'        => 'file-text',
                    'color'       => 'primary',
                    'title'       => "Faktura #{$invoice->number} vystavena",
                    'description' => $formatted,
                    'date'        => $invoice->created_at,
                    'url'         => route('panel.billing.invoices.show', $invoice),
                    'badge'       => $invoice->status->label(),
                    'badge_color' => $invoice->status->color(),
                ]);

                if ($invoice->paid_at !== null) {
                    $events->push([
                        'type'        => 'invoice_paid',
                        'icon'        => 'check-circle',
                        'color'       => 'success',
                        'title'       => "Faktura #{$invoice->number} zaplacena",
                        'description' => $formatted,
                        'date'        => $invoice->paid_at,
                        'url'         => route('panel.billing.invoices.show', $invoice),
                        'badge'       => 'Zaplacena',
                        'badge_color' => 'success',
                    ]);
                }
            });
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectPaymentEvents(Customer $customer, Collection $events): void
    {
        $customer->payments()
            ->latest('id')
            ->limit(100)
            ->get()
            ->each(function (Payment $payment) use ($events): void {
                $isSuccess = $payment->status === PaymentStatus::Completed;

                $events->push([
                    'type'        => $isSuccess ? 'payment_received' : 'payment_failed',
                    'icon'        => $isSuccess ? 'credit-card' : 'alert-circle',
                    'color'       => $isSuccess ? 'success' : 'danger',
                    'title'       => $isSuccess ? 'Platba přijata' : 'Platba selhala',
                    'description' => MoneyFormatter::format($payment->amount),
                    'date'        => $payment->created_at,
                    'url'         => null,
                    'badge'       => $payment->status->label(),
                    'badge_color' => $isSuccess ? 'success' : 'danger',
                ]);
            });
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectOrderEvents(Customer $customer, Collection $events): void
    {
        $customer->orders()
            ->latest('id')
            ->limit(100)
            ->get()
            ->each(function (Order $order) use ($events): void {
                $events->push([
                    'type'        => 'order_created',
                    'icon'        => 'shopping-bag',
                    'color'       => 'info',
                    'title'       => "Objednávka #{$order->id} vytvořena",
                    'description' => MoneyFormatter::format($order->total),
                    'date'        => $order->created_at,
                    'url'         => route('panel.orders.show', $order),
                    'badge'       => $order->status->label(),
                    'badge_color' => $order->status->color(),
                ]);
            });
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectTicketEvents(Customer $customer, Collection $events): void
    {
        $customer->supportTickets()
            ->latest('id')
            ->limit(50)
            ->get()
            ->each(function (SupportTicket $ticket) use ($events): void {
                $events->push([
                    'type'        => 'ticket_opened',
                    'icon'        => 'message-circle',
                    'color'       => 'warning',
                    'title'       => "Ticket #{$ticket->id} otevřen",
                    'description' => $ticket->subject,
                    'date'        => $ticket->created_at,
                    'url'         => route('panel.support.show', $ticket),
                    'badge'       => $ticket->status->label(),
                    'badge_color' => 'warning',
                ]);

                if ($ticket->closed_at !== null && $ticket->status === TicketStatus::Closed) {
                    $events->push([
                        'type'        => 'ticket_closed',
                        'icon'        => 'check-square',
                        'color'       => 'secondary',
                        'title'       => "Ticket #{$ticket->id} uzavřen",
                        'description' => $ticket->subject,
                        'date'        => $ticket->closed_at,
                        'url'         => route('panel.support.show', $ticket),
                        'badge'       => 'Uzavřen',
                        'badge_color' => 'secondary',
                    ]);
                }
            });
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectServiceEvents(Customer $customer, Collection $events): void
    {
        Service::where('customer_id', $customer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->each(function (Service $service) use ($events): void {
                $events->push([
                    'type'        => 'service_created',
                    'icon'        => 'server',
                    'color'       => $service->status->color(),
                    'title'       => 'Služba zřízena',
                    'description' => $service->label ?: null,
                    'date'        => $service->created_at,
                    'url'         => route('panel.services.show', $service),
                    'badge'       => $service->status->label(),
                    'badge_color' => $service->status->color(),
                ]);
            });
    }

    /** @param Collection<int, array<string, mixed>> $events */
    private function collectCreditEvents(Customer $customer, Collection $events): void
    {
        $customer->creditTransactions()
            ->latest('id')
            ->limit(50)
            ->get()
            ->each(function ($tx) use ($events): void {
                $events->push([
                    'type'        => 'credit_' . $tx->type->value,
                    'icon'        => $tx->amount->isPositive() ? 'trending-up' : 'trending-down',
                    'color'       => $tx->type->color(),
                    'title'       => 'Kredit: ' . $tx->type->label(),
                    'description' => MoneyFormatter::format($tx->amount),
                    'date'        => $tx->created_at,
                    'url'         => route('panel.billing.credits'),
                    'badge'       => null,
                    'badge_color' => null,
                ]);
            });
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
