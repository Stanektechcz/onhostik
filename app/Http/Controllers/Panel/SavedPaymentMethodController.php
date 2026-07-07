<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SavedPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        $methods = SavedPaymentMethod::where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return view('panel.payment-methods.index', compact('methods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'provider'   => ['required', 'string', 'max:30'],
            'label'      => ['required', 'string', 'max:100'],
            'last4'      => ['nullable', 'string', 'size:4'],
            'card_brand' => ['nullable', 'string', 'max:30'],
            'expires_at' => ['nullable', 'string', 'max:7'],
        ]);

        if ($request->boolean('is_default')) {
            SavedPaymentMethod::where('customer_id', $customer->id)->update(['is_default' => false]);
        }

        SavedPaymentMethod::create(array_merge($validated, [
            'customer_id' => $customer->id,
            'is_default'  => $request->boolean('is_default'),
        ]));

        return back()->with('status', 'Platební metoda přidána.');
    }

    public function setDefault(Request $request, SavedPaymentMethod $method): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($customer === null || $method->customer_id !== $customer->id, 403);

        SavedPaymentMethod::where('customer_id', $customer->id)->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return back()->with('status', 'Výchozí platební metoda nastavena.');
    }

    public function destroy(Request $request, SavedPaymentMethod $method): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($customer === null || $method->customer_id !== $customer->id, 403);

        $method->delete();

        return back()->with('status', 'Platební metoda odstraněna.');
    }
}
