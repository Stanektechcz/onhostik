<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    public function index(): View
    {
        return view('admin.calendar');
    }

    /** Returns FullCalendar-compatible events as JSON. */
    public function events(): JsonResponse
    {
        $events = [];

        // ── Service renewals (next 90 days) ──────────────────────────────
        Service::with('customer')
            ->where('status', ServiceStatus::Active->value)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '>=', now()->toDateString())
            ->whereDate('next_due_date', '<=', now()->addDays(90)->toDateString())
            ->get()
            ->each(function (Service $service) use (&$events): void {
                $daysLeft = (int) now()->diffInDays($service->next_due_date, false);
                $color    = $daysLeft <= 3 ? '#dc3545' : ($daysLeft <= 7 ? '#f39c12' : '#7366FF');
                $events[] = [
                    'title' => 'Obnova: ' . ($service->label ?: 'Služba #' . $service->id),
                    'start' => $service->next_due_date->format('Y-m-d'),
                    'color' => $color,
                    'url'   => route('admin.services.show', $service),
                    'extendedProps' => [
                        'type'     => 'renewal',
                        'customer' => $service->customer->email ?? '',
                    ],
                ];
            });

        // ── Domain expirations (next 90 days) ────────────────────────────
        DomainRegistration::with('service.customer')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', now()->addDays(90)->toDateString())
            ->get()
            ->each(function (DomainRegistration $domain) use (&$events): void {
                $daysLeft = (int) now()->diffInDays($domain->expires_at, false);
                $color    = $daysLeft <= 7 ? '#dc3545' : '#e67e22';
                $events[] = [
                    'title' => 'Doména: ' . $domain->fqdn(),
                    'start' => $domain->expires_at->format('Y-m-d'),
                    'color' => $color,
                    'url'   => route('admin.domains.show', $domain),
                    'extendedProps' => [
                        'type' => 'domain',
                    ],
                ];
            });

        // ── Overdue invoices (today and older) ────────────────────────────
        Invoice::whereIn('status', [InvoiceStatus::Overdue->value, InvoiceStatus::Sent->value])
            ->whereDate('due_date', '<=', now()->toDateString())
            ->get()
            ->each(function (Invoice $invoice) use (&$events): void {
                $events[] = [
                    'title' => 'Faktura: ' . $invoice->number,
                    'start' => $invoice->due_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'color' => $invoice->status === InvoiceStatus::Overdue ? '#dc3545' : '#f39c12',
                    'url'   => route('admin.invoices.show', $invoice),
                    'extendedProps' => [
                        'type' => 'invoice',
                    ],
                ];
            });

        // ── Future invoice due dates (next 30 days, sent) ─────────────────
        Invoice::where('status', InvoiceStatus::Sent->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays(30)->toDateString())
            ->get()
            ->each(function (Invoice $invoice) use (&$events): void {
                $events[] = [
                    'title' => 'Splatnost: ' . $invoice->number,
                    'start' => $invoice->due_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'color' => '#54ba4a',
                    'url'   => route('admin.invoices.show', $invoice),
                    'extendedProps' => [
                        'type' => 'invoice_due',
                    ],
                ];
            });

        return response()->json($events);
    }
}
