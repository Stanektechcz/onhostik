<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\GameConfigurator;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Scheduling\CartCapacity;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Limits\LimitRaises;
use Onhost\Domain\Services\Limits\LimitRaiseWaiver;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\PlanChangeService;
use Onhost\Domain\Services\PlanFit;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\SiteNames;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Support\Hostname;

/**
 * Turns cart items into an immutable quote: catalog resolution, configurator
 * options, commitment pricing, promo, tax. The quote locks product/price/plan/tax
 * versions and shows renewal totals next to first-period totals (§49.4, §78).
 *
 * Item shape: {sku?, product_key, plan_key?, qty?, period?, config?: {options?, fqdn?, period_years?, hostname?, ...}}
 */
final class QuoteService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly TaxEngine $tax,
        private readonly PricingRules $rules,
        private readonly LimitRaiseLine $limitRaise,
    ) {}

    /**
     * One line is one service, so a quantity is that many lines. A quantity used to be priced × N (renewals too) while ONE service
     * was delivered; then it was refused — and the cart of the storefront offers a quantity, so an order for two servers could
     * not be placed at all. Every copy is a line of its own: its own price, its own share of a discount, its own service and
     * subscription. The add-on lines of a line are copied with it, each copy attached to its own parent.
     *
     * What cannot be had twice is refused: a domain name, a plan change of one service, a line that names one site.
     *
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function expandQuantities(array $items): array
    {
        $max = max(1, (int) config('onhost.orders.max_quantity', 10));
        $qtyOf = [];
        foreach (array_values($items) as $index => $item) {
            $qtyOf[(string) ($item['line_id'] ?? ('l'.($index + 1)))] = max(1, (int) ($item['qty'] ?? 1));
        }
        $out = [];
        foreach (array_values($items) as $index => $item) {
            $lineId = (string) ($item['line_id'] ?? ('l'.($index + 1)));
            $qty = $qtyOf[$lineId];
            $config = (array) ($item['config'] ?? []);
            $parent = (string) ($config['parent_line_id'] ?? '');
            $item['line_id'] = $lineId; // kept on the line: the positions shift once lines are copied
            $item['qty'] = 1;
            if ($parent !== '' && isset($qtyOf[$parent]) && $qtyOf[$parent] !== $qty) {
                throw new DomainError('addon_quantity_mismatch', 'An add-on is ordered as many times as the service it belongs to.', 422, ['field' => "items.{$index}.qty", 'parent' => $parent]);
            }
            if ($qty === 1) {
                $out[] = $item;

                continue;
            }
            if ($qty > $max) {
                throw new DomainError('quantity_too_large', "At most {$max} of one service fit on one order line.", 422, ['field' => "items.{$index}.qty", 'max' => $max]);
            }
            $named = array_values(array_filter(['fqdn', 'domain', 'hostname'], fn (string $key) => ! empty($config[$key])));
            if (($item['product_key'] ?? '') === 'domain' || ($item['product_key'] ?? '') === LimitRaises::PRODUCT || ! empty($config['upgrade_of']) || $named !== []) {
                throw new DomainError('quantity_unsupported', 'This line names one thing (a domain name, a site, a service that changes its plan); add another line for another one.', 422, ['field' => "items.{$index}.qty"]);
            }
            for ($copy = 1; $copy <= $qty; $copy++) {
                $line = $item;
                if ($copy > 1) {
                    $line['line_id'] = "{$lineId}#{$copy}";
                    if ($parent !== '') {
                        $line['config'] = array_merge($config, ['parent_line_id' => "{$parent}#{$copy}"]);
                    }
                    if (! empty($config['label'])) {
                        $line['config'] = array_merge((array) $line['config'], ['label' => mb_substr((string) $config['label'], 0, 58).' '.$copy]);
                    }
                }
                $out[] = $line;
            }
        }
        $limit = max(1, (int) config('onhost.orders.max_lines', 50));
        if (count($out) > $limit) {
            throw new DomainError('order_too_large', "An order holds at most {$limit} lines.", 422, ['field' => 'items', 'max' => $limit]);
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @param  array{country?:string,customer_class?:string,vat_id?:?string,vat_status?:string,vat_reason?:?string,ip_country?:?string}  $customer
     */
    public function quote(array $items, Currency|string $currency, array $customer, int $commitMonths = 1, ?string $promoCode = null, ?Organization $organization = null, string $locale = 'cs', ?LimitRaiseWaiver $waiver = null): Quote
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        if ($organization !== null) {
            // who the customer is for tax and for the regional price list is a fact of the organization — its country, whether it
            // is a business, what VIES said about its VAT number — never something a request can say. A cart that claimed
            // `b2b` + `vat_status: valid` from another EU country was quoted, ordered and invoiced without VAT. What VIES said counts
            // through VatStanding only (TASK-0031): the number that was checked, at most 30 days ago, or a staff override in force.
            $customer = VatStanding::taxCustomer($organization, $customer['ip_country'] ?? null);
        } else {
            $customer['vat_id'] = null;
            $customer['vat_status'] = 'unknown'; // a guest's quote is an estimate; a VAT number is verified on the account, not claimed in a cart
        }
        $items = $this->expandQuantities($items); // one line is one service: a quantity is that many lines
        $country = strtoupper((string) ($customer['country'] ?? 'CZ'));
        $region = $this->rules->regionFor($country); // regional list price (audit §5j-8)
        $loyaltyPct = $organization !== null ? round(max(0.0, min(30.0, (float) data_get($organization->settings, 'loyalty_discount.pct', 0))), 2) : 0.0; // the streak discount finance granted (audit §5j-3)
        if ($items === []) {
            throw new DomainError('cart_empty', 'The cart is empty.');
        }
        if (! in_array($commitMonths, [1, 12, 24], true)) {
            throw new DomainError('invalid_commitment', 'Commitment must be 1, 12 or 24 months.');
        }
        $promo = $promoCode ? PromoCode::query()->find(strtoupper(trim($promoCode))) : null;
        if ($promoCode && ($promo === null || ! $promo->isUsable())) {
            throw new DomainError('promo_invalid', 'The promo code is not valid.', 422, ['field' => 'promo']);
        }
        // A code for a fixed amount is spent ONCE per order: it was applied to every line, so "100 Kč off" took 100 Kč off each of
        // ten lines. A percent code is a share of every line it applies to. What a line got is what its renewals get when the code
        // says it lasts (`first_period_only = false`) — the box was stored and never read, so a lasting discount renewed at full price.
        $promoLeft = $promo !== null && $promo->kind !== 'percent' ? Money::decimal((string) $promo->value, $currency) : null;
        $promoFor = function (Money $net, string $family) use ($promo, &$promoLeft): Money {
            if ($promo === null) {
                return Money::zero($net->currency);
            }
            $discount = $promo->discountFor($net, $family);
            if ($promoLeft === null) {
                return $discount;
            }
            $discount = $discount->greaterThan($promoLeft) ? $promoLeft : $discount;
            $promoLeft = $promoLeft->subtract($discount);

            return $discount;
        };

        $lines = [];
        $claimed = []; // the web names this one cart asks for: two lines cannot order one name either
        $versions = ['plans' => [], 'prices' => [], 'domain_prices' => []];
        $subtotal = Money::zero($currency);
        $discount = Money::zero($currency);
        $renewalTotal = Money::zero($currency);

        $raised = []; // limit raises of this cart per service and number (LimitRaiseLine)
        $parentProducts = []; // cart lines that may carry add-ons (line id → product key)
        foreach ($items as $index => $item) {
            if (($item['product_key'] ?? '') !== 'domain' && empty($item['config']['parent_line_id'])) {
                $parentProducts[(string) ($item['line_id'] ?? ('l'.($index + 1)))] = (string) ($item['product_key'] ?? '');
            }
        }

        foreach ($items as $index => $item) {
            $qty = 1; // `expandQuantities()` made every line one service: a line is priced, discounted, delivered and renewed once
            $productKey = (string) ($item['product_key'] ?? '');
            $config = (array) ($item['config'] ?? []);
            $lineId = (string) ($item['line_id'] ?? ('l'.($index + 1)));

            if ($productKey === 'domain') {
                $fqdn = Hostname::canonical((string) ($config['fqdn'] ?? ''));
                $tld = Hostname::tld($fqdn);
                $policy = $this->catalog->tld($tld);
                $years = (int) ($config['period_years'] ?? $policy->default_period);
                if ($years < 1 || ! $policy->allowsPeriod($years)) { // registrars sell whole years, at least one
                    throw new DomainError('domain_period_invalid', "Registration period {$years} years is not available for .{$tld}: domains are registered for at least one year; allowed: ".implode(', ', array_map('strval', (array) ($policy->periods ?: [1]))).' year(s).', 422, ['field' => 'period_years', 'periods' => (array) ($policy->periods ?: [1])]);
                }
                $price = $this->catalog->domainPrice($tld, $currency);
                $action = (string) ($config['action'] ?? 'register');
                $unit = $action === 'transfer' ? $price->transfer() : $price->register();
                $net = $unit->multiply($years);
                $renewal = $price->renew()->multiply($years);
                // domains never get a commitment discount: a TLD discount exists only when staff configured one, a promo code only when it names the domain family
                $tldDiscount = $this->rules->domainDiscount($tld, $action);
                $lineDiscount = $tldDiscount['percent'] > 0 ? $net->percent((string) $tldDiscount['percent']) : Money::zero($currency);
                if ($promo !== null) {
                    $lineDiscount = $lineDiscount->add($promoFor($net->subtract($lineDiscount), 'domain'));
                }
                $versions['domain_prices'][] = $price->id;
                $lines[] = [
                    'line_id' => $lineId, 'sku' => "domain-{$tld}-{$action}", 'product_key' => 'domain', 'plan_key' => null, 'plan_version_id' => null, 'price_id' => $price->id,
                    'name' => ($action === 'transfer' ? 'Transfer domény ' : 'Registrace domény ').Hostname::unicode($fqdn)." ({$years} ".($years === 1 ? 'rok' : ($years < 5 ? 'roky' : 'let')).')',
                    'qty' => 1, 'period' => 'year', 'unit_net' => $net, 'discount' => $lineDiscount, 'net' => $net->subtract($lineDiscount), 'renewal_net' => $renewal,
                    'product_class' => 'domain', 'family' => 'domain', 'config' => array_merge($config, ['line_id' => $lineId, 'fqdn' => $fqdn, 'tld' => $tld, 'period_years' => $years, 'action' => $action, 'discount_label' => $tldDiscount['label']]),
                    'entitlements' => null,
                ];
                $subtotal = $subtotal->add($net);
                $discount = $discount->add($lineDiscount);
                $renewalTotal = $renewalTotal->add($renewal);

                continue;
            }
            if ($productKey === LimitRaises::PRODUCT) { // one number of one service, at its product's option price (TASK-0022 limit-raise)
                $line = $this->limitRaise->build($organization, $item, $currency, $lineId, $raised, $waiver, $locale);
                $lines[] = $line;
                $subtotal = $subtotal->add($line['unit_net']);
                $discount = $discount->add($line['discount']);
                $renewalTotal = $renewalTotal->add($line['renewal_net']);

                continue;
            }

            $planKey = (string) ($item['plan_key'] ?? '');
            $requestedPeriod = isset($item['period']) ? (string) $item['period'] : null;
            $period = $requestedPeriod ?? ($commitMonths >= 12 ? 'year' : 'month');
            $resolved = $this->catalog->resolve($productKey, $planKey, $currency, $period);
            $change = ! empty($config['upgrade_of']) ? $this->planChange($organization, (string) $config['upgrade_of'], $resolved['product'], $planKey, $resolved['version'], $requestedPeriod) : null;
            if ($change !== null && $change['period'] !== $period) {
                $period = $change['period'];
                $resolved = $this->catalog->resolve($productKey, $planKey, $currency, $period); // a plan change keeps the subscription's billing period unless the line asks for the other one
            }
            $product = $resolved['product'];
            $price = $resolved['price'];
            if ($product->family === 'game' && $planKey !== (string) config('onhost.game.configurator.plan', 'game-custom')) {
                unset($config['options']); // the game sliders are absolute values of the configurator plan; a fixed plan keeps its own sizes (audit §5v)
            }
            if ($product->family === 'game' && $planKey === (string) config('onhost.game.configurator.plan', 'game-custom') && $change === null) {
                $eggs = (array) data_get($product->meta, 'eggs', []);
                $config['options'] = app(GameConfigurator::class)->clamp((string) ($config['egg'] ?? ($eggs[0] ?? '')), (array) ($config['options'] ?? [])); // never below the game's floors or outside the sliders
            }
            // what is delivered is what was priced: only the options this product sells, each within its range — and the
            // plan's limits and resources are the plan's, never the cart's
            $config['options'] = $this->catalog->normalizeOptions($product, (array) ($config['options'] ?? []));
            if ($config['options'] === []) {
                unset($config['options']);
            }
            unset($config['limits'], $config['entitlements']);
            if (isset($config['region']) && ! Region::query()->where('code', (string) $config['region'])->where('state', 'active')->exists()) {
                throw new DomainError('region_unknown', 'This location is not on offer.', 422, ['field' => 'region']);
            }
            if ($product->family === 'game' && $change === null) { // §5s: an available template, the RAM floor, the customer's inputs
                app(GameTemplates::class)->assertOrderable($product, $config, app(ServiceService::class)->entitlementsFor($resolved['version'], (array) ($config['options'] ?? []), $product)); // the sliders' values count, not the bare base plan (audit §5v)
            }
            $parentLine = (string) ($config['parent_line_id'] ?? '');
            if ($parentLine !== '') { // an add-on line belongs to a service line and must be one of the add-ons that service offers
                $parentKey = $parentProducts[$parentLine] ?? null;
                if ($parentKey === null) {
                    throw new DomainError('addon_parent_missing', 'The add-on refers to a cart line that does not exist.', 422, ['field' => 'items']);
                }
                if (! in_array($productKey, $this->rules->addonProducts($parentKey), true)) {
                    throw new DomainError('addon_not_applicable', "{$productKey} is not offered as an add-on of {$parentKey}.", 422, ['field' => 'items', 'parent' => $parentKey]);
                }
            }
            $base = $price->firstPeriodAmount();
            $renewalBase = $price->renewalAmount();
            if ($region['adjust_pct'] != 0.0) { // a percentage on top of (or off) the catalogue price for the customer's country group; the renewal follows it
                $base = self::adjusted($base, $region['adjust_pct']);
                $renewalBase = self::adjusted($renewalBase, $region['adjust_pct']);
            }
            $periodMonths = $period === 'year' ? 12 : 1;
            $configured = $this->catalog->configure($product, $base, (array) ($config['options'] ?? []), $periodMonths);
            $renewalConfigured = $this->catalog->configure($product, $renewalBase, (array) ($config['options'] ?? []), $periodMonths);
            $periodsBilled = $period === 'year' && $commitMonths === 24 ? 2 : 1;
            $unitNet = $configured['net']->multiply($periodsBilled);
            $lineNet = $unitNet->multiply($qty)->add($price->setup()->multiply($qty));
            // a commitment discount exists only when staff approved one for the family (Nastavení → Slevy a doplňky); nothing is implied by the term itself
            $commitPct = $this->rules->commitDiscountPercent($product->family, $commitMonths);
            $commitDiscount = $commitPct > 0 ? $lineNet->percent((string) $commitPct) : Money::zero($currency);
            $promoDiscount = $change === null ? $promoFor($lineNet->subtract($commitDiscount), $product->family) : Money::zero($currency); // a plan change carries no discounts, so it spends none of the code
            $loyaltyDiscount = $loyaltyPct > 0 ? $lineNet->subtract($commitDiscount)->subtract($promoDiscount)->percent((string) $loyaltyPct) : Money::zero($currency); // after the other discounts, never on a plan change
            $lineDiscount = $commitDiscount->add($promoDiscount)->add($loyaltyDiscount);
            $net = $lineNet->subtract($lineDiscount);
            $renewalNet = $renewalConfigured['net']->multiply($periodsBilled)->multiply($qty);
            if ($promo !== null && ! $promo->first_period_only && $change === null && $promoDiscount->isPositive()) {
                // the code lasts: a percent code takes its share of every renewal, a fixed one what this line got of it
                $lasting = $promo->kind === 'percent' ? $promo->discountFor($renewalNet, $product->family) : ($promoDiscount->greaterThan($renewalNet) ? $renewalNet : $promoDiscount);
                $renewalNet = $renewalNet->subtract($lasting);
                $config = array_merge($config, ['renewal_promo' => $promo->code, 'renewal_promo_minor' => $lasting->minor]);
            }
            if ($change !== null) { // a plan change: the pro-rated difference for the rest of the period, no setup, no discounts; the new price renews from the next period
                $qty = 1;
                $periodsBilled = 1;
                $newNet = $renewalConfigured['net'];
                // a billing-period change starts a new period now: the new period's price minus the unused rest of the current one
                $unusedCredit = $change['period_change'] ? (int) round($change['old_net_minor'] * $change['fraction']) : 0;
                $diff = $change['period_change'] ? max(0, $newNet->minor - $unusedCredit) : (int) round(max(0, $newNet->minor - $change['old_net_minor']) * $change['fraction']);
                $unitNet = Money::minor($diff, $currency);
                $lineNet = $unitNet;
                $lineDiscount = Money::zero($currency);
                $net = $unitNet;
                $renewalNet = $newNet;
                $config = array_merge($config, ['plan_change' => [
                    'from_plan' => $change['from_plan'], 'to_plan' => $planKey, 'fraction' => round($change['fraction'], 4), 'old_net_minor' => $change['old_net_minor'], 'new_net_minor' => $newNet->minor,
                    'period' => $period, 'from_period' => $change['from_period'], 'period_change' => $change['period_change'], 'unused_credit_minor' => $unusedCredit, 'service_id' => $change['service']->id,
                ]]);
            }
            if ($change === null) { // a server that no node can take is refused while it is a cart, not after it was paid (H04)
                $this->assertCapacity($product, app(ServiceService::class)->entitlementsFor($resolved['version'], (array) ($config['options'] ?? []), $product), $config, $organization, (string) $planKey);
                $this->assertNamesFree($product, $config, $organization, $claimed); // and neither is a name somebody else already serves
            }
            $versions['plans'][] = $resolved['version']->id;
            $versions['prices'][] = $price->id;
            $lines[] = [
                'line_id' => $lineId, 'sku' => "{$productKey}-{$planKey}", 'product_key' => $productKey, 'plan_key' => $planKey, 'plan_version_id' => $resolved['version']->id, 'price_id' => $price->id,
                'name' => $product->localizedName($locale).' '.$resolved['plan']->localizedName($locale),
                'qty' => $qty, 'period' => $period, 'unit_net' => $unitNet, 'discount' => $lineDiscount, 'net' => $net, 'renewal_net' => $renewalNet,
                'product_class' => 'esd', 'family' => $product->family, 'config' => array_merge($config, ['line_id' => $lineId, 'price_region' => $region['key'], 'price_region_pct' => $region['adjust_pct'], 'loyalty_pct' => $change === null ? $loyaltyPct : 0.0, 'options_priced' => $configured['lines'], 'periods_billed' => $periodsBilled, 'executor' => $product->executor, 'sla_class' => $resolved['plan']->sla_class]),
                'entitlements' => $resolved['version']->entitlements,
            ];
            if ($change !== null) {
                $last = array_key_last($lines);
                $lines[$last]['sku'] = "{$productKey}-{$planKey}-change";
                $samePlan = $change['from_plan'] === $planKey;
                $lines[$last]['name'] = ($samePlan ? 'Změna období: ' : 'Změna tarifu: ').$lines[$last]['name'].($change['period_change'] ? ($period === 'year' ? ' (ročně)' : ' (měsíčně)') : '');
            }
            $subtotal = $subtotal->add($lineNet);
            $discount = $discount->add($lineDiscount);
            $renewalTotal = $renewalTotal->add($renewalNet);
        }

        $taxInput = [
            'country' => $country,
            'customer_class' => $customer['customer_class'] ?? 'b2c',
            'vat_id' => $customer['vat_id'] ?? null,
            'vat_status' => $customer['vat_status'] ?? VatStanding::UNKNOWN,
            'vat_reason' => $customer['vat_reason'] ?? null,
            'ip_country' => $customer['ip_country'] ?? null,
        ];
        $taxResult = $this->tax->calculate($taxInput, array_map(fn ($l) => ['key' => $l['line_id'], 'net' => $l['net'], 'product_class' => $l['product_class']], $lines), $currency, $organization?->id);
        $taxByLine = collect($taxResult['lines'])->keyBy('key');
        foreach ($lines as &$line) {
            $t = $taxByLine[$line['line_id']];
            $line['tax_rate'] = $t['rate'];
            $line['tax_category'] = $t['category'];
            $line['tax'] = $t['tax'];
            $line['total'] = $t['total'];
        }
        unset($line);
        $taxTotal = $taxResult['tax_total'];
        $total = $subtotal->subtract($discount)->add($taxTotal);

        return Quote::query()->create([
            'organization_id' => $organization?->id,
            'currency' => $currency->value,
            'lines' => array_map(fn ($l) => $this->serializeLine($l), $lines),
            'subtotal_minor' => $subtotal->minor,
            'discount_minor' => $discount->minor,
            'tax_minor' => $taxTotal->minor,
            'total_minor' => $total->minor,
            'renewal_total_minor' => $renewalTotal->minor,
            'tax_calculation_id' => $taxResult['calculation']->id,
            'tax_rule_version_id' => $taxResult['calculation']->rule_version_id,
            'versions' => array_merge($versions, ['promo' => $promo?->code, 'commit_months' => $commitMonths, 'price_region' => $region['key'], 'price_region_pct' => $region['adjust_pct'], 'loyalty_pct' => $loyaltyPct, 'tax_review_required' => $taxResult['review_required'], 'tax_reasons' => $taxResult['reasons'], 'vat_review' => $taxResult['vat_review'], 'vat' => $organization !== null ? VatStanding::snapshot($organization) : null, 'terms' => $this->currentTermsVersions()]),
            'valid_until' => now()->addHours(2),
            'state' => 'open',
        ]);
    }

    /** The regional adjustment on a list price: +pct on top, −pct off. */
    private static function adjusted(Money $amount, float $pct): Money
    {
        $delta = $amount->percent((string) abs($pct));

        return $pct >= 0 ? $amount->add($delta) : $amount->subtract($delta);
    }

    /** @return array<string,string> document key => version */
    public function currentTermsVersions(): array
    {
        $out = [];
        foreach (ConsentDocument::query()->where('effective_from', '<=', now())->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))->get() as $doc) {
            $out[$doc->key] = $doc->version;
        }

        return $out;
    }

    /** @param array<string,mixed> $line */
    /**
     * Validates a plan change and describes the subscription it replaces. A line that names the other billing period
     * (`period` ≠ the subscription's) is a period change, allowed on the plan the service already runs.
     *
     * @return array{service:Service, subscription:Subscription, from_plan:?string, period:string, from_period:string, period_change:bool, fraction:float, old_net_minor:int}
     */
    /**
     * Servers take a dedicated share of a node. When nodes of the kind are registered and none of them can take this one
     * within its sellable share, the product is sold out for now — said here, before an order or a payment exists.
     * Without registered nodes there is nothing to judge by and the order goes on (provisioning is then not automatic).
     *
     * @param  array<string,mixed>  $entitlements
     * @param  array<string,mixed>  $config
     */
    private function assertCapacity(Product $product, array $entitlements, array $config, ?Organization $organization, ?string $planKey = null): void
    {
        if (! config('onhost.provisioning.capacity_gate', true)) {
            return;
        }
        $region = (string) ($config['region'] ?? config('onhost.provisioning.default_region', 'cz1'));
        // servers since H04, web and managed hosting since TASK-0023 (the plan's placement, its panel rule and the disk it sells)
        $fits = app(CartCapacity::class)->fits($product, $entitlements, $region, $planKey, NodeScheduler::sandboxFor($organization?->id));
        if ($fits === false) {
            throw new DomainError('capacity_sold_out', "{$product->key} of this size is sold out in {$region} right now. Nothing was ordered or charged; a smaller plan or another location may be available.", 409, ['field' => 'items', 'product' => $product->key, 'region' => $region]);
        }
    }

    /**
     * A web hosting is the name it serves. Ordering one for a name another customer already serves used to be quoted,
     * paid and then either refused by the panel (an order stuck after the money) or — on a node the neighbour does not
     * share — built, at which point two vhosts claimed one name and the certificate followed whoever asked first.
     * The cart is where that is caught, for the same reason a sold-out server is (H04): before anybody pays.
     *
     * @param  array<string,mixed>  $config
     * @param  list<string>  $claimed  the names earlier lines of this same cart already asked for
     */
    private function assertNamesFree(Product $product, array $config, ?Organization $organization, array &$claimed): void
    {
        if (! in_array($product->family, array_merge(SiteNames::WEB, SiteNames::MAIL), true)) {
            return;
        }
        $mail = in_array($product->family, SiteNames::MAIL, true);
        $families = $mail ? SiteNames::MAIL : SiteNames::WEB;
        $wanted = trim((string) ($config['fqdn'] ?? $config['domain'] ?? ''));
        if ($wanted === '') {
            return; // no name yet: the platform gives the site a preview name of its own when it is created
        }
        $domain = SiteNames::assertFree($wanted, $organization?->id, null, 'domain', $families);
        $names = $mail ? [$domain] : array_merge([$domain], SiteNames::aliases($config['aliases'] ?? [], $domain, $organization?->id));
        foreach ($names as $name) {
            $key = ($mail ? 'mail:' : 'web:').$name; // a website and a mailbox domain of one name are two different orders
            if (in_array($key, $claimed, true)) {
                throw new DomainError('site_name_taken', "Doména {$name} je v objednávce dvakrát; jedno jméno obsluhuje jedna služba.", 409, ['field' => 'items', 'domain' => $name]);
            }
            $claimed[] = $key;
        }
    }

    private function planChange(?Organization $organization, string $serviceId, Product $product, string $planKey, PlanVersion $version, ?string $requestedPeriod = null): array
    {
        if ($organization === null) {
            throw new DomainError('plan_change_requires_account', 'Sign in to change the plan of a service.', 422, ['field' => 'items']);
        }
        $service = Service::query()->where('organization_id', $organization->id)->find($serviceId);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        if ($service->product_key !== $product->key) {
            throw new DomainError('plan_change_product_mismatch', 'The plan belongs to a different product than the service.', 422, ['field' => 'items']);
        }
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('plan_change_state_invalid', 'Only an active service can change its plan.', 409, ['state' => $service->state]);
        }
        $subscription = Subscription::query()->where('service_id', $service->id)->first();
        if ($subscription === null || $subscription->state !== Subscription::ACTIVE) {
            throw new DomainError('plan_change_no_subscription', 'The service has no active subscription to change.', 409);
        }
        $fromPlan = $service->plan_version_id ? PlanVersion::query()->with('plan')->find($service->plan_version_id)?->plan?->key : null;
        $fromPeriod = in_array($subscription->period, ['month', 'year'], true) ? $subscription->period : 'month';
        $periodChange = in_array($requestedPeriod, ['month', 'year'], true) && $requestedPeriod !== $fromPeriod;
        if ($fromPlan === $planKey && ! $periodChange) {
            throw new DomainError('plan_change_same_plan', 'The service already runs this plan.', 422, ['field' => 'items']);
        }
        // …and it has to fit what the service already holds: a plan that sells one site does not take a service with three
        app(PlanFit::class)->assertFits($service, LimitRaises::withActiveDeltas($service, (array) $version->entitlements)); // its paid raises go with it

        return [
            'service' => $service, 'subscription' => $subscription, 'from_plan' => $fromPlan, 'period' => $periodChange ? $requestedPeriod : $fromPeriod, 'from_period' => $fromPeriod, 'period_change' => $periodChange,
            'fraction' => PlanChangeService::prorationFraction($subscription), 'old_net_minor' => (int) $subscription->amount_minor,
        ];
    }

    private function serializeLine(array $line): array
    {
        foreach (['unit_net', 'discount', 'net', 'renewal_net', 'tax', 'total'] as $key) {
            if (isset($line[$key]) && $line[$key] instanceof Money) {
                $line[$key] = $line[$key]->minor;
            }
        }
        if (isset($line['config']['options_priced'])) {
            $line['config']['options_priced'] = array_map(fn ($o) => ['key' => $o['key'], 'label' => $o['label'], 'qty' => $o['qty'], 'unit_net' => $o['unit_net']->minor, 'net' => $o['net']->minor], $line['config']['options_priced']);
        }

        return $line;
    }
}
