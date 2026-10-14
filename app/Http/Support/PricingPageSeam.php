<?php

declare(strict_types=1);

namespace App\Http\Support;

use Onhost\Platform\Money\Money;

/**
 * K7-2 (L2, L-03): the pricing page `#/ceny` sells the catalogue's web hosting plans (`ONHOST_DATA.plans`), but the rest of the
 * page came from the prototype — a static VPS comparison table in config (vCPU / RAM / NVMe, Anti-DDoS 100 Gbps–1,2 Tbps, SLA
 * 99,99 %, support "10 min / telefon"), an FAQ promising a migration "bez výpadku", 1,2 Tbps of filtering and five EU locations, a
 * "free trial" button, and a yearly switch that said "2 měsíce zdarma" and divided every price by 12/10 in the browser whatever the
 * catalogue said (owner rule: no implicit discounts). The card button put "VPS <plan>" in the cart.
 *
 * Here: the table is built from the plans the cards show; the yearly price is the catalogue's yearly price and the "months free"
 * line is derived from it (or absent); the FAQ says only what the documents in force say (SLA 2026-09: 99.9 % Standard, 99.95 %
 * Business; VOP: 14 days to withdraw; the location stated with the plan); the cart line names the real product. The prototype stays
 * byte-identical; demo mode keeps its copy.
 */
final class PricingPageSeam
{
    /** The pricing page's comparison table has the feature column and three plan columns. */
    private const PLAN_COLUMNS = 3;

    /** SLA 2026-09 (resources/legal/sla.md, čl. 2): the contractual figure per class; other classes are not in the document in force. */
    private const SLA_IN_FORCE = ['business' => ['cs' => 'SLA 99,95 %', 'en' => 'SLA 99.95%']];

    private const MIGRATION_CS = 'S přenosem webů, databází i e-mailů pomůžeme; termín a případnou odstávku domluvíme předem.';

    private const MIGRATION_EN = 'We help move sites, databases and mailboxes; the timing and any downtime are agreed in advance.';

    /**
     * `[label, plan 1, plan 2, plan 3]` rows of the pricing page table from the plans of the product the cards show (catalogue order).
     *
     * @param  array<string, mixed>|null  $product  one product of `CatalogService::publicCatalog()`
     * @return list<list<string>>
     */
    public static function compare(?array $product, string $locale): array
    {
        if ($product === null) {
            return [];
        }
        $plans = array_slice(array_map(
            fn (array $p) => ['name' => (string) $p['name'], 'entitlements' => (array) ($p['entitlements'] ?? [])],
            array_values((array) ($product['plans'] ?? [])),
        ), 0, self::PLAN_COLUMNS);
        if ($plans === []) {
            return [];
        }

        return array_map(
            fn (array $row) => array_pad($row, self::PLAN_COLUMNS + 1, '—'),
            CatalogPresentation::compareRows($plans, (string) ($product['family'] ?? 'web'), $locale),
        );
    }

    /**
     * The line next to the yearly switch: "N months free" only when every plan's catalogue year costs the same whole number of
     * months less than twelve, "cheaper by the price list" when the year is cheaper by different amounts, nothing otherwise.
     *
     * @param  list<array<string, mixed>>  $plans  plans of `CatalogService::publicCatalog()`
     */
    public static function yearlySave(array $plans, string $locale): string
    {
        $cs = $locale !== 'en';
        $saved = [];
        foreach ($plans as $plan) {
            $month = self::amount(data_get($plan, 'price.month'));
            $year = self::amount(data_get($plan, 'price_year.year'));
            if ($month <= 0.0 || $year <= 0.0) {
                return ''; // a plan without a yearly price: the switch promises nothing
            }
            $saved[] = round(12 - $year / $month, 2);
        }
        if ($saved === [] || min($saved) <= 0.0) {
            return '';
        }
        $first = $saved[0];
        $uniform = count(array_unique(array_map('strval', $saved))) === 1 && abs($first - round($first)) < 0.01;
        if (! $uniform) {
            return $cs ? 'Ročně levněji podle ceníku' : 'Yearly is cheaper per the price list';
        }
        $n = (int) round($first);

        return $cs ? 'Ročně = '.$n.' '.self::monthsCs($n).' zdarma' : 'Yearly = '.$n.' '.($n === 1 ? 'month' : 'months').' free';
    }

    /** The plan badge names an SLA figure only when the SLA in force contains it (no "SLA 99,99 %"). */
    public static function slaTag(?string $slaClass, string $locale): string
    {
        return self::SLA_IN_FORCE[(string) $slaClass][$locale === 'en' ? 'en' : 'cs'] ?? '';
    }

