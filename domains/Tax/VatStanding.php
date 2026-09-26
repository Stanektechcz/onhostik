<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Models\VatValidation;

/**
 * What the platform may rely on about an organization's VAT number at a moment (TASK-0031, D31.2): the one vocabulary
 * {unknown, valid, invalid}, and the only place that builds the customer input of the tax engine. The stored column is
 * evidence, not the answer: `valid` counts only for the number that was checked, while the check is at most
 * `onhost.vies.freshness_days` old, and when the number's country is the organization's country; a staff override counts
 * until it ends.
 *
 * Rows written before the check existed (vat_status_source NULL) are honoured exactly as the money treated them until the
 * operator re-checks them with `onhost:vat:verify --apply` (owner rule: no mass write to existing customers): a stored
 * `valid` stays effective `valid` and is flagged `legacy_unverified`, a stored `payer` stays a VAT payer for self-billing.
 * Any other value outside the vocabulary counts as `unknown`.
 */
final class VatStanding
{
    public const UNKNOWN = 'unknown';

    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const VOCABULARY = [self::UNKNOWN, self::VALID, self::INVALID];

    /** The number the standing is about (VAT ID, else DIČ). */
    public static function subject(Organization $organization): ?VatNumber
    {
        return VatNumber::forOrganization($organization);
    }

    public static function effectiveStatus(Organization $organization, ?CarbonInterface $at = null): string
    {
        return self::standing($organization, $at)['status'];
    }

    /**
     * The effective status and why: staff_override, override_expired, legacy_unverified, legacy_invalid, no_number, unchecked
     * (never checked or checked for another number), fresh, stale, vat_country_mismatch, invalid, stored_unknown.
     *
     * @return array{status:string, reason:string}
     */
    public static function standing(Organization $organization, ?CarbonInterface $at = null): array
    {
        $at = $at === null ? CarbonImmutable::now() : CarbonImmutable::instance($at);
        $stored = self::stored($organization);
        $source = $organization->vat_status_source;
        $until = $organization->vat_override_until;
        if ($source === 'staff' && $until !== null && CarbonImmutable::parse($until)->greaterThan($at)) {
            // an override is about one number of one country (review round 1): a customer who moves to another member state keeps
            // a prefixed number, and a German override must not reverse-charge an Austrian address — the same rule as a VIES answer
            $subject = self::subject($organization);
            if ($stored === self::VALID && ($subject === null || (string) $organization->vat_checked_number !== $subject->value || $subject->toIsoCountry() !== strtoupper((string) $organization->country))) {
                return ['status' => self::UNKNOWN, 'reason' => 'vat_country_mismatch'];
            }

            return ['status' => $stored, 'reason' => 'staff_override'];
        }
        if ($source === 'staff') {
            // an override that ended is not a VIES answer: it must not live on as a "fresh check" of the day it was set (TASK-0031 WP B)
            return ['status' => self::UNKNOWN, 'reason' => 'override_expired'];
        }
        if ($source === null && $stored === self::VALID) {
            return ['status' => self::VALID, 'reason' => 'legacy_unverified']; // today's reverse charge until the operator re-checks it
        }
        if ($source === null && $stored === self::INVALID) {
            return ['status' => self::INVALID, 'reason' => 'legacy_invalid'];
        }
        $subject = self::subject($organization);
        if ($subject === null) {
            return ['status' => self::UNKNOWN, 'reason' => 'no_number'];
        }
        if ((string) $organization->vat_checked_number !== $subject->value) {
            return ['status' => self::UNKNOWN, 'reason' => 'unchecked'];
        }
        if ($stored === self::VALID) {
            if ($subject->toIsoCountry() !== strtoupper((string) $organization->country)) {
                return ['status' => self::UNKNOWN, 'reason' => 'vat_country_mismatch'];
            }
            $checked = $organization->vat_checked_at;
            $fresh = $checked !== null && CarbonImmutable::parse($checked)->greaterThanOrEqualTo($at->subDays(self::freshnessDays()));

            return $fresh ? ['status' => self::VALID, 'reason' => 'fresh'] : ['status' => self::UNKNOWN, 'reason' => 'stale'];
        }

        return $stored === self::INVALID ? ['status' => self::INVALID, 'reason' => 'invalid'] : ['status' => self::UNKNOWN, 'reason' => 'stored_unknown'];
    }

