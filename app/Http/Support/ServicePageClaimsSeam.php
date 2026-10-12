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

    /**
     * L3 / M1 (K7-3, Comgate review 1/3): the body of the `legal` page (`#/sluzba/legal`) as the prototype has it — invented
     * panels, KPIs (4 documents, 30 days' notice), ISO 27001 / NIS2 chips, documents "VOP v4.2" … with sizes, a cookie banner, a
     * 31/90-page comparison, an ISO tender case, an FAQ with data "in Prague and Brno" and incident reports within 24 hours.
     */
    private const LEGAL_PAGE_OLD = <<<'LEGAL_PAGE_OLD'
      panelsTitle: _('Jak s dokumenty pracujeme', 'How we handle these documents'), panelsLead: _('Krátké, srozumitelné a beze změn na poslední chvíli. Každá verze zůstává dohledatelná.', 'Short, readable and never changed at the last minute. Every version stays traceable.'),
      panels: [
        [_('Změny', 'Changes'), _('Novou verzi posíláme e-mailem s vyznačenými rozdíly, měsíc předem.', 'A new version arrives by email with differences highlighted, a month ahead.'), [[_('Oznámení předem', 'Notice'), '30 ' + _('dní', 'days')], [_('Rozdíly vyznačené', 'Diff highlighted'), '✓'], [_('Archiv verzí', 'Version archive'), _('od 2019', 'since 2019')], [_('Změn za rok', 'Changes a year'), '2–3']]],
        [_('Data a soukromí', 'Data and privacy'), _('Osobní údaje zůstávají v Praze a Brně, subdodavatele zveřejňujeme.', 'Personal data stays in Prague and Brno; sub-processors are published.'), [[_('Umístění dat', 'Data location'), _('Praha, Brno', 'Prague, Brno')], [_('Mimo EU', 'Outside the EU'), '—'], [_('Subdodavatelé', 'Sub-processors'), _('veřejný seznam', 'public list')], [_('Doba uchování', 'Retention'), _('30 dní po výpovědi', '30 days after termination')]]],
        [_('Když se něco stane', 'When something happens'), _('Kredity vyplácíme sami, incidenty hlásíme do 24 hodin.', 'We pay credits ourselves and report incidents within 24 hours.'), [[_('Kredit za výpadek', 'Outage credit'), _('automaticky', 'automatic')], [_('Hlášení incidentu', 'Incident report'), '< 24 h'], [_('Postmortem', 'Postmortem'), _('do 5 dnů', 'within 5 days')], [_('Pojištění', 'Insurance'), _('na vyžádání', 'on request')]]]
      ],
      cmp: null,
      cat: 'Onhost', crumb: _('Podmínky, GDPR a cookies', 'Terms, GDPR and cookies'),
      kicker: _('Dokumenty psané srozumitelně', 'Documents written to be read'),
      title: _('Smluvní dokumenty, které jdou přečíst', 'Contract documents you can actually read'),
      lead: _('Obchodní podmínky bez odstavců na půl stránky, zpracování údajů popsané tak, aby to pochopil i právník klienta, a cookie lišta, která se ptá jednou a pamatuje si to.', 'Terms without half-page paragraphs, data processing described so your client can follow it, and a cookie banner that asks once and remembers.'),
      kpis: [['4', _('dokumenty celkem', 'documents in total')], ['30 ' + _('dní', 'days'), _('oznámení před změnou', 'notice before a change')], ['0', _('sledovacích cookies bez souhlasu', 'tracking cookies without consent')]],
      chips: ['VOP', 'GDPR', 'DPA', 'SLA', 'Cookies', 'ISO 27001', 'NIS2', _('Archiv verzí', 'Version archive')],
      plansTitle: _('Dokumenty ke stažení', 'Documents to download'),
      docs: [
        [_('Obchodní podmínky', 'Terms of service'), _('Práva a povinnosti, fakturace, výpověď, odpovědnost a zakázané využití.', 'Rights and obligations, billing, termination, liability and prohibited use.'), 'v4.2', _('1. 1. 2026', '1 Jan 2026'), '186 kB'],
        [_('Zpracování osobních údajů (DPA)', 'Data processing agreement (DPA)'), _('Účel a rozsah zpracování, doba uchování, bezpečnostní opatření, hlášení incidentů.', 'Purpose and scope, retention, security measures, incident notification.'), 'v3.1', _('1. 1. 2026', '1 Jan 2026'), '142 kB'],
        [_('Seznam subdodavatelů', 'List of sub-processors'), _('Kdo se může dostat k datům, k čemu a kde fyzicky sídlí.', 'Who can access data, for what, and where they are physically located.'), 'v2.9', _('12. 6. 2026', '12 Jun 2026'), '38 kB'],
        [_('SLA a kredity', 'SLA and credits'), _('Garantovaná dostupnost, reakční doby podle priority, výše kreditů a jejich výplata.', 'Guaranteed uptime, response times by priority, credit levels and how they are paid.'), 'v4.0', _('1. 1. 2026', '1 Jan 2026'), '96 kB'],
        [_('Zásady cookies', 'Cookie policy'), _('Seznam cookies s účelem a dobou platnosti, jak souhlas odvolat.', 'Cookie inventory with purpose and lifetime, and how to withdraw consent.'), 'v2.4', _('3. 3. 2026', '3 Mar 2026'), '54 kB'],
        [_('Zásady férového využití', 'Fair use policy'), _('Co znamená „neomezeně“ u přenosu a kdy se ozveme.', 'What “unlimited” means for transfer and when we get in touch.'), 'v1.8', _('3. 3. 2026', '3 Mar 2026'), '41 kB'],
        [_('Bezpečnostní politika', 'Security policy'), _('Řízení přístupů, šifrování, testování a hlášení zranitelností.', 'Access control, encryption, testing and vulnerability disclosure.'), 'v3.4', _('20. 5. 2026', '20 May 2026'), '118 kB'],
        [_('Archiv starších verzí', 'Archive of older versions'), _('Všechny verze od roku 2019 s vyznačenými rozdíly.', 'Every version since 2019 with the differences highlighted.'), '—', _('průběžně', 'ongoing'), '—']
      ],
      docsNote: _('Dokumenty jsou veřejné, bez registrace. Změny hlásíme e-mailem třicet dní předem.', 'Documents are public and need no signup. Changes are emailed thirty days in advance.'),
      plansTitleOld: _('Dokumenty', 'The documents'),
      plansNote: _('Všechny verze archivujeme, takže se dá dohledat, co platilo v den podpisu.', 'Every version is archived, so you can find what applied on the day you signed.'),
      plans: [
        { name: _('Obchodní podmínky', 'Terms of service'), tag: _('Platné od 1. 1. 2026', 'Effective 1 Jan 2026'), price: 0, priceLabel: 'PDF', priceNote: _('12 stran, bez odkazů jinam', '12 pages, no cross-references'), ctaLabel: _('Otevřít VOP', 'Open the terms'), specs: [_('Práva a povinnosti', 'Rights and obligations'), _('Fakturace a splatnost', 'Billing and payment terms'), _('Výpověď a export dat', 'Termination and data export'), _('Odpovědnost a limity', 'Liability and limits'), _('Zakázané využití', 'Prohibited use'), _('Archiv starších verzí', 'Archive of older versions')] },
        { name: _('GDPR a DPA', 'GDPR and DPA'), tag: '', price: 0, priceLabel: 'PDF', priceNote: _('podepíšeme elektronicky', 'signed electronically'), ctaLabel: _('Stáhnout DPA', 'Download the DPA'), specs: [_('Seznam subdodavatelů', 'List of sub-processors'), _('Účel a rozsah zpracování', 'Purpose and scope'), _('Doba uchování', 'Retention periods'), _('Bezpečnostní opatření', 'Security measures'), _('Hlášení incidentů', 'Incident notification'), _('Data zůstávají v EU', 'Data stays in the EU')] },
        { name: _('SLA a cookies', 'SLA and cookies'), tag: '', price: 0, priceLabel: 'PDF', priceNote: _('včetně výše kreditů', 'credit levels included'), ctaLabel: _('Otevřít SLA', 'Open the SLA'), specs: [_('Garantovaná dostupnost', 'Guaranteed uptime'), _('Automatické kredity', 'Automatic credits'), _('Reakční doby podle priority', 'Response times by priority'), _('Plánované odstávky', 'Planned maintenance'), _('Seznam cookies', 'Cookie inventory'), _('Odvolání souhlasu', 'Withdrawing consent')] }
      ],
      feats: [
        ['01', _('Krátké věty, žádné odkazy do prázdna', 'Short sentences, no dead cross-references'), _('Každý dokument dává smysl sám o sobě.', 'Each document stands on its own.')],
        ['02', _('Změny s měsíčním předstihem', 'Changes with a month of notice'), _('Nová verze e-mailem, se zvýrazněnými rozdíly.', 'A new version by email, with the differences highlighted.')],
        ['03', _('Subdodavatelé na jednom seznamu', 'Sub-processors on one list'), _('Víte přesně, kdo se k datům může dostat.', 'You know exactly who can reach your data.')],
        ['04', _('Kredity vyplácíme sami', 'We pay credits ourselves'), _('Nemusíte žádat. Přepočte se v nejbližší faktuře.', 'You do not have to ask. It appears on your next invoice.')],
        ['05', _('Cookie lišta se ptá jednou', 'The cookie banner asks once'), _('Bez sledovacích skriptů před souhlasem a bez tmavých vzorů.', 'No tracking scripts before consent and no dark patterns.')],
        ['06', _('Export dat i po výpovědi', 'Data export after termination'), _('Třicet dní na stažení všeho, bez poplatku.', 'Thirty days to download everything, free of charge.')]
      ],
      tech: ['GDPR', 'DPA', 'ISO 27001', 'NIS2', 'eIDAS', _('Archiv verzí', 'Version archive')],
      bench: [
        { label: _('Onhost VOP — délka', 'Onhost terms — length'), pct: 30, note: '12 ' + _('stran', 'pages') },
        { label: _('Typický hosting v ČR', 'Typical Czech host'), pct: 78, note: '~31 ' + _('stran', 'pages') },
        { label: _('Velký cloud', 'Hyperscaler'), pct: 100, note: '~90 ' + _('stran', 'pages') }
      ],
      benchNote: _('Rozsah obchodních podmínek včetně příloh. Kratší je tu záměr.', 'Length of the terms including annexes. Shorter is deliberate here.'),
      cases: [
        { t: _('Právník klienta', 'A client legal team'), d: _('DPA se seznamem subdodavatelů a dobou uchování.', 'A DPA with sub-processors and retention periods.'), m: 'GDPR / DPA' },
        { t: _('Veřejná zakázka', 'A public tender'), d: _('SLA s pokutami a doklad o ISO 27001.', 'An SLA with penalties and ISO 27001 evidence.'), m: 'SLA' },
        { t: _('Audit cookies', 'A cookie audit'), d: _('Seznam cookies s účelem a dobou platnosti.', 'A cookie inventory with purpose and lifetime.'), m: 'Cookies' }
      ],
      faq: [
        [_('Podepíšete naši smlouvu?', 'Will you sign our contract?'), _('U enterprise ano, běžně jedeme na našich podmínkách.', 'For enterprise yes; normally we work on our own terms.')],
        [_('Kde leží osobní údaje?', 'Where does personal data live?'), _('V EU, konkrétně v Praze a Brně. Mimo EU nic.', 'In the EU — Prague and Brno. Nothing outside.')],
        [_('Jak dlouho držíte data po výpovědi?', 'How long is data kept after termination?'), _('Třicet dní na export, pak nevratně mažeme.', 'Thirty days for export, then irreversible deletion.')],
        [_('Máte pojištění odpovědnosti?', 'Do you carry liability insurance?'), _('Ano, doklad pošleme na vyžádání.', 'Yes — proof on request.')],
        [_('Jak hlásíte bezpečnostní incident?', 'How do you report a security incident?'), _('Do 24 hodin e-mailem a telefonem, s popisem dopadu.', 'Within 24 hours by email and phone, with an impact description.')]
      ]
