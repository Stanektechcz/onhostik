<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        $user = $request->user();
        $customer = $user?->customer;

        return view('panel.account.profile', compact('user', 'customer'));
    }

    public function billing(Request $request): View
    {
        $customer = $this->customer($request);

        return view('panel.account.billing', [
            'customer' => $customer,
            'address'  => $customer->billingAddress(),
        ]);
    }

    /**
     * Billing details upsert — required before a tax document can be
     * issued (street + city + zip + name). Changes are audit-logged by
     * the Customer model's LogsActivity configuration.
     */
    public function updateBilling(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'                => ['required', Rule::in(['person', 'company'])],
            'company_name'        => ['nullable', 'string', 'max:150', 'required_if:type,company'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'vat_number'          => ['nullable', 'string', 'max:20'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'country_code'        => ['required', 'string', 'size:2'],
            'street'              => ['required', 'string', 'max:150'],
            'city'                => ['required', 'string', 'max:100'],
            'zip'                 => ['required', 'string', 'max:12'],
        ]);

        $customer = $this->customer($request);

        $customer->update([
            'type'                => $validated['type'],
            'company_name'        => $validated['company_name'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'vat_number'          => $validated['vat_number'] ?? null,
            'phone'               => $validated['phone'] ?? null,
            'country_code'        => mb_strtoupper($validated['country_code']),
        ]);

        $customer->addresses()->updateOrCreate(
            ['type' => 'billing'],
            [
                'street'       => $validated['street'],
                'city'         => $validated['city'],
                'zip'          => $validated['zip'],
                'country_code' => mb_strtoupper($validated['country_code']),
                'is_primary'   => true,
            ],
        );

        return back()->with('status', __('panel.account.billing_saved'));
    }

    public function security(Request $request): View
    {
        return view('panel.account.security', [
            'user' => $request->user(),
        ]);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
