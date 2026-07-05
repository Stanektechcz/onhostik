<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkController extends Controller
{
    public function index(): View
    {
        return view('admin.bulk.index', [
            'serviceCount'  => Service::count(),
            'customerCount' => Customer::count(),
            'invoiceCount'  => Invoice::count(),
        ]);
    }

    // ── Services ──────────────────────────────────────────────────────────────

    public function serviceExtendDueDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'  => ['integer', 'exists:services,id'],
            'days'   => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) $validated['days'];
        $count = 0;

        Service::whereIn('id', $validated['ids'])
            ->whereNotNull('next_due_date')
            ->each(function (Service $service) use ($days, $request, &$count): void {
                $service->update([
                    'next_due_date' => Carbon::parse($service->next_due_date)->addDays($days),
                ]);
                activity('provisioning')
                    ->performedOn($service)
                    ->causedBy($request->user())
                    ->withProperties(['operation' => 'bulk_extend_due_date', 'days' => $days])
                    ->log('service.due_date_extended');
                $count++;
            });

        return back()->with('status', "Splatnost prodloužena o {$days} dní u {$count} služeb.");
    }

    public function serviceTerminate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'    => ['required', 'array', 'min:1', 'max:50'],
            'ids.*'  => ['integer', 'exists:services,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $count = 0;
        Service::whereIn('id', $validated['ids'])
            ->whereNotIn('status', [ServiceStatus::Terminated->value])
            ->each(function (Service $service) use ($validated, $request, &$count): void {
                ChangeServiceStateJob::dispatch($service->id, 'terminate', $validated['reason']);
                activity('provisioning')
                    ->performedOn($service)
                    ->causedBy($request->user())
                    ->withProperties(['operation' => 'bulk_terminate', 'reason' => $validated['reason']])
                    ->log('service.bulk_terminate_requested');
                $count++;
            });

        return back()->with('status', "Ukončení odesláno: {$count} služeb.");
    }

    public function serviceExport(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:services,id'],
        ]);

        $services = Service::whereIn('id', $validated['ids'])
            ->with(['customer', 'product'])
            ->orderBy('id')
            ->get();

        $filename = 'sluzby-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($services): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Label', 'Status', 'Zákazník', 'E-mail', 'Produkt', 'Příští platba'], ';');
            foreach ($services as $s) {
                fputcsv($out, [
                    $s->id,
                    $s->label ?? '',
                    $s->status->value,
                    $s->customer?->user->name ?? '',
                    $s->customer->email ?? '',
                    $s->product?->getTranslation('name', 'cs') ?? '',
                    $s->next_due_date?->format('Y-m-d') ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Invoices ──────────────────────────────────────────────────────────────

    public function invoiceVoid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $count = Invoice::whereIn('id', $validated['ids'])
            ->whereIn('status', ['draft', 'sent'])
            ->update(['status' => 'cancelled']);

        return back()->with('status', "Stornováno {$count} faktur.");
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    public function customerExport(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'exists:customers,id'],
        ]);

        $customers = Customer::whereIn('id', $validated['ids'])
            ->with('user')
            ->orderBy('id')
            ->get();

        $filename = 'zakaznici-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($customers): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Jméno', 'E-mail', 'Firma', 'Stát', 'Registrace'], ';');
            foreach ($customers as $c) {
                fputcsv($out, [
                    $c->id,
                    $c->user->name ?? '',
                    $c->email ?? '',
                    $c->company_name ?? '',
                    $c->country ?? '',
                    $c->created_at?->format('Y-m-d') ?? '',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