LEGAL_PAGE_OLD;

    /**
     * What the page says instead: the documents in force from `ONHOST_DATA.legalDocs(cs)` (PublicLegalLinks::documents, the rows
     * `/dokumenty` lists, each opening its own address), and only what those documents say (versions at their own address, 14 days
     * to withdraw, 14 days' notice of a privacy-policy change, ČOI). `bench`, `kpis`, `feats`, `faq`, `plans` stay arrays; no panels.
     */
    private const LEGAL_PAGE_NEW = <<<'LEGAL_PAGE_NEW'
      cmp: null,
      cat: 'Onhost', crumb: _('Podmínky, GDPR a cookies', 'Terms, GDPR and cookies'),
      kicker: _('Dokumenty v platnosti', 'Documents in force'),
      title: _('Smluvní dokumenty, které jdou přečíst', 'Contract documents you can actually read'),
      lead: _('Obchodní podmínky, ochrana osobních údajů, SLA a další dokumenty, které právě platí. Každá verze má vlastní adresu, takže dohledáte znění platné v den objednávky.', 'The terms of service, the privacy policy, the SLA and the other documents in force. Every version has its own address, so you can find the text that applied on the day you ordered.'),
      kpis: [[String(((window.ONHOST_DATA && window.ONHOST_DATA.legalDocs) ? window.ONHOST_DATA.legalDocs(cs) : []).length), _('dokumentů v platnosti', 'documents in force')]],
      chips: [_('Verze s datem účinnosti', 'Versions with an effective date'), _('Starší verze dostupné', 'Earlier versions available'), _('Bez registrace', 'No signup')],
      plansTitle: _('Dokumenty v platnosti', 'Documents in force'),
      docs: ((window.ONHOST_DATA && window.ONHOST_DATA.legalDocs) ? window.ONHOST_DATA.legalDocs(cs) : []),
      docsNote: _('Dokumenty jsou veřejné, bez registrace. Řádek otevře znění dokumentu, u každého dokumentu jsou vypsané i jeho starší verze.', 'The documents are public and need no signup. A row opens the text; each document lists its earlier versions too.'),
      plansNote: _('Každá verze zůstává dostupná, takže se dá dohledat, co platilo v den objednávky.', 'Every version stays available, so you can find what applied on the day you ordered.'),
      plans: [],
      feats: [
        ['01', _('Každá verze na vlastní adrese', 'Every version at its own address'), _('Znění platné v den objednávky zůstává dostupné.', 'The text that applied on the day you ordered stays available.')],
        ['02', _('Verze a datum účinnosti', 'Version and effective date'), _('Uvádíme je u každého dokumentu, na stránce i v samotném textu.', 'Stated for every document, on this page and in the text itself.')],
        ['03', _('Mimosoudní řešení sporů', 'Out-of-court dispute resolution'), _('Spotřebitel se může obrátit na Českou obchodní inspekci.', 'A consumer may turn to the Czech Trade Inspection Authority.')]
      ],
      tech: [],
      bench: [],
      benchNote: _('Každý dokument uvádí svou verzi a datum účinnosti.', 'Every document states its version and effective date.'),
      cases: [
        { t: _('Spotřebitel', 'A consumer'), d: _('Poučení o právu na odstoupení se vzorovým formulářem.', 'The withdrawal notice with the model form.'), m: _('Odstoupení', 'Withdrawal') },
        { t: _('Firma, která zpracovává osobní údaje', 'A company processing personal data'), d: _('Smlouva o zpracování osobních údajů podle čl. 28 GDPR.', 'The data processing agreement under Art. 28 GDPR.'), m: 'DPA' },
        { t: _('Držitel domény', 'A domain holder'), d: _('Podmínky registrace a správy domén.', 'The domain registration and management terms.'), m: _('Domény', 'Domains') }
      ],
      faq: [
        [_('Kde najdu znění, se kterým jsem souhlasil?', 'Where do I find the text I agreed to?'), _('Každá verze má vlastní adresu. U dokumentu v platnosti jsou vypsané i jeho starší verze.', 'Every version has its own address. The document in force lists its earlier versions too.')],
        [_('Mohu od smlouvy odstoupit?', 'Can I withdraw from the contract?'), _('Spotřebitel může odstoupit do 14 dnů od uzavření smlouvy. Postup a vzorový formulář jsou v poučení o odstoupení; od provedené registrace domény odstoupit nelze.', 'A consumer may withdraw within 14 days of the contract. The withdrawal notice describes how and has a model form; a completed domain registration cannot be withdrawn from.')],
        [_('Jak se dozvím o změně zásad ochrany osobních údajů?', 'How do I learn about a change of the privacy policy?'), _('E-mailem a v panelu nejméně 14 dní předem.', 'By e-mail and in the panel at least 14 days ahead.')],
        [_('Kam se obrátit, když se neshodneme?', 'Where do I turn if we disagree?'), _('Nejdřív na nás přes panel nebo e-mailem. Spotřebitel může spor řešit mimosoudně u České obchodní inspekce.', 'First to us through the panel or by e-mail. A consumer may settle a dispute out of court with the Czech Trade Inspection Authority.')]
      ],
      endTitle: _('Otázka k dokumentům?', 'A question about the documents?'),
      endLead: _('Napište nám přes zákaznický panel nebo e-mailem.', 'Write to us through the customer panel or by e-mail.')
