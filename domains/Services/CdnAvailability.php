<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Platform\Errors\DomainError;

/**
 * K7-5 (M2, owner decision R4 "a plan offers only what its server delivers"): the add-on "CDN a WAF" (`cdn`) is delivered by the
 * platform's Cloudflare connection (Addons `cdn`, ServiceFeatures `cdn`, CloudflareCdnProvider). Without a configured account it
 * delivered nothing while it was sold and billed. It is offered (PricingRules::addonProducts), quoted (QuoteService) and paid
 * (CheckoutService) only while `onhost.cdn.cloudflare.secret_ref` is set; its public page says "Připravujeme" otherwise.
 *
 * Not the owner's "bez CDN" (H-R2, 2026-10-06): that decision is about the platform's own origin, not a customer add-on.
 */
final class CdnAvailability
{
    public const PRODUCT = 'cdn';

    public static function configured(): bool
    {
        return trim((string) config('onhost.cdn.cloudflare.secret_ref', '')) !== '';
    }

    /**
     * A quote lives two hours: the account is asked again when it is paid.
     *
     * @param  iterable<mixed>  $lines  the quote's lines
     */
    public static function assertQuotable(iterable $lines): void
    {
        if (self::configured()) {
            return;
        }
        foreach ($lines as $line) {
            if (is_array($line) && ($line['product_key'] ?? null) === self::PRODUCT) {
                throw self::unavailable();
            }
        }
    }

    public static function unavailable(): DomainError
    {
        return new DomainError('cdn_unavailable', 'CDN teď nemůžeme dodat: účet CDN u nás není nastavený. Nic nebylo objednáno ani účtováno; odeberte CDN z košíku.', 409, ['field' => 'items']);
    }
}
