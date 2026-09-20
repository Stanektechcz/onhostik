<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;

/**
 * TLS verification for vendor APIs, shared by every adapter:
 *  - option `tls_ca` pins the vendor's CA or self-signed certificate — either a path on the control plane or the PEM
 *    text itself (pasted in the console; it is written once to storage/app/tls/<instance>.pem);
 *  - option `verify_tls: false` is a development convenience only; production always verifies or pins.
 */
final class TlsOptions
{
    /** @return array<string,mixed> Guzzle options (`verify`) for the instance */
    public static function verify(ProviderInstance $instance, string $provider, string $option = 'tls_ca'): array
    {
        $ca = $instance->option($option) ?? ($option === 'tls_ca' ? null : $instance->option('tls_ca')); // a second certificate (a game panel's daemons) falls back to the instance's
        if (is_string($ca) && trim($ca) !== '') {
            return ['verify' => str_contains($ca, '-----BEGIN CERTIFICATE-----') ? self::pinned($instance, $ca, $option === 'tls_ca' || $instance->option($option) === null ? '' : '-'.$option) : $ca];
        }
        if ($instance->option('verify_tls', true) === false) {
            if (app()->environment('production')) {
                throw new ProviderException($provider, ProviderErrorCode::AUTH, 'TLS verification cannot be disabled in production; pin the certificate with option tls_ca.');
            }

            return ['verify' => false];
        }

        return [];
    }

    private static function pinned(ProviderInstance $instance, string $pem, string $suffix = ''): string
    {
        $dir = storage_path('app/tls');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir.'/'.preg_replace('/[^a-z0-9-]/', '-', strtolower($instance->key.$suffix)).'.pem';
        $normalised = trim($pem)."\n";
        if (! is_file($path) || file_get_contents($path) !== $normalised) {
            file_put_contents($path, $normalised, LOCK_EX);
        }

        return $path;
    }
}
