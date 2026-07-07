<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Models\UserLoginHistory;
use Illuminate\Contracts\View\View;

class CustomerLoginHistoryController extends Controller
{
    public function show(Customer $customer): View
    {
        $user = $customer->user;

        $history = $user === null
            ? collect()
            : UserLoginHistory::query()
                ->where('user_id', $user->id)
                ->latest('created_at')
                ->paginate(30);

        return view('admin.customer-login-history', compact('customer', 'history'));
    }
}
