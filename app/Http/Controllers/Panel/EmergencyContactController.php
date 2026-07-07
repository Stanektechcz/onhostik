<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmergencyContactController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $contacts = EmergencyContact::where('customer_id', $customerId)->get();

        return view('panel.emergency-contacts.index', compact('contacts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $validated = $request->validate([
            'name'                 => 'required|string|max:100',
            'email'                => 'required|email|max:150',
            'phone'                => 'nullable|string|max:30',
            'relationship'         => 'nullable|string|max:60',
            'notify_on_suspension' => 'boolean',
            'notify_on_expiry'     => 'boolean',
        ]);

        EmergencyContact::create([
            ...$validated,
            'customer_id' => $customerId,
        ]);

        return back()->with('status', 'Kontakt přidán.');
    }

    public function destroy(Request $request, EmergencyContact $emergencyContact): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($emergencyContact->customer_id === $customerId, 403);

        $emergencyContact->delete();

        return back()->with('status', 'Kontakt smazán.');
    }
}
