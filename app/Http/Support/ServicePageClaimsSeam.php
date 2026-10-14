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
 *
 * N1 (phase N, the rest of the unsupported figures): the compute pages' SLA band (99,99 % "network and power uptime", a P1 response
 * "< 15 min", credits "converted automatically"), the VPS "root in 55 s", "0 % CPU oversubscription" and "99,99 % uptime in 2025",
 * the managed-server page's 15- and 60-minute responses, "SLA 99,99 % with credits" and its "measured" 15-minute median, and on every
 * service page of Onhost.dc.html a chat engineer "within ten minutes" and a 14-day trial "without a card" (`pageCopySwaps`). Where a
 * figure is replaced, the replacement is what a document in force says: SLA 2026-09 (resources/legal/sla.md čl. 2 and 4 — Standard
 * 99,9 % with a response within 4 hours, Business 99,95 % within 1 hour, compensation claimed in the panel and credited) and the VOP
 * (resources/legal/terms.md čl. 2 bod 2, čl. 5 bod 3 and čl. 8 bod 1 — paid in advance for a month or a year, notice for the end of
 * the prepaid period, a consumer withdraws within 14 days). The 99,99 % class of SLA 2026-09 is "by individual contract", never a
 * plan's promise.
 *
 * O1 (phase O): the compute SLA band's "2× N+1" and "10 Gbit/s", the VPS sold as "not oversold" with a public steal-time figure
 * (the catalogue sells it on shared cores), "SLA 99,99 %" in the plans and tables of pages no catalogue product overlays (inference,
 * vector database, object storage, the solution pages) and the home page's free trial (`homeCopySwaps`).
 */
final class ServicePageClaimsSeam
{
    /** N1: the SLA band of the compute pages, as the corporate pages' band says it since L3 / M1 (SLA 2026-09, čl. 2). */
    private const SLA_BAND_NEW = "    lead: _('Smluvní SLA: dostupnost, reakce na incident a kompenzace podle třídy služby, jak je uvádí dokument SLA v platnosti.', 'A contractual SLA: availability, incident response and compensation by service class, as the SLA in force states them.'),\n    rows: [\n      { k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)', 'monthly availability (Standard, SLA)') },\n      { k: '4 h', v: _('reakce na incident (Standard)', 'incident response (Standard)') },\n";

    /** N1: a managed-server plan answers an incident within the time its SLA class sets (SLA 2026-09, čl. 2). */
    private const RESPONSE_PER_SLA = "_('Reakce na incident podle SLA', 'Incident response per the SLA')";

    /** O1: SLA 2026-09 knows 99,99 % only "by individual contract" — never as the promise of a plan. */
    private const SLA_BY_CONTRACT = "_('SLA podle individuální smlouvy', 'SLA by individual contract')";

    /** O1: the comparison row of a page the catalogue does not overlay — Standard and Business as SLA 2026-09 states them. */
    private const SLA_ROW_OLD = "['SLA', '99,9 %', '99,95 %', '99,99 %']";

    private const SLA_ROW_NEW = "['SLA', '99,9 %', '99,95 %', _('podle smlouvy', 'per contract')]";

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

    /** P1 (P-R37, OP-1): support answers Mon–Fri 08:00–17:00 Europe/Prague through the panel and e-mail. */
    private const SUPPORT_HOURS_ROW = "{ k: _('po–pá 8–17', 'Mon–Fri 8–17'), v: _('podpora přes panel a e-mail', 'support via the panel and e-mail') }";

    /** P2 (P-R69, DD-1): DDoS protection is not documented; only a documented figure may come back. */
    private const DDOS_UNDOCUMENTED = "_('Parametry ochrany proti DDoS (kapacitu, vrstvy a dobu reakce) uvedeme, až je doložíme. Do té doby je neslibujeme.', 'We will state DDoS protection figures (capacity, layers and response time) once they are documented. Until then we do not promise them.')";

    /** P1: the support page of onhost-svc-other.js (the last page of the module), from its key to the end of the module. */
    private const SUPPORT_PAGE_FROM = "    'support': C({\n";

    private const SUPPORT_PAGE_TO = "    })\n  };\n}";

