<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * T4 (phase T, the leftovers S2 found and left, `.ai/tasks/TASK-0211.md` "Out of scope"; P-R37, P-R46, owner decision 2026-09-25 no. 2)
 * — copy nobody documented, corrected on its way out like PhaseRCopySeam (the prototypes stay byte-identical, demo mode keeps their copy):
 *
 *  - `Onhost.dc.html` `#/ceny-a-sla` "Kde jsme dražší a proč": "dražší o 40 %", "290 Kč", an engineer "at three in the morning" and the
 *    other invented market comparisons → what the price includes as the documents in force say (support Mon–Fri 8–17, SLA Standard).
 *  - "1 400 lidí na Discordu" (no community is run), the panel mock's "Telefon 24/7", a named NOC engineer and 15-minute P1 replies, and
 *    "a recording of every session" in the day timeline and the tender's NIS2 row (nothing records operator sessions; the audit log does).
 *  - `onhost-svc-web.js`: mail "archiv 10 let" (eDiscovery, legal hold, DLP) and "data in Prague and Brno"; the SSL page's OV/EV products
 *    and their insurance (the `ssl` product is a draft — no paid certificate is sold); "SLA 99,99 %" on web, e-shop and database plans and
 *    their tables (SLA 2026-09 knows 99,99 % only by individual contract); the e-shop's "1 900 objednávek/h" load test nobody ran.
 *  - "SLA 99,99 %" rows of the VPS and dedicated-server tables (`onhost-svc-compute.js`) and the "Prague and Brno" FAQ answers of
 *    `onhost-svc-more.js` and `onhost-svc-dev.js`.
 */
final class PhaseTCopySeam
{
    private const BY_CONTRACT = "_('SLA podle smlouvy', 'SLA per contract')";

    private const SLA_ROW_OLD = "['SLA', '99,9 %', '99,95 %', '99,99 %']";

    private const SLA_ROW_NEW = "['SLA', '99,9 %', '99,95 %', _('podle smlouvy', 'per contract')]";

    private const LOCATIONS = "_('Lokality, ve kterých služby provozujeme, uvádí stránka Datacentra.', 'The locations we run services in are listed on the Data centres page.')";

