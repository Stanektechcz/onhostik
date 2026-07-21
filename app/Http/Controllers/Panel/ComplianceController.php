<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Compliance\Enums\GdprRequestStatus;
use App\Domains\Compliance\Enums\GdprRequestType;
use App\Domains\Compliance\Models\GdprRequest;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;
        $requests = $customer
            ? GdprRequest::where('customer_id', $customer->id)->latest()->get()
            : collect();

        return view('panel.compliance.index', compact('requests'));
    }

    public function requestExport(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $pending = GdprRequest::where('customer_id', $customer->id)
            ->where('type', GdprRequestType::Export->value)
            ->whereIn('status', [GdprRequestStatus::Pending->value, GdprRequestStatus::Processing->value])
            ->exists();

        if ($pending) {
            return back()->withErrors(['type' => 'Již máte aktivní žádost o export dat.']);
        }

        GdprRequest::create([
            'customer_id' => $customer->id,
            'type'        => GdprRequestType::Export,
            'status'      => GdprRequestStatus::Completed,
            'completed_at' => now(),
        ]);

        return back()->with('status', 'Export dat byl zahájen. Data si lze stáhnout níže.');
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $pending = GdprRequest::where('customer_id', $customer->id)
            ->where('type', GdprRequestType::Deletion->value)
            ->whereIn('status', [GdprRequestStatus::Pending->value, GdprRequestStatus::Processing->value])
            ->exists();

        if ($pending) {
            return back()->withErrors(['type' => 'Již máte aktivní žádost o smazání účtu.']);
        }

        GdprRequest::create([
            'customer_id' => $customer->id,
            'type'        => GdprRequestType::Deletion,
            'status'      => GdprRequestStatus::Pending,
        ]);

        return back()->with('status', 'Žádost o smazání účtu byla odeslána. Bude zpracována do 30 dnů.');
    }

    public function downloadExport(Request $request, Customer $customer): Response
    {
        abort_if($request->user()?->customer?->id !== $customer->id, 403);

        $data = $this->buildExportData($customer);

        return response(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type'        => 'application/json',
                'Content-Disposition' => 'attachment; filename="gdpr-export-' . now()->format('Y-m-d') . '.json"',
            ]
        );
    }

    /**
     * Download the customer's Data Processing Agreement (audit 178).
     *
     * A business customer is the controller of the personal data in their
     * services and routinely needs a signed-shape DPA for their own art. 28
     * compliance. Generating it on demand beats "email support and wait".
     */
    public function downloadDpa(Request $request): Response
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $result = app(\App\Domains\Compliance\Services\DpaPdfService::class)->generate($customer);

        // A DPA names the parties and the processing terms — worth an audit
        // trail of who generated which version and when.
        activity('compliance')
            ->performedOn($customer)
            ->causedBy($request->user())
            ->withProperties(['dpa_version' => $result['version']])
            ->log('dpa.generated');

        return response($result['pdf'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
        ]);
    }

    /** @return array<string, mixed> */
    private function buildExportData(Customer $customer): array
    {
        $customer->loadMissing(['user', 'orders', 'invoices', 'services', 'supportTickets', 'addresses']);

        return [
            'exported_at' => now()->toIso8601String(),
            'customer'    => [
                'id'              => $customer->id,
                'name'            => $customer->user->name ?? null,
                'email'           => $customer->user->email ?? null,
                'company_name'    => $customer->company_name,
                'phone'           => $customer->phone,
                'vat_number'      => $customer->vat_number ?? null,
                'created_at'      => $customer->created_at->toIso8601String(),
            ],
            'services'    => $customer->services->map(fn ($s) => [
                'id'         => $s->id,
                'label'      => $s->label,
                'status'     => $s->status->value,
                'created_at' => $s->created_at->toIso8601String(),
            ])->toArray(),
            'invoices'    => $customer->invoices->map(fn ($inv) => [
                'id'         => $inv->id,
                'number'     => $inv->number ?? null,
                'amount'     => $inv->total_amount ?? null,
                'status'     => $inv->status ?? null,
                'created_at' => $inv->created_at->toIso8601String(),
            ])->toArray(),
            'tickets'     => $customer->supportTickets->map(fn ($t) => [
                'id'         => $t->id,
                'subject'    => $t->subject,
                'status'     => $t->status,
                'created_at' => $t->created_at->toIso8601String(),
            ])->toArray(),
        ];
    }
}
