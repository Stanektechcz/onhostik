<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonateController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an impersonation session once its time box expires (audit G98).
 *
 * Without this, "log in as this customer" stayed active until the admin
 * remembered to click stop — an admin-initiated session sitting on a
 * customer's account for as long as the browser session lived.
 *
 * On expiry the admin is switched back to their own account rather than
 * logged out, so they never lose their place.
 */
class StopExpiredImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminId   = Session::get('_impersonated_by');
        $expiresAt = Session::get('_impersonation_expires_at');

        if ($adminId === null) {
            return $next($request);
        }

        // A session started before this feature existed has no expiry — give
        // it one now rather than letting it run forever.
        if (! is_int($expiresAt)) {
            Session::put('_impersonation_expires_at', now()->addMinutes(ImpersonateController::MAX_MINUTES)->timestamp);

            return $next($request);
        }

        if (now()->timestamp < $expiresAt) {
            return $next($request);
        }

        Session::forget(['_impersonated_by', '_impersonating_as', '_impersonation_expires_at']);

        Auth::loginUsingId((int) $adminId);

        activity()
            ->causedBy(Auth::user())
            ->withProperties(['reason' => 'expired', 'max_minutes' => ImpersonateController::MAX_MINUTES])
            ->log('admin_stopped_impersonation');

        return redirect()
            ->route('admin.customers.index')
            ->with('warning', 'Přihlášení za uživatele vypršelo po ' . ImpersonateController::MAX_MINUTES . ' minutách a bylo ukončeno.');
    }
}
