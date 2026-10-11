<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * Owner decision J-R2 (2026-10-11, follow-up 1b of J-R1, K8b): the service pages stop showing the invented eco figures and the
 * EPYC configuration and stock claims until the owner documents real ones. J-R1 withdrew them from `#/technika`; the same numbers
 * were still on the pages the service scripts `onhost-svc-compute/corp/other/more.js` draw — green infrastructure (PUE 1,18, 62 %
 * renewables, 2,4 GWh and 1 640 MWh of heat, 164 k m³ of gas, 840 kWp, 2 MWh of batteries, a 90 % target, "two buildings"),
 * data centres (PUE 1,18 and its comparison bars, 214 days of free cooling), colocation (PUE, 62 %), dedicated servers (EPYC 9354
 * builds, "in stock within 24 hours", "delivery from stock").
 *
 * The scripts are prototypes and stay byte-identical; the served copy is corrected here on its way out (outside demo mode), like
 * `onhost-svc-web.js` (SurfaceController). Each needle must land exactly once (`ServicePageClaimsTest` pins it against the
 * prototype, so a redesigned script cannot silently bring a figure back). A withdrawn figure is removed, never replaced by another
 * number: a KPI or a spec row disappears, a comparison chart of PUE keeps only an honest note, a sentence loses its number.
 */
final class ServicePageClaimsSeam
{
    private const PUE_NOTE_CS = 'Účinnost (PUE) zveřejníme, až ji doložíme měřením.';

    private const PUE_NOTE_EN = 'We will publish efficiency (PUE) once it is backed by measurement.';

