<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\DTOs;

use Spatie\LaravelData\Data;

/**
 * Normalized result of a registrar availability check.
 */
final class DomainCheckResult extends Data
{
    public function __construct(
        public readonly string $fqdn,
        public readonly bool $available,
        public readonly ?string $reason = null,
    ) {}

    public static function available(string $fqdn): self
    {
        return new self(fqdn: $fqdn, available: true);
    }

    public static function unavailable(string $fqdn, string $reason): self
    {
        return new self(fqdn: $fqdn, available: false, reason: $reason);
    }
}
