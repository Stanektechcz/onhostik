<?php

declare(strict_types=1);

namespace Onhost\Providers\Payments;

use Onhost\Platform\Errors\DomainError;

/**
 * H3 (TASK-0121): the addresses a payment gateway sends the payer back to are the platform's own. An order or invoice payment may
 * carry `return_urls` from its request; passed on as they came, a link could make ONhost's payment page forward a paying customer
 * to any site (an open redirect behind a trusted page). Every gateway adapter (Comgate, GoPay, Stripe) asks this before it calls
 * the gateway. Allowed: an absolute URL without credentials on the portal's or the application's own origin (scheme, host and port
 * as configured), or https on a host of `onhost.payments.return_hosts` (PAYMENT_RETURN_HOSTS). Nothing (null or '') stays nothing —
 * the gateway's own default.
 */
final class ReturnUrls
{
    private const MAX_LENGTH = 2000;

    public static function allowed(mixed $url, string $field): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $parts = is_string($url) && strlen($url) <= self::MAX_LENGTH && preg_match('/[\s\x00-\x1f\\\\]/', $url) !== 1 ? parse_url($url) : false;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        if (! is_array($parts) || ! in_array($scheme, ['https', 'http'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || ! in_array(self::origin($scheme, $host, $parts['port'] ?? null), self::origins(), true)) {
            throw new DomainError('payment_return_url_invalid', 'The return address of a payment must be an address of this portal.', 422, ['field' => $field]);
        }

        return $url;
    }

    /** @return list<string> */
    private static function origins(): array
    {
        $allowed = [];
        foreach ([(string) config('onhost.portal_url'), (string) config('app.url')] as $own) {
            $o = parse_url($own);
            if (is_array($o) && isset($o['scheme'], $o['host'])) {
                $allowed[] = self::origin(strtolower((string) $o['scheme']), strtolower((string) $o['host']), $o['port'] ?? null);
            }
        }
        foreach ((array) config('onhost.payments.return_hosts', []) as $extra) {
            $allowed[] = self::origin('https', strtolower(trim((string) $extra)), null);
        }

        return $allowed;
    }

    private static function origin(string $scheme, string $host, mixed $port): string
    {
        return $scheme.'://'.$host.':'.(int) ($port ?? ($scheme === 'https' ? 443 : 80));
    }
}
