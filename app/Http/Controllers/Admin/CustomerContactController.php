<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerContact;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerContactController extends Controller
{
    public function index(Customer $customer): View
    {
        $contacts = $customer->contacts()->orderByDesc('is_primary')->orderBy('name')->get();

        return view('admin.customer-contacts.index', compact('customer', 'contacts'));
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:120'],
            'email'                  => ['required', 'email', 'max:150'],
            'phone'                  => ['nullable', 'string', 'max:30'],
            'role'                   => ['required', 'in:' . implode(',', array_keys(CustomerContact::ROLES))],
            'receives_invoices'      => ['boolean'],
            'receives_notifications' => ['boolean'],
            'is_primary'             => ['boolean'],
            'note'                   => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['is_primary'])) {
            $customer->contacts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $customer->contacts()->create($data);

        return redirect()->route('admin.customer-contacts.index', $customer)
            ->with('success', 'Kontakt byl přidán.');
    }

    public function update(Request $request, Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:120'],
            'email'                  => ['required', 'email', 'max:150'],
            'phone'                  => ['nullable', 'string', 'max:30'],
            'role'                   => ['required', 'in:' . implode(',', array_keys(CustomerContact::ROLES))],
            'receives_invoices'      => ['boolean'],
            'receives_notifications' => ['boolean'],
            'is_primary'             => ['boolean'],
            'note'                   => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['is_primary'])) {
            $customer->contacts()->where('is_primary', true)->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($data);

        return redirect()->route('admin.customer-contacts.index', $customer)
            ->with('success', 'Kontakt byl aktualizován.');
    }

    public function destroy(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()->route('admin.customer-contacts.index', $customer)
            ->with('success', 'Kontakt byl smazán.');
    }

    /**
     * Returns all contacts that should receive invoices for this customer.
     * Used by invoice email dispatchers.
     *
     * @return array<string>
     */
    public static function invoiceEmails(Customer $customer): array
    {
        $extras = $customer->contacts()
            ->where('receives_invoices', true)
            ->pluck('email')
            ->toArray();

        $primary = [$customer->email ?? $customer->user?->email];

        return array_unique(array_filter(array_merge($primary, $extras)));
    }
}
