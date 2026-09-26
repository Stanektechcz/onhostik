<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Onhost\Domain\Tax\Models\TaxCalculation;
use Onhost\Domain\Tax\Models\TaxRuleVersion;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * Versioned VAT engine (blueprint §64.4, §23.8). Inputs: supplier legal entity,
 * customer B2B/B2C, country evidence, VAT ID validation, product classification,
 * supply date. Output per line: rate, UNCL5305 category, legal note. Nothing is
 * `if country == CZ then 21` — every decision references the rule version.
 *
 * Rule set shape (see database/seeders/TaxRuleSeeder):
 *   supplier: {country: CZ, vat_payer: true}
 *   standard_rates: {CZ: 21, SK: 23, …}
 *   eu_members: [...]
 *   oss: {registered: true, from: "2026-01-01"}   # B2C cross-border ESD taxed at destination
 *   product_classes: {esd: {}, domain: {}, hardware: {}}
 *   evidence: {require_two_pieces: true, strict: false}
 */
final class TaxEngine
{
    public const CAT_STANDARD = 'S';

    public const CAT_ZERO = 'Z';

    public const CAT_EXEMPT = 'E';

    public const CAT_REVERSE_CHARGE = 'AE';

    public const CAT_OUT_OF_SCOPE = 'O';

    public function currentRules(?\DateTimeInterface $at = null): TaxRuleVersion
    {
        $at ??= now();
        $version = TaxRuleVersion::query()
            ->where('state', 'active')
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version')
            ->first();
        if ($version === null) {
            throw new DomainError('tax_rules_missing', 'No active tax rule version for the supply date.', 500);
        }

        return $version;
    }

    /**
     * `vat_status` is the standing VatStanding decided (unknown | valid | invalid), never the stored column; `vat_reason` says why
     * (VatStanding::standing) — a reverse charge that rests only on a row from before the VIES check is flagged for review.
     *
     * @param  array{country:string, customer_class:string, vat_id?:?string, vat_status?:string, vat_reason?:?string, vat_name_mismatch?:bool, ip_country?:?string, product_class?:string, supply_date?:?string}  $customer
     * @param  list<array{key:string, net:Money, product_class?:string}>  $lines
     * @return array{calculation:TaxCalculation, lines:list<array{key:string, net:Money, rate:string, category:string, tax:Money, total:Money, note:?string}>, tax_total:Money, review_required:bool, vat_review:bool, reasons:list<string>}
     */
    public function calculate(array $customer, array $lines, Currency|string $currency, ?string $organizationId = null): array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $rules = $this->currentRules(isset($customer['supply_date']) ? new \DateTimeImmutable($customer['supply_date']) : null);
        $r = $rules->rules;
        $supplierCountry = strtoupper((string) data_get($r, 'supplier.country', 'CZ'));
        $country = strtoupper((string) ($customer['country'] ?? $supplierCountry));
        $class = ($customer['customer_class'] ?? 'b2c') === 'b2b' ? 'b2b' : 'b2c';
        $vatStatus = (string) ($customer['vat_status'] ?? 'unknown');
        $eu = array_map('strtoupper', (array) data_get($r, 'eu_members', []));
        $rates = (array) data_get($r, 'standard_rates', []);
        $ossRegistered = (bool) data_get($r, 'oss.registered', false);
        $reasons = [];
        $review = false;
        $vatReview = false; // TASK-0031 (D31.4): a VAT ID was given and the decision could not rely on it — finance looks at it

