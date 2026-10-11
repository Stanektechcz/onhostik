<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\EgressGuard;
use Throwable;

/**
 * The security headers a site really sends, read once a night from outside.
 *
 * One HTTPS GET to the site's own name (never to anything a visitor or a redirect names): a short timeout, a byte range
 * so no page body is pulled, the response body never read, and the connection pinned to an address `EgressGuard` has
 * checked. A redirect is followed only to the same site (the same name, or its `www.` twin) and re-checked on every hop;
 * a redirect to a foreign host ends the walk and the last response from our own site is what is judged.
 *
 * It only reads and it only advises: the result lands in `tags.security_headers` and `ServiceHealthCheck` turns it
 * into a finding with concrete advice. Nothing is ever changed on the site. A probe that could not be made (refused
 * address, timeout, TLS failure) is recorded as an error and keeps the last good reading — it is never read as "fine".
 */
final class SecurityHeadersWatch
{
    /** What can be missing, in the order advice is given. */
    public const CHECKS = ['hsts', 'csp', 'frame', 'nosniff', 'referrer', 'cookie_secure', 'cookie_httponly', 'cookie_samesite'];

    public const TIMEOUT_SECONDS = 8;

    public const MAX_HOPS = 3;

    private const RANGE = 'bytes=0-2047';

    /** @var array<string,array{0:string,1:string}> check => [cs, en] advice, concrete and never applied by us */
    public const ADVICE = [
        'hsts' => ['hlavička Strict-Transport-Security (např. „max-age=31536000; includeSubDomains“) — prohlížeč pak sám volí HTTPS', 'add a Strict-Transport-Security header (e.g. "max-age=31536000; includeSubDomains") so browsers insist on HTTPS'],
        'csp' => ['hlavička Content-Security-Policy (např. „default-src \'self\'“) — omezí, odkud se smí načítat skripty', 'add a Content-Security-Policy header (e.g. "default-src \'self\'") to limit where scripts may load from'],
        'frame' => ['X-Frame-Options: SAMEORIGIN, nebo v CSP „frame-ancestors \'self\'“ — web pak nejde vložit do cizího rámu (clickjacking)', 'set X-Frame-Options: SAMEORIGIN, or "frame-ancestors \'self\'" in the CSP, so the site cannot be framed by others (clickjacking)'],
        'nosniff' => ['X-Content-Type-Options: nosniff — prohlížeč nebude hádat typ souboru', 'set X-Content-Type-Options: nosniff so browsers do not guess file types'],
        'referrer' => ['Referrer-Policy (např. „strict-origin-when-cross-origin“) — nevyzrazuje celé adresy cizím webům', 'set a Referrer-Policy (e.g. "strict-origin-when-cross-origin") so full URLs are not leaked to other sites'],
        'cookie_secure' => ['cookies bez příznaku Secure — přidejte „Secure“, ať se neposílají po nešifrovaném spojení', 'cookies without the Secure flag — add "Secure" so they are never sent over plain HTTP'],
        'cookie_httponly' => ['cookies bez příznaku HttpOnly — přidejte „HttpOnly“, ať je skripty nepřečtou', 'cookies without the HttpOnly flag — add "HttpOnly" so scripts cannot read them'],
        'cookie_samesite' => ['cookies bez příznaku SameSite — přidejte „SameSite=Lax“ (nebo Strict)', 'cookies without the SameSite attribute — add "SameSite=Lax" (or Strict)'],
    ];

    public function __construct(private readonly HttpFactory $http, private readonly EgressGuard $egress) {}

