<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdminIpAllowlist;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the admin IP allowlist when at least one active entry exists.
 *
 * Fail-open: if the allowlist is empty (no active rows), all IPs are allowed.
 * This prevents locking out the first admin before any entry is configured.
 *
 * Bypass: in 'testing' environment the check is always skipped.
 */
class CheckAdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $entries = AdminIpAllowlist::query()
            ->where('is_active', true)
            ->get();

        // Fail-open: no active entries = no restriction
        if ($entries->isEmpty()) {
            return $next($request);
        }

        $ip = $request->ip() ?? '';

        foreach ($entries as $entry) {
            if ($entry->containsIp($ip)) {
                return $next($request);
            }
        }

        abort(403, 'Admin access denied: your IP address is not on the allowlist.');
    }
}
