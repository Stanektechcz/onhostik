<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

use Closure;

/**
 * The blocklist questions go to the resolver the operator named (`ONHOST_DNSBL_RESOLVER`, `host`, `host:port`, `[v6]:port`
 * or a bare IPv6 address — an own recursor with an attributable reverse name); without one, to the first `nameserver` of the
 * host's resolv.conf — over the same UDP client in both cases, because `dns_get_record()` cannot tell NXDOMAIN (not listed)
 * from SERVFAIL or a timeout, and "no answer" must never read as "not listed". A host with neither gets a failed answer.
 *
 * Only a named resolver that is not a well-known public one counts as own (`isOwnResolver()`): the lists that refuse public
 * resolvers are then never asked through anything else (`BlocklistCatalog::needsOwnResolver()`).
 */
final class ResolverDnsblLookup implements DnsblLookup
{
    public const MIN_TIMEOUT_SECONDS = 0.5;

    public const MAX_TIMEOUT_SECONDS = 5.0;

    /** Open resolvers the blocklists refuse or cannot attribute (Google, Cloudflare, Quad9, OpenDNS, AdGuard, CleanBrowsing, Level3). */
    private const PUBLIC_RESOLVERS = [
        '8.8.8.8', '8.8.4.4', '2001:4860:4860::8888', '2001:4860:4860::8844',
        '1.1.1.1', '1.0.0.1', '1.1.1.2', '1.0.0.2', '1.1.1.3', '1.0.0.3', '2606:4700:4700::1111', '2606:4700:4700::1001',
        '9.9.9.9', '149.112.112.112', '9.9.9.10', '9.9.9.11', '2620:fe::fe', '2620:fe::9', '2620:fe::10',
        '208.67.222.222', '208.67.220.220', '2620:119:35::35', '2620:119:53::53',
        '94.140.14.14', '94.140.15.15', '185.228.168.9', '185.228.169.9', '4.2.2.1', '4.2.2.2', '76.76.2.0', '76.76.10.0',
    ];

    private readonly string $resolvConf;

    /**
     * @param  string|null  $resolvConf  where the system resolver is read from (tests point it elsewhere)
     * @param  (Closure(string $host, string $packet, int $port, float $timeout): ?string)|null  $transport  the UDP send (tests replace it)
     */
    public function __construct(?string $resolvConf = null, private readonly ?Closure $transport = null)
    {
        $this->resolvConf = $resolvConf ?? '/etc/resolv.conf';
    }

    public function a(string $name): DnsblAnswer
    {
        $endpoint = $this->endpoint();
        if ($endpoint === null) {
            return DnsblAnswer::failed('no resolver: set ONHOST_DNSBL_RESOLVER');
        }

        return (new UdpDnsClient($endpoint[0], $endpoint[1], self::timeout(), $this->transport))->a($name);
    }

    public function describe(): string
    {
        $resolver = self::named();

        return $resolver === '' ? 'system resolver' : 'resolver '.$resolver;
    }

    public function isOwnResolver(): bool
    {
        $resolver = self::named();
        if ($resolver === '') {
            return false;
        }
        $host = self::split($resolver)[0];
        $packed = @inet_pton($host);
        if ($packed === false) {
            return true; // a host name the operator runs — the public resolvers are known by address
        }

        return ! in_array(inet_ntop($packed), array_map(fn (string $ip) => (string) inet_ntop((string) inet_pton($ip)), self::PUBLIC_RESOLVERS), true);
    }

    /** The per-question timeout, between half a second and five: a run of thousands of questions must end within its budget. */
    public static function timeout(): float
    {
        return min(self::MAX_TIMEOUT_SECONDS, max(self::MIN_TIMEOUT_SECONDS, (float) config('onhost.antispam.dnsbl.timeout_seconds', 2.0)));
    }

    /** @return array{0:string, 1:int}|null */
    private function endpoint(): ?array
    {
        $resolver = self::named();
        if ($resolver !== '') {
            return self::split($resolver);
        }
        $conf = is_readable($this->resolvConf) ? (string) @file_get_contents($this->resolvConf) : '';
        if (preg_match('/^\s*nameserver\s+([0-9a-fA-F:.]+)(?:%\S+)?\s*$/m', $conf, $m) === 1 && @inet_pton($m[1]) !== false) {
            return [$m[1], 53];
        }

        return null;
    }

    private static function named(): string
    {
        return trim((string) config('onhost.antispam.dnsbl.resolver', ''));
    }

    /** host, host:port, [v6]:port or a bare IPv6 address @return array{0:string, 1:int} */
    private static function split(string $resolver): array
    {
        if (preg_match('/^\[(.+)\]:(\d+)$/', $resolver, $m) === 1) {
            [$host, $port] = [$m[1], (int) $m[2]];
        } elseif (substr_count($resolver, ':') === 1) {
            [$host, $port] = explode(':', $resolver);
            $port = (int) $port;
        } else {
            [$host, $port] = [$resolver, 53];
        }

        return [$host, $port > 0 && $port <= 65535 ? $port : 53];
    }
}