        if (! data_get($r, 'supplier.vat_payer', true)) {
            $decision = ['rate' => '0', 'category' => self::CAT_EXEMPT, 'note' => 'Supplier is not a VAT payer'];
        } elseif ($country === $supplierCountry) {
            $decision = ['rate' => (string) ($rates[$country] ?? 0), 'category' => self::CAT_STANDARD, 'note' => null];
            $reasons[] = "domestic supply {$country}";
        } elseif (in_array($country, $eu, true)) {
            if ($class === 'b2b' && $vatStatus === 'valid') {
                $decision = ['rate' => '0', 'category' => self::CAT_REVERSE_CHARGE, 'note' => 'Reverse charge — Article 196 of Council Directive 2006/112/EC; VAT to be accounted for by the recipient.'];
                $reasons[] = 'intra-EU B2B with validated VAT ID';
                if (($customer['vat_reason'] ?? null) === 'legacy_unverified') {
                    $vatReview = $review = true; // today's money for a row written before the check, until onhost:vat:verify --apply
                    $reasons[] = 'VAT ID valid only by a record from before the VIES check — review';
                }
                if ((bool) ($customer['vat_name_mismatch'] ?? false)) {
                    $vatReview = $review = true; // the verdict stands, finance looks at who holds the number (TASK-0031 review round 1)
                    $reasons[] = 'VAT ID valid in VIES but registered to another trader name (name_mismatch) — review';
                }
            } else {
                if ($class === 'b2b' && $vatStatus !== 'valid') {
                    $reasons[] = 'B2B without validated VAT ID treated as B2C';
                    $review = $review || $vatStatus === 'invalid';
                }
                if (trim((string) ($customer['vat_id'] ?? '')) !== '' && $vatStatus !== 'valid') {
                    $vatReview = $review = true;
                    $reasons[] = "VAT ID given but not verified in VIES (status {$vatStatus}) — destination VAT, review";
                }
                if ($ossRegistered) {
                    $decision = ['rate' => (string) ($rates[$country] ?? $rates[$supplierCountry] ?? 0), 'category' => self::CAT_STANDARD, 'note' => "VAT of the Member State of consumption ({$country}) — OSS"];
                    $reasons[] = "OSS destination rate {$country}";
                } else {
                    $decision = ['rate' => (string) ($rates[$supplierCountry] ?? 0), 'category' => self::CAT_STANDARD, 'note' => 'Origin VAT (below the EU-wide B2C threshold, no OSS registration)'];
                    $reasons[] = 'origin rate (no OSS)';
                }
            }
        } else {
            $decision = ['rate' => '0', 'category' => self::CAT_OUT_OF_SCOPE, 'note' => 'Place of supply outside the EU — not subject to Czech VAT (§ 9 Act No. 235/2004 Coll.).'];
            $reasons[] = 'non-EU customer';
        }

        $ipCountry = isset($customer['ip_country']) ? strtoupper((string) $customer['ip_country']) : null;
        if (data_get($r, 'evidence.require_two_pieces', true) && $ipCountry !== null && $ipCountry !== $country && $class === 'b2c' && in_array($country, $eu, true)) {
            $reasons[] = "country evidence conflict: billing {$country} vs ip {$ipCountry}";
            $review = true;
            if (data_get($r, 'evidence.strict', false)) {
                throw new DomainError('tax_evidence_conflict', 'Country evidence is inconsistent; finance review required before checkout.', 409, ['billing_country' => $country, 'ip_country' => $ipCountry]);
            }
        }

        $outLines = [];
        $taxTotal = Money::zero($currency);
        foreach ($lines as $line) {
            $net = $line['net'];
            $productClass = $line['product_class'] ?? 'esd';
            $override = data_get($r, "product_classes.{$productClass}.override");
            $rate = is_array($override) && isset($override['rate']) ? (string) $override['rate'] : $decision['rate'];
            $category = is_array($override) && isset($override['category']) ? (string) $override['category'] : $decision['category'];
            $tax = $net->percent($rate);
            $taxTotal = $taxTotal->add($tax);
            $outLines[] = ['key' => $line['key'], 'net' => $net, 'rate' => $rate, 'category' => $category, 'tax' => $tax, 'total' => $net->add($tax), 'note' => $decision['note']];
        }

        $calculation = TaxCalculation::query()->create([
            'rule_version_id' => $rules->id,
            'organization_id' => $organizationId,
            'inputs' => array_merge($customer, ['currency' => $currency->value, 'lines' => array_map(fn ($l) => ['key' => $l['key'], 'net' => $l['net']->minor, 'product_class' => $l['product_class'] ?? 'esd'], $lines)]),
            'result' => ['decision' => $decision, 'reasons' => $reasons, 'review_required' => $review, 'vat_review' => $vatReview, 'lines' => array_map(fn ($l) => ['key' => $l['key'], 'rate' => $l['rate'], 'category' => $l['category'], 'tax' => $l['tax']->minor], $outLines)],
            'total_tax_minor' => $taxTotal->minor,
            'currency' => $currency->value,
        ]);

        return ['calculation' => $calculation, 'lines' => $outLines, 'tax_total' => $taxTotal, 'review_required' => $review, 'vat_review' => $vatReview, 'reasons' => $reasons];
    }
}