    /** @return array<string, string> prototype needle => served replacement (each lands exactly once) */
    public static function swaps(): array
    {
        $save = "((window.ONHOST_DATA && window.ONHOST_DATA.yearlySave) ? window.ONHOST_DATA.yearlySave(%s) : '')";

        return [
            "save: 'Ročně = 2 měsíce zdarma', order: 'Vyzkoušet zdarma →'," => 'save: '.sprintf($save, 'true').", order: 'Objednat →',",
            "save: 'Yearly = 2 months free', order: 'Start free trial →'," => 'save: '.sprintf($save, 'false').", order: 'Order →',",
            // the card: the catalogue's yearly price (never monthly × 10/12), the cart line named as the catalogue names it
            "price: this.czk(this.yr(p.price)), per, wrapStyle: planStyle(p.hi, i === 2), ctaStyle: ctaStyle(p.hi), go: pick('VPS ' + c[0], p.price) };" => "price: this.czk(s.period === 'year' && p.priceYear ? p.priceYear / 12 : p.price), per, wrapStyle: planStyle(p.hi, i === 2), ctaStyle: ctaStyle(p.hi), go: pick(p.label || ('VPS ' + c[0]), p.price) };",
            // every other yearly figure of the page (game cards, game configurator): no discount computed in the browser; the cart
            // prices the year from the catalogue (onhost-cart.api.js periodPrice)
            "yr(n) { return this.state.period === 'year' ? n * 10 / 12 : n; }" => 'yr(n) { return n; }',
            "cfgNote: s.period === 'year' ? (cs ? 'Ročně: 2 měsíce zdarma' : 'Yearly: 2 months free')" => "cfgNote: s.period === 'year' ? (cs ? 'Roční cenu spočítá košík podle ceníku' : 'The cart prices the year from the price list')",
            // FAQ: only what the documents in force say
            "['Zvládnete migraci z jiného hostingu?', 'Ano, a zdarma. Pošleš přístupy, my přeneseme weby, databáze i e-maily většinou do 24 hodin a bez výpadku.']," => "['Pomůžete s přechodem z jiného hostingu?', '".self::MIGRATION_CS."'],",
            "['Můžu měnit parametry serveru později?', 'Kdykoliv. CPU, RAM i disk posuneš v panelu, účtujeme po hodinách a měsíční cena je strop.']," => "['Můžu tarif později změnit?', 'Ano, změnu tarifu objednáte v klientském panelu a její cenu uvidíte v objednávce před potvrzením.'],",
            "['Jak funguje deploy z Gitu?', 'Připojíš repozitář (náš Git nebo GitHub), my detekujeme runtime a při každém pushi postavíme a nasadíme novou verzi. Rollback je jeden příkaz.']," => "['Jak mohu zaplatit?', 'Bankovním převodem podle zálohové faktury (QR platba). Platbu kartou pokladna nabízí, jen když je platební brána v provozu.'],",
            "['Máte root přístup a vlastní Dockerfile?', 'Na VPS/VDS ano — root, vlastní image, docker compose. Na devhostingu stačí Dockerfile v repu.']," => "['Mohu od smlouvy odstoupit?', 'Spotřebitel může od smlouvy uzavřené na dálku odstoupit do 14 dnů; postup popisuje dokument Odstoupení od smlouvy.'],",
            "['Co když mi někdo pošle DDoS?', 'Filtrujeme na hraně sítě, kapacita 1,2 Tbps. U gameserverů máme profily pro Minecraft, CS2 a Rust zvlášť.']," => "['Jakou dostupnost garantujete?', 'Dostupnost a náhrady stanoví SLA: 99,9 % pro úroveň Standard a 99,95 % pro úroveň Business.'],",
            "['Kde leží data?', 'V EU — Praha, Brno, Frankfurt, Amsterdam, Varšava. Lokalitu si vybíráš při vytvoření.']" => "['Kde leží data?', 'Lokalitu služby uvádíme u tarifu a v objednávce.']",
            "['Can you migrate me from another host?', 'Yes, for free. Send credentials and we move sites, databases and mail — usually within 24 hours, with no downtime.']," => "['Will you help me move from another host?', '".self::MIGRATION_EN."'],",
            "['Can I change specs later?', 'Any time. Move CPU, RAM and disk in the panel; we bill hourly and the monthly price is the cap.']," => "['Can I change the plan later?', 'Yes, you order a plan change in the client panel and see its price in the order before you confirm.'],",
            "['How does Git deploy work?', 'Connect a repo (ours or GitHub), we detect the runtime and build and ship on every push. Rollback is one command.']," => "['How can I pay?', 'By bank transfer against a pro forma invoice (QR payment). The checkout offers a card only while the payment gateway is in operation.'],",
            "['Do I get root and my own Dockerfile?', 'On VPS/VDS yes — root, custom images, docker compose. On devhosting a Dockerfile in the repo is enough.']," => "['Can I withdraw from the contract?', 'A consumer may withdraw from a distance contract within 14 days; the Withdrawal document describes how.'],",
            "['What if someone DDoSes me?', 'We filter at the network edge with 1.2 Tbps of capacity, plus separate profiles for Minecraft, CS2 and Rust.']," => "['What availability do you guarantee?', 'Availability and credits are set by the SLA: 99.9 % for the Standard level and 99.95 % for the Business level.'],",
            "['Where does my data live?', 'In the EU — Prague, Brno, Frankfurt, Amsterdam, Warsaw. You pick the region at creation.']" => "['Where does my data live?', 'The location of a service is stated with the plan and in the order.']",
        ];
    }

    private static function monthsCs(int $n): string
    {
        return match (true) {
            $n === 1 => 'měsíc',
            $n >= 2 && $n <= 4 => 'měsíce',
            default => 'měsíců',
        };
    }

    /** Money value object, its JSON form `{minor, currency, decimal}` or a plain number → float amount. */
    private static function amount(mixed $value): float
    {
        if ($value instanceof Money) {
            return (float) $value->toDecimal();
        }
        if (is_array($value)) {
            return (float) ($value['decimal'] ?? (isset($value['minor']) ? $value['minor'] / 100 : 0));
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
