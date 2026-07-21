<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DomainTransferRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainTransferRequestController extends Controller
{
    public function index(): View
    {
        $requests = DomainTransferRequest::with(['customer'])->orderByDesc('created_at')->paginate(20);
        return view('admin.domain-transfer-requests.index', compact('requests'));
    }

    public function update(Request $request, DomainTransferRequest $domainTransferRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status'     => ['required', 'in:pending,processing,completed,failed,cancelled'],
            'admin_note' => ['nullable', 'max:500'],
        ]);

        $previous = $domainTransferRequest->status;

        $domainTransferRequest->update([...$validated, 'handled_by' => $request->user()->id]);

        // Moving a request to "processing" is the approval — it must actually
        // start the transfer at the registrar. Until now it only changed a
        // label in our database and nothing ever happened (audit F86).
        if ($validated['status'] === 'processing' && $previous !== 'processing') {
            $this->initiateTransfer($domainTransferRequest, $request);
        }

        activity('domain')
            ->causedBy($request->user())
            ->withProperties([
                'transfer_request_id' => $domainTransferRequest->id,
                'domain'              => $domainTransferRequest->domain_name,
                'from'                => $previous,
                'to'                  => $validated['status'],
            ])
            ->log('domain.transfer_status_changed');

        return back()->with('status', 'Žádost o přenos aktualizována.');
    }

    /**
     * Hand the auth code to the registrar. Failure is recorded on the request
     * instead of thrown, so one bad transfer never blocks the admin screen —
     * and the auth code itself is never written to the note or the log.
     */
    private function initiateTransfer(DomainTransferRequest $transfer, Request $request): void
    {
        $setting = \App\Domains\Integrations\Models\IntegrationSetting::query()
            ->where('provider', 'wedos')
            ->first();

        if ($setting === null) {
            // No registrar wired up — the admin is tracking a transfer they
            // are running by hand elsewhere. Record that nothing automatic
            // happened, but do NOT override the status they just chose.
            $transfer->update([
                'admin_note' => trim((string) $transfer->admin_note . ' Registrátor není nakonfigurován — přenos nebyl spuštěn automaticky.'),
            ]);

            return;
        }

        try {
            (new \App\Domains\Integrations\Clients\WedosWapiClient($setting))
                ->transferDomain($transfer->domain_name, (string) $transfer->auth_code);
        } catch (\Throwable $e) {
            $transfer->update([
                'status'     => 'failed',
                'admin_note' => 'Registrátor odmítl přenos: ' . mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }
}