    /** @return array<string, array<string, string>> script => [needle => replacement] (merged into ServicePageClaimsSeam::replacements()) */
    public static function scriptSwaps(): array
    {
        return [
            'onhost-svc-web.js' => [
                // WordPress: "SLA 99,99 %" on WP Scale and in the table
                "_('Zálohy 180 dní', '180-day backups'), _('SLA 99,99 %', '99.99% SLA')]" => "_('Zálohy 180 dní', '180-day backups'), ".self::BY_CONTRACT.']',
                "[_('Zálohy', 'Backups'), _('30 dní', '30 days'), _('90 dní', '90 days'), _('180 dní', '180 days')],\n          ".self::SLA_ROW_OLD => "[_('Zálohy', 'Backups'), _('30 dní', '30 days'), _('90 dní', '90 days'), _('180 dní', '180 days')],\n          ".self::SLA_ROW_NEW,
                // e-shop: 1 900 orders an hour, 0,5 s to checkout and 99,99 % "during campaigns" — no load test, no measurement
                "kpis: [['1 900', _('objednávek/h na Boost', 'orders/h on Boost')], ['0,5 s', _('doba do platby', 'time to checkout')], ['99,99 %', _('dostupnost v kampani', 'uptime during campaigns')]]," => 'kpis: [],',
                "_('SLA 99,99 % a technik na kampaň', '99.99% SLA and a campaign engineer')" => self::BY_CONTRACT,
                "          [_('Objednávky / hodina', 'Orders / hour'), '180', '850', '1 900+'],\n" => '',
                "_('Technik po dobu kampaně', 'Engineer on standby')],\n          ".self::SLA_ROW_OLD => "_('Technik po dobu kampaně', 'Engineer on standby')],\n          ".self::SLA_ROW_NEW,
                "      bench: [\n        { label: 'Shop Peak (autoscaling)', pct: 100, note: _('1 900 obj./h', '1,900 orders/h') },\n        { label: 'Shop Growth', pct: 45, note: _('850 obj./h', '850 orders/h') },\n        { label: _('Typický sdílený hosting', 'Typical shared hosting'), pct: 11, note: _('210 obj./h', '210 orders/h') }\n      ],\n      benchNote: _('Zátěžový test: WooCommerce, 12 000 produktů, dokončený checkout s platební bránou.', 'Load test: WooCommerce, 12,000 products, completed checkout with payment gateway.'),\n" => "      bench: [],\n      benchNote: _('Kapacitu e-shopu zveřejníme, až ji doloží zátěžový test.', 'We will publish store capacity once a load test backs it.'),\n",
                // mail: a ten-year archive with eDiscovery, legal hold and DLP, and storage "in Prague and Brno"
                "kicker: _('IMAP · DKIM · Archiv 10 let', 'IMAP · DKIM · 10-year archive')," => "kicker: _('IMAP · DKIM · DMARC', 'IMAP · DKIM · DMARC'),",
                ", _('Podpisy centrálně', 'Central signatures'), _('Archiv 3 roky', '3-year archive')]" => ", _('Podpisy centrálně', 'Central signatures')]",
                "'250 GB', _('Archiv 10 let', '10-year archive'), 'eDiscovery a legal hold', _('DLP pravidla', 'DLP rules'), _('S/MIME', 'S/MIME')]" => "'250 GB', 'IMAP, SMTP, webmail', _('Antispam a antivirus', 'Antispam and antivirus')]",
                "          [_('Archivace', 'Archiving'), '—', _('3 roky', '3 years'), _('10 let', '10 years')],\n          ['eDiscovery / legal hold', '—', '—', '✓'],\n          [_('DLP a pravidla obsahu', 'DLP and content rules'), '—', _('Základní', 'Basic'), _('Plné', 'Full')],\n" => '',
                "        ['04', _('Archiv, který obstojí u soudu', 'An archive that holds up in court'), _('Neměnitelný archiv s časovými značkami, export v PST i EML.', 'Immutable, timestamped archive with PST and EML export.')],\n" => '',
                "['06', _('Data v České republice', 'Data in the Czech Republic'), _('Primární i zálohové úložiště v Praze a Brně. Žádný přenos mimo EU.', 'Primary and backup storage in Prague and Brno. No transfer outside the EU.')]" => "['06', _('Kde data leží', 'Where the data lives'), ".self::LOCATIONS.']',
                // SSL: no paid OV/EV certificate is sold (the `ssl` product is a draft), so no insurance and no company validation
                "kicker: _('DV · OV · EV · Wildcard', 'DV · OV · EV · Wildcard')," => "kicker: _('DV · Wildcard přes DNS-01', 'DV · Wildcard via DNS-01'),",
                "lead: _('Let\\u2019s Encrypt v ceně každého hostingu, komerční certifikáty s pojištěním a ověřením firmy. Automatická obnova, hlídání expirace u domén i mimo Onhost.', 'Let\\u2019s Encrypt included with every hosting plan, commercial certificates with warranty and company validation. Auto-renewal and expiry monitoring, even for domains outside Onhost.')," => "lead: _('Let\\u2019s Encrypt v ceně každého hostingu s automatickou obnovou. Placené certifikáty OV a EV zatím neprodáváme.', 'Let\\u2019s Encrypt included with every hosting plan and renewed automatically. We do not sell paid OV or EV certificates yet.'),",
                "_('Pojištění do 1,5 mil. $', 'Warranty up to $1.5M'), " => '',
                "        { name: 'OV Business', tag: '', price: 1490, unitYear: true, specs: [_('Ověření firmy', 'Organisation validation'), _('Pojištění 1 mil. $', '$1M warranty'), _('Vystavení do 2 dnů', 'Issued within 2 days'), _('Zelený audit log', 'Audit log'), _('Podpora s prioritou', 'Priority support'), _('Site seal', 'Site seal')] },\n        { name: 'Wildcard OV', tag: '', price: 3990, unitYear: true, specs: [_('*.vasedomena.cz', '*.yourdomain.com'), _('Neomezeně subdomén', 'Unlimited subdomains'), _('Pojištění 1,5 mil. $', '$1.5M warranty'), _('Vystavení do 2 dnů', 'Issued within 2 days'), _('Instalace zdarma', 'Free installation'), _('Hlídání expirace', 'Expiry monitoring')] }\n" => '',
                "        ['04', _('Ověření firmy s námi', 'We handle organisation validation'), _('Podklady pro certifikační autoritu připravíme a komunikaci vedeme za vás.', 'We prepare the CA paperwork and handle the correspondence for you.')],\n" => '',
                "        { t: _('E-shop s platbami', 'Store with payments'), d: _('OV certifikát s ověřenou firmou a pojištěním pro brány.', 'OV certificate with validated company and gateway-friendly warranty.'), m: 'OV Business' },\n" => '',
                "m: 'Wildcard OV' }" => "m: _('Zdarma', 'Free') }",
                "_('Pro většinu webů ano — šifruje stejně silně. Rozdíl je v ověření firmy a pojištění, které řeší e-shopy a finance.', 'For most sites, yes — the encryption is identical. The difference is organisation validation and warranty, which matter for shops and finance.')" => "_('Pro většinu webů ano — šifruje stejně silně jako placený certifikát. Placené certifikáty OV a EV zatím neprodáváme.', 'For most sites, yes — the encryption is as strong as a paid certificate. We do not sell paid OV or EV certificates yet.')",
                "_('U OV a Wildcard ano, instalaci provedeme na vašem VPS nebo balanceru v rámci ceny.', 'For OV and Wildcard, yes — installation on your VPS or balancer is included.')" => "_('U služeb, které u nás běží, se certifikát nasadí sám. Na vlastním VPS použijte ACME klienta, například certbot.', 'On services that run with us the certificate is deployed by itself. On your own VPS use an ACME client such as certbot.')",
                // managed databases: a 20-second failover, 1-second PITR (not provided, owner decision 2) and 99,99 % HA nobody measured
                "kpis: [['< 20 s', _('automatický failover', 'automatic failover')], ['1 s', _('granularita PITR', 'PITR granularity')], ['99,99 %', _('dostupnost v HA', 'HA uptime')]]," => 'kpis: [],',
                "_('DBA na projekt', 'Dedicated DBA time')],\n          ".self::SLA_ROW_OLD => "_('DBA na projekt', 'Dedicated DBA time')],\n          ".self::SLA_ROW_NEW,
            ],
            'onhost-svc-compute.js' => [
                self::SLA_ROW_OLD => self::SLA_ROW_NEW, // VPS table
                "['SLA', '99,95 %', '99,99 %', '99,99 %']" => "['SLA', '99,95 %', _('podle smlouvy', 'per contract'), _('podle smlouvy', 'per contract')]", // dedicated servers
            ],
            'onhost-svc-more.js' => [
                "_('V Praze a Brně, v našich vlastních sálech. Nikde jinde.', 'In Prague and Brno, in our own halls. Nowhere else.')" => self::LOCATIONS,
            ],
            'onhost-svc-dev.js' => [
                "_('V Praze a Brně. Nikam mimo EU se nereplikují.', 'In Prague and Brno. Nothing is replicated outside the EU.')" => self::LOCATIONS,
            ],
        ];
    }

