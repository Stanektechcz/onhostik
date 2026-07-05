<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Api\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
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

        ApiUsageLog::create([
            'user_id'          => $user?->id,
            'token_id'         => $token?->id,
            'endpoint'         => $request->path(),
            'method'           => $request->method(),
            'status_code'      => $response->getStatusCode(),
            'response_time_ms' => $elapsed,
            'ip_address'       => $request->ip(),
        ]);

        return $response;
    }
}