    /** @return array{checked:int, missing:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'missing' => 0, 'errors' => 0];
        $services = Service::query()
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', SiteNames::WEB)->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $name = mb_strtolower(rtrim(trim((string) ($service->hostname ?? '')), '.'));
            if ($name === '') {
                continue; // no live hostname: nothing to look at
            }
            $tags = (array) ($service->tags ?? []);
            $before = (array) ($tags['security_headers'] ?? []);
            try {
                $result = $this->probe($name);
                $tags['security_headers'] = ['missing' => $result['missing'], 'status' => $result['status'], 'checked_at' => now()->toIso8601String(), 'error' => null];
                $stats['checked']++;
                $stats['missing'] += $result['missing'] === [] ? 0 : 1;
            } catch (Throwable $e) {
                // keep what was last seen: a failed look says nothing about the site, only about this run
                $tags['security_headers'] = array_merge($before, ['error' => $e instanceof DomainError ? 'refused' : 'unreachable', 'error_at' => now()->toIso8601String()]);
                $stats['errors']++;
            }
            $service->forceFill(['tags' => $tags])->save();
        }

        return $stats;
    }

    /**
     * @return array{missing:list<string>, status:int}
     *
     * @throws DomainError when the address is not one the platform may connect to
     * @throws Throwable when the site does not answer
     */
    public function probe(string $host): array
    {
        $url = 'https://'.$host.'/';
        $response = null;
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            $response = $this->http->withOptions($this->egress->options($url) + ['stream' => true])
                ->timeout(self::TIMEOUT_SECONDS)->connectTimeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Range' => self::RANGE])->withUserAgent('ONhost-SecurityHeaders/1.0')->get($url);
            $next = $this->sameSiteRedirect($response, $host, $url);
            if ($next === null) {
                break;
            }
            $url = $next;
        }

        return ['missing' => self::missing($response?->headers() ?? []), 'status' => (int) $response?->status()];
    }

    /** The Location of a redirect that stays on the same site over HTTPS; null when the response is not one or leaves. */
    private function sameSiteRedirect(Response $response, string $host, string $current): ?string
    {
        if ($response->status() < 300 || $response->status() > 399 || ($location = trim($response->header('Location'))) === '') {
            return null;
        }
        $target = parse_url((string) preg_replace('~^//~', 'https://', $location)) ?: [];
        if (! isset($target['host'])) { // relative
            $base = parse_url($current) ?: [];
            $path = str_starts_with($location, '/') ? $location : '/'.$location;

            return 'https://'.($base['host'] ?? $host).$path;
        }
        $to = mb_strtolower((string) $target['host']);
        $same = $to === $host || $to === 'www.'.$host || 'www.'.$to === $host;

        return $same && mb_strtolower((string) ($target['scheme'] ?? 'https')) === 'https' ? $location : null;
    }

    /**
     * Which of the checks the response fails.
     *
     * @param  array<string,mixed>  $headers  as an HTTP client gives them: name => list of values
     * @return list<string>
     */
    public static function missing(array $headers): array
    {
        $h = [];
        foreach ($headers as $name => $values) {
            $h[mb_strtolower((string) $name)] = array_values(array_map('strval', (array) $values));
        }
        $first = fn (string $name): string => trim($h[$name][0] ?? '');
        $csp = $first('content-security-policy');
        $missing = [];
        if ($first('strict-transport-security') === '' || preg_match('/max-age\s*=\s*"?0"?\s*(;|$)/i', $first('strict-transport-security')) === 1) {
            $missing[] = 'hsts';
        }
        if ($csp === '') {
            $missing[] = 'csp';
        }
        if ($first('x-frame-options') === '' && ! str_contains(mb_strtolower($csp), 'frame-ancestors')) {
            $missing[] = 'frame';
        }
        if (! str_contains(mb_strtolower($first('x-content-type-options')), 'nosniff')) {
            $missing[] = 'nosniff';
        }
        if ($first('referrer-policy') === '') {
            $missing[] = 'referrer';
        }
        $flags = ['cookie_secure' => 'secure', 'cookie_httponly' => 'httponly', 'cookie_samesite' => 'samesite'];
        foreach ($flags as $check => $flag) {
            foreach ($h['set-cookie'] ?? [] as $cookie) {
                if (! str_contains(mb_strtolower($cookie), $flag)) {
                    $missing[] = $check;

                    break;
                }
            }
        }

        return $missing;
    }
}
