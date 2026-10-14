<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

use Onhost\Platform\Dns\RecordResolver;
use Throwable;

/**
 * The blocklist questions go to the resolver the operator named (`ONHOST_DNSBL_RESOLVER`, `host` or `host:port`, an own
 * recursor with an attributable reverse name); without one, to the system resolver — which the blocklists may refuse, and the
 * doctor row "mail blocklist resolver is accepted" says so once they do.
 */
final class ResolverDnsblLookup implements DnsblLookup
{
    public function __construct(private readonly RecordResolver $system) {}

    public function a(string $name): DnsblAnswer
    {
        $client = $this->client();
        if ($client !== null) {
            return $client->a($name);
        }
        try {
            return DnsblAnswer::records(array_values(array_filter(array_map(fn (array $r) => (string) ($r['ip'] ?? ''), $this->system->records($name, 'A')))));
        } catch (Throwable $e) {
            return DnsblAnswer::failed(get_class($e));
        }
    }

    public function describe(): string
    {
        $resolver = trim((string) config('onhost.antispam.dnsbl.resolver', ''));

        return $resolver === '' ? 'system resolver' : 'resolver '.$resolver;
    }

    private function client(): ?UdpDnsClient
    {
        $resolver = trim((string) config('onhost.antispam.dnsbl.resolver', ''));
        if ($resolver === '') {
            return null;
        }
        // host, host:port, [v6]:port or a bare IPv6 address
        if (preg_match('/^\[(.+)\]:(\d+)$/', $resolver, $m) === 1) {
            [$host, $port] = [$m[1], (int) $m[2]];
        } elseif (substr_count($resolver, ':') === 1) {
            [$host, $port] = [explode(':', $resolver)[0], (int) explode(':', $resolver)[1]];
        } else {
            [$host, $port] = [$resolver, 53];
        }

        return new UdpDnsClient($host, $port > 0 ? $port : 53, max(0.5, (float) config('onhost.antispam.dnsbl.timeout_seconds', 2.0)));
    }
}
