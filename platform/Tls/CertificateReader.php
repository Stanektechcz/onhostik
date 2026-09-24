<?php

declare(strict_types=1);

namespace Onhost\Platform\Tls;

/**
 * The certificate a node really serves for a name — not the one a panel believes it installed.
 *
 * The two differ exactly when it matters: a renewal that failed, a vhost that fell back to the node's default site,
 * a certificate the panel wrote but the web server never reloaded. Only the first one decides what a visitor's
 * browser shows, so only the first one is worth checking.
 */
interface CertificateReader
{
    /**
     * What `<address>:443` answers with when asked for `$serverName` (SNI), or null when nothing answers — the node
     * is down, the port is closed, the handshake failed. Silence is not a verdict: the caller must not read it as a
     * problem with the certificate.
     *
     * @return array{expires_at:int, issued_at:int, issuer:string, names:list<string>}|null
     */
    public function read(string $address, string $serverName): ?array;
}
