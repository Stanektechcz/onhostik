<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Platform\Http\EgressGuard;
use Throwable;

/**
 * The DS record the parent zone (the registry) publishes for a name. PHP's `dns_get_record()` cannot ask for DS, and the
 * registrar adapters (WEDOS, Subreg) hand back only a keyset handle, never the DS the registry holds, so the question is put
 * to a public validating resolver over DNS-over-HTTPS (JSON form). The resolver is chosen by the platform
 * (`onhost.dns.doh_url`) and still goes through `EgressGuard`: pinned address, no redirects.
 */
class ParentDsResolver
{
    private const TYPE_DS = 43;

    public function __construct(private readonly HttpFactory $http, private readonly EgressGuard $egress) {}

    /**
     * @return list<string>|null the DS records as "keytag algorithm digesttype digest" (an empty list: the parent publishes
     *                           none); null when the question could not be answered — which is not the same as "none"
     */
    public function lookup(string $zone): ?array
    {
        $zone = rtrim(mb_strtolower(trim($zone)), '.');
        $endpoint = (string) config('onhost.dns.doh_url', '');
        if ($zone === '' || $endpoint === '') {
            return null;
        }
        $url = $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query(['name' => $zone, 'type' => 'DS']);
        try {
            $response = $this->http->withOptions($this->egress->options($url))->timeout(8)->acceptJson()->withHeaders(['Accept' => 'application/dns-json'])->get($url);
            if (! $response->successful()) {
                return null;
            }
            $json = (array) $response->json();
        } catch (Throwable) {
            return null;
        }
        if (! isset($json['Status']) || (int) $json['Status'] !== 0) { // SERVFAIL (a broken chain, too), NXDOMAIN, …: no answer to rely on
            return null;
        }
        $ds = [];
        foreach ((array) ($json['Answer'] ?? []) as $answer) {
            if ((int) ($answer['type'] ?? 0) === self::TYPE_DS && is_string($answer['data'] ?? null)) {
                $ds[] = self::normalize($answer['data']);
            }
        }

        return array_values(array_unique(array_filter($ds)));
    }

    /** "12345 13 2 ABCD …" in any case and spacing => "12345 13 2 abcd…"; '' when it is not a DS. */
    public static function normalize(string $ds): string
    {
        if (preg_match('/^\s*(\d{1,5})\s+(\d{1,3})\s+(\d{1,3})\s+([0-9a-fA-F\s]+)$/', $ds, $m) !== 1) {
            return '';
        }

        return $m[1].' '.$m[2].' '.$m[3].' '.mb_strtolower((string) preg_replace('/\s+/', '', $m[4]));
    }
}