LEGAL_PAGE_NEW;

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
                // GPU (I-R16: on the site as "coming", no stock and no delivery promise; found by the K7 browser check): the free-card
                // count and the start time go, and the title no longer says the cards stand in Prague
                "title: _('H100 v Praze. Vaše data zůstanou v Česku.', 'H100s in Prague. Your data stays in the country.')," => "title: _('GPU instance připravujeme', 'GPU instances are coming'),",
                "kpis: [['7 / 24', _('volných karet H100', 'H100 cards free')], ['90 s', _('start instance', 'instance start')], ['0', _('přenosů dat mimo EU', 'transfers outside the EU')]]," => 'kpis: [],',
                // colocation: PUE and the share of renewables
                "kpis: [['1,18', 'PUE'], ['2N', _('napájení a UPS', 'power and UPS')], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')]]," => "kpis: [['2N', _('napájení a UPS', 'power and UPS')]],",
                "        { label: _('Onhost Praha (BESS + obnovitelné)', 'Onhost Prague (BESS + renewables)'), pct: 100, note: 'PUE 1,18' },\n        { label: _('Průměrné evropské datacentrum', 'Average European data centre'), pct: 62, note: 'PUE 1,55' },\n        { label: _('Vlastní serverovna ve firmě', 'In-house company server room'), pct: 32, note: 'PUE 2,4' }\n" => '',
                "benchNote: _('PUE měřené za 12 měsíců včetně chlazení a rozvodů; nižší je lepší, sloupec ukazuje účinnost.', 'PUE measured over 12 months including cooling and distribution; lower is better, the bar shows efficiency.')," => $pueNote,
            ],
            'onhost-svc-corp.js' => [
                // the SLA band: the share of renewables (K8b); then (L3 / M1) what the SLA in force says — 99,9 % and a 4-hour incident
                // response for Standard, compensation claimed in the panel (not "paid automatically"), no named contact, no six locations
                "    lead: _('Smluvní SLA, jmenovaný kontakt a kredity vyplácené automaticky. Žádné výjimky psané malým písmem.', 'Contractual SLA, a named contact and credits paid automatically. No exceptions in small print.'),
    rows: [
      { k: '99,99 %', v: _('dostupnost s pokutou', 'uptime with penalties') },
      { k: '15 min', v: _('reakce na P1 24/7', 'P1 response, 24/7') },
      { k: '6', v: _('lokalit v Evropě', 'European locations') },
      { k: '62 %', v: _('energie z obnovitelných zdrojů', 'energy from renewables') }" => "    lead: _('Smluvní SLA: dostupnost, reakce na incident a kompenzace podle třídy služby, jak je uvádí dokument SLA v platnosti.', 'A contractual SLA: availability, incident response and compensation by service class, as the SLA in force states them.'),
    rows: [
      { k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)', 'monthly availability (Standard, SLA)') },
      { k: '4 h', v: _('reakce na incident (Standard)', 'incident response (Standard)') }",
                // the page "Podmínky, GDPR a cookies" (L3 / M1)
                self::LEGAL_PAGE_OLD => self::LEGAL_PAGE_NEW,
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

    /**
     * L3 / M1: a row of the document list in `Onhost.dc.html` (the service page's `docs`) only swallowed the click; it now opens the
     * address the server gave the row (`d[5]`, a `/dokumenty/<slug>` path from PublicLegalLinks::documents). Rows without one (the
     * other pages' prototype rows) keep doing nothing.
     *
     * @return array<string, string> needle => replacement in the public surface
     */
    public static function documentRowSwaps(): array
    {
        return [
            "        openLabel: cs ? 'Otevřít' : 'Open', pdfLabel: cs ? 'PDF' : 'PDF',\n        on: (e) => { if (e && e.preventDefault) e.preventDefault(); }\n" => "        openLabel: cs ? 'Otevřít' : 'Open', pdfLabel: cs ? 'PDF' : 'PDF',\n        on: (e) => { if (e && e.preventDefault) e.preventDefault(); if (d[5]) window.location.assign(d[5]); }\n",
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
