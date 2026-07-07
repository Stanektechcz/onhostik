<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailSuppressionController extends Controller
{
    public function index(Request $request): View
    {
        $suppressed = \App\Models\User::whereNotNull('email_suppressed_at')
            ->with('customer')
            ->orderByDesc('email_suppressed_at')
            ->paginate(25);

        return view('admin.email-suppression.index', compact('suppressed'));
    }

    public function suppress(Customer $customer): RedirectResponse
    {
        $user = $customer->user;
        abort_if($user === null, 404);

        $user->update(['email_suppressed_at' => now()]);

        return back()->with('status', 'E-mail zákazníka byl potlačen.');
    }

    public function unsuppress(Customer $customer): RedirectResponse
    {
        $user = $customer->user;
        abort_if($user === null, 404);

        $user->update(['email_suppressed_at' => null]);

        return back()->with('status', 'Potlačení e-mailu zrušeno.');
    }
}
