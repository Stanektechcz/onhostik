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

        $domainTransferRequest->update([...$validated, 'handled_by' => $request->user()->id]);

        return back()->with('status', 'Žádost o přenos aktualizována.');
    }
}
