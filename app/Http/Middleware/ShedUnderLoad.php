<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Domain\Provisioning\LoadShedding;
use Symfony\Component\HttpFoundation\Response;

/**
 * Put on routes that can wait — reports, analytics, cross-service overviews (Brain card H139). Under overload they are
 * refused with the reason and `Retry-After`; nothing stale is served in their place. Never put it on an action, a
 * restore, access management or a payment: those are what the refusal protects.
 */
final class ShedUnderLoad
{
    public const RETRY_AFTER = 120;

    public function __construct(private readonly LoadShedding $load) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->load->active()) {
            return $next($request);
        }

        return response()->json([
            'error' => 'load_shedding', 'status' => 503, 'retry_after' => self::RETRY_AFTER, 'degraded' => true,
            'message' => 'Přehledy a statistiky jsou dočasně pozastavené, aby měly přednost operace se službami, obnovy a platby. Zkuste to za chvíli; vaše služby tím nejsou dotčeny.',
            'help' => '/dokumentace/api#load-shedding',
        ], 503, ['Retry-After' => (string) self::RETRY_AFTER, 'Cache-Control' => 'no-store']);
    }
}
