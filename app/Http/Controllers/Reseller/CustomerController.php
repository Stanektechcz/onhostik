<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Domains\Customer\Models\Customer;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $profile = $user ? ResellerProfile::where('user_id', $user->id)->where('status', 'active')->first() : null;

        $customers = $profile
            ? Customer::query()
                ->where('reseller_id', $profile->id)
                ->with(['user', 'services'])
                ->withCount(['services', 'orders', 'invoices'])
                ->latest()
                ->paginate(25)
            : collect();

        return view('reseller.customers.index', [
            'profile'   => $profile,
            'customers' => $customers,
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $user    = $request->user();
        $profile = $user ? ResellerProfile::where('user_id', $user->id)->where('status', 'active')->first() : null;

        abort_unless($profile && $customer->reseller_id === $profile->id, 403);

        $customer->load(['user', 'services', 'orders', 'invoices', 'addresses']);

        return view('reseller.customers.show', [
            'profile'  => $profile,
            'customer' => $customer,
        ]);
    }
}