    /**
     * The customer input of TaxEngine::calculate / QuoteService::quote — every caller builds it here, so no caller can pass
     * the stored column (or a vocabulary of its own) as the verdict.
     *
     * `vat_reason` lets the engine flag a reverse charge that rests only on a row from before the check; `vat_name_mismatch` one
     * whose number VIES registers to another trader (review round 1).
     *
     * @return array{country:string, customer_class:string, vat_id:?string, vat_status:string, vat_reason:string, vat_name_mismatch:bool, ip_country:?string}
     */
    public static function taxCustomer(Organization $organization, ?string $ipCountry = null): array
    {
        $standing = self::standing($organization);

        return [
            'country' => strtoupper((string) ($organization->country ?? 'CZ')),
            'customer_class' => (string) ($organization->customer_class ?? 'b2c'),
            'vat_id' => self::subject($organization)?->value,
            'vat_status' => $standing['status'],
            'vat_reason' => $standing['reason'],
            'vat_name_mismatch' => $standing['status'] === self::VALID && self::nameMismatch($organization),
            'ip_country' => $ipCountry,
        ];
    }

    /**
     * The evidence behind a quote, an order or a document: which number, what counted and why it counted.
     *
     * @return array{number:?string, status:string, reason:string, stored_status:string, source:?string, checked_at:?string, consultation_number:?string, override_until:?string, name_mismatch:bool}
     */
    public static function snapshot(Organization $organization): array
    {
        $standing = self::standing($organization);

        return [
            'number' => self::subject($organization)?->value,
            'status' => $standing['status'],
            'reason' => $standing['reason'],
            'stored_status' => (string) ($organization->vat_status ?? self::UNKNOWN),
            'source' => $organization->vat_status_source,
            'checked_at' => $organization->vat_checked_at === null ? null : CarbonImmutable::parse($organization->vat_checked_at)->toIso8601String(),
            'consultation_number' => $organization->vat_consultation_number,
            'override_until' => $organization->vat_override_until === null ? null : CarbonImmutable::parse($organization->vat_override_until)->toIso8601String(),
            'name_mismatch' => $standing['status'] === self::VALID && self::nameMismatch($organization),
        ];
    }

    /**
     * Whether a quote should ask VIES first (D31.3b): a well-formed number of another EU member state whose standing is not
     * known now. A number whose country is not the organization's is not asked about — no answer could make it count.
     */
    public static function needsCheck(Organization $organization): bool
    {
        $subject = self::subject($organization);
        if ($subject === null || ! $subject->isWellFormed()) {
            return false;
        }
        $iso = (string) $subject->toIsoCountry();
        if ($iso === VatNumber::supplierCountry() || $iso !== strtoupper((string) $organization->country)) {
            return false;
        }

        return self::effectiveStatus($organization) === self::UNKNOWN;
    }

    /**
     * A VAT payer for the partner self-billing document (D31.6): a staff override to valid in force, the current number
     * checked valid (registration does not lapse in a month, so no freshness here), or a row written before the check that
     * said `payer` (today's self-billing VAT, until the operator re-checks it).
     */
    public static function isVatPayer(Organization $organization): bool
    {
        $standing = self::standing($organization);
        if ($standing['reason'] === 'staff_override') {
            return $standing['status'] === self::VALID;
        }
        if ($organization->vat_status_source === null && (string) $organization->vat_status === 'payer') {
            return true;
        }
        $subject = self::subject($organization);

        return $organization->vat_status_source !== null && $organization->vat_status_source !== 'staff' && (string) $organization->vat_status === self::VALID
            && $subject !== null && (string) $organization->vat_checked_number === $subject->value;
    }

