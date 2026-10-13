<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * R1 (phase R, leftovers of Q1; P-R37, P-R69): copy nobody documented, corrected on its way out like ServicePageClaimsSeam (the
 * prototypes stay byte-identical, demo mode keeps their copy).
 *
 *  - The status page of `onhost-svc-other.js` ("99,993 % za 90 dní", "3 incidenty", nine probe locations of an "independent"
 *    service, a 24-month history, a paid monitoring plan) and the careers page (open roles with salaries, 42 people, 81 % retention,
 *    a NOC) are replaced whole by what the documents in force say (SLA 2026-09: measured from at least three places outside the
 *    network, monthly; a summary within five working days after an incident; compensation claimed in the panel within 30 days).
 *  - `Onhost.dc.html`: the public postmortems (a deploy outage with "46 200 Kč" credited to "214 customers", a NIX.CZ detour, a
 *    PRG1 maintenance), the "test us" steps (a call "at two on a Sunday", "four write-ups", a server "in 55 seconds"), the credit
 *    steps (nine locations, credited "automatically" and "cashable" — SLA čl. 4: claimed in the panel, credited to the account;
 *    owner decision: credit is never paid out), the status page's 99,98 % and a 15-minute P1 reply "around the clock".
 *  - DNS: "anycast in six locations" (Prague … Ashburn), a 9 ms median from "ten Czech networks, June 2026" — `onhost-svc-web.js`
 *    and the menus of `Onhost.dc.html`.
 */
final class PhaseRCopySeam
{
    /** @return array<string, array<string, string>> script => [needle => replacement] (merged into ServicePageClaimsSeam::replacements()) */
    public static function scriptSwaps(): array
    {
        return [
            'onhost-svc-web.js' => [
                "kicker: _('420+ koncovek · Anycast DNS', '420+ TLDs · Anycast DNS')," => "kicker: _('420+ koncovek · DNS v ceně', '420+ TLDs · DNS included'),",
                "lead: _('Registrace, transfer i správa DNS s anycast sítí v šesti lokalitách. DNSSEC na klik, historie změn, náhled před uložením — a žádné skryté poplatky za transfer.', 'Registration, transfers and DNS on an anycast network in six locations. One-click DNSSEC, change history, preview before saving — and no hidden transfer fees.')," => "lead: _('Registrace, transfer i správa DNS v jednom panelu. DNSSEC na klik, historie změn, náhled před uložením — a žádné skryté poplatky za transfer.', 'Registration, transfers and DNS in one panel. One-click DNSSEC, change history, preview before saving — and no hidden transfer fees.'),",
                "kpis: [['9 ms', _('medián odezvy DNS v ČR', 'median DNS response in CZ')], " => 'kpis: [',
                "chips: ['Anycast', 'DNSSEC', " => "chips: ['DNSSEC', ",
                "_('Anycast v 6 lokalitách', 'Anycast in 6 locations'), " => '',
                "          [_('Anycast lokality', 'Anycast locations'), '3', '6', _('6 + vlastní PoP', '6 + custom PoP')],\n" => '',
                "        ['03', _('Anycast v šesti lokalitách', 'Anycast in six locations'), _('Praha, Brno, Varšava, Frankfurt, Amsterdam, Ashburn. Dotaz obslouží nejbližší uzel.', 'Prague, Brno, Warsaw, Frankfurt, Amsterdam, Ashburn. The nearest node answers.')]," => "        ['03', _('DNS u služby', 'DNS with the service'), _('Záznamy, které web nebo pošta potřebuje, zapíše do zóny platforma sama.', 'The records a web or mail service needs are written to the zone by the platform itself.')],",
                "        { label: _('Onhost anycast (Praha)', 'Onhost anycast (Prague)'), pct: 100, note: '9 ms' },\n        { label: _('Globální poskytovatel (Frankfurt)', 'Global provider (Frankfurt)'), pct: 45, note: '20 ms' },\n        { label: _('DNS u registrátora domén', 'Registrar bundled DNS'), pct: 20, note: '46 ms' }\n" => '',
                "benchNote: _('Medián odezvy autoritativního dotazu z deseti českých sítí, červen 2026.', 'Median authoritative query response from ten Czech networks, June 2026.')," => "benchNote: _('Odezvu DNS zveřejníme, až ji doložíme měřením.', 'We will publish DNS response times once they are backed by measurement.'),",
            ],
        ];
    }

