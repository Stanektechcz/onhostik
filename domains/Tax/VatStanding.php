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
        $verdict = self::verdict($organization, $at === null ? CarbonImmutable::now() : CarbonImmutable::instance($at), true);

        return ['status' => $verdict['status'], 'reason' => $verdict['reason']];
    }

    /**
     * The acceptance rules for a number, in one place for the customer's standing and the partner's payer status (review
     * round 3: isVatPayer had its own copy without the country rule and the name, and a partner — who controls its own name,
     * country and number — was paid 21 % VAT on a real Czech company's DIČ or on a valid SK number with a CZ address). A
     * staff override counts for its number and country until it ends; a row from before the check keeps what it said; a
     * recorded check counts for the number it was about, of the organization's own country, and — for a tax decision — while
     * it is at most `freshness_days` old. `name_mismatch` says VIES registers that number to another trader; what it means is
     * the caller's decision (a customer keeps the reverse charge under review, a partner is not paid VAT on it).
     *
     * With `$freshness` false (the partner's registration, which does not lapse in a month) a valid check older than the
     * window reads `valid` with reason `registered` instead of `unknown`/`stale`.
     *
     * @return array{status:string, reason:string, name_mismatch:bool}
     */
    private static function verdict(Organization $organization, CarbonImmutable $at, bool $freshness): array
    {
        $verdict = self::rules($organization, $at, $freshness);
        // only a VIES answer about the current number names a trader; an override, a legacy row or a stale check has no name
        $named = $verdict['status'] === self::VALID && in_array($verdict['reason'], ['fresh', 'registered'], true);

        return $verdict + ['name_mismatch' => $named && self::nameMismatch($organization)];
    }

    /** @return array{status:string, reason:string} */
    private static function rules(Organization $organization, CarbonImmutable $at, bool $freshness): array
    {
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
        $override = self::numberOverride($organization, $at);
        if ($override !== null) {
            // the override belongs to the number, not to the row (review round 2): a customer who changes the number and changes it
            // back wipes the row, and the next VIES answer would undo a four-eyes decision taken because VIES says "valid"
            if ($override->status === self::VALID && self::subject($organization)?->toIsoCountry() !== strtoupper((string) $organization->country)) {
                return ['status' => self::UNKNOWN, 'reason' => 'vat_country_mismatch'];
            }

            return ['status' => (string) $override->status, 'reason' => 'staff_override'];
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
            if ($fresh) {
                return ['status' => self::VALID, 'reason' => 'fresh'];
            }

            return $freshness ? ['status' => self::UNKNOWN, 'reason' => 'stale'] : ['status' => self::VALID, 'reason' => 'registered'];
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
        $standing = self::verdict($organization, CarbonImmutable::now(), true);

        return [
            'country' => strtoupper((string) ($organization->country ?? 'CZ')),
            'customer_class' => (string) ($organization->customer_class ?? 'b2c'),
            'vat_id' => self::subject($organization)?->value,
            'vat_status' => $standing['status'],
            'vat_reason' => $standing['reason'],
            'vat_name_mismatch' => $standing['name_mismatch'],
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
        $standing = self::verdict($organization, CarbonImmutable::now(), true);
        // an override the number carries while the row was reset (review round 2) is evidence like one the row carries
        $override = $standing['reason'] === 'staff_override' && $organization->vat_status_source !== 'staff' ? self::numberOverride($organization) : null;

        return [
            'number' => self::subject($organization)?->value,
            'status' => $standing['status'],
            'reason' => $standing['reason'],
            'stored_status' => (string) ($organization->vat_status ?? self::UNKNOWN),
            'source' => $override !== null ? 'staff' : $organization->vat_status_source,
            'checked_at' => $override !== null || $organization->vat_checked_at === null ? null : CarbonImmutable::parse($organization->vat_checked_at)->toIso8601String(),
            'consultation_number' => $override !== null ? null : $organization->vat_consultation_number,
            'override_until' => $override !== null ? ($override->expires_at === null ? null : CarbonImmutable::parse($override->expires_at)->toIso8601String())
                : ($organization->vat_override_until === null ? null : CarbonImmutable::parse($organization->vat_override_until)->toIso8601String()),
            'name_mismatch' => $standing['name_mismatch'],
        ];
    }

    /**
     * A snapshot() as the customer (or the partner) may see it: what the document prints — when the number was checked, the
     * consultation number, and whether it rests on VIES or on evidence staff accepted. The reason, the stored status, the
     * override's end and whether VIES names another trader are finance's (review rounds 2 and 3): the customer's invoice and
     * the partner's self-billing document never tip off somebody using another trader's number.
     *
     * @param  array<string,mixed>  $check
     * @return array{checked_at:mixed, consultation_number:mixed, source:?string}
     */
    public static function customerEvidence(array $check): array
    {
        return ['checked_at' => $check['checked_at'] ?? null, 'consultation_number' => $check['consultation_number'] ?? null,
            'source' => ($check['source'] ?? null) === 'staff' ? 'staff' : (empty($check['checked_at']) ? null : 'vies')];
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

    /** A VAT payer for the partner self-billing document (D31.6) — see payerStanding(). */
    public static function isVatPayer(Organization $organization): bool
    {
        return self::payerStanding($organization)['payer'];
    }

    /**
     * Whether the partner is a VAT payer for its self-billing document (D31.6), and why (not): the same acceptance rules as the
     * customer's standing (verdict()), without the freshness window — a registration does not lapse in a month.
     *
     * - a staff override in force decides (to valid: a payer — the only way a genuine name difference is accepted);
     * - a row written before the check that said `payer` stays a payer until the operator re-checks it (critic, review round 1);
     * - a legacy `valid` row is not proof of registration (`legacy_unverified`, never a payer, as before);
     * - a VIES answer about the current number of the organization's own country is proof, unless VIES registers the number to
     *   another trader (review round 3): ONhost would pay that VAT out in cash and deduct it as input VAT the tax office
     *   denies, where a customer's reverse charge only moves the tax to the buyer — so the name is part of the proof here, and
     *   the reason is `name_mismatch`. A number of another country reads `vat_country_mismatch`.
     * - VAT paid out in cash — a partner of the supplier's own country, billed at the standard rate — needs finance's
     *   confirmation of the supplier on top (closing review, security + billing MEDIUM): the partner edits its own name, and a
     *   name edit neither resets the check nor queues one, so a partner that typed a real company's DIČ and that company's VIES
     *   name — before or after the check — passed the name rule. The confirmation is the newest staff evidence row for the
     *   current number (the CRITICAL override to valid, four eyes) and the organization name it was given for; it outlives the
     *   override's 1–30 days, so the VIES answer carries on from there, and it covers a genuine name difference finance
     *   accepted. None: `identity_unconfirmed`; the name changed since: `identity_changed`; a changed number has none.
     *   A partner of another member state is billed under reverse charge — no VAT is paid out — and needs no confirmation.
     *
     * @return array{payer:bool, reason:string}
     */
    public static function payerStanding(Organization $organization): array
    {
        $verdict = self::verdict($organization, CarbonImmutable::now(), false);
        if ($verdict['reason'] === 'staff_override') {
            return ['payer' => $verdict['status'] === self::VALID, 'reason' => 'staff_override'];
        }
        if ($organization->vat_status_source === null && (string) $organization->vat_status === 'payer') {
            return ['payer' => true, 'reason' => 'legacy_payer'];
        }
        if ($verdict['status'] !== self::VALID || $verdict['reason'] === 'legacy_unverified') {
            return ['payer' => false, 'reason' => $verdict['reason']];
        }
        $identity = self::supplierIdentity($organization);
        if ($identity === 'confirmed') {
            return ['payer' => true, 'reason' => $verdict['reason']];
        }
        if ($verdict['name_mismatch']) {
            return ['payer' => false, 'reason' => 'name_mismatch'];
        }

        return strtoupper((string) $organization->country) === VatNumber::supplierCountry()
            ? ['payer' => false, 'reason' => $identity]
            : ['payer' => true, 'reason' => $verdict['reason']];
    }

    /**
     * Whether finance confirmed the supplier the organization is now (closing review): the newest staff evidence row about the
     * current number said `valid`, and the organization still carries the name that row was set for — the same words once case,
     * accents, punctuation and the legal form are set aside (a renamed legal form is the same supplier; any other rename is not).
     *
     * @return 'confirmed'|'identity_changed'|'identity_unconfirmed'
     */
    private static function supplierIdentity(Organization $organization): string
    {
        $subject = self::subject($organization);
        if ($subject === null || $organization->id === null) {
            return 'identity_unconfirmed';
        }
        $row = VatValidation::query()->where('organization_id', $organization->id)->where('vat_id', $subject->value)->where('source', 'staff')
            ->orderByDesc('checked_at')->orderByDesc('id')->first();
        if ($row === null || (string) $row->status !== self::VALID) {
            return 'identity_unconfirmed';
        }
        $confirmed = self::nameWords((string) $row->name);
        $current = self::nameWords((string) $organization->name);
        sort($confirmed);
        sort($current);

        return $confirmed !== [] && $confirmed === $current ? 'confirmed' : 'identity_changed';
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

    /**
     * A number the partner's self-billing document depends on that no check has spoken about (review round 2): a well-formed
     * EU number — a Czech DIČ included, which the quote never asks about because domestic VAT does not depend on it — that is
     * not a VAT payer's, with no recorded check for it and no staff override. The operator's command checks these; until then
     * the document says the registration is not verified, not that the partner is not a VAT payer.
     */
    public static function payerUnverified(Organization $organization): bool
    {
        $subject = self::subject($organization);
        // an override that ended is not an answer either (closing review): finance's confirmation outlives it, but only a new
        // check of the number carries the partner on — without one it would stay "not verified" for good
        $ended = (string) $organization->vat_checked_number === (string) $subject?->value && self::standing($organization)['reason'] === 'override_expired';
        if ($subject === null || ! $subject->isWellFormed() || ((string) $organization->vat_checked_number === $subject->value && ! $ended)) {
            return false;
        }

        return ! self::isVatPayer($organization) && self::standing($organization)['reason'] !== 'staff_override';
    }

    /**
     * The staff override in force for the organization's current number, when the row no longer carries it (review round 2):
     * the newest piece of evidence about this number is a staff decision that has not ended. A later verdict about the same
     * number — only the operator's check can record one over an override — is newer and wins; so does the row itself whenever
     * it holds a recorded check of the current number.
     */
    private static function numberOverride(Organization $organization, ?CarbonImmutable $at = null): ?VatValidation
    {
        $subject = self::subject($organization);
        if ($subject === null || $organization->id === null || (string) $organization->vat_checked_number === $subject->value) {
            return null;
        }
        $latest = VatValidation::query()->where('organization_id', $organization->id)->where('vat_id', $subject->value)
            ->orderByDesc('checked_at')->orderByDesc('id')->first();
        if ($latest === null || $latest->source !== 'staff' || ! in_array((string) $latest->status, [self::VALID, self::INVALID], true)) {
            return null;
        }

        return $latest->expires_at !== null && CarbonImmutable::parse($latest->expires_at)->greaterThan($at ?? CarbonImmutable::now()) ? $latest : null;
    }

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
