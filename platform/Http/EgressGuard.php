<?php

declare(strict_types=1);

namespace Onhost\Platform\Http;

use Onhost\Platform\Errors\DomainError;

/**
 * Where the platform may connect when a CUSTOMER names the destination (an uptime check, a webhook, an import URL).
 * The control plane lives in the management network next to aaPanel, ISPConfig, Proxmox and the game panel, so such a
 * request is a way into that network unless the destination is checked: an uptime monitor on
 * `http://10.0.0.5:8888/…` probed the panel every minute and reported status, timing and whether a keyword was in the
 * answer — a byte oracle over the management plane.
 *
 * The rule: http(s) only, no credentials in the URL, and EVERY address the name resolves to must be a public one
 * (no loopback, private, link-local, CGNAT, multicast, documentation or operator-listed ranges). The request is then
 * pinned to the address that was checked — a name that answers differently a moment later (DNS rebinding) connects
 * nowhere else — and redirects are not followed, because a redirect is a second, unchecked destination.
 */
final class EgressGuard
{
    /** Not covered by PHP's private/reserved flags: CGNAT, benchmarking, IETF protocol assignments, 6to4 relay, NAT64, Teredo. */
    private const EXTRA_DENY = ['100.64.0.0/10', '198.18.0.0/15', '192.0.0.0/24', '192.88.99.0/24', '64:ff9b::/96', '2001::/32', '2001:db8::/32', '2002::/16'];

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @return array{scheme:string, host:string, port:int, ip:string}
     *
     * @throws DomainError destination_not_allowed (422)
     */
    public function check(string $url): array
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw $this->refused('only http(s) addresses without credentials are accepted');
        }
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        // names that mean "here" or "inside" whatever a resolver says: localhost, a bare label (search domains), private-use suffixes
        if (! $isIp && (! str_contains($host, '.') || preg_match('/(^|\.)(localhost|local|internal|intranet|lan|home\.arpa|corp|mgmt)$/', $host) === 1)) {
            throw $this->refused("{$host} is a local name");
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $addresses = $isIp ? [$host] : $this->resolver->resolve($host);
        if ($addresses === []) {
            throw $this->refused("{$host} does not resolve");
        }
        foreach ($addresses as $ip) {
            if (! $this->isPublic($ip)) {
                throw $this->refused("{$host} points into a private or reserved network");
            }
        }

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    /**
     * Options for the HTTP client: connect to the checked address only, follow nothing.
     *
     * @return array<string,mixed>
     */
    public function options(string $url): array
    {
        $target = $this->check($url);
        $options = ['allow_redirects' => false];
        if (filter_var($target['host'], FILTER_VALIDATE_IP) === false) {
            $ip = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]];
        }

        return $options;
    }

    public function isPublic(string $ip): bool
    {
        foreach (array_map('strval', (array) config('onhost.egress.allow_cidrs', [])) as $cidr) { // a lab whose nodes live on private addresses says so explicitly
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        foreach (array_merge(self::EXTRA_DENY, array_map('strval', (array) config('onhost.egress.deny_cidrs', []))) as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return false;
            }
        }
        if (str_starts_with(strtolower($ip), 'ff') && str_contains($ip, ':')) { // IPv6 multicast
            return false;
        }

        return ! (str_contains($ip, '.') && ! str_contains($ip, ':') && (int) explode('.', $ip)[0] >= 224); // IPv4 multicast and above
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $a = @inet_pton($ip);
        $b = @inet_pton((string) $net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bits = $bits === null ? strlen($a) * 8 : max(0, min(strlen($a) * 8, (int) $bits));
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    private function refused(string $why): DomainError
    {
        return new DomainError('destination_not_allowed', "This address cannot be used: {$why}.", 422, ['field' => 'url']);
    }
}
