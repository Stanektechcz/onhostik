<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceCustomerNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCustomerNoteController extends Controller
{
    public function index(Request $request, Service $service): View
    {
        abort_unless($service->customer_id === $request->user()->customer?->id, 403);

        $notes = ServiceCustomerNote::where('service_id', $service->id)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('panel.service-notes.index', compact('service', 'notes'));
    }

    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->customer_id === $request->user()->customer?->id, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        ServiceCustomerNote::create([
            'service_id' => $service->id,
            'user_id'    => $request->user()->id,
            'content'    => $validated['content'],
        ]);

        return back()->with('status', 'Poznámka uložena.');
    }

    public function destroy(Request $request, ServiceCustomerNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $note->delete();

        return back()->with('status', 'Poznámka smazána.');
    }
}
