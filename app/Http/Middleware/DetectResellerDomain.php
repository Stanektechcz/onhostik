<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Reseller\Models\ResellerProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class DetectResellerDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        /** @var ResellerProfile|null $profile */
        $profile = Cache::remember(
            "reseller_domain:{$host}",
            300,
            fn () => ResellerProfile::where('custom_domain', $host)
                ->where('status', 'active')
                ->first()
        );

        if ($profile !== null) {
            View::share('resellerBranding', $profile->branding ?? []);
            View::share('resellerProfile', $profile);
        }

        return $next($request);
    }
}