    /** @return array<string, list<array{0: string, 1: string, 2: string}>> script => [[from, to, replacement]] (merged into ServicePageClaimsSeam::blocks()) */
    public static function scriptBlocks(): array
    {
        return [
            'onhost-svc-other.js' => [
                ["    'status': C({\n", "    }),\n\n    'about': C({", self::STATUS_PAGE],
                ["    'careers': C({\n", "    }),\n\n    'support': C({", self::CAREERS_PAGE],
            ],
        ];
    }

    /** @return array<string, string> needle => replacement in the public surface (each exactly once) */
    public static function publicSwaps(): array
    {
        return [
            // the status page (#/stav): a measured 99,98 % and a 15-minute P1 reply "around the clock" (support Mon–Fri 8–17, P-R37)
            "          { k: cs ? 'Dostupnost 12 měsíců' : 'Uptime, 12 months', v: '99,98 %', n: cs ? 'měřeno zvenčí ze tří míst' : 'measured externally from three sites' }," => "          { k: cs ? 'Smluvní dostupnost' : 'Contractual uptime', v: '99,9 %', n: cs ? 'tarif Standard podle SLA' : 'Standard plans per the SLA' },",
            "          { k: cs ? 'Reakce na P1' : 'P1 response', v: cs ? 'do 15 min' : 'under 15 min', n: cs ? 'nonstop, člověk ne robot' : 'around the clock, a human not a bot' }" => "          { k: cs ? 'Reakce na incident' : 'Incident response', v: cs ? 'do 4 h' : 'within 4 h', n: cs ? 'Standard podle SLA, Business do 1 h' : 'Standard per the SLA, Business within 1 h' }",
            "        ? 'Časová osa je stejná, jakou vidí naše směna. Kredit podle SLA vystavíme sami, nemusíte o něj žádat.'\n        : 'The timeline is the one our shift sees. Any SLA credit is issued by us; you do not have to ask.'," => "        ? 'Časová osa je stejná, jakou vidí naše směna. Kompenzaci podle SLA uplatníte v zákaznickém panelu do 30 dnů od konce měsíce.'\n        : 'The timeline is the one our shift sees. Claim SLA compensation in the customer panel within 30 days of the end of the month.',",
            "        ? 'Žádný otevřený incident. Zápisy z uzavřených incidentů včetně těch, které jsme způsobili sami, jsou v sekci Jak fungujeme.'\n        : 'No open incident. Write-ups for resolved incidents, including the ones we caused, are under How we work.'," => "        ? 'Žádný otevřený incident. Po incidentu s dopadem na dostupnost zveřejníme do pěti pracovních dnů shrnutí příčin a nápravných opatření.'\n        : 'No open incident. After an incident that affected availability we publish a summary of its causes and remedies within five working days.',",
            "        ? 'Červený den znamená incident, ne ohlášené servisní okno. Okno hlásíme sedm dní předem a do dostupnosti se nepočítá.'\n        : 'A red day means an incident, not an announced maintenance window. Windows are announced seven days ahead and do not count against uptime.'," => "        ? 'Červený den znamená incident. Údržbu oznamujeme nejméně 48 hodin předem; do nedostupnosti se nepočítá jen v rozsahu, který uvádí SLA.'\n        : 'A red day means an incident. Maintenance is announced at least 48 hours ahead; it does not count as downtime only within the limits the SLA states.',",
            // the search: "nine locations, public incident write-ups"
            "          ['stránka', 'Stav služeb a postmortemy', 'Devět lokalit, veřejné zápisy incidentů', 'stav služeb status postmortem incident', 'home']," => "          ['stránka', 'Stav služeb a incidenty', 'Stav služeb a otevřené incidenty', 'stav služeb status postmortem incident', 'home'],",
            "          ['page', 'Status and postmortems', 'Nine locations, public incident write-ups', 'status postmortem incident', 'home']," => "          ['page', 'Status and incidents', 'Service status and open incidents', 'status postmortem incident', 'home'],",
            // DNS in the menus and the search: "Anycast"
            "'Anycast, DNSSEC, 420+ koncovek'], " => "'DNSSEC, 420+ koncovek'], ",
            "'Anycast, DNSSEC, 420+ TLDs'], " => "'DNSSEC, 420+ TLDs'], ",
            "'Anycast, DNSSEC, 420+ koncovek', 'domény dns" => "'DNSSEC, 420+ koncovek', 'domény dns",
            "'Anycast, DNSSEC, 420+ TLDs', 'domains dns" => "'DNSSEC, 420+ TLDs', 'domains dns",
            "'Anycast, DNSSEC, 420+ koncovek', 'services']," => "'DNSSEC, 420+ koncovek', 'services'],",
            "'Anycast, DNSSEC, 420+ TLDs', 'services']," => "'DNSSEC, 420+ TLDs', 'services'],",
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string}> [from, to, replacement] in the public surface; `to` stays */
    public static function publicBlocks(): array
    {
        return [
            ["      pmKicker: cs ? 'Veřejné postmortemy' : 'Public postmortems',\n", "      isSvc: s.view === 'svc' && !!svcObj, svc: svcObj,", self::POSTMORTEMS],
            ["      tryTitle: cs ? 'Jak si nás otestovat, než u nás zaplatíte' : 'How to test us before you pay',\n", '      dearTitle: cs ?', self::TRY_AND_CREDIT],
        ];
    }

    private const POSTMORTEMS = <<<'POSTMORTEMS'
      pmKicker: cs ? 'Incidenty' : 'Incidents',
      pmTitle: cs ? 'Když se něco pokazí, napíšeme to' : 'When something breaks, we say so',
      pmLead: cs ? 'Stav služeb a průběh incidentů je na stavové stránce. Po incidentu s dopadem na dostupnost zveřejníme do pěti pracovních dnů shrnutí příčin a nápravných opatření.' : 'Service status and the course of incidents are on the status page. After an incident that affected availability we publish a summary of its causes and remedies within five working days.',
      pmCta: cs ? 'Stav služeb' : 'Service status',
      pmFoot: cs ? 'Shrnutí incidentů zveřejňujeme na stavové stránce, až k nějakému dojde. Čísla, která jsme nenaměřili, tu neuvádíme.' : 'Incident summaries are published on the status page once an incident happens. We do not state figures we have not measured.',
      postmortems: [],

POSTMORTEMS;

    private const TRY_AND_CREDIT = <<<'TRY_AND_CREDIT'
      tryTitle: cs ? 'Jak si nás prověřit, než u nás zaplatíte' : 'How to check us before you pay',
      tryLead: cs ? 'Co si můžete ověřit dřív, než nám dáte první korunu. Nic z toho nevyžaduje účet.' : 'What you can check before you pay us anything. None of it needs an account.',
      trySteps: (cs ? [
        ['01', 'Přečtěte si dokumenty', 'Ceník, obchodní podmínky, SLA a zásady zpracování osobních údajů jsou veřejné ve verzi, která platí.', 'bez účtu'],
        ['02', 'Podívejte se na stav služeb', 'Stav služeb a otevřené incidenty jsou na stavové stránce; po incidentu s dopadem na dostupnost zveřejníme do pěti pracovních dnů shrnutí příčin a nápravy.', 'bez účtu'],
        ['03', 'Zeptejte se stavového API', 'Stav služeb vrací i veřejný endpoint /v1/status, bez klíče.', 'bez účtu'],
        ['04', 'Napište nám dotaz', 'Na e-mail odpovídáme v pracovní dny 8–17. Zákazníci píšou tiket v panelu.', 'bez účtu']
      ] : [
        ['01', 'Read the documents', 'The price list, the terms, the SLA and the privacy policy are public in the version in force.', 'no account'],
        ['02', 'Look at the service status', 'Service status and open incidents are on the status page; after an incident that affected availability we publish a summary of causes and remedies within five working days.', 'no account'],
        ['03', 'Ask the status API', 'The public endpoint /v1/status returns the service status too, without a key.', 'no account'],
        ['04', 'Write us a question', 'We answer e-mail on working days, 8–17. Customers open a ticket in the panel.', 'no account']
      ]).map(x => ({ n: x[0], t: x[1], d: x[2], v: x[3] })),
      tryFoot: cs ? 'Ceny, podmínky a stav služeb jsou veřejné, abyste nás mohli posoudit dřív, než zaplatíte.' : 'Prices, terms and the service status are public, so you can judge us before you pay.',
      slaCreditTitle: cs ? 'Jak funguje kompenzace za nedodržené SLA' : 'How compensation for a missed SLA works',
      slaCreditLead: cs ? 'Podle SLA v platnosti: dostupnost se vyhodnocuje za kalendářní měsíc a kompenzaci uplatníte v zákaznickém panelu.' : 'Per the SLA in force: availability is evaluated per calendar month and you claim compensation in the customer panel.',
      slaSteps: (cs ? [
        ['01', 'Měření', '3+ místa', 'Dostupnost se měří z nejméně tří nezávislých míst mimo síť poskytovatele.'],
        ['02', 'Za měsíc', '99,9 %', 'Smluvní dostupnost tarifu Standard je 99,9 %, Business 99,95 %; vyhodnocuje se za kalendářní měsíc.'],
        ['03', 'Uplatnění v panelu', 'do 30 dní', 'Nárok uplatníte v zákaznickém panelu do 30 dnů od konce měsíce, ve kterém k nedodržení došlo.'],
        ['04', 'Kredit', '5–10 %', 'Za každou započatou hodinu nad limit 5 % (Standard) nebo 10 % (Business) měsíční ceny služby; připíše se jako kredit.']
      ] : [
        ['01', 'Measurement', '3+ places', 'Availability is measured from at least three independent places outside the provider’s network.'],
        ['02', 'Per month', '99.9%', 'The contractual availability is 99.9% for Standard plans and 99.95% for Business; it is evaluated per calendar month.'],
        ['03', 'Claimed in the panel', 'within 30 days', 'Claim compensation in the customer panel within 30 days of the end of the month in which the SLA was missed.'],
        ['04', 'Credit', '5–10%', 'For every started hour over the limit, 5% (Standard) or 10% (Business) of the service’s monthly price, credited to your account.']
      ]).map(x => ({ n: x[0], t: x[1], v: x[2], d: x[3] })),
      slaNotes: (cs ? [
        ['Plánovaná údržba', 'Údržba oznámená nejméně 48 hodin předem, mimo pracovní dobu a nejvýše 4 hodiny měsíčně se do nedostupnosti nepočítá.'],
        ['Reakce na incident', 'Standard do 4 hodin, Business do 1 hodiny, jak uvádí SLA.'],
        ['Co SLA nekryje', 'Služby třetích stran, které neovládáme (registry domén, platební brány), a výpadky způsobené zákazníkem.']
      ] : [
        ['Planned maintenance', 'Maintenance announced at least 48 hours ahead, outside working hours and at most 4 hours a month does not count as downtime.'],
        ['Incident response', 'Standard within 4 hours, Business within 1 hour, as the SLA states.'],
        ['What the SLA does not cover', 'Third-party services we do not control (domain registries, payment gateways) and outages the customer caused.']
      ]).map(x => ({ t: x[0], d: x[1] })),
      pageLinks: (cs ? [
        ['Stav služeb', 'stav a otevřené incidenty', 'Onhost.dc.html#/stav'],
        ['API a CLI', 'dokumentace API', 'Onhost.dc.html#/technika'],
        ['SLA', 'dostupnost a kompenzace', 'Onhost.dc.html#/ceny-a-sla'],
        ['Lidé v podpoře', 'kdo vám odpoví', 'Onhost.dc.html#/lide']
      ] : [
        ['Service status', 'status and open incidents', 'Onhost.dc.html#/stav'],
        ['API and CLI', 'API documentation', 'Onhost.dc.html#/technika'],
        ['SLA', 'availability and compensation', 'Onhost.dc.html#/ceny-a-sla'],
        ['Support team', 'who answers you', 'Onhost.dc.html#/lide']
      ]).map(x => ({ label: x[0], note: x[1], href: x[2] })),

TRY_AND_CREDIT;

    private const STATUS_PAGE = <<<'STATUS_PAGE'
    'status': C({
      panelsTitle: _('Jak měříme dostupnost', 'How availability is measured'), panelsLead: _('Dostupnost, její měření a kompenzaci určuje dokument SLA v platnosti. Tady je, co v něm stojí.', 'The SLA in force sets availability, how it is measured and the compensation. This is what it says.'),
      panels: [
        [_('Měření', 'Measurement'), _('Dostupnost se měří z nejméně tří nezávislých míst mimo naši síť a vyhodnocuje se za kalendářní měsíc.', 'Availability is measured from at least three independent places outside our network and evaluated per calendar month.'), [[_('Měřicí místa', 'Measuring points'), _('nejméně 3', 'at least 3')], [_('Vyhodnocení', 'Evaluated'), _('za měsíc', 'monthly')]]],
        [_('Incidenty', 'Incidents'), _('Stav služeb je na stavové stránce. Po incidentu s dopadem na dostupnost zveřejníme do pěti pracovních dnů shrnutí příčin a nápravných opatření.', 'Service status is on the status page. After an incident that affected availability we publish a summary of causes and remedies within five working days.'), [[_('Shrnutí po incidentu', 'Post-incident summary'), _('do 5 pracovních dnů', 'within 5 working days')]]],
        [_('Kompenzace', 'Compensation'), _('Nárok uplatníte v zákaznickém panelu do 30 dnů od konce měsíce; připíše se jako kredit.', 'Claim it in the customer panel within 30 days of the end of the month; it is credited to your account.'), [[_('Uplatnění', 'Claim'), _('v panelu', 'in the panel')], [_('Lhůta', 'Deadline'), _('30 dní', '30 days')]]]
      ],
      cmp: null,
      plansTitle: cs ? 'Kde stav najdete' : 'Where to find the status', plansNote: cs ? 'Stav služeb a otevřené incidenty jsou veřejné.' : 'Service status and open incidents are public.',
      cat: _('Podpora', 'Support'), crumb: _('Stav služeb', 'Service status'),
      kicker: _('Stav služeb · incidenty · SLA', 'Service status · incidents · SLA'),
      title: _('Stav služeb a incidenty', 'Service status and incidents'),
      lead: _('Aktuální stav služeb a otevřené incidenty zveřejňujeme na stavové stránce. Dostupnost, její měření a kompenzaci určuje dokument SLA v platnosti.', 'The current service status and open incidents are on the status page. The SLA in force sets availability, how it is measured and the compensation.'),
      kpis: [],
      chips: [_('Stavová stránka', 'Status page'), _('SLA v platnosti', 'SLA in force'), _('Kompenzace v panelu', 'Compensation in the panel')],
      plans: [
        { name: _('Stavová stránka', 'Status page'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('bez registrace', 'no account needed'), ctaLabel: _('Otevřít status', 'Open status'), specs: [_('Stav služeb', 'Service status'), _('Otevřené incidenty', 'Open incidents'), _('Ohlášená údržba', 'Announced maintenance'), _('Veřejné API /v1/status', 'Public API /v1/status')] }
      ],
      feats: [
        ['01', _('Měření podle SLA', 'Measured per the SLA'), _('Z nejméně tří nezávislých míst mimo naši síť, vyhodnocení za kalendářní měsíc.', 'From at least three independent places outside our network, evaluated per calendar month.')],
        ['02', _('Shrnutí po incidentu', 'A summary after an incident'), _('Po incidentu s dopadem na dostupnost do pěti pracovních dnů: příčiny a nápravná opatření.', 'After an incident that affected availability, within five working days: causes and remedies.')],
        ['03', _('Údržba předem', 'Maintenance in advance'), _('Plánovanou údržbu oznamujeme na stavové stránce a v panelu nejméně 48 hodin předem.', 'Planned maintenance is announced on the status page and in the panel at least 48 hours ahead.')],
        ['04', _('Kompenzace v panelu', 'Compensation in the panel'), _('Nárok uplatníte v panelu do 30 dnů od konce měsíce; připíše se jako kredit.', 'Claim it in the panel within 30 days of the end of the month; it is credited to your account.')]
      ],
      tech: [],
      bench: [],
      benchNote: _('Naměřenou dostupnost zveřejníme, až za sebou budeme mít celé měsíce provozu.', 'We will publish measured availability once we have whole months of operation behind us.'),
      cases: [],
      faq: [
        [_('Jak se dostupnost měří?', 'How is availability measured?'), _('Z nejméně tří nezávislých míst mimo síť poskytovatele; služba je nedostupná, selže-li kontrola na většině z nich. Vyhodnocuje se za kalendářní měsíc.', 'From at least three independent places outside the provider’s network; the service is down when the check fails on most of them. It is evaluated per calendar month.')],
        [_('Jak se dozvím o údržbě?', 'How do I hear about maintenance?'), _('Na stavové stránce a v panelu, nejméně 48 hodin předem.', 'On the status page and in the panel, at least 48 hours ahead.')],
        [_('Dostanu kompenzaci za výpadek?', 'Do I get compensation for an outage?'), _('Podle SLA, když dostupnost klesne pod smluvní hodnotu tarifu. Nárok uplatníte v zákaznickém panelu do 30 dnů od konce měsíce.', 'Per the SLA, when availability falls below the plan’s contractual value. Claim it in the customer panel within 30 days of the end of the month.')]
      ]
    }),

    'about': C({
STATUS_PAGE;

    private const CAREERS_PAGE = <<<'CAREERS_PAGE'
    'careers': C({
      panelsTitle: _('Práce v Onhostu', 'Working at Onhost'), panelsLead: _('Otevřené pozice tu zveřejníme i s podmínkami, až budeme nabírat.', 'We will list open roles here, with their terms, once we are hiring.'),
      panels: [],
      cmp: null,
      plansTitle: cs ? 'Otevřené pozice' : 'Open roles', plansNote: cs ? 'Žádnou pozici právě neinzerujeme.' : 'We are not advertising any role right now.',
      cat: 'Onhost', crumb: _('Kariéra', 'Careers'),
      kicker: _('Kariéra', 'Careers'),
      title: _('Otevřené pozice', 'Open roles'),
      lead: _('Žádnou pozici právě neinzerujeme. Až budeme nabírat, zveřejníme ji tady i s podmínkami.', 'We are not advertising any role right now. When we hire, the role and its terms will be listed here.'),
      kpis: [],
      chips: [],
      plans: [
        { name: _('Kontakt', 'Contact'), tag: '', price: 0, priceLabel: _('E-mail', 'E-mail'), priceNote: _('po–pá 8:00–17:00', 'Mon–Fri 8:00–17:00'), ctaLabel: _('Napsat nám', 'Write to us'), specs: [_('Kontakt uvádějí obchodní podmínky', 'The terms give the contact')] }
      ],
      feats: [],
      tech: [],
      bench: [],
      benchNote: '',
      cases: [],
      faq: [
        [_('Kdy budete nabírat?', 'When will you hire?'), _('Otevřenou pozici zveřejníme na této stránce i s podmínkami.', 'An open role will be listed on this page with its terms.')]
      ]
    }),

    'support': C({
CAREERS_PAGE;
}
