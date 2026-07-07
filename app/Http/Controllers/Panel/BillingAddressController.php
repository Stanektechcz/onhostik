<?php
declare(strict_types=1);
namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BillingAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingAddressController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $addresses = BillingAddress::where('customer_id', $customerId)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return view('panel.billing-addresses.index', compact('addresses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $validated = $request->validate([
            'label'        => ['required', 'max:80'],
            'company_name' => ['nullable', 'max:150'],
            'street'       => ['required', 'max:150'],
            'city'         => ['required', 'max:80'],
            'postal_code'  => ['required', 'max:10'],
            'country_code' => ['required', 'size:2'],
            'vat_number'   => ['nullable', 'max:30'],
            'is_default'   => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            BillingAddress::where('customer_id', $customerId)->update(['is_default' => false]);
        }

        BillingAddress::create([...$validated, 'customer_id' => $customerId]);

        return back()->with('status', 'Adresa přidána.');
    }

    public function edit(Request $request, BillingAddress $billingAddress): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);
        abort_unless($billingAddress->customer_id === $customerId, 403);

        $address = $billingAddress;
        return view('panel.billing-addresses.edit', compact('address'));
    }

    public function update(Request $request, BillingAddress $billingAddress): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);
        abort_unless($billingAddress->customer_id === $customerId, 403);

        $validated = $request->validate([
            'label'        => ['required', 'max:80'],
            'company_name' => ['nullable', 'max:150'],
            'street'       => ['required', 'max:150'],
            'city'         => ['required', 'max:80'],
            'postal_code'  => ['required', 'max:10'],
            'country_code' => ['required', 'size:2'],
            'vat_number'   => ['nullable', 'max:30'],
            'is_default'   => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            BillingAddress::where('customer_id', $customerId)->update(['is_default' => false]);
        }

        $billingAddress->update($validated);

        return back()->with('status', 'Adresa aktualizována.');
    }

    public function destroy(Request $request, BillingAddress $billingAddress): RedirectResponse
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);
        abort_unless($billingAddress->customer_id === $customerId, 403);

        $billingAddress->delete();

        return back()->with('status', 'Adresa smazána.');
    }
}