    /** @return array<string, list<array{0: string, 1: string, 2: string}>> script => [[from, to, replacement]] (merged into ServicePageClaimsSeam::blocks(); `to` is consumed) */
    public static function scriptBlocks(): array
    {
        return [
            // SSL: the comparison of DV with the OV products nobody sells
            'onhost-svc-web.js' => [
                ["      cmp: {\n        cols: [_('DV zdarma', 'DV free'), 'OV Business', 'Wildcard OV'],\n", "      feats: [\n        ['01', _('Obnova bez zásahu', 'Hands-off renewal'),", "      feats: [\n        ['01', _('Obnova bez zásahu', 'Hands-off renewal'),"],
            ],
        ];
    }

    /** @return array<string, string> needle => replacement in the public surface (each exactly once, after every other public seam — PublicContentSeam swaps inside the comparison block) */
    public static function publicSwaps(): array
    {
        return [
            // no community is run: no Discord with 1 400 people, no meetups, academy or grants
            "resources: { kicker: cs ? 'Komunita' : 'Community', title: cs ? '1 400 lidí na Discordu' : '1,400 people on Discord', lead: cs ? 'Kanál #noc-status, code review od ostatních zákazníků a měsíční meetup v Praze.' : 'A #noc-status channel, code review from other customers and a monthly Prague meetup.', chips: ['Discord', cs ? 'Meetupy' : 'Meetups', cs ? 'Akademie' : 'Academy', cs ? 'Granty' : 'Grants']," => "resources: { kicker: cs ? 'Návody' : 'Guides', title: cs ? 'Návody a dokumentace' : 'Guides and documentation', lead: cs ? 'Návody krok za krokem, dokumentace API a stav služeb na jednom místě.' : 'Step-by-step guides, the API documentation and the service status in one place.', chips: [cs ? 'Návody' : 'Guides', 'API', cs ? 'Stav služeb' : 'Status'],",
            // the panel mock: a named NOC engineer, a 24/7 telephone, 15-minute P1 replies and a 4-minute chat (support Mon–Fri 8–17, P-R37)
            "          { title: _('Jmenovaný inženýr', 'Named engineer'), meta: _('Marek Šimek · NOC Praha', 'Marek Šimek · NOC Prague'), value: _('online', 'online'), kind: 'ok' },\n          { title: _('Telefon 24/7', 'Phone, 24/7'), meta: '+420 210 000 111', value: 'P1', kind: 'ok' },\n          { title: _('Reakce podle SLA', 'SLA response'), meta: _('P1 do 15 min, P2 do 60 min', 'P1 in 15 min, P2 in 60 min'), value: '✓', kind: 'ok' },\n          { title: _('Chat v panelu', 'Panel chat'), meta: _('průměrně 4 min', '4 min on average'), value: _('otevřít', 'open'), kind: 'ok' }" => "          { title: _('Podpora', 'Support'), meta: _('po–pá 8–17, panel a e-mail', 'Mon–Fri 8–17, panel and e-mail'), value: '✓', kind: 'ok' },\n          { title: _('Telefon', 'Telephone'), meta: _('při výpadku v pracovní době', 'for an outage in working hours'), value: 'P1', kind: 'ok' },\n          { title: _('Reakce podle SLA', 'SLA response'), meta: _('Standard do 4 h, Business do 1 h', 'Standard within 4 h, Business within 1 h'), value: '✓', kind: 'ok' },\n          { title: _('Asistent v panelu', 'Panel assistant'), meta: _('odpovídá hned', 'answers right away'), value: _('otevřít', 'open'), kind: 'ok' }",
            // nothing records an operator's session: the audit log records who acted and when
            "'Jen s předem schváleným přístupem, s termínem a nahrávkou celé relace. Trvalý přístup do vašich služeb nemá nikdo, ani vedoucí směny.'" => "'Jen s předem schváleným přístupem a s termínem; zásah se zapíše do auditního záznamu. Trvalý přístup do vašich služeb nemá nikdo, ani vedoucí směny.'",
            "'Only with pre-approved access, an expiry and a full session recording. Nobody holds standing access to your services, not even the shift lead.'" => "'Only with pre-approved access and an expiry; the work is written to the audit log. Nobody holds standing access to your services, not even the shift lead.'",
            "'Evidujeme každý zásah operátora s důvodem a jménem, přístup do služby má vždy termín a nahrávku. Podklady pro audit dodáme jako součást nabídky.'" => "'Každý zásah operátora se zapíše do auditního záznamu se jménem a časem, přístup do služby má vždy termín. Podklady pro audit dodáme jako součást nabídky.'",
            "'Every operator action is recorded with a reason and a name; access to a service always has an expiry and a recording. Audit evidence ships with the bid.'" => "'Every operator action is written to the audit log with a name and a time; access to a service always has an expiry. Audit evidence ships with the bid.'",
            // the tender's "data in the Czech Republic": no location is documented beyond the data centres page
            "['Data v České republice', 'Lokality Praha a Brno, provoz i podpora z Česka. Kopie zálohy nikdy neopouští EU a v nabídce vám napíšeme jmenovitě, které lokality se vaší zakázky týkají.', 'splňujeme', 1]" => "['Data v České republice', 'Lokality, ve kterých služby provozujeme, uvádí stránka Datacentra; v nabídce napíšeme jmenovitě, které se vaší zakázky týkají.', 'na vyžádání', 0]",
            "['Data in the Czech Republic', 'Prague and Brno locations, operations and support from Czechia. Backup copies never leave the EU and the bid names the exact locations involved.', 'we meet it', 1]" => "['Data in the Czech Republic', 'The locations we run services in are listed on the Data centres page; the bid names the ones your contract involves.', 'on request', 0]",
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string}> [[from, to, replacement]] in the public surface (`to` is kept) */
    public static function publicBlocks(): array
    {
        return [
            // #/ceny-a-sla "Kde jsme dražší a proč": invented market comparisons and night-time support (P-R37)
            ["      dearTitle: cs ? 'Kde jsme dražší a proč' : 'Where we cost more, and why',\n", "      ]).map(x => ({\n        item: x[0], tag: x[1], us: x[2], them: x[3], why: x[5],", self::DEAR_BLOCK],
        ];
    }

    private const DEAR_BLOCK = <<<'DEAR'
      dearTitle: cs ? 'Co v ceně je a co ne' : 'What the price includes, and what it does not',
      dearLead: cs ? 'Ceny jsou v ceníku a v košíku včetně všech poplatků. Tady je, co za ně dostanete a co ne.' : 'Prices are on the price list and in the cart, every fee included. Here is what they include and what they do not.',
      dearFoot: cs ? 'Retenční slevu nenabízíme — sleva ve chvíli, kdy zákazník odchází, jen přiznává, že jsme mu ji mohli dát dřív a nedali.' : 'We never offer a retention discount — a discount at the moment somebody leaves only admits we could have given it earlier and did not.',
      dearRows: (cs ? [
        ['Podpora', 'pracovní dny', 'po–pá 8–17', 'panel a e-mail', 2, 'Podpora odpovídá v pracovní dny od 8 do 17 hodin přes panel a e-mail; telefon je určený pro výpadek v pracovní době. Noční podporu nenabízíme.'],
        ['Dostupnost', 'podle SLA', '99,9 %', 'tarif Standard', 1, 'Smluvní dostupnost a kompenzaci uvádí dokument SLA. Kompenzaci podle SLA uplatníte v zákaznickém panelu do 30 dnů od konce měsíce; připíše se jako kredit.'],
        ['Sleva za to, že odcházíte', 'nenabízíme', '0 %', '—', 2, 'Když se rozhodnete odejít, dostanete pomoc s odchodem, ne slevu.']
      ] : [
        ['Support', 'working days', 'Mon–Fri 8–17', 'panel and e-mail', 2, 'Support answers on working days from 8:00 to 17:00 through the panel and e-mail; the telephone is for an outage during working hours. We do not offer night-time support.'],
        ['Availability', 'per the SLA', '99.9%', 'Standard plans', 1, 'Contractual availability and compensation are set by the SLA document. Claim SLA compensation in the customer panel within 30 days of the end of the month; it is credited to your account.'],
        ['A discount for leaving', 'not offered', '0%', '—', 2, 'If you decide to leave you get help leaving, not a discount.']

DEAR;
}