    /**
     * Whether a document should be looked at by finance (D31.4): the buyer — as the document freezes it — is a business of
     * another EU member state that gave a VAT ID, and a line still carries standard-rated VAT; or its reverse charge rests only
     * on a row from before the check. The same predicate lists past documents for the accountant (`onhost:vat:verify`).
     *
     * @param  array<string,mixed>  $buyer  the buyer snapshot (vat_id, dic, country, customer_class, vat_check)
     * @param  iterable<array<string,mixed>>  $lines  tax_category and tax (minor units)
     */
    public static function invoiceNeedsReview(array $buyer, iterable $lines): bool
    {
        $vatId = trim((string) ($buyer['vat_id'] ?? '')) !== '' ? trim((string) $buyer['vat_id']) : trim((string) ($buyer['dic'] ?? ''));
        $country = strtoupper((string) ($buyer['country'] ?? ''));
        if ($vatId === '' || ($buyer['customer_class'] ?? '') !== 'b2b' || $country === VatNumber::supplierCountry() || ! in_array($country, VatNumber::euMembers(), true)) {
            return false;
        }
        // a reverse charge resting only on a row from before the check, or on a number VIES registers to another trader (review round 1)
        $legacy = data_get($buyer, 'vat_check.reason') === 'legacy_unverified' || data_get($buyer, 'vat_check.name_mismatch') === true;
        foreach ($lines as $line) {
            $category = (string) ($line['tax_category'] ?? 'S');
            if (($category === TaxEngine::CAT_STANDARD && (int) ($line['tax'] ?? 0) > 0) || ($legacy && $category === TaxEngine::CAT_REVERSE_CHARGE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether VIES named somebody else as the holder of the number that counts as valid (review round 1): anybody can type a
     * valid VAT number of a real company and was reverse-charged on it. The verdict stays — VIES said the number is valid, and a
     * trader's register name often differs from the name in our form — but finance looks at it (`vat_review`, reason
     * `name_mismatch`). Only a disclosed name is compared (several member states answer `---`); a check by format or a staff
     * override has no name to compare.
     */
    public static function nameMismatch(Organization $organization): bool
    {
        if ($organization->vat_status_source !== 'vies' || (string) $organization->vat_status !== self::VALID || $organization->vat_validation_id === null) {
            return false;
        }
        $registered = VatValidation::query()->whereKey($organization->vat_validation_id)->value('name');
        if (! is_string($registered) || trim($registered) === '') {
            return false;
        }

        return ! self::sameTrader((string) $organization->name, $registered);
    }

    /**
     * Two spellings of one trader: the same words once case, accents, punctuation, the legal form and joining words are set
     * aside — one name's words all found in the other's, or the same letters run together ("Pixel Art" / "PIXELART").
     */
    public static function sameTrader(string $ours, string $registered): bool
    {
        $a = self::nameWords($ours);
        $b = self::nameWords($registered);
        if ($a === [] || $b === []) {
            return true; // nothing left to compare (a name that is only a legal form): no evidence of another trader
        }

        return array_diff($a, $b) === [] || array_diff($b, $a) === [] || implode('', $a) === implode('', $b);
    }

    /** @return list<string> */
    private static function nameWords(string $name): array
    {
        $text = strtr(mb_strtolower($name), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', '&' => ' ', '+' => ' ']);
        $text = strtr(Str::ascii($text), ['ae' => 'a', 'oe' => 'o', 'ue' => 'u']);
        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $w) => strlen($w) > 1 && ! in_array($w, self::NAME_NOISE, true))));
    }

    /** Legal forms and joining words that say nothing about who the trader is (after the folding in nameWords). */
    private const NAME_NOISE = ['und', 'and', 'et', 'en', 'the', 'der', 'die', 'das', 'co', 'cie', 'gmbh', 'ag', 'kg', 'ohg', 'ug', 'se', 'ev', 'mbh', 'haftungsbeschrankt',
        'sro', 'spol', 'as', 'vos', 'ks', 'zs', 'ltd', 'limited', 'plc', 'llc', 'inc', 'corp', 'sa', 'sas', 'sarl', 'srl', 'spa', 'snc', 'bv', 'nv', 'vof', 'oy', 'oyj', 'ab', 'aps',
        'kft', 'zrt', 'nyrt', 'bt', 'doo', 'dd', 'sp', 'zoo', 'oo', 'eood', 'ood', 'ad', 'ou', 'uab', 'sia', 'teo', 'ehf', 'lda', 'sl', 'slu', 'aktiengesellschaft', 'gesellschaft', 'beschrankter', 'haftung', 'mit'];

    /** Rows written before the check (source NULL) whose stored value still changes money: `valid` or `payer`. */
    public static function isLegacy(Organization $organization): bool
    {
        return $organization->vat_status_source === null && in_array((string) $organization->vat_status, [self::VALID, 'payer'], true);
    }

    public static function freshnessDays(): int
    {
        return max(1, (int) config('onhost.vies.freshness_days', 30));
    }

    /** The stored column read in the one vocabulary; anything else (`payer`, `not_registered`, typos) is `unknown`. */
    private static function stored(Organization $organization): string
    {
        $value = (string) ($organization->vat_status ?? self::UNKNOWN);

        return in_array($value, self::VOCABULARY, true) ? $value : self::UNKNOWN;
    }
}
