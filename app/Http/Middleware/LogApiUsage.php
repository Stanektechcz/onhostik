<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Api\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LogApiUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $start    = hrtime(true);
        $response = $next($request);
        $elapsed  = (int) round((hrtime(true) - $start) / 1_000_000); // ns → ms

        $user  = $request->user();
        $token = $user?->currentAccessToken();

        // Stateful (SPA-cookie) Sanctum auth yields a TransientToken, which has
        // no id — only a real personal access token has one to log.
        $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : null;

        ApiUsageLog::create([
            'user_id'          => $user?->id,
            'token_id'         => $tokenId,
            'endpoint'         => $request->path(),
            'method'           => $request->method(),
            'status_code'      => $response->getStatusCode(),
            'response_time_ms' => $elapsed,
            'ip_address'       => $request->ip(),
        ]);

        return $response;
    }
}
