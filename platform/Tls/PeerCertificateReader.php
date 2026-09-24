<?php

declare(strict_types=1);

namespace Onhost\Platform\Tls;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * One TLS handshake, straight to the node's own address, asking for the site's name.
 *
 * Two things make this safe to run from the platform. The address is **ours** — the node the service is placed on,
 * never wherever a customer's DNS happens to point — so it is not a request aimed by a customer. And the peer is
 * deliberately **not verified**: an expired or wrong certificate is exactly what this is here to find, and a
 * verifying handshake would refuse to show it.
 *
 * Nothing is sent after the handshake: no request, no headers, no bytes of anyone's site. The socket is opened,
 * the certificate is read and the socket is closed.
 */
final class PeerCertificateReader implements CertificateReader
{
    private const TIMEOUT = 5;

    public function __construct(private readonly Cache $cache) {}

    public function read(string $address, string $serverName): ?array
    {
        $address = trim($address);
        $serverName = rtrim(mb_strtolower(trim($serverName)), '.');
        if ($serverName === '' || ! filter_var($address, FILTER_VALIDATE_IP)) {
            return null;
        }
        $host = str_contains($address, ':') ? '['.$address.']' : $address; // IPv6 needs brackets in a socket address

        return $this->cache->remember('onhost:tls:'.$address.':'.$serverName, now()->addHour(), function () use ($host, $serverName) {
            $context = stream_context_create(['ssl' => [
                'capture_peer_cert' => true, 'SNI_enabled' => true, 'peer_name' => $serverName,
                'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
            ]]);
            $socket = @stream_socket_client('ssl://'.$host.':443', $errno, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
            if ($socket === false) {
                return null; // the node is down, the port is closed, the handshake failed — not a verdict about a certificate
            }
            $params = stream_context_get_params($socket);
            fclose($socket);
            $peer = $params['options']['ssl']['peer_certificate'] ?? null;
            $parsed = $peer === null ? false : @openssl_x509_parse($peer);
            if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
                return null;
            }

            return [
                'expires_at' => (int) $parsed['validTo_time_t'],
                'issued_at' => (int) ($parsed['validFrom_time_t'] ?? 0),
                'issuer' => (string) ($parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? ''),
                'names' => self::names($parsed),
            ];
        });
    }

    /**
     * Every name the certificate covers: the subject alternative names, and the common name for a certificate old
     * enough not to have any.
     *
     * @param  array<string,mixed>  $parsed
     * @return list<string>
     */
    private static function names(array $parsed): array
    {
        $names = [];
        foreach (explode(',', (string) (($parsed['extensions'] ?? [])['subjectAltName'] ?? '')) as $entry) {
            $entry = trim($entry);
            if (str_starts_with($entry, 'DNS:')) {
                $names[] = mb_strtolower(substr($entry, 4));
            }
        }
        $common = mb_strtolower((string) (($parsed['subject'] ?? [])['CN'] ?? ''));
        if ($common !== '' && ! in_array($common, $names, true)) {
            $names[] = $common;
        }

        return array_values(array_filter($names));
    }
}