    /** @return array<string, array<string, string>> script => [needle => replacement] */
    public static function replacements(): array
    {
        $pueNote = "benchNote: _('".self::PUE_NOTE_CS."', '".self::PUE_NOTE_EN."'),";

        return [
            'onhost-svc-compute.js' => [
                // VPS: the benchmark row named one EPYC model
                "{ label: _('Onhost VPS 4 (EPYC 9354)', 'Onhost VPS 4 (EPYC 9354)'), pct: 100, note: '1 842 pts' }," => "{ label: _('Onhost VPS 4', 'Onhost VPS 4'), pct: 100, note: '1 842 pts' },",
                // dedicated servers: stock and delivery-from-stock claims, EPYC 9354 builds
                "lead: _('Vybraný hardware skladem do 24 hodin, nebo sestavíme na míru do pěti dnů. Můžete si ho pronajmout, splácet po 24 měsíců, nebo koupit a nechat u nás v racku.', 'Selected hardware in stock within 24 hours, or built to order in five days. Rent it, pay it off over 24 months, or buy it and keep it in our rack.')," => "lead: _('Sestavu domluvíme podle vaší potřeby a termín dodání potvrdíme předem. Můžete si ji pronajmout, splácet po 24 měsíců, nebo koupit a nechat u nás v racku.', 'We agree the build with you and confirm the delivery date up front. Rent it, pay it off over 24 months, or buy it and keep it in our rack.'),",
                "kpis: [['24 h', _('dodání ze skladu', 'delivery from stock')], ['3 roky'," => "kpis: [['3 roky',",
                "_('Dodání do 24 h', 'Delivered within 24 h')] }," => "_('Termín dodání potvrdíme předem', 'Delivery date confirmed up front')] },",
                "specs: ['EPYC 9354, 32 c / 64 t'," => "specs: ['AMD EPYC, 32 c / 64 t',",
                "{ label: _('EPYC 9354 (32 jader)', 'EPYC 9354 (32 cores)'), pct: 100, note: '14 900 pts' }," => "{ label: _('AMD EPYC (32 jader)', 'AMD EPYC (32 cores)'), pct: 100, note: '14 900 pts' },",
                // colocation: PUE and the share of renewables
                "kpis: [['1,18', 'PUE'], ['2N', _('napájení a UPS', 'power and UPS')], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')]]," => "kpis: [['2N', _('napájení a UPS', 'power and UPS')]],",
                "        { label: _('Onhost Praha (BESS + obnovitelné)', 'Onhost Prague (BESS + renewables)'), pct: 100, note: 'PUE 1,18' },\n        { label: _('Průměrné evropské datacentrum', 'Average European data centre'), pct: 62, note: 'PUE 1,55' },\n        { label: _('Vlastní serverovna ve firmě', 'In-house company server room'), pct: 32, note: 'PUE 2,4' }\n" => '',
                "benchNote: _('PUE měřené za 12 měsíců včetně chlazení a rozvodů; nižší je lepší, sloupec ukazuje účinnost.', 'PUE measured over 12 months including cooling and distribution; lower is better, the bar shows efficiency.')," => $pueNote,
            ],
            'onhost-svc-corp.js' => [
                // a prototype customer quote (never rendered outside demo mode since I-R14, but served): its number goes too
                "text: _('Odvod tepla z jejich datacentra ohřívá dvě sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms two neighbouring buildings. This is how infrastructure should work.')" => "text: _('Odvod tepla z jejich datacentra ohřívá sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms the neighbouring buildings. This is how infrastructure should work.')",
                // data centres: PUE, free-cooling days, "two buildings"
                "[['PUE', '1,18'], [_('Volné chlazení', 'Free cooling'), _('214 dní / rok', '214 days a year')], [_('Teplota v sále', 'Hall temperature'), '22 °C'], [_('Odvod tepla', 'Heat recovery'), _('2 budovy', '2 buildings')]]" => "[[_('Teplota v sále', 'Hall temperature'), '22 °C']]",
                "['1,18', _('průměrné PUE v Praze', 'average PUE in Prague')], " => '',
                "_('Odpadní teplo ohřívá dvě budovy vedle nás, ne vzduch nad střechou.', 'Waste heat warms two neighbouring buildings, not the air above the roof.')" => "_('Odpadní teplo ohřívá budovy vedle nás, ne vzduch nad střechou.', 'Waste heat warms the neighbouring buildings, not the air above the roof.')",
                "        { label: _('PRG1 — PUE', 'PRG1 — PUE'), pct: 100, note: '1,18' },\n        { label: _('Průměr datacenter v EU', 'EU data centre average'), pct: 62, note: '~1,55' },\n        { label: _('Starší sály v ČR', 'Older Czech halls'), pct: 45, note: '~1,9' }\n" => '',
                "benchNote: _('Roční průměr PUE za 2025. Nižší číslo znamená méně energie na chlazení.', 'Annual average PUE for 2025. Lower means less energy spent on cooling.')," => $pueNote,
            ],
            'onhost-svc-other.js' => [
                // a prototype customer quote (never rendered outside demo mode since I-R14, but served): its number goes too
                "text: _('Odvod tepla z jejich datacentra ohřívá dvě sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms two neighbouring buildings. This is how infrastructure should work.')" => "text: _('Odvod tepla z jejich datacentra ohřívá sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms the neighbouring buildings. This is how infrastructure should work.')",
                // the SLA band: the share of renewables
                "      { k: '6', v: _('lokalit v Evropě', 'European locations') },\n      { k: '62 %', v: _('energie z obnovitelných zdrojů', 'energy from renewables') }" => "      { k: '6', v: _('lokalit v Evropě', 'European locations') }",
                // green infrastructure: every eco figure
                "[[_('Obnovitelné', 'Renewable'), '62 %'], [_('Vlastní FVE', 'Own solar'), '840 kWp'], [_('Baterie', 'Battery storage'), '2 MWh'], [_('Cíl 2030', '2030 target'), '90 %']]" => '[]',
                "_('Teplo ze sálů ohřívá dvě sousední budovy místo vzduchu nad střechou.', 'Hall heat warms two neighbouring buildings instead of the air above the roof.'), [[_('Budov', 'Buildings'), '2'], [_('Dodané teplo 2025', 'Heat delivered in 2025'), '1 640 MWh'], [_('Úspora plynu', 'Gas saved'), '164 ' + _('tis. m³', 'k m³')], ['PUE', '1,18']]]" => "_('Teplo ze sálů ohřívá sousední budovy místo vzduchu nad střechou.', 'Hall heat warms the neighbouring buildings instead of the air above the roof.'), []]",
                "kpis: [['1,18', 'PUE'], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')], ['2,4 GWh', _('tepla vráceno v 2025', 'heat returned in 2025')]]," => 'kpis: [],',
                "_('Odpadní teplo ohřívá okolní budovy. V roce 2025 to bylo 2,4 GWh.', 'Waste heat warms nearby buildings — 2.4 GWh of it in 2025.')" => "_('Odpadní teplo ohřívá okolní budovy.', 'Waste heat warms nearby buildings.')",
                "        { label: _('Onhost Praha (BESS + FVE + teplo)', 'Onhost Prague (BESS + solar + heat)'), pct: 100, note: 'PUE 1,18' },\n        { label: _('Moderní komerční datacentrum', 'Modern commercial data centre'), pct: 74, note: 'PUE 1,40' },\n        { label: _('Firemní serverovna', 'Company server room'), pct: 32, note: 'PUE 2,40' }\n" => '',
                "benchNote: _('PUE za 12 měsíců včetně chlazení, rozvodů a ztrát; sloupec ukazuje účinnost, nižší PUE je lepší.', 'PUE over 12 months including cooling, distribution and losses; the bar shows efficiency — lower PUE is better.')," => $pueNote,
                // "how Onhost works": the share of renewables; a location card: PUE
                ", [_('OZE', 'Renewables'), '62 %']]]" => ']]',
                "_('Odvod tepla do teplovodu', 'Heat into district loop'), 'PUE 1,18', " => "_('Odvod tepla do teplovodu', 'Heat into district loop'), ",
            ],
            'onhost-svc-more.js' => [
                "_('Odpadní teplo z našich sálů ohřívá dvě sousední budovy v Praze.', 'Waste heat from our halls warms two neighbouring Prague buildings.')" => "_('Odpadní teplo z našich sálů ohřívá sousední budovy v Praze.', 'Waste heat from our halls warms neighbouring Prague buildings.')",
            ],
        ];
    }

    public static function covers(string $path): bool
    {
        return array_key_exists($path, self::replacements());
    }

    /** The served copy of a service script without the withdrawn figures (J-R2). */
    public static function apply(string $script, string $path): string
    {
        $map = self::replacements()[$path] ?? [];

        return $map === [] ? $script : str_replace(array_keys($map), array_values($map), $script);
    }

    /**
     * How often each needle occurs in the prototype script (the test expects exactly one each).
     *
     * @return array<string, int>
     */
    public static function anchorCounts(string $script, string $path): array
    {
        $counts = [];
        foreach (array_keys(self::replacements()[$path] ?? []) as $needle) {
            $counts[$needle] = substr_count($script, $needle);
        }

        return $counts;
    }
}
