<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Onhost\Domain\Organizations\Models\Organization;
use Throwable;

/**
 * A VAT identification number as the platform compares and checks it (TASK-0031): upper case, without spaces, dots or
 * dashes, with its country prefix. Greece is EL in VIES, so a number typed as GR… is kept as EL…, and `toIsoCountry()`
 * gives GR back for comparing with the organization's country. A number typed without a prefix (a Czech DIČ written as
 * "12345678") takes the organization's country, so the same number is never "changed" by the way it was typed.
 */
final class VatNumber
{
    /** The member states when no tax rule version is active (a fresh install, a test without TaxRuleSeeder). */
    public const EU_MEMBERS = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    /** Digit counts of the numbers whose shape the platform knows exactly; others only get the general 2–12 character rule. */
    private const DIGITS = ['CZ' => [8, 10], 'DE' => [9, 9]];

    private function __construct(public readonly string $value) {}

    public static function of(?string $raw): ?self
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9+*]/', '', (string) $raw));
        if ($clean === '') {
            return null;
        }
        if (str_starts_with($clean, 'GR') && strlen($clean) > 2 && ctype_digit($clean[2])) {
            $clean = 'EL'.substr($clean, 2);
        }

        return new self($clean);
    }

    /** The number the organization gave (VAT ID, else DIČ), prefixed with its country when it was typed without one. */
    public static function forOrganization(Organization $organization): ?self
    {
        $given = trim((string) ($organization->vat_id ?? '')) !== '' ? (string) $organization->vat_id : (string) ($organization->dic ?? '');
        $number = self::of($given);
        if ($number === null || $number->countryCode() !== null) {
            return $number;
        }
        $country = strtoupper((string) ($organization->country ?? ''));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? self::of(($country === 'GR' ? 'EL' : $country).$number->value) : $number;
    }

    /** The two-letter prefix as VIES writes it (EL for Greece), or null when the number starts with a digit. */
    public function countryCode(): ?string
    {
        return preg_match('/^[A-Z]{2}/', $this->value) === 1 ? substr($this->value, 0, 2) : null;
    }

    /** The prefix as an ISO 3166 country (GR for EL), for comparing with an organization's country. */
    public function toIsoCountry(): ?string
    {
        $code = $this->countryCode();

        return $code === 'EL' ? 'GR' : $code;
    }

    /** The number without its prefix — what VIES wants as `vatNumber`. */
    public function number(): string
    {
        return $this->countryCode() === null ? $this->value : substr($this->value, 2);
    }

    /** An EU member-state prefix, 2–12 characters after it, and the digit count where the platform knows it. */
    public function isWellFormed(): bool
    {
        $iso = $this->toIsoCountry();
        if ($iso === null || $this->countryCode() === 'GR' || ! in_array($iso, self::euMembers(), true)) {
            return false;
        }
        $rest = $this->number();
        if (preg_match('/^[0-9A-Z+*]{2,12}$/', $rest) !== 1) {
            return false;
        }
        if (isset(self::DIGITS[$iso])) {
            [$min, $max] = self::DIGITS[$iso];

            return ctype_digit($rest) && strlen($rest) >= $min && strlen($rest) <= $max;
        }

        return true;
    }

    /** Whether the prefix names an EU member state at all (a Swiss CHE…, a British GB… or a Norwegian NO… does not). */
    public function isEuPrefixed(): bool
    {
        $iso = $this->toIsoCountry();

        return $iso !== null && $this->countryCode() !== 'GR' && in_array($iso, self::euMembers(), true);
    }

    /** Enough to recognise the number in a message, not enough to be the number: country + last three characters. */
    public function hint(): string
    {
        return ($this->countryCode() ?? '').'…'.substr($this->value, -3);
    }

    /** The member states of the active tax rule version (ISO codes, GR for Greece). @return list<string> */
    public static function euMembers(): array
    {
        try {
            $members = array_values(array_map('strtoupper', (array) data_get(app(TaxEngine::class)->currentRules()->rules, 'eu_members', [])));
        } catch (Throwable) {
            $members = [];
        }

        return $members !== [] ? $members : self::EU_MEMBERS;
    }

    /** The supplier's country from the active tax rule version (CZ). */
    public static function supplierCountry(): string
    {
        try {
            return strtoupper((string) data_get(app(TaxEngine::class)->currentRules()->rules, 'supplier.country', 'CZ'));
        } catch (Throwable) {
            return 'CZ';
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
