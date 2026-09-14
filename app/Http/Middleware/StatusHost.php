<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The organization's status page on its own host name (audit §5k-3): `status.<their-domain>` is a CNAME to the portal
 * host; once the customer verified it, the root of that host and `/badge.svg` answer with their status page. Every
 * other host passes through untouched, so the portal itself never changes behaviour.
 */
final class StatusHost
{
    public function __construct(private readonly OrganizationStatusService $status) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->getHost());
        $portal = strtolower((string) (parse_url((string) config('onhost.portal_url'), PHP_URL_HOST) ?: ''));
        $path = trim($request->path(), '/');
        if ($host === '' || $host === $portal || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || ! in_array($path, ['', 'badge.svg'], true)) {
            return $next($request);
        }
        $organization = $this->status->forHost($host);
        if ($organization === null) {
            return $next($request);
        }
        $data = $this->status->build($organization);
        if ($path === 'badge.svg') {
            return response($this->status->badgeSvg($data['overall'], $data['title']), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=120']);
        }

        return response()->view('status-page', ['status' => $data]);
    }
}
