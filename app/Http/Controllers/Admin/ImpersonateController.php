<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonateController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $admin = Auth::user();

        if ($user->id === $admin->id) {
            return back()->withErrors(['impersonate' => 'Nelze se přihlásit za sebe.']);
        }

        /* Never impersonate another admin — only customers */
        if ($user->hasRole('admin') && !$admin->id === $user->id) {
            return back()->withErrors(['impersonate' => 'Nelze se přihlásit za jiného admina.']);
        }

        Session::put('_impersonated_by', $admin->id);
        Session::put('_impersonating_as', $user->id);

        Auth::loginUsingId($user->id);

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['admin_id' => $admin->id, 'admin_email' => $admin->email])
            ->log('admin_impersonated_user');

        return redirect()->route('panel.dashboard')
            ->with('status', "Přihlášen za uživatele {$user->name}. Pro návrat klikněte na banner v sidebaru.");
    }

    public function stop(): RedirectResponse
    {
        $adminId = Session::pull('_impersonated_by');
        Session::forget('_impersonating_as');

        if (!$adminId) {
            return redirect()->route('panel.dashboard');
        }

        Auth::loginUsingId($adminId);

        activity()
            ->causedBy(Auth::user())
            ->log('admin_stopped_impersonation');

        return redirect()->route('admin.customers.index')
            ->with('status', 'Vrácen zpět na admin účet.');
    }
}
