<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the active account for a user who belongs to several (sub-accounts).
 * The selection is stored in the session and honoured by ResolveMemberCustomer.
 */
final class CustomerSwitchController extends Controller
{
    public function switch(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        abort_unless(
            $user->memberCustomers()->where('customers.id', $customer->id)->exists(),
            403,
            'K tomuto účtu nemáte přístup.',
        );

        $request->session()->put('active_customer_id', $customer->id);

        return redirect()->route('panel.dashboard')->with('status', 'Účet byl přepnut.');
    }
}