    /** P1 (P-R37, OP-1): what support is — channels, hours, the SLA's incident response — and nothing measured that was not. */
    private const SUPPORT_PAGE_NEW = <<<'SUPPORT_PAGE_NEW'
    'support': C({
      panelsTitle: _('Jak podpora funguje', 'How support works'), panelsLead: _('Podpora odpovídá v pracovní dny přes zákaznický panel a e-mail.', 'Support answers on working days through the customer panel and e-mail.'),
      panels: [
        [_('Kanály a doby', 'Channels and hours'), _('Tiket v zákaznickém panelu nebo e-mail, po–pá 8:00–17:00 (Europe/Prague). Tikety a e-maily přijímáme i mimo tuto dobu a odpovíme v nejbližší pracovní době.', 'A ticket in the customer panel or e-mail, Mon–Fri 8:00–17:00 (Europe/Prague). Tickets and e-mails are received outside these hours too and answered in the next working hours.'), [[_('Pracovní doba', 'Hours'), _('po–pá 8–17', 'Mon–Fri 8–17')], [_('Tiket a e-mail', 'Ticket and e-mail'), '✓'], [_('Telefon', 'Phone'), _('výpadek v pracovní době', 'outage in working hours')]]],
        [_('Incident podle SLA', 'Incidents per the SLA'), _('Dobu reakce na incident stanoví SLA podle třídy služby.', 'The SLA sets the incident response time by service class.'), [['Standard', '4 h'], ['Business', '1 h']]]
      ],
      cmp: null,
      plansTitle: cs ? 'Podpora' : 'Support', plansNote: cs ? 'Podpora je u všech tarifů v ceně.' : 'Support is included with every plan.',
      cat: _('Podpora', 'Support'), crumb: _('Kontakt a podpora', 'Contact and support'),
      kicker: _('Po–pá 8:00–17:00 · panel a e-mail', 'Mon–Fri 8:00–17:00 · panel and e-mail'),
      title: _('Kontakt a podpora', 'Contact and support'),
      lead: _('Napište nám tiket v zákaznickém panelu nebo e-mail. Odpovídáme v pracovní dny 8:00–17:00; telefon slouží pro výpadek služby v pracovní době.', 'Open a ticket in the customer panel or send an e-mail. We answer on working days 8:00–17:00; the phone is for a service outage in working hours.'),
      kpis: [],
      chips: [_('Tikety v panelu', 'Tickets in the panel'), _('E-mail', 'Email'), _('Česky a anglicky', 'Czech and English')],
      plans: [
        { name: _('Standardní', 'Standard'), tag: _('V ceně', 'Included'), price: 0, priceLabel: _('V ceně', 'Included'), priceNote: _('u všech tarifů', 'on every plan'), ctaLabel: _('Napsat tiket', 'Open a ticket'), specs: [_('Tiket a e-mail', 'Ticket and e-mail'), _('Po–pá 8:00–17:00', 'Mon–Fri 8:00–17:00'), _('Reakce na incident podle SLA', 'Incident response per the SLA')] }
      ],
      feats: [
        ['01', _('Tiket v panelu', 'A ticket in the panel'), _('Tiket i celou jeho historii najdete v zákaznickém panelu.', 'The ticket and its whole history are in the customer panel.')],
        ['02', _('Reakce podle SLA', 'Response per the SLA'), _('Standard do 4 hodin, Business do 1 hodiny, jak uvádí SLA v platnosti.', 'Standard within 4 hours, Business within 1 hour, as the SLA in force states.')]
      ],
      tech: [_('Tikety', 'Tickets'), 'E-mail'],
      bench: [],
      benchNote: _('Dobu odpovědi zveřejníme, až ji doložíme měřením.', 'We will publish response times once they are backed by measurement.'),
      cases: [],
      faq: [
        [_('Kdy podpora odpovídá?', 'When does support answer?'), _('V pracovní dny 8:00–17:00 (Europe/Prague). Tikety a e-maily přijímáme i mimo tuto dobu.', 'On working days 8:00–17:00 (Europe/Prague). Tickets and e-mails are received outside these hours too.')],
        [_('Mluvíte česky?', 'Do you speak Czech?'), _('Ano. Anglicky také.', 'Yes. English too.')],
        [_('Kde najdu telefon?', 'Where is the phone number?'), _('V patičce webu. Telefon slouží pro výpadek služby v pracovní době; ostatní dotazy prosím tiketem nebo e-mailem.', 'In the site footer. The phone is for a service outage in working hours; anything else by ticket or e-mail, please.')]
      ]
    })
  };
}
SUPPORT_PAGE_NEW;

    /** Q1: the enterprise page of onhost-svc-other.js, from its key to the next page's key. */
    private const ENTERPRISE_PAGE_FROM = "    'enterprise': C({\n";

    private const ENTERPRISE_PAGE_TO = "    }),\n\n    'green': C({";

    /**
     * Q1 (phase Q): what a company gets is what SLA 2026-09 says (resources/legal/sla.md čl. 2 and 4: Standard 99,9 % with a reply
     * within 4 hours, Business 99,95 % within 1 hour, a higher level only by individual contract, compensation claimed in the panel
     * and credited), the DPA in force and the support hours (P-R37). No price list of its own, no NIS2/ISO claim, no measured uptime.
     */
    private const ENTERPRISE_PAGE_NEW = <<<'ENTERPRISE_PAGE_NEW'
    'enterprise': C({
      panelsTitle: _('Jak se domluvíme', 'How we agree'), panelsLead: _('Firemní spolupráci stavíme na SLA v platnosti a na smlouvě, kterou spolu uzavřeme.', 'Business work rests on the SLA in force and on the contract we sign together.'),
      panels: [
        [_('SLA podle třídy služby', 'SLA by service class'), _('Dostupnost a reakci na incident stanoví SLA v platnosti; vyšší úroveň jen podle individuální smlouvy.', 'Availability and incident response are set by the SLA in force; a higher level only by individual contract.'), [['Standard', _('99,9 % · reakce do 4 h', '99.9% · response within 4 h')], ['Business', _('99,95 % · reakce do 1 h', '99.95% · response within 1 h')], [_('Individuální', 'Individual'), _('podle smlouvy', 'per contract')]]],
        [_('Podpora', 'Support'), _('Tiket v zákaznickém panelu nebo e-mail, po–pá 8:00–17:00 (Europe/Prague).', 'A ticket in the customer panel or e-mail, Mon–Fri 8:00–17:00 (Europe/Prague).'), [[_('Pracovní doba', 'Hours'), _('po–pá 8–17', 'Mon–Fri 8–17')], [_('Tiket a e-mail', 'Ticket and e-mail'), '✓']]]
      ],
      cmp: null,
      plansTitle: cs ? 'Firemní nabídka' : 'Business offer', plansNote: cs ? 'Nabídku připravíme podle rozsahu; podmínky stanoví smlouva a SLA v platnosti.' : 'We prepare the offer by scope; the contract and the SLA in force set the terms.',
      cat: 'Onhost', crumb: 'Enterprise a SLA',
      kicker: _('SLA v platnosti · individuální smlouva', 'The SLA in force · individual contract'),
      title: _('Služby pro firmy podle smlouvy', 'Services for companies under contract'),
      lead: _('Pro firmy připravíme nabídku podle rozsahu. Dostupnost a reakci na incident stanoví SLA v platnosti: Standard 99,9 % a reakce do 4 hodin, Business 99,95 % a reakce do 1 hodiny; vyšší úroveň jen podle individuální smlouvy.', 'We prepare an offer for companies by scope. The SLA in force sets availability and incident response: Standard 99.9% with a response within 4 hours, Business 99.95% within 1 hour; a higher level only by individual contract.'),
      kpis: [],
      chips: ['SLA', 'GDPR / DPA', _('Individuální smlouva', 'Individual contract')],
      plans: [
        { name: _('Firemní nabídka', 'Business offer'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('podle rozsahu', 'by scope'), ctaLabel: _('Vyžádat nabídku', 'Request an offer'), specs: [_('SLA Standard nebo Business', 'SLA Standard or Business'), _('SLA podle individuální smlouvy', 'SLA by individual contract'), _('Zpracovatelská smlouva (DPA)', 'Data processing agreement (DPA)'), _('Podpora po–pá 8–17', 'Support Mon–Fri 8–17')] }
      ],
      feats: [
        ['01', _('Kompenzace kreditem', 'Compensation as credit'), _('Kompenzaci za nedodrženou dostupnost uplatníte v zákaznickém panelu a připíšeme ji jako kredit (SLA).', 'Compensation for missed availability is claimed in the customer panel and credited (SLA).')],
        ['02', _('Zpracovatelská smlouva', 'A data processing agreement'), _('DPA je jedním z dokumentů v platnosti.', 'The DPA is one of the documents in force.')]
      ],
      tech: [],
      bench: [],
      benchNote: _('Naměřenou dostupnost zveřejníme, až ji doložíme měřením.', 'We will publish measured availability once it is backed by measurement.'),
      cases: [],
      faq: [
        [_('Jaké SLA nabízíte?', 'Which SLA do you offer?'), _('Standard 99,9 % s reakcí na incident do 4 hodin a Business 99,95 % do 1 hodiny, jak uvádí SLA v platnosti. Vyšší úroveň jen podle individuální smlouvy.', 'Standard 99.9% with an incident response within 4 hours and Business 99.95% within 1 hour, as the SLA in force states. A higher level only by individual contract.')],
        [_('Jak uplatním kompenzaci?', 'How do I claim compensation?'), _('V zákaznickém panelu; připíšeme ji jako kredit.', 'In the customer panel; it is credited to your account.')],
        [_('Kdy podpora odpovídá?', 'When does support answer?'), _('V pracovní dny 8:00–17:00 (Europe/Prague), tiketem nebo e-mailem.', 'On working days 8:00–17:00 (Europe/Prague), by ticket or e-mail.')]
      ]
    }),

    'green': C({
ENTERPRISE_PAGE_NEW;

    /** Q1: the about page of onhost-svc-other.js, from its key to the next page's key. */
    private const ABOUT_PAGE_FROM = "    'about': C({\n";

    private const ABOUT_PAGE_TO = "    }),\n\n    'careers': C({";

    /** Q1 (phase Q): who runs Onhost is what the terms and the price list say — no head count, customer count, sites or NOC. */
    private const ABOUT_PAGE_NEW = <<<'ABOUT_PAGE_NEW'
    'about': C({
      panelsTitle: _('Jak Onhost funguje', 'How Onhost works'), panelsLead: _('Co nabízíme, za kolik a za jakých podmínek, je na webu ve verzi, která platí.', 'What we offer, at what price and on what terms is on the site in the version in force.'),
      panels: [
        [_('Ceny a podmínky', 'Prices and terms'), _('Ceník, obchodní podmínky, SLA a zásady zpracování osobních údajů jsou veřejné.', 'The price list, the terms, the SLA and the privacy policy are public.'), [[_('Ceník', 'Price list'), '✓'], [_('Dokumenty v platnosti', 'Documents in force'), '✓']]],
        [_('Podpora', 'Support'), _('Tiket v zákaznickém panelu nebo e-mail, po–pá 8:00–17:00 (Europe/Prague).', 'A ticket in the customer panel or e-mail, Mon–Fri 8:00–17:00 (Europe/Prague).'), [[_('Pracovní doba', 'Hours'), _('po–pá 8–17', 'Mon–Fri 8–17')]]]
      ],
      cmp: null,
      plansTitle: cs ? 'Kontakt' : 'Contact', plansNote: cs ? 'Provozovatele (název, IČO a sídlo) uvádějí obchodní podmínky.' : 'The terms name the operator (name, company ID and registered office).',
      cat: 'Onhost', crumb: _('O nás', 'About us'),
      kicker: _('Hosting, domény a servery · podpora po–pá 8–17', 'Hosting, domains and servers · support Mon–Fri 8–17'),
      title: _('Kdo Onhost provozuje', 'Who runs Onhost'),
      lead: _('Služby, které nabízíme, jejich ceny a podmínky uvádí ceník a dokumenty v platnosti. Provozovatele (název, IČO a sídlo) najdete v obchodních podmínkách.', 'The price list and the documents in force state the services we offer, their prices and terms. The terms name the operator (name, company ID and registered office).'),
      kpis: [],
      chips: [_('Veřejný ceník', 'Public price list'), _('Dokumenty v platnosti', 'Documents in force'), _('Podpora po–pá 8–17', 'Support Mon–Fri 8–17')],
      plans: [
        { name: _('Kontakt', 'Contact'), tag: '', price: 0, priceLabel: _('Panel a e-mail', 'Panel and e-mail'), priceNote: _('po–pá 8:00–17:00', 'Mon–Fri 8:00–17:00'), ctaLabel: _('Napsat nám', 'Write to us'), specs: [_('Tiket v zákaznickém panelu', 'A ticket in the customer panel'), _('E-mail', 'E-mail'), _('Telefon při výpadku v pracovní době', 'Phone for an outage in working hours')] }
      ],
      feats: [
        ['01', _('Ceny a podmínky veřejně', 'Prices and terms in the open'), _('Ceník, obchodní podmínky, SLA a zásady zpracování osobních údajů jsou na webu ve verzi, která platí.', 'The price list, the terms, the SLA and the privacy policy are on the site in the version in force.')],
        ['02', _('Podpora v pracovní dny', 'Support on working days'), _('Tiket nebo e-mail, po–pá 8:00–17:00.', 'Ticket or e-mail, Mon–Fri 8:00–17:00.')],
        ['03', _('Jen doložená čísla', 'Only documented figures'), _('Lokality, certifikace a provozní čísla uvedeme, až je doložíme.', 'We will state locations, certifications and operating figures once they are documented.')]
      ],
      tech: [],
      bench: [],
      benchNote: _('Provozní čísla zveřejníme, až je doložíme.', 'We will publish operating figures once they are documented.'),
      cases: [],
      faq: [
        [_('Kdo Onhost provozuje?', 'Who operates Onhost?'), _('Provozovatele (název, IČO a sídlo) uvádějí obchodní podmínky.', 'The terms name the operator (name, company ID and registered office).')],
        [_('Jak vás kontaktuji?', 'How do I reach you?'), _('Tiketem v zákaznickém panelu nebo e-mailem, po–pá 8:00–17:00.', 'By ticket in the customer panel or by e-mail, Mon–Fri 8:00–17:00.')]
      ]
    }),

    'careers': C({
ABOUT_PAGE_NEW;

    /** Q1: the data-centre page of onhost-svc-corp.js, from its key to the next page's key. */
    private const DATACENTERS_PAGE_FROM = "    'datacenters': C({\n";

    private const DATACENTERS_PAGE_TO = "    }),\n\n    'partners': C({";

    /** Q1 (phase Q): no six sites, own halls, TIER III, ISO 27001, 2N or 24/7 NOC — the order and the panel say where a service runs. */
    private const DATACENTERS_PAGE_NEW = <<<'DATACENTERS_PAGE_NEW'
    'datacenters': C({
      panelsTitle: _('Kde služby běží', 'Where services run'), panelsLead: _('Kde konkrétní služba běží, vám sdělí podpora.', 'Support tells you where a particular service runs.'),
      panels: [
        [_('Lokalita služby', 'Service location'), _('Kde služba běží, vám sdělí podpora (tiket nebo e-mail).', 'Support tells you where a service runs (ticket or e-mail).'), [[_('Dotaz na podporu', 'Ask support'), _('po–pá 8–17', 'Mon–Fri 8–17')]]],
        [_('Parametry datacenter', 'Data-centre figures'), _('Certifikace, napájení, chlazení a konektivitu uvedeme, až je doložíme. Do té doby je neslibujeme.', 'We will state certifications, power, cooling and connectivity once they are documented. Until then we do not promise them.'), []]
      ],
      cmp: null,
      cat: 'Onhost', crumb: _('Datacentra', 'Data centres'),
      kicker: _('Lokality služeb', 'Service locations'),
      title: _('Datacentra a lokality', 'Data centres and locations'),
      lead: _('Kde konkrétní služba běží, vám sdělí podpora. Parametry datacenter (certifikace, napájení, chlazení a konektivitu) zveřejníme, až je doložíme; do té doby je neslibujeme.', 'Support tells you where a particular service runs. We will publish data-centre figures (certifications, power, cooling and connectivity) once they are documented; until then we do not promise them.'),
      kpis: [],
      chips: [],
      plansTitle: _('Lokality', 'Locations'),
      plansNote: _('Lokalitu konkrétní služby vám sdělí podpora.', 'Support tells you the location of a particular service.'),
      plans: [
        { name: _('Lokalita podle služby', 'Location per service'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('sdělí podpora', 'support tells you'), ctaLabel: _('Napsat nám', 'Write to us'), specs: [_('Lokalitu sdělí podpora', 'Support tells you the location'), _('Tiket nebo e-mail', 'Ticket or e-mail')] }
      ],
      feats: [
        ['01', _('Jen doložené parametry', 'Only documented figures'), _('Certifikace, napájení a konektivitu uvedeme, až je doložíme.', 'We will state certifications, power and connectivity once they are documented.')]
      ],
      tech: [],
      bench: [],
      benchNote: _('Účinnost (PUE) zveřejníme, až ji doložíme měřením.', 'We will publish efficiency (PUE) once it is backed by measurement.'),
      cases: [],
      faq: [
        [_('Kde služba poběží?', 'Where will my service run?'), _('Zeptejte se podpory, lokalitu konkrétní služby vám sdělí.', 'Ask support; they will tell you where a particular service runs.')]
      ]
    }),

    'partners': C({
DATACENTERS_PAGE_NEW;

    /** @return array<string, array<string, string>> script => [needle => replacement] (R1: PhaseRCopySeam::scriptSwaps() merged in) */
    public static function replacements(): array
    {
        $all = self::claimSwaps();
        foreach (PhaseRCopySeam::scriptSwaps() as $script => $swaps) {
            $all[$script] = ($all[$script] ?? []) + $swaps;
        }
        foreach (PhaseTCopySeam::scriptSwaps() as $script => $swaps) { // T4: the leftovers S2 found
            $all[$script] = ($all[$script] ?? []) + $swaps;
        }

        return $all;
    }

    /** @return array<string, array<string, string>> script => [needle => replacement] (J-R2 … Q1) */
    private static function claimSwaps(): array
    {
        $pueNote = "benchNote: _('".self::PUE_NOTE_CS."', '".self::PUE_NOTE_EN."'),";

        return [
            'onhost-svc-compute.js' => [
                // VPS: the benchmark row named one EPYC model
                // P3 (P-R70, DD-2): the VPS Geekbench rows — 1 842 pts, a "global cloud" and "VPS with oversold cores" — were never measured
                "        { label: _('Onhost VPS 4 (EPYC 9354)', 'Onhost VPS 4 (EPYC 9354)'), pct: 100, note: '1 842 pts' },\n        { label: _('Srovnatelný VPS u globálního cloudu', 'Comparable VPS at a global cloud'), pct: 71, note: '1 310 pts' },\n        { label: _('VPS s přeprodanými jádry', 'VPS with oversold cores'), pct: 43, note: '790 pts' }\n" => '',
                "benchNote: _('Geekbench 6 multi-core, 4 vCPU, medián z deseti běhů v různých denních hodinách.', 'Geekbench 6 multi-core, 4 vCPU, median of ten runs across different times of day.')," => "benchNote: _('Výkon VPS zveřejníme, až ho doložíme měřením.', 'We will publish VPS performance once it is backed by measurement.'),",
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
                "kpis: [['1,18', 'PUE'], ['2N', _('napájení a UPS', 'power and UPS')], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')]]," => 'kpis: [],', // P3: no "2N" either
                "        { label: _('Onhost Praha (BESS + obnovitelné)', 'Onhost Prague (BESS + renewables)'), pct: 100, note: 'PUE 1,18' },\n        { label: _('Průměrné evropské datacentrum', 'Average European data centre'), pct: 62, note: 'PUE 1,55' },\n        { label: _('Vlastní serverovna ve firmě', 'In-house company server room'), pct: 32, note: 'PUE 2,4' }\n" => '',
                "benchNote: _('PUE měřené za 12 měsíců včetně chlazení a rozvodů; nižší je lepší, sloupec ukazuje účinnost.', 'PUE measured over 12 months including cooling and distribution; lower is better, the bar shows efficiency.')," => $pueNote,
                // N1: the SLA band of every compute page — 99,99 % and "< 15 min" are not the SLA in force, and a credit is claimed in
                // the panel (SLA 2026-09 čl. 4), not converted automatically; the spare-hardware promise goes with the sentence
                "    lead: _('Každá minuta nad limit se přepočítá na kredit automaticky. Hardware držíme v rezervě, takže výměna disku neznamená výpadek služby.', 'Every minute over the limit converts to credit automatically. We keep spare hardware, so a disk swap does not mean an outage.'),\n    rows: [\n      { k: '99,99 %', v: _('dostupnost sítě a napájení', 'network and power uptime') },\n      { k: '< 15 min', v: _('reakce na incident P1', 'P1 incident response') },\n" => self::SLA_BAND_NEW,
                // N1: VPS — "root in 55 s", "0 % CPU oversubscription" and "99,99 % uptime in 2025" were never measured
                "kicker: _('AMD EPYC · NVMe · root do 55 s', 'AMD EPYC · NVMe · root in 55 s')," => "kicker: _('AMD EPYC · NVMe', 'AMD EPYC · NVMe'),",
                "kpis: [['55 s', _('od objednávky k rootu', 'from order to root')], ['0 %', _('přeprodej CPU', 'CPU oversubscription')], ['99,99 %', _('dostupnost 2025', 'uptime in 2025')]]," => 'kpis: [],',
                // O1: the SLA band's "2× N+1 power and cooling" and "10 Gbit/s as standard" — no document states either
                "      { k: '2× N+1', v: _('napájení a chlazení', 'power and cooling') },\n      { k: '10 Gbit/s', v: _('konektivita v základu', 'connectivity as standard') }\n" => '',
                // O1: VPS — the catalogue sells it on shared cores (cpu_class `shared`, Compute 8/16 `performance`); dedicated (pinned)
                // cores are the VDS. No "not oversold", no steal time "under 0,5 %, measured publicly"; title and lead from the catalogue
                "title: _('VPS s dedikovanými jádry, ne s přeprodaným výkonem', 'A VPS with dedicated cores, not oversold capacity')," => "title: _('VPS na KVM s hodinovým účtováním a měsíčním stropem', 'A KVM VPS billed by the hour with a monthly cap'),",
                "lead: _('Jádra AMD EPYC vyhrazená jen vám, NVMe v RAID10, snapshoty po hodinách a plný root. Konfiguraci měníte za provozu a platíte po hodinách.', 'AMD EPYC cores reserved for you alone, NVMe in RAID10, hourly snapshots and full root. Resize live and pay by the hour.')," => "lead: _('Virtuální server na KVM (Proxmox VE) s NVMe, snapshoty a plným rootem. Platíte po hodinách s měsíčním stropem, IPv6 je v ceně.', 'A virtual server on KVM (Proxmox VE) with NVMe, snapshots and full root. Billed by the hour with a monthly cap, IPv6 included.'),",
                "['01', _('Jádra jen pro vás', 'Cores only for you'), _('Nepřeprodáváme CPU. Steal time držíme pod 0,5 %, a měříme to veřejně.', 'We do not oversell CPU. Steal time stays under 0.5%, and we publish the measurement.')]," => "['01', _('Třída CPU podle tarifu', 'CPU class by plan'), _('Menší tarify běží na sdílených jádrech, větší ve výkonnější třídě. Vyhrazená jádra má VDS.', 'Smaller plans run on shared cores, larger ones in a performance class. Dedicated cores come with a VDS.')],",
                // O1: inference and the vector database (no catalogue product, so nothing overlays plans or table): no 99,99 % plan
                "_('Vlastní domény endpointů', 'Custom endpoint domains'), 'SLA 99,99 %'" => "_('Vlastní domény endpointů', 'Custom endpoint domains'), ".self::SLA_BY_CONTRACT,
                "['SLA', '—', '99,95 %', '99,99 %']" => "['SLA', '—', '99,95 %', _('podle smlouvy', 'per contract')]",
                "_('GPU indexace', 'GPU indexing'), 'SLA 99,99 %'" => "_('GPU indexace', 'GPU indexing'), ".self::SLA_BY_CONTRACT,
                // P3 (P-R70, DD-2): colocation "2N power, N+1 cooling", 2N UPS and "2N + generator" — no document states them
                "kicker: _('TIER III · 2N napájení · 6 lokalit', 'TIER III · 2N power · 6 locations')," => "kicker: _('Colocation · prostor v racku', 'Colocation · rack space'),",
                "_('Od jedné U po celý rack, s 2N napájením, chlazením N+1 a přístupem 24/7. " => "_('Od jedné U po celý rack. ", // Q1: no 24/7 access either
                "'From a single U to a whole rack, with 2N power, N+1 cooling and 24/7 access. " => "'From a single U to a whole rack. ",
                "chips: ['TIER III', '2N UPS', " => 'chips: [', // Q1: no TIER III either (no certificate or document states it)
                "'2,5 kW', _('2N napájení', '2N power'), " => "'2,5 kW', ",
                "'7 kW', _('2N + generátor', '2N + generator'), " => "'7 kW', ",
                // Q1 (phase Q): colocation "24/7 access" and "24/7 badge" — no document states an access regime; access is arranged
                "_('BESS baterie', 'BESS storage'), _('Přístup 24/7', '24/7 access'), " => "_('BESS baterie', 'BESS storage'), ",
                ", _('Přístup 24/7 na kartu', '24/7 badge access')] }," => '] },',
                "[_('Fyzický přístup', 'Physical access'), _('Na ohlášení', 'On request'), _('24/7 na kartu', '24/7 badge'), _('24/7 na kartu', '24/7 badge')]," => "[_('Fyzický přístup', 'Physical access'), _('Po dohodě', 'By arrangement'), _('Po dohodě', 'By arrangement'), _('Po dohodě', 'By arrangement')],",
                "_('U racku a čtvrtracku 24/7 na kartu s dvoufaktorem, u 1U na ohlášení do 2 hodin.', 'Full and quarter racks get 24/7 two-factor badge access; 1U is on request within 2 hours.')" => "_('Fyzický přístup k racku domlouváme předem.', 'Physical access to the rack is arranged in advance.')",
                // Q3 (P-R77): the vector database's "14 ms p95" and "98,6 % recall@10" were never measured
                "kpis: [['14 ms', _('p95 dotaz nad 5 mil. vektorů', 'p95 query over 5M vectors')], ['98,6 %', 'recall@10'], " => 'kpis: [',
                "        { label: _('Vector Pro (HNSW, NVMe)', 'Vector Pro (HNSW, NVMe)'), pct: 100, note: '14 ms p95' },\n        { label: _('Sdílená cloudová vektorová služba', 'Shared cloud vector service'), pct: 47, note: '30 ms p95' },\n        { label: _('pgvector bez ladění indexu', 'pgvector with untuned index'), pct: 22, note: '64 ms p95' }\n" => '',
                "benchNote: _('5 mil. vektorů dimenze 1024, recall@10 nad 98 %, 50 souběžných dotazů.', '5M vectors at 1024 dimensions, recall@10 above 98%, 50 concurrent queries.')," => "benchNote: _('Výkon vektorové databáze zveřejníme, až ho doložíme měřením.', 'We will publish vector database performance once it is backed by measurement.'),",
                "          [_('Napájení', 'Power feeds'), '2×', '2N', _('2N + generátor', '2N + generator')],\n" => '',
                "_('Dvě nezávislé trasy, UPS 2N, dieselagregát s palivem na 72 hodin.', 'Two independent feeds, 2N UPS, diesel generator with 72 hours of fuel.')" => "_('Dvě nezávislé napájecí trasy, UPS a dieselagregát.', 'Two independent feeds, UPS and a diesel generator.')",
            ],
            'onhost-svc-dev.js' => [
                // N1: managed servers — no 15- or 60-minute response, no 99,99 % and no measured median: the SLA class decides
                "kpis: [['15 min', _('reakce na incident 24/7', 'incident response, 24/7')], ['99,99 %', _('dostupnost s kreditem', 'uptime, credited')], [_('0 zásahů', 'zero touches'), _('měsíčně na vaší straně', 'a month on your side')]]," => "kpis: [[_('0 zásahů', 'zero touches'), _('měsíčně na vaší straně', 'a month on your side')]],",
                "_('Reakce do 60 minut', '60-minute response')" => self::RESPONSE_PER_SLA,
                "_('Reakce do 15 minut', '15-minute response'), _('SLA 99,99 % s kreditem', '99.99% SLA with credits')" => self::RESPONSE_PER_SLA.", _('SLA s kompenzací kreditem', 'SLA with compensation as credit')",
                "[_('Reakce', 'Response'), '60 min', '15 min', _('podle smlouvy', 'per contract')]," => "[_('Reakce', 'Response'), _('podle SLA', 'per the SLA'), _('podle SLA', 'per the SLA'), _('podle smlouvy', 'per contract')],",
                "['SLA', '99,9 %', '99,99 %', _('na míru', 'bespoke')]" => "['SLA', '99,9 %', _('podle třídy služby', 'by service class'), _('na míru', 'bespoke')]",
                "        { label: _('Onhost — reakce na P1', 'Onhost — P1 response'), pct: 100, note: '15 min' },\n        { label: _('Externí správce (typicky)', 'External admin (typical)'), pct: 25, note: '~2 h' },\n        { label: _('Vlastní admin na telefonu', 'In-house admin on call'), pct: 45, note: '~45 min' }\n" => '',
                "benchNote: _('Medián doby od alertu k prvnímu zásahu člověka, 12měsíční okno.', 'Median time from alert to first human action, 12-month window.')," => "benchNote: _('Dobu reakce na incident stanoví SLA podle třídy služby: Standard do 4 hodin, Business do 1 hodiny.', 'The SLA sets the incident response by service class: Standard within 4 hours, Business within 1 hour.'),",
                // O1: object storage (draft product, no overlay) — the Archive plan and the comparison row
                "_('Export na disk poštou', 'Disk export by post'), _('SLA 99,99 %', '99.99% SLA')" => "_('Export na disk poštou', 'Disk export by post'), ".self::SLA_BY_CONTRACT,
                self::SLA_ROW_OLD => self::SLA_ROW_NEW,
                // P1 (P-R37, OP-1): support works Mon–Fri 08:00–17:00 — no "24/7 hands-on" and no night-time production on-call
                "kicker: _('Updaty · monitoring · zásahy 24/7', 'Updates · monitoring · 24/7 hands-on')," => "kicker: _('Updaty · monitoring · zásahy podle SLA', 'Updates · monitoring · hands-on per the SLA'),",
                "_('Zásahy 24/7 bez příplatku', '24/7 hands-on, no surcharge')" => "_('Zásahy bez příplatku', 'Hands-on, no surcharge')",
                "{ t: _('Produkce s nočním provozem', 'Production that runs at night'), d: _('Zásahy 24/7 a ladění databáze místo interního on-call.', '24/7 hands-on and database tuning instead of internal on-call.')," => "{ t: _('Produkce bez vlastního admina', 'Production without an in-house admin'), d: _('Zásahy podle SLA a ladění databáze bez vlastního správce.', 'Hands-on per the SLA and database tuning without an admin of your own.'),",
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
                // Q1 (phase Q): the careers page's NOC role "in Prague, 24/7 shifts" (support is Mon–Fri 8–17, P-R37)
                "specs: [_('Praha, směny 24/7', 'Prague, 24/7 shifts'), " => "specs: [_('Praha', 'Prague'), ",
                // and a head count of 42 the team never had (I-R19: four first names on the people page)
                "kicker: _('42 lidí · žádné agentury · Praha nebo remote', '42 people · no agencies · Prague or remote')," => "kicker: _('Bez agentur · Praha nebo remote', 'No agencies · Prague or remote'),",
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
                // P1 (P-R37, OP-1): the corporate SLA band of these pages — 99,99 % "with penalties" and a 15-minute P1 response "24/7"
                "    lead: _('Smluvní SLA, jmenovaný kontakt a kredity vyplácené automaticky. Žádné výjimky psané malým písmem.', 'Contractual SLA, a named contact and credits paid automatically. No exceptions in small print.'),\n    rows: [\n      { k: '99,99 %', v: _('dostupnost s pokutou', 'uptime with penalties') },\n      { k: '15 min', v: _('reakce na P1 24/7', 'P1 response, 24/7') },\n" => self::SLA_BAND_NEW,
                "kpis: [['99,99 %', _('smluvní dostupnost', 'contractual uptime')], ['15 min', _('reakce P1, 24/7', 'P1 response, 24/7')], " => 'kpis: [',
                // P2 (P-R69, DD-1): the game pages' DDoS figures — 1,2 Tbps, detection "within three seconds", a profile "for every game"
                "_('Přišel DDoS na 300 Gbps a hráči si toho nevšimli. Přesně za to platíme.', 'A 300 Gbps DDoS hit and players never noticed. That is exactly what we pay for.')" => "_('Hráči si ničeho nevšimli. Přesně za to platíme.', 'Players never noticed a thing. That is exactly what we pay for.')",
                "    lead: _('Anti-DDoS profily ladíme pro každou hru zvlášť, restarty plánujeme mimo prime time a zálohy běží každou hodinu.', 'Anti-DDoS profiles are tuned per game, restarts are scheduled outside prime time and backups run hourly.'),\n" => "    lead: _('Restarty plánujeme mimo prime time a zálohy běží každou hodinu.', 'Restarts are scheduled outside prime time and backups run hourly.'),\n",
                "      { k: '1,2 Tbps', v: _('kapacita mitigace', 'mitigation capacity') },\n      { k: '< 3 s', v: _('detekce útoku', 'attack detection') },\n" => '',
                "kicker: _('Modpacky · Bedrock · Anti-DDoS', 'Modpacks · Bedrock · Anti-DDoS')," => "kicker: _('Modpacky · Bedrock', 'Modpacks · Bedrock'),",
                "['40+', _('modpacků na klik', 'one-click modpacks')], ['1,2 Tbps', _('anti-DDoS', 'anti-DDoS')]]" => "['40+', _('modpacků na klik', 'one-click modpacks')]]",
                "['8 ms', _('ping z ČR a SK', 'ping from CZ and SK')], ['1,2 Tbps', _('anti-DDoS', 'anti-DDoS')]]" => "['8 ms', _('ping z ČR a SK', 'ping from CZ and SK')]]",
                "          [_('Anti-DDoS', 'Anti-DDoS'), '✓', '✓', _('Profil na míru', 'Custom profile')],\n" => '',
                ",\n        ['06', _('Anti-DDoS ladený pro Minecraft', 'Anti-DDoS tuned for Minecraft'), _('Filtrujeme bot connecty a UDP flood, aniž bychom odřízli hráče.', 'We filter bot connects and UDP floods without cutting off players.')]" => '',
                "konzole, plánované úlohy a anti-DDoS profil pro každý titul zvlášť.', " => "konzole a plánované úlohy.', ",
                "console, scheduled tasks and an anti-DDoS profile per title.')" => "console and scheduled tasks.')",
                "          [_('Anti-DDoS profil', 'Anti-DDoS profile'), _('Standardní', 'Standard'), _('Podle hry', 'Per game'), _('Na míru', 'Bespoke')],\n" => '',
                "[_('Co když nás někdo bude DDoSovat?', 'What if we get DDoSed?'), _('Filtrujeme automaticky do tří sekund. U turnajů držíme technika po celou dobu.', 'We filter automatically within three seconds. During tournaments an engineer stands by.')]" => "[_('Chráníte servery proti DDoS?', 'Do you protect servers against DDoS?'), ".self::DDOS_UNDOCUMENTED.']',
            ],
            'onhost-svc-more.js' => [
                // Q1 (phase Q): no ISO 27001 certificate is documented, and NIS2 duties are not something a host "handles" for a customer
                "_('ISO 27001, DPA, popis architektury a záloh na vyžádání.', 'ISO 27001, a DPA, an architecture and backup description on request.')" => "_('Zpracovatelská smlouva (DPA) a popis služby na vyžádání.', 'A data processing agreement (DPA) and a description of the service on request.')",
                "_('Ano, včetně dokumentace, auditu a hlášení incidentů ve lhůtě.', 'Yes — including documentation, audits and incident reporting within deadlines.')" => "_('Povinnosti podle NIS2 nese provozovatel regulované služby. Co k nim dodáme my (zpracovatelskou smlouvu, popis služby), domluvíme ve smlouvě.', 'NIS2 duties rest with the operator of the regulated service. What we contribute (a data processing agreement, a description of the service) is agreed in the contract.')",
                "_('Odpadní teplo z našich sálů ohřívá dvě sousední budovy v Praze.', 'Waste heat from our halls warms two neighbouring Prague buildings.')" => "_('Odpadní teplo z našich sálů ohřívá sousední budovy v Praze.', 'Waste heat from our halls warms neighbouring Prague buildings.')",
                // O1: the solution pages have no catalogue product behind them — their band, KPIs, plans and tables state the SLA
                // 2026-09 classes, and 99,99 % only "by individual contract"
                "{ k: '99,99 %', v: _('dostupnost s kreditem', 'uptime, credited') }," => "{ k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)', 'monthly availability (Standard, SLA)') },",
                "_('Privátní síť', 'Private networking'), _('SLA 99,99 %', '99.99% SLA')" => "_('Privátní síť', 'Private networking'), ".self::SLA_BY_CONTRACT,
                "['RBAC', '—', '✓', '✓'],\n          ".self::SLA_ROW_OLD => "['RBAC', '—', '✓', '✓'],\n          ".self::SLA_ROW_NEW,
                "['99,99 %', _('dostupnost s pokutou', 'uptime with penalties')], " => '',
                "_('SLA 99,99 % s pokutou', '99.99% SLA with penalties')" => self::SLA_BY_CONTRACT,
                "['SLA', '99,9 %', '99,99 %', _('na míru', 'bespoke')]" => "['SLA', '99,9 %', _('podle smlouvy', 'per contract'), _('na míru', 'bespoke')]",
                "_('Prioritní fronta', 'Priority queue'), _('SLA 99,99 %', '99.99% SLA')" => "_('Prioritní fronta', 'Priority queue'), ".self::SLA_BY_CONTRACT,
                "_('podle klienta', 'per client')],\n          ".self::SLA_ROW_OLD => "_('podle klienta', 'per client')],\n          ".self::SLA_ROW_NEW,
                "_('Dohled během kampaně', 'Campaign war room'), _('SLA 99,99 %', '99.99% SLA')" => "_('Dohled během kampaně', 'Campaign war room'), ".self::SLA_BY_CONTRACT,
                "_('na vyžádání', 'on request'), '✓'],\n          ".self::SLA_ROW_OLD => "_('na vyžádání', 'on request'), '✓'],\n          ".self::SLA_ROW_NEW,
                "_('GPU na vyžádání', 'GPU on demand'), _('SLA 99,99 %', '99.99% SLA')" => "_('GPU na vyžádání', 'GPU on demand'), ".self::SLA_BY_CONTRACT,
                "_('rezervace', 'reserved')],\n          ".self::SLA_ROW_OLD => "_('rezervace', 'reserved')],\n          ".self::SLA_ROW_NEW,
                // P1 (P-R37, OP-1): support hours instead of "30 min, 24/7", no 24/7 NOC, no 24/7 support tier
                "      { k: '30 min', v: _('reakce podpory 24/7', 'support response, 24/7') },\n" => '      '.self::SUPPORT_HOURS_ROW.",\n",
                "_('NOC 24/7 a eskalace', '24/7 NOC and escalation')" => "_('Eskalace podle SLA', 'Escalation per the SLA')",
                "[_('Podpora', 'Support'), _('pracovní doba', 'business hours'), '24/7', _('24/7 + inženýr', '24/7 + engineer')]" => "[_('Podpora', 'Support'), _('pracovní doba', 'business hours'), _('pracovní doba', 'business hours'), _('podle smlouvy', 'per contract')]",
                // P2 (P-R69, DD-1): the gaming communities page
                "kicker: _('Anti-DDoS · sítě serverů · panel pro moderátory', 'Anti-DDoS · server fleets · moderator panel')," => "kicker: _('Sítě serverů · panel pro moderátory', 'Server fleets · moderator panel'),",
                "práva pro moderátory bez sdílení hesla, anti-DDoS profil laděný pro každou hru a restarty naplánované mimo prime time.', 'Several servers under one account, moderator roles without sharing a password, an anti-DDoS profile tuned per game, and restarts scheduled outside prime time.')" => "práva pro moderátory bez sdílení hesla a restarty naplánované mimo prime time.', 'Several servers under one account, moderator roles without sharing a password, and restarts scheduled outside prime time.')",
                "kpis: [['1,2 Tbps', _('kapacita mitigace', 'mitigation capacity')], ['20 TPS'," => "kpis: [['20 TPS',",
                "'Palworld', _('Anti-DDoS', 'Anti-DDoS'), _('Moderátoři', 'Moderators')" => "'Palworld', _('Moderátoři', 'Moderators')",
                "_('Anti-DDoS základní', 'Basic anti-DDoS'), " => '',
                "_('Anti-DDoS Pro s L7', 'Anti-DDoS Pro with L7'), " => '',
                "          ['Anti-DDoS', _('základní', 'basic'), 'Pro (L7)', 'Pro (L7)'],\n" => '',
                "['02', _('Anti-DDoS laděný pro hru', 'Anti-DDoS tuned per game'), _('Filtr pro Minecraft se chová jinak než pro CS2. Nastavujeme ho my.', 'The Minecraft filter behaves differently to CS2. We set it up.')]" => "['02', _('Restarty mimo prime time', 'Restarts outside prime time'), _('Restarty a údržbu naplánujete v panelu na dobu, kdy nikdo nehraje.', 'You schedule restarts and maintenance in the panel for when nobody plays.')]",
                "d: _('Čtyři servery, role pro deset moderátorů a anti-DDoS Pro.', 'Four servers, roles for ten moderators and anti-DDoS Pro.')" => "d: _('Čtyři servery a role pro deset moderátorů.', 'Four servers and roles for ten moderators.')",
                "[_('Jak rychle reagujete na DDoS?', 'How fast do you react to a DDoS?'), _('Detekce do tří sekund, mitigace automaticky, bez vašeho zásahu.', 'Detection within three seconds, mitigation automatic, no action needed.')]" => "[_('Jak reagujete na DDoS?', 'How do you react to a DDoS?'), ".self::DDOS_UNDOCUMENTED.']',
            ],
            'onhost-svc-web.js' => [
                // Q1 (phase Q): the band's lead promised a credit "automatically, no request needed"; SLA 2026-09 čl. 4: claimed in the panel
                "    lead: _('SLA je součástí smlouvy, ne marketingový slib. Když ji nedodržíme, kredit vracíme automaticky — nemusíte o nic žádat.', 'The SLA is part of the contract, not a marketing promise. If we miss it, credit is refunded automatically — no ticket needed.')," => "    lead: _('SLA je součástí smlouvy, ne marketingový slib. Kompenzaci za nedodrženou dostupnost uplatníte v zákaznickém panelu a připíšeme ji jako kredit.', 'The SLA is part of the contract, not a marketing promise. Compensation for missed availability is claimed in the customer panel and credited.'),",
                // Q1: the CDN page (not sold, P-R46) — 1,2 Tbps, 14 PoPs and detection within 3 s are not documented; its plans come
                // from the catalogue overlay (CdnAvailability says "Připravujeme")
                "kicker: _('1,2 Tbps · 14 PoP · WAF', '1.2 Tbps · 14 PoPs · WAF')," => "kicker: _('CDN a WAF · připravujeme', 'CDN and WAF · coming soon'),",
                "lead: _('Statika ze čtrnácti lokalit, mitigace útoků do 1,2 Tbps a WAF s pravidly pro konkrétní aplikace. Provoz zůstává v Evropě, logy máte k dispozici.', 'Static assets from fourteen locations, mitigation up to 1.2 Tbps and a WAF with application-specific rules. Traffic stays in Europe and logs are yours.')," => "lead: _('CDN a ochranu proti útokům připravujeme. Kapacitu, počet lokalit a dobu detekce uvedeme, až je doložíme; do té doby je neslibujeme.', 'CDN and attack protection are coming. We will state capacity, locations and detection time once they are documented; until then we do not promise them.'),",
                "kpis: [['1,2 Tbps', _('kapacita mitigace', 'mitigation capacity')], ['< 3 s', _('detekce útoku', 'attack detection')], ['14', _('PoP lokalit', 'PoP locations')]]," => 'kpis: [],',
                // P1 (P-R37): the web hosting SLA band — a "measured" 99,98 %, a 10-minute 24/7 reply, a 10× credit and TIER III data centres
                // in three cities, none documented — states SLA 2026-09 (Standard) and the support hours
                "      { k: '99,98 %', v: _('měřená dostupnost za 12 měsíců', 'measured uptime over 12 months') },\n      { k: '< 10 min', v: _('první reakce podpory (24/7)', 'first support response (24/7)') },\n      { k: '10×', v: _('kredit za každou hodinu nad limit', 'credit for every hour over the limit') },\n      { k: 'TIER III', v: _('datacentra v Praze, Brně, Varšavě', 'data centres in Prague, Brno, Warsaw') }\n" => "      { k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)', 'monthly availability (Standard, SLA)') },\n      { k: '4 h', v: _('reakce na incident (Standard)', 'incident response (Standard)') },\n      ".self::SUPPORT_HOURS_ROW."\n",
            ],
            // Q1 (phase Q, P-R69): the public SLA page's exclusions named a DDoS filtering capacity ("do 2 Tb/s") nobody documented
            'onhost-public.js' => [
                "{ t: 'DDoS nad kapacitu ochrany', d: 'Filtrujeme do 2 Tb/s. Nad tím se snažíme dál, ale negarantujeme to. Reálný objem a délku útoku vždy zveřejníme.' }" => "{ t: 'DDoS útok', d: 'Parametry ochrany proti DDoS (kapacitu, vrstvy a dobu reakce) uvedeme, až je doložíme. Do té doby je neslibujeme.' }",
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

    /**
     * N1: the copy every service page of `Onhost.dc.html` shares. The FAQ lead sent people to a chat where "an engineer replies within
     * ten minutes" (the only chat is the assistant; no reply time is documented) and the closing block promised "14 days, no card,
     * no commitment … cancel in one click and pay nothing" (there is no trial: services are paid in advance for a month or a year,
     * VOP čl. 2 bod 2). Only the defaults change: a page that brings its own `endTitle` / `endLead` keeps them.
     *
     * @return array<string, string> needle => replacement in the public surface
     */
    public static function pageCopySwaps(): array
    {
        return [
            "      faqLead: cs ? 'Nenašli jste odpověď? Napište na chat, technik odpovídá do deseti minut.' : 'No answer here? Ping the chat — an engineer replies within ten minutes.',\n" => "      faqLead: cs ? 'Nenašli jste odpověď? Napište nám přes zákaznický panel nebo e-mailem.' : 'No answer here? Write to us through the customer panel or by e-mail.',\n",
            "      endTitle: p.endTitle || (cs ? 'Zkuste to 14 dní. Bez karty, bez závazku.' : 'Try it for 14 days. No card, no commitment.'),\n" => "      endTitle: p.endTitle || (cs ? 'Měsíčně nebo ročně, výpověď ke konci období.' : 'Monthly or yearly, notice for the end of the period.'),\n",
            "      endLead: p.endLead || (cs ? 'Když to nesedne, zrušíte to jedním klikem a nic neplatíte. Migraci uděláme za vás.' : 'If it does not fit, cancel in one click and pay nothing. We handle the migration.')\n" => "      endLead: p.endLead || (cs ? 'Spotřebitel může od smlouvy odstoupit do 14 dnů podle VOP. S přechodem z jiného hostingu pomůžeme, termín domluvíme předem.' : 'A consumer may withdraw from the contract within 14 days under the terms. We help you move from another host; the timing is agreed in advance.')\n",
        ];
    }

    /**
     * O1: the home page's closing block and the sign-up form of `Onhost.dc.html` offered "the first 14 days free … cancel in one
     * click and pay nothing" ("Prvních 14 dní zdarma", "Vytvořit účet a začít 14 dní zdarma", "14 dní zdarma, bez rizika"). There is
     * no trial: services are paid in advance for a month or a year (VOP čl. 2 bod 2), notice runs to the end of the period (čl. 5 bod
     * 3) and a consumer withdraws within 14 days (čl. 8 bod 1) — the same words the service pages' closing block uses since N1.
     *
     * @return array<string, string> needle => replacement in the public surface
     */
    public static function homeCopySwaps(): array
    {
        return [
            "      ctaTitle: 'Prvních 14 dní zdarma. Migraci uděláme za vás.', ctaSub: 'Postavte si server za dvě minuty. Když to nesedne, zrušíte to jedním klikem a nic neplatíte.',\n" => "      ctaTitle: 'Měsíčně nebo ročně, výpověď ke konci období.', ctaSub: 'Spotřebitel může od smlouvy odstoupit do 14 dnů podle VOP. S přechodem z jiného hostingu pomůžeme, termín domluvíme předem.',\n",
            "      ctaTitle: 'First 14 days free. We do the migration.', ctaSub: 'Spin up a server in two minutes. If it does not fit, cancel in one click and pay nothing.',\n" => "      ctaTitle: 'Monthly or yearly, notice for the end of the period.', ctaSub: 'A consumer may withdraw from the contract within 14 days under the terms. We help you move from another host; the timing is agreed in advance.',\n",
            "authRegBtn: 'Vytvořit účet a začít 14 dní zdarma'," => "authRegBtn: 'Vytvořit účet',",
            "authRegBtn: 'Create account and start 14 days free'," => "authRegBtn: 'Create account',",
            "authProofTitle: '14 dní zdarma, bez rizika'," => "authProofTitle: 'Bez závazku, výpověď ke konci období',",
            "authProofTitle: '14 days free, no risk'," => "authProofTitle: 'No lock-in, notice for the end of the period',",
        ];
    }

    /**
     * P1 (P-R37): whole pages replaced from a start marker (exactly once) to the first end marker after it. Q1 (phase Q): the
     * enterprise, about and data-centre pages were made up end to end (42 people, 12 800 customers, six data centres, own halls,
     * TIER III, ISO 27001, a 24/7 NOC, SLA 99,99 % with a 15-minute reply, "measured" uptime) and are replaced by what the
     * documents in force say.
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>> script => [[from, to, replacement]]
     */
    public static function blocks(): array
    {
        return array_merge_recursive([
            'onhost-svc-other.js' => [
                [self::SUPPORT_PAGE_FROM, self::SUPPORT_PAGE_TO, self::SUPPORT_PAGE_NEW],
                [self::ENTERPRISE_PAGE_FROM, self::ENTERPRISE_PAGE_TO, self::ENTERPRISE_PAGE_NEW], // Q1
                [self::ABOUT_PAGE_FROM, self::ABOUT_PAGE_TO, self::ABOUT_PAGE_NEW], // Q1
            ],
            'onhost-svc-corp.js' => [[self::DATACENTERS_PAGE_FROM, self::DATACENTERS_PAGE_TO, self::DATACENTERS_PAGE_NEW]], // Q1
        ], PhaseRCopySeam::scriptBlocks(), PhaseTCopySeam::scriptBlocks()); // R1: the status and careers pages; T4: the SSL comparison
    }

    /**
     * Phase P on the public surface (`Onhost.dc.html`): P1 (P-R37, OP-1) support hours instead of "24/7" in the menu, the stats and
     * the promises table ("Co vám slibujeme"), which also states SLA 2026-09 and the VOP instead of a 15-minute 24/7 reply, an
     * automatic credit, "nine probes", "no notice period" and credit refunded on request; P2 (P-R69, DD-1) no DDoS figure in the
     * game badge, menu, search, game features or the network table (where it says "not documented", like its other honest rows).
     *
     * @return array<string, string> needle => replacement in the public surface
     */
    public static function phasePCopySwaps(): array
    {
        $hoursCs = "['Kontakt a podpora', 'Po–pá 8–17, panel a e-mail']";
        $hoursEn = "['Contact and support', 'Mon–Fri 8–17, panel and e-mail']";

        return [
            "gmBadge: 'Anti-DDoS 1,2 Tbps · 24/7'," => "gmBadge: 'Herní servery s vlastním panelem',",
            "gmBadge: 'Anti-DDoS 1.2 Tbps · 24/7'," => "gmBadge: 'Game servers with their own panel',",
            "['CDN a DDoS ochrana', '1,2 Tbps, WAF, 14 PoP']" => "['CDN a DDoS ochrana', 'Připravujeme']",
            "['CDN and DDoS protection', '1.2 Tbps, WAF, 14 PoPs']" => "['CDN and DDoS protection', 'Coming soon']",
            ", ['Anti-DDoS pro hry', '1,2 Tbps, filtr na L7']" => '',
            ", ['Anti-DDoS for games', '1.2 Tbps, L7 filtering']" => '',
            "['Kontakt a podpora', 'Telefon, e-mail, chat 24/7']" => $hoursCs,
            "['Contact and support', 'Phone, e-mail, chat 24/7']" => $hoursEn,
            "['Kontakt a podpora', 'Praha, telefon, e-mail 24/7']" => $hoursCs,
            "['Contact and support', 'Prague, phone, e-mail 24/7']" => $hoursEn,
            "'Minecraft, CS2, Rust · anti-DDoS v ceně'" => "'Minecraft, CS2, Rust'",
            "'Minecraft, CS2, Rust · anti-DDoS included'" => "'Minecraft, CS2, Rust'",
            "['Herní servery', 'Anti-DDoS a sloty v ceně', 'game']" => "['Herní servery', 'Minecraft, CS2, Rust a další', 'game']",
            "['Game servers', 'Anti-DDoS and slots included', 'game']" => "['Game servers', 'Minecraft, CS2, Rust and more', 'game']",
            "['02', 'Anti-DDoS 1,2 Tbps', 'Profily ladíme zvlášť pro každou hru, ne jeden filtr na všechno.']," => "['02', 'Hráči podle RAM', 'Kolik hráčů server unese, určuje jeho RAM a hra, ne pevný počet slotů.'],",
            "['02', 'Anti-DDoS 1.2 Tbps', 'Profiles tuned per game, not one filter for everything.']," => "['02', 'Players by RAM', 'How many players a server holds depends on its RAM and the game, not on a fixed slot count.'],",
            "['Ochrana proti DDoS', 'na hraně', 'Filtrujeme na vstupu do sítě, ne až na vašem serveru. Provoz, který nemá projít, se k vám vůbec nedostane.']," => "['Ochrana proti DDoS', 'nedoloženo', 'Parametry ochrany proti DDoS (kapacitu, vrstvy a dobu reakce) uvedeme, až je doložíme. Do té doby ji neslibujeme.'],",
            "['DDoS protection', 'at the edge', 'We filter at the network edge, not at your server. Traffic that should not arrive never reaches you.']," => "['DDoS protection', 'not documented', 'We will state DDoS protection figures (capacity, layers and response time) once they are documented. Until then we do not promise it.'],",
            "{ v: '24/7', l: cs ? 'lidská podpora' : 'human support' }" => "{ v: cs ? 'po–pá 8–17' : 'Mon–Fri 8–17', l: cs ? 'lidská podpora' : 'human support' }",
            "['NOC 24/7 na pozadí', " => "['Dohled na pozadí', ",
            "['24/7 NOC behind you', " => "['Monitoring behind you', ",
            "['Zvedneme to do 15 minut', 'U priority P1 (služba neběží) reagujeme do 15 minut, 24/7. Reakcí se rozumí odpověď člověka, nikoli automatické potvrzení.'],\n        ['Když to nestihneme, vrátíme kredit sami', 'Při nedodržení reakční doby připisujeme kredit automaticky, bez žádosti zákazníka, do 24 hodin od zjištění.'],\n        ['Dostupnost 99,9 % a výš', 'Měřeno měsíčně z devíti sond. Pod 99,9 % vracíme 10 % měsíční platby za službu, pod 99 % celou platbu.']," => "['Reagujeme podle SLA', 'Na incident reagujeme podle třídy služby: Standard do 4 hodin, Business do 1 hodiny (SLA v platnosti). Podpora odpovídá po–pá 8:00–17:00.'],\n        ['Kompenzace kreditem', 'Kompenzaci za nedodrženou dostupnost uplatníte v zákaznickém panelu a připíšeme ji jako kredit (SLA).'],\n        ['Dostupnost podle SLA', 'Standard 99,9 %, Business 99,95 % měsíčně. Za každou započatou hodinu nad limit náleží 5 % (Standard, nejvýše 50 %), resp. 10 % (Business, nejvýše 100 %) měsíční ceny.'],",
            "['Smlouvu můžete kdykoli ukončit', 'Bez výpovědní doby a bez penále. Nevyčerpaný kredit vracíme na požádání, data exportujeme jedním kliknutím.']" => "['Výpověď ke konci období', 'Služby platíte předem na měsíc nebo rok, výpověď platí ke konci předplaceného období. Spotřebitel může od smlouvy odstoupit do 14 dnů (VOP).']",
            "['We answer within 15 minutes', 'For P1 (service down) we respond within 15 minutes, 24/7. A response means a human reply, not an automated acknowledgement.'],\n        ['If we miss it, the credit comes without asking', 'On a missed response time we credit the account automatically, without a request, within 24 hours of discovery.'],\n        ['Uptime of 99.9% and above', 'Measured monthly from nine probes. Below 99.9% we return 10% of the monthly fee; below 99% the whole fee.']," => "['We respond per the SLA', 'Incident response by service class: Standard within 4 hours, Business within 1 hour (the SLA in force). Support answers Mon–Fri 8:00–17:00.'],\n        ['Compensation as credit', 'Compensation for missed availability is claimed in the customer panel and credited (SLA).'],\n        ['Availability per the SLA', 'Standard 99.9%, Business 99.95% a month. Each started hour over the limit earns 5% (Standard, at most 50%) or 10% (Business, at most 100%) of the monthly price.'],",
            "['You can leave at any time', 'No notice period, no penalty. Unused credit is refunded on request and your data exports in one click.']" => "['Notice for the end of the period', 'Services are paid in advance for a month or a year; notice takes effect at the end of the prepaid period. A consumer may withdraw within 14 days (terms).']",
        ];
    }

    /**
     * Phase Q on the public surface (`Onhost.dc.html`): Q3 (P-R77) the header, hero, closing block and configurator buttons said
     * "Vyzkoušet zdarma" / "Start free trial" although there is no trial (VOP čl. 2 bod 2; the buttons open the sign-up form, so they
     * say what they do). Q1 (P-R69, P-R37): the prototype still sold "Anti-DDoS Pro" (configurator, cart upsells, the price
     * overview; the catalogue product `anti-ddos-pro` is a draft and nothing delivers it; the game plan cards come from the catalogue,
     * `ONHOST_DATA.gameSlots`, so their prototype fallback is left alone), the status history showed a "480 Gbps" DDoS incident that never happened, and the search
     * offered colocation with "remote hands 24/7".
     *
     * @return array<string, string> needle => replacement in the public surface
     */
    public static function phaseQCopySwaps(): array
    {
        return [
            "navLogin: 'Klientská zóna', navCta: 'Vyzkoušet zdarma'," => "navLogin: 'Klientská zóna', navCta: 'Vytvořit účet',",
            "navLogin: 'Client area', navCta: 'Start free trial'," => "navLogin: 'Client area', navCta: 'Create account',",
            "heroCta1: 'Vyzkoušet zdarma', heroCta2:" => "heroCta1: 'Vytvořit účet', heroCta2:",
            "heroCta1: 'Start free trial', heroCta2:" => "heroCta1: 'Create account', heroCta2:",
            "ctaBtn1: 'Vyzkoušet zdarma', ctaBtn2:" => "ctaBtn1: 'Vytvořit účet', ctaBtn2:",
            "ctaBtn1: 'Start free trial', ctaBtn2:" => "ctaBtn1: 'Create account', ctaBtn2:",
            "cfgOrder: 'Vyzkoušet zdarma', cfgFine:" => "cfgOrder: 'Objednat', cfgFine:",
            "cfgOrder: 'Start free trial', cfgFine:" => "cfgOrder: 'Order', cfgFine:",
            "      ['Anti-DDoS Pro', 'Filtr na L7 a ochrana herních portů', 149],\n" => '',
            "      ['Anti-DDoS Pro', 'L7 filtering and game port protection', 149],\n" => '',
            "      ['ddos', cs ? 'Anti-DDoS Pro' : 'Anti-DDoS Pro', cs ? '1,2 Tbps, game profily' : '1.2 Tbps, game profiles', 149],\n" => '',
            "        ['ddos', cs ? 'Anti-DDoS Pro' : 'Anti-DDoS Pro', 149],\n" => '',
            "['Ostatní hry (CS2, Rust)', 199], ['Anti-DDoS Pro', 149], " => "['Ostatní hry (CS2, Rust)', 199], ",
            "['Other games (CS2, Rust)', 199], ['Anti-DDoS Pro', 149], " => "['Other games (CS2, Rust)', 199], ",
            "        ['28. 7. 2026', 'DDoS 480 Gbps na herní segment', 'Odfiltrováno v Praze. Prvních dvacet sekund se hráčům trhalo, pak už ne. Zákazníkovi jsme napsali první.', '20 sekund · bez výpadku', 'odfiltrováno', 'ok'],\n" => '',
            "        ['28 Jul 2026', 'DDoS 480 Gbps on the game segment', 'Scrubbed in Prague. The first twenty seconds stuttered for players, then nothing. We wrote to the customer first.', '20 seconds · no downtime', 'scrubbed', 'ok'],\n" => '',
            "['služba', 'Housing a colocation', 'A+B napájení, ruce na místě 24/7', 'housing colocation rack', 'services']," => "['služba', 'Housing a colocation', 'Prostor v racku', 'housing colocation rack', 'services'],",
            "['service', 'Housing and colocation', 'A+B power, remote hands 24/7', 'housing colocation rack', 'services']," => "['service', 'Housing and colocation', 'Rack space', 'housing colocation rack', 'services'],",
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
        $script = $map === [] ? $script : str_replace(array_keys($map), array_values($map), $script);
        foreach (self::blocks()[$path] ?? [] as [$from, $to, $replacement]) {
            $start = strpos($script, $from);
            $end = $start === false ? false : strpos($script, $to, $start);
            if ($start !== false && $end !== false) {
                $script = substr($script, 0, $start).$replacement.substr($script, $end + strlen($to));
            }
        }

        return $script;
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
