<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AdminAuditService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonateController extends Controller
{
    /** How long an impersonation session may stay open. */
    public const MAX_MINUTES = 30;

    public function start(User $user): RedirectResponse
    {
        $admin = Auth::user();

        if ($user->id === $admin->id) {
            return back()->withErrors(['impersonate' => 'Nelze se přihlásit za sebe.']);
        }

        /* Never impersonate another admin — only customers */
        if ($user->hasRole('admin')) {
            return back()->withErrors(['impersonate' => 'Nelze se přihlásit za jiného admina.']);
        }

        Session::put('_impersonated_by', $admin->id);
        Session::put('_impersonating_as', $user->id);
        // Time-boxed (audit G98): an impersonation session left open is an
        // admin-privileged login sitting on a customer account indefinitely.
        Session::put('_impersonation_expires_at', now()->addMinutes(self::MAX_MINUTES)->timestamp);

        Auth::loginUsingId($user->id);

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['admin_id' => $admin->id, 'admin_email' => $admin->email])
            ->log('admin_impersonated_user');

        app(AdminAuditService::class)->log($admin, 'impersonate_start', $user, [
            'target_email' => $user->email,
        ]);

        return redirect()->route('panel.dashboard')
            ->with('status', "Přihlášen za uživatele {$user->name}. Pro návrat klikněte na banner v sidebaru.");
    }

    public function stop(): RedirectResponse
    {
        $adminId = Session::pull('_impersonated_by');
        Session::forget('_impersonating_as');
        Session::forget('_impersonation_expires_at');

        if (!$adminId) {
            return redirect()->route('panel.dashboard');
        }

        Auth::loginUsingId($adminId);

        $adminUser = Auth::user();
        activity()
            ->causedBy($adminUser)
            ->log('admin_stopped_impersonation');

        if ($adminUser !== null) {
            app(AdminAuditService::class)->log($adminUser, 'impersonate_stop');
        }

        return redirect()->route('admin.customers.index')
            ->with('status', 'Vrácen zpět na admin účet.');
    }
}
