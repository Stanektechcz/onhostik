<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * U3 (phase U, the leftovers T4 found and left, `.ai/tasks/TASK-0215.md` "Out of scope") — copy nobody documented, corrected on its
 * way out like PhaseTCopySeam (the prototypes stay byte-identical, demo mode keeps their copy):
 *
 *  - `onhost-svc-web.js` managed databases: point-in-time recovery in the lead, the chips, the plan cards, the comparison table and the
 *    features (owner decision 2, 2026-09-25: PITR is not provided; the catalogue overlays the plans and the table live, the served
 *    script still carried them for anyone without JavaScript); the WordPress kpis "4 200 managed installs" and "0 broken updates in
 *    2025" (with the LCP figure on the same line) — nobody counted them.
 *  - `onhost-svc-compute.js` and the public configurator: VPS "Neměřený přenos" — VPS/VDS plans sell a monthly transfer (`traffic_tb`)
 *    that is measured since phase T (a soft limit: it warns, never throttles or cuts off).
 *  - `Onhost.dc.html` network table: "our own backbone between Prague and Brno along two separate paths" — no such route is documented.
 *  - `onhost-public.js` API changelog: an invented history (a reverted export limit "that broke two customers' night backups", "412
 *    accounts", "34 accounts … not all four thousand"); outside demo mode the page shows `docs/api/CHANGELOG.md` (PublicApiDocs), so
 *    the prototype's list is not served at all.
 *
 * The tender row "Enterprise do 30 minut telefonicky" was PhaseRCopySeam's own replacement; it is corrected there (Enterprise only by
 * individual contract, the telephone only for an outage in working hours, P-R37).
 */
final class PhaseUCopySeam
{
    /** @return array<string, array<string, string>> script => [needle => replacement] (merged into ServicePageClaimsSeam::replacements()) */
    public static function scriptSwaps(): array
    {
        return [
            'onhost-svc-web.js' => [
                // WordPress: install counts and an update record nobody kept, and a cache figure nobody measured
                "kpis: [['0,3 s', _('LCP po zapnutí cache', 'LCP with cache on')], ['4 200', _('spravovaných instalací', 'managed installs')], ['0', _('rozbitých updatů v 2025', 'broken updates in 2025')]]," => 'kpis: [],',
                // managed databases: no point-in-time recovery (owner decision 2)
                "lead: _('PostgreSQL, MySQL, Valkey a ClickHouse s automatickým failoverem, point-in-time obnovou a upgrady bez výpadku. Ladíme dotazy s vámi, ne za příplatek.', 'PostgreSQL, MySQL, Valkey and ClickHouse with automatic failover, point-in-time recovery and zero-downtime upgrades. Query tuning included, not billed extra.')," => "lead: _('Spravované databáze s parametry podle tarifu; databázové stroje, které tarif nabízí, uvádí jeho popis. Obnovu k libovolné sekundě (point-in-time) neposkytujeme.', 'Managed databases sized by plan; the plan lists the engines it offers. We do not provide point-in-time recovery.'),",
                "'PgBouncer', _('PITR', 'PITR'), _('Read replicas', 'Read replicas')]" => "'PgBouncer', _('Read replicas', 'Read replicas')]",
                "_('Denní zálohy', 'Daily backups'), _('PITR 7 dní', '7-day PITR'), " => "_('Denní zálohy', 'Daily backups'), ",
                "_('Automatický failover', 'Automatic failover'), _('PITR 30 dní', '30-day PITR'), " => "_('Automatický failover', 'Automatic failover'), ",
                "_('Čtecí repliky ve 2 lokalitách', 'Read replicas in 2 locations'), _('PITR 90 dní', '90-day PITR'), " => "_('Čtecí repliky ve 2 lokalitách', 'Read replicas in 2 locations'), ",
                "          ['PITR', _('7 dní', '7 days'), _('30 dní', '30 days'), _('90 dní', '90 days')],\n          [_('Čtecí repliky', 'Read replicas'), '—', '1', _('Až 5, i jiná lokalita', 'Up to 5, cross-region')]," => "          [_('Čtecí repliky', 'Read replicas'), '—', '1', _('Až 5, i jiná lokalita', 'Up to 5, cross-region')],",
                "['02', _('Point-in-time obnova', 'Point-in-time recovery'), _('Vrátíte databázi na sekundu před tím, než někdo pustil DELETE bez WHERE.', 'Roll back to the second before someone ran DELETE without WHERE.')]" => "['02', _('Zálohy podle tarifu', 'Backups per plan'), _('Kolik záloh a jak dlouho je držíme, uvádí tarif. Obnovu k libovolné sekundě (point-in-time) neposkytujeme.', 'The plan says how many backups we keep and for how long. We do not provide point-in-time recovery.')]",
            ],
            'onhost-svc-compute.js' => [
                // VPS cards: the monthly transfer is measured against the plan (a soft limit), not unmetered
                "'80 GB NVMe', _('Neměřený přenos', 'Unmetered traffic')" => "'80 GB NVMe', ".self::TRANSFER,
                "'160 GB NVMe', _('Neměřený přenos', 'Unmetered traffic')" => "'160 GB NVMe', ".self::TRANSFER,
            ],
        ];
    }

    private const TRANSFER = "_('Přenos podle tarifu, bez škrcení', 'Transfer per plan, never throttled')";

    /** @return array<string, list<array{0: string, 1: string, 2: string}>> script => [[from, to, replacement]] (merged into ServicePageClaimsSeam::blocks(); `to` is consumed) */
    public static function scriptBlocks(): array
    {
        return [
            // the API changelog's invented history; the live list comes from docs/api/CHANGELOG.md (PublicApiDocs::publicScript)
            'onhost-public.js' => [
                ["      changes: [\n", "      rules: [\n", "      changes: [],\n      rules: [\n"],
            ],
        ];
    }

    /** @return array<string, string> needle => replacement in the public surface (each exactly once, after every other public seam) */
    public static function publicSwaps(): array
    {
        return [
            // the service configurator: VPS transfer is measured against the plan, and no port speed is documented
            "{ k: cs ? 'Přenos dat' : 'Traffic', v: cs ? 'Neměřený, 1 Gbit/s' : 'Unmetered, 1 Gbit/s' }," => "{ k: cs ? 'Přenos dat' : 'Traffic', v: cs ? 'Měsíční přenos podle tarifu' : 'Monthly transfer per plan' },",
            // the network table: no own backbone between Prague and Brno is documented
            "        ['Vlastní páteř v ČR', 'naše', 'Praha a Brno propojujeme vlastními trasami ve dvou fyzicky oddělených cestách. Když jednu někdo překopne, druhá nese provoz sama.'],\n" => "        ['Síť v ČR', 'podle lokality', 'Kudy vede provoz ke konkrétní službě, vám sdělí podpora; lokality, ve kterých služby provozujeme, uvádí stránka Datacentra.'],\n",
            "        ['Our own backbone in Czechia', 'ours', 'Prague and Brno are joined by our own routes along two physically separate paths. If one is cut, the other carries the traffic alone.'],\n" => "        ['Network in Czechia', 'by location', 'Support tells you which way the traffic of a particular service runs; the data centres page lists the locations we run services in.'],\n",
        ];
    }
}
