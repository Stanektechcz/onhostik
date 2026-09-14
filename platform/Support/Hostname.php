<?php

declare(strict_types=1);

namespace Onhost\Platform\Support;

use InvalidArgumentException;

/** Canonical hostname / domain handling (IDNA/UTS-46 via ext-intl, lower-case, no trailing dot). */
final class Hostname
{
    public static function canonical(string $name): string
    {
        $trimmed = rtrim(mb_strtolower(trim($name)), '.');
        if ($trimmed === '') {
            throw new InvalidArgumentException('Empty hostname');
        }
        $ascii = idn_to_ascii($trimmed, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false) {
            throw new InvalidArgumentException("Invalid hostname: {$name}");
        }
        if (strlen($ascii) > 253 || ! preg_match('/^(?=.{1,253}$)(?:(?!-)[a-z0-9-]{1,63}(?<!-)\.)+[a-z0-9-]{2,63}$/', $ascii)) {
            throw new InvalidArgumentException("Invalid hostname: {$name}");
        }

        return $ascii;
    }

    public static function unicode(string $ascii): string
    {
        $u = idn_to_utf8($ascii, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);

        return $u === false ? $ascii : $u;
    }

    public static function tld(string $fqdn): string
    {
        $parts = explode('.', self::canonical($fqdn));

        return end($parts);
    }

    /** Second-level registrable name for the TLDs ONhost sells (cz, sk, eu, com, net, org, io, dev, gg, …). */
    public static function isRegistrable(string $fqdn): bool
    {
        return count(explode('.', self::canonical($fqdn))) === 2;
    }

    public static function isValidLabel(string $label): bool
    {
        return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/i', $label) === 1 || $label === '@' || $label === '*';
    }
}
