// Onhost — data podstránek: firemní záložka Onhost a zdroje.

export function corpPages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviews = [
    { name: 'Ing. Hana Dvořáková', role: _('IT ředitelka, Vitrum Group', 'IT director, Vitrum Group'), text: _('Smlouva má jasné SLA a telefon, který někdo zvedne. To u předchozího dodavatele nebylo.', 'The contract has a clear SLA and a phone number somebody answers. Our previous vendor had neither.') },
    { name: 'Pavel Hrdina', role: _('jednatel, Zelená Energie Vysočina', 'director, Zelená Energie Vysočina'), text: _('Odvod tepla z jejich datacentra ohřívá dvě sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms two neighbouring buildings. This is how infrastructure should work.') },
    { name: 'Klára Šimková', role: _('vývojářka, členka komunity', 'developer, community member'), text: _('Přišla jsem na jeden meetup, odešla se stipendiem na server pro open-source projekt.', 'I came to one meetup and left with a grant for an open-source project server.') }
  ];

  const kb = [
    { title: _('Jak čteme SLA a kredity', 'How our SLA and credits work'), read: '4 min' },
    { title: _('Zpracování osobních údajů a DPA', 'Data processing and the DPA'), read: '6 min' },
    { title: _('Bezpečnostní incident: co děláme', 'Security incidents: what we do'), read: '5 min' },
    { title: _('Fakturace, kredit a DPH', 'Invoicing, credit and VAT'), read: '3 min' }
  ];

  const sla = {
    title: _('Co máme na papíře, to platí', 'What is on paper is what happens'),
    lead: _('Smluvní SLA, jmenovaný kontakt a kredity vyplácené automaticky. Žádné výjimky psané malým písmem.', 'Contractual SLA, a named contact and credits paid automatically. No exceptions in small print.'),
    rows: [
      { k: '99,99 %', v: _('dostupnost s pokutou', 'uptime with penalties') },
      { k: '15 min', v: _('reakce na P1 24/7', 'P1 response, 24/7') },
      { k: '6', v: _('lokalit v Evropě', 'European locations') },
      { k: '62 %', v: _('energie z obnovitelných zdrojů', 'energy from renewables') }
    ]
  };

  const migration = {
    title: _('Přechod řídíme jako projekt', 'We run the switch as a project'),
    lead: _('Jmenovaný vedoucí, plán s termíny, rollback v každém kroku. U větších přechodů děláme i zkušební cvičení.', 'A named lead, a dated plan, a rollback at every step. For larger moves we run a rehearsal.'),
    steps: [
      { n: '01', t: _('Vstupní audit', 'Initial audit'), d: _('Sepíšeme systémy, závislosti, rizika a okna pro změny.', 'We document systems, dependencies, risks and change windows.') },
      { n: '02', t: _('Plán a smlouva', 'Plan and contract'), d: _('SLA, odpovědnosti, kontaktní matice a termíny.', 'SLA, responsibilities, a contact matrix and dates.') },
      { n: '03', t: _('Cvičný přechod', 'Rehearsal'), d: _('Zkoušíme na kopii, měříme dobu a ladíme kroky.', 'We rehearse on a copy, time it and refine the steps.') },
      { n: '04', t: _('Přechod a předání', 'Cutover and handover'), d: _('Ostrý přechod, monitoring a runbook pro váš tým.', 'The real cutover, monitoring and a runbook for your team.') }
    ]
  };

  const C = (o) => Object.assign({ config: false, roi: false, sla, migration, reviews, kb }, o);

  return {
    'datacenters': C({
      panelsTitle: _('Co je v každém sále', 'What every hall contains'), panelsLead: _('Energie, chlazení a konektivita jsou tři věci, na kterých se datacentrum láme. Tady je, jak je řešíme.', 'Power, cooling and connectivity are where a data centre breaks. Here is how we handle them.'),
      panels: [
        [_('Energie', 'Power'), _('Dvě nezávislé větve, baterie na deset minut, dieselagregát na 72 hodin.', 'Two independent feeds, ten minutes of battery, 72 hours of diesel.'), [[_('Redundance', 'Redundancy'), '2N'], [_('Baterie', 'Battery'), '10 min'], [_('Agregát', 'Gensets'), '72 h'], [_('Test agregátu', 'Genset test'), _('měsíčně', 'monthly')]]],
        [_('Chlazení', 'Cooling'), _('Volné chlazení většinu roku, odpadní teplo jde do sousedních budov.', 'Free cooling most of the year; waste heat goes to neighbouring buildings.'), [['PUE', '1,18'], [_('Volné chlazení', 'Free cooling'), _('214 dní / rok', '214 days a year')], [_('Teplota v sále', 'Hall temperature'), '22 °C'], [_('Odvod tepla', 'Heat recovery'), _('2 budovy', '2 buildings')]]],
        [_('Konektivita', 'Connectivity'), _('Vlastní AS, peering na třech uzlech, dvě nezávislé trasy do každého sálu.', 'Our own AS, peering at three exchanges, two independent routes into every hall.'), [[_('Kapacita', 'Capacity'), '1,2 Tbps'], [_('Uzly', 'Exchanges'), 'NIX, DE-CIX, AMS-IX'], [_('Trasy do sálu', 'Routes per hall'), '2'], [_('Latence do ČR', 'Latency to Czechia'), '< 8 ms']]]
      ],
      cmp: null,
      cat: 'Onhost', crumb: _('Datacentra', 'Data centres'),
      kicker: _('6 lokalit · TIER III · 2N napájení', '6 sites · TIER III · 2N power'),
      title: _('Šest lokalit, dvě z nich stavíme a provozujeme sami', 'Six locations, two of them built and run by us'),
      lead: _('Praha, Brno, Ostrava, Varšava, Frankfurt a Amsterdam. V Praze máme vlastní sály s 2N napájením, odvodem tepla do sousedních budov a NOC, který sedí o patro výš.', 'Prague, Brno, Ostrava, Warsaw, Frankfurt and Amsterdam. In Prague we own the halls, with 2N power, heat recovery into neighbouring buildings and a NOC sitting one floor up.'),
      kpis: [['6', _('lokalit v Evropě', 'European locations')], ['1,18', _('průměrné PUE v Praze', 'average PUE in Prague')], ['2N', _('napájení a chlazení', 'power and cooling')]],
      chips: ['TIER III', '2N', 'ISO 27001', _('Vlastní sály', 'Our own halls'), 'NIX.CZ', _('Odvod tepla', 'Heat recovery'), _('NOC 24/7', '24/7 NOC'), _('Exkurze', 'Tours')],
      plansTitle: _('Lokality a k čemu se hodí', 'Sites and what they suit'),
      plansNote: _('Lokalitu si vyberete při objednávce a můžete ji později změnit. Přesun mezi lokalitami je zdarma.', 'You choose the site at order time and can change it later. Moving between sites is free.'),
      plans: [
        { name: 'Praha — PRG1, PRG2', tag: _('Vlastní sály', 'Our own halls'), price: 0, priceLabel: _('2 sály', '2 halls'), priceNote: _('TIER III, v provozu od 2014', 'TIER III, running since 2014'), ctaLabel: _('Objednat v Praze', 'Order in Prague'), specs: [_('2N napájení a chlazení', '2N power and cooling'), _('Peering na NIX.CZ', 'Peering at NIX.CZ'), _('Odvod tepla do okolí', 'Heat recovery to neighbours'), _('NOC v budově 24/7', '24/7 NOC on site'), _('Colocation i exkurze', 'Colocation and tours'), _('Latence do ČR < 8 ms', 'Sub-8 ms to Czechia')] },
        { name: _('Brno a Ostrava', 'Brno and Ostrava'), tag: _('DR lokality', 'DR sites'), price: 0, priceLabel: _('2 lokality', '2 sites'), priceNote: _('záložní a DR provoz', 'backup and DR operations'), ctaLabel: _('Zjistit dostupnost', 'Check availability'), specs: [_('Replikace z Prahy', 'Replication from Prague'), _('Zálohy a immutable kopie', 'Backups and immutable copies'), _('Nezávislé napájení', 'Independent power feeds'), _('Vzdálenost 200+ km', '200+ km apart'), _('Colocation na vyžádání', 'Colocation on request'), _('Latence do Prahy < 6 ms', 'Sub-6 ms to Prague')] },
        { name: _('Varšava, Frankfurt, Amsterdam', 'Warsaw, Frankfurt, Amsterdam'), tag: '', price: 0, priceLabel: _('3 lokality', '3 sites'), priceNote: _('partnerská datacentra v EU', 'partner data centres in the EU'), ctaLabel: _('Zjistit dostupnost', 'Check availability'), specs: [_('Anycast PoP pro CDN', 'Anycast PoPs for the CDN'), _('DR mimo ČR', 'DR outside Czechia'), _('Peering na DE-CIX a AMS-IX', 'Peering at DE-CIX and AMS-IX'), _('Stejný panel a fakturace', 'Same panel and billing'), _('Data zůstávají v EU', 'Data stays in the EU'), _('Latence do ČR < 20 ms', 'Sub-20 ms to Czechia')] }
      ],
      feats: [
        ['01', _('Dva sály stavíme sami', 'Two halls are ours'), _('Od rozvaděče po chlazení. Nikoho nemusíme prosit o zásah.', 'From switchgear to cooling. We never have to ask anyone to intervene.')],
        ['02', _('2N, ne N+1', '2N, not N+1'), _('Dvě nezávislé větve napájení i chlazení. Údržba není odstávka.', 'Two independent power and cooling paths. Maintenance is not an outage.')],
        ['03', _('Teplo jde do sousedství', 'Heat goes next door'), _('Odpadní teplo ohřívá dvě budovy vedle nás, ne vzduch nad střechou.', 'Waste heat warms two neighbouring buildings, not the air above the roof.')],
        ['04', _('NOC o patro výš', 'The NOC is one floor up'), _('Když je potřeba fyzický zásah, jde o minuty, ne o výjezd.', 'When hands are needed it is minutes, not a callout.')],
        ['05', _('Peering tam, kde to dává smysl', 'Peering where it matters'), _('NIX.CZ, DE-CIX i AMS-IX. Provoz do ČR nechodí přes Frankfurt.', 'NIX.CZ, DE-CIX and AMS-IX. Czech traffic does not detour via Frankfurt.')],
        ['06', _('Můžete se přijít podívat', 'You can come and look'), _('Exkurze každý druhý čtvrtek. Ukážeme i to, co se nepovedlo.', 'Tours every other Thursday. We show the mistakes too.')]
      ],
      tech: ['TIER III', '2N UPS', _('Dieselagregát', 'Diesel gensets'), 'NIX.CZ', 'DE-CIX', 'AMS-IX', 'ISO 27001', _('Odvod tepla', 'Heat recovery')],
      bench: [
        { label: _('PRG1 — PUE', 'PRG1 — PUE'), pct: 100, note: '1,18' },
        { label: _('Průměr datacenter v EU', 'EU data centre average'), pct: 62, note: '~1,55' },
        { label: _('Starší sály v ČR', 'Older Czech halls'), pct: 45, note: '~1,9' }
      ],
      benchNote: _('Roční průměr PUE za 2025. Nižší číslo znamená méně energie na chlazení.', 'Annual average PUE for 2025. Lower means less energy spent on cooling.'),
      cases: [
        { t: _('Nízká latence do ČR', 'Low latency to Czechia'), d: _('Herní servery a e-shopy patří do Prahy.', 'Game servers and stores belong in Prague.'), m: _('Praha', 'Prague') },
        { t: _('Záložní lokalita', 'A DR site'), d: _('Immutable kopie dvě stě kilometrů od produkce.', 'An immutable copy two hundred kilometres from production.'), m: _('Brno / Ostrava', 'Brno / Ostrava') },
        { t: _('Zákazníci v Německu', 'Customers in Germany'), d: _('Frankfurt jako druhá lokalita, stejná faktura.', 'Frankfurt as a second site, on the same invoice.'), m: _('Zahraničí', 'Abroad') }
      ],
      faq: [
        [_('Můžu se přijít podívat?', 'Can I visit?'), _('Ano, do PRG1 každý druhý čtvrtek. Stačí se ohlásit.', 'Yes — PRG1 every other Thursday. Just book a slot.')],
        [_('Jsou sály certifikované?', 'Are the halls certified?'), _('Praha odpovídá TIER III a máme ISO 27001 na provoz.', 'Prague is built to TIER III and we hold ISO 27001 for operations.')],
        [_('Můžu si vybrat lokalitu?', 'Can I pick the location?'), _('Ano, při objednávce i později. Přesun je zdarma.', 'Yes, at order time or later. The move is free.')],
        [_('Kde leží zálohy?', 'Where do backups live?'), _('Vždy v jiné lokalitě, než běží produkce.', 'Always in a different site from production.')],
        [_('Opustí data EU?', 'Does data leave the EU?'), _('Ne, nikdy. Všech šest lokalit je v Evropské unii.', 'No, never. All six sites are in the European Union.')]
      ]
    }),

    'partners': C({
      panelsTitle: _('Jak spolupráce vzniká', 'How a partnership starts'), panelsLead: _('Vždycky jedním společným nasazením, ne memorandem. Teprve pak řešíme smlouvu a marketing.', 'Always with one joint deployment, never a memorandum. Contracts and marketing come after.'),
      panels: [
        [_('První krok', 'First step'), _('Technická schůzka a jedno nasazení, na kterém se ukáže, jestli si sedneme.', 'A technical meeting and one deployment that shows whether we fit.'), [[_('Od poptávky ke schůzce', 'Enquiry to meeting'), '< 5 ' + _('dní', 'days')], [_('Pilotní nasazení', 'Pilot deployment'), '2–6 ' + _('týdnů', 'weeks')], [_('Náklady pilota', 'Pilot cost'), _('0 Kč', 'Free')], [_('Exkluzivita', 'Exclusivity'), '—']]],
        [_('Provoz', 'Running it'), _('Zákazník má jeden telefon. Kdo problém vyřeší, si vyříkáme mezi sebou.', 'The customer has one phone number. Which of us fixes it is our problem, not theirs.'), [[_('Sdílená podpora', 'Shared support'), '✓'], [_('Rozdělené SLA', 'Split SLA'), _('smluvně', 'contractual')], [_('Společný on-call', 'Joint on-call'), '✓'], [_('Eskalace mezi týmy', 'Cross-team escalation'), '15 min']]],
        [_('Hardware', 'Hardware'), _('Dodavatele vybíráme podle dostupnosti dílů a rychlosti výměny, ne podle slevy.', 'We choose suppliers by parts availability and swap speed, not discount.'), [[_('Výměna vadného kusu', 'Faulty unit swap'), '24 h'], [_('Díly na skladě', 'Spares on site'), _('Praha, Brno', 'Prague, Brno')], [_('Test před nasazením', 'Pre-deployment test'), '72 h'], [_('Partnerů', 'Partners'), '4']]]
      ],
      cmp: null,
      cat: 'Onhost', crumb: _('Partneři a Electree', 'Partners and Electree'),
      kicker: _('Energetika · integrace · společné projekty', 'Energy · integrations · joint projects'),
      title: _('S kým stavíme a proč zrovna s nimi', 'Who we build with, and why them'),
      lead: _('Electree v energetice, dodavatelé hardwaru, kteří umí opravit do druhého dne, a agentury, které u nás mají klienty. Partnerství znamená společný projekt, ne logo na webu.', 'Electree in energy, hardware suppliers who fix things next day, and agencies whose clients live here. A partnership means a joint project, not a logo on a page.'),
      kpis: [['4', _('technologičtí partneři', 'technology partners')], ['180+', _('partnerských agentur', 'partner agencies')], ['24 h', _('výměna vadného hardwaru', 'faulty hardware swap')]],
      chips: ['Electree', 'AMD', 'NVIDIA', 'Cloudflare', 'NIX.CZ', _('Agentury', 'Agencies'), _('Reselleři', 'Resellers'), 'BESS'],
      plansTitle: _('Typy spolupráce', 'Types of partnership'),
      plansNote: _('Partnerství vzniká na projektu, ne na memorandu. Začínáme vždy jedním společným nasazením.', 'Partnerships start on a project, not a memorandum. We always begin with one joint deployment.'),
      plans: [
        { name: _('Technologický partner', 'Technology partner'), tag: 'Electree', price: 0, priceLabel: _('Společný projekt', 'Joint project'), priceNote: _('energetika, BESS, měření', 'energy, BESS, metering'), ctaLabel: _('Domluvit schůzku', 'Book a meeting'), specs: [_('Společné nasazení', 'A joint deployment'), _('Sdílená podpora', 'Shared support'), _('Integrace na úrovni API', 'API-level integration'), _('Referenční instalace', 'Reference installations'), _('Společný vývoj', 'Joint development'), _('Rozdělení SLA', 'A split SLA')] },
        { name: _('Dodavatel hardwaru', 'Hardware supplier'), tag: '', price: 0, priceLabel: '24 h', priceNote: _('výměna vadného kusu', 'to swap a faulty unit'), ctaLabel: _('Stát se dodavatelem', 'Become a supplier'), specs: [_('Servery EPYC a Ryzen', 'EPYC and Ryzen servers'), _('GPU H100 a L40S', 'H100 and L40S GPUs'), _('Náhradní díly na skladě', 'Spare parts on site'), _('Výměna do 24 hodin', '24-hour replacement'), _('Testování před nasazením', 'Pre-deployment testing'), _('Dlouhodobé smlouvy', 'Long-term contracts')] },
        { name: _('Agentura a reseller', 'Agency and reseller'), tag: _('Nejčastější', 'Most common'), price: 0, priceLabel: '25–45 %', priceNote: _('marže podle objemu', 'margin by volume'), ctaLabel: _('Otevřít reseller účet', 'Open a reseller account'), specs: [_('Bílý štítek panelu', 'White-labelled panel'), _('Vlastní ceník', 'Your own pricing'), _('Fakturace pod vaší značkou', 'Invoices under your brand'), _('Technická podpora na pozadí', 'Support in the background'), _('Společné případové studie', 'Joint case studies'), _('Přednostní kapacita', 'Priority capacity')] }
      ],
      feats: [
        ['01', _('Electree v energetice', 'Electree in energy'), _('Řízení baterií a komunitní energetiky běží na naší infrastruktuře, integrace je hotová.', 'Battery control and community energy run on our infrastructure; the integration is built.')],
        ['02', _('Hardware, který se dá opravit', 'Hardware that can be fixed'), _('Vybíráme dodavatele podle dostupnosti dílů, ne podle slevy.', 'We choose suppliers by parts availability, not by discount.')],
        ['03', _('Podporu sdílíme', 'Support is shared'), _('U společných projektů má zákazník jeden telefon, ne dva.', 'On joint projects the customer has one phone number, not two.')],
        ['04', _('Reference se dají ověřit', 'References can be verified'), _('Každé partnerství má nasazení, na které se dá zeptat.', 'Every partnership has a deployment you can ask about.')],
        ['05', _('Bez exkluzivity', 'No exclusivity'), _('Nechceme vázat partnery ani zákazníky. Držíme je kvalitou.', 'We do not lock in partners or customers. Quality does that.')],
        ['06', _('Otevřené rozhraní', 'An open interface'), _('Integrace stavíme na veřejném API, ne na tajné dohodě.', 'Integrations are built on the public API, not a private deal.')]
      ],
      tech: ['Electree', 'AMD EPYC', 'NVIDIA H100', 'Cloudflare', 'NIX.CZ', 'Modbus', 'OCPP', 'S3'],
      bench: [
        { label: _('Partnerské agentury 2025', 'Partner agencies in 2025'), pct: 100, note: '180' },
        { label: '2024', pct: 66, note: '119' },
        { label: '2023', pct: 38, note: '68' }
      ],
      benchNote: _('Počet aktivních partnerských a reseller účtů na konci roku.', 'Active partner and reseller accounts at year end.'),
      cases: [
        { t: _('Bateriové úložiště', 'Battery storage'), d: _('Electree řídí, my držíme infrastrukturu a SLA.', 'Electree controls it, we hold the infrastructure and the SLA.'), m: _('Technologický', 'Technology') },
        { t: _('Obnova vadné karty', 'Replacing a failed card'), d: _('GPU vyměněná do 24 hodin ze skladu v Praze.', 'A GPU swapped within 24 hours from Prague stock.'), m: 'Hardware' },
        { t: _('Agentura s padesáti klienty', 'An agency with fifty clients'), d: _('Bílý štítek, vlastní ceník, jedna faktura.', 'White label, own pricing, one invoice.'), m: _('Agentura', 'Agency') }
      ],
      faq: [
        [_('Jak se stát partnerem?', 'How do I become a partner?'), _('Napište, co chcete postavit. Začneme jedním projektem.', 'Tell us what you want to build. We start with one project.')],
        [_('Chcete exkluzivitu?', 'Do you want exclusivity?'), _('Ne, ani ji nenabízíme.', 'No — and we do not offer it either.')],
        [_('Prodáváte hardware i mimo hosting?', 'Do you sell hardware outside hosting?'), _('Ano, servery i GPU dodáváme a instalujeme i u zákazníka.', 'Yes — we supply and install servers and GPUs on customer premises too.')],
        [_('Co je Electree?', 'What is Electree?'), _('Partner pro řízení bateriových úložišť a komunitní energetiku.', 'Our partner for battery storage control and community energy.')],
        [_('Máte partnerský program pro vývojáře?', 'Is there a developer partner programme?'), _('Ano, přes affiliate a reseller účet, s marží podle objemu.', 'Yes — through the affiliate and reseller accounts, with volume margins.')]
      ]
    }),

    'press': C({
      panelsTitle: _('Jak s námi mluvit', 'How to work with us'), panelsLead: _('Odpovídáme rychle a bez schvalování textu. Tady je, co od nás dostanete a v jaké lhůtě.', 'We answer fast and never demand copy approval. Here is what you get and when.'),
      panels: [
        [_('Lhůty', 'Turnaround'), _('V pracovní den odpovídáme do čtyř hodin, i na nepříjemné dotazy.', 'On a business day we reply within four hours, awkward questions included.'), [[_('Dotaz médií', 'Media query'), '< 4 h'], [_('Rozhovor', 'Interview'), _('do 3 dnů', 'within 3 days')], [_('Exkurze', 'Tour'), _('každý druhý čtvrtek', 'every other Thursday')], [_('Fotky na míru', 'Bespoke photos'), _('do 5 dnů', 'within 5 days')]]],
        [_('Co komentujeme', 'What we comment on'), _('Trh, ceny energie, výpadky i vlastní chyby. Konkurenci ne.', 'The market, energy prices, outages and our own mistakes. Not competitors.'), [[_('Provoz a incidenty', 'Operations and incidents'), '✓'], [_('Ceny a trh', 'Pricing and market'), '✓'], [_('Energetika', 'Energy'), '✓'], [_('Konkrétní konkurence', 'Named competitors'), '—']]],
        [_('Pravidla', 'Rules'), _('Materiály jsou volné pro redakční použití, jen značku needitujte.', 'Materials are free for editorial use; just do not edit the mark.'), [[_('Schvalování textu', 'Copy approval'), '—'], [_('Uvedení zdroje', 'Attribution'), _('vítáme', 'welcome')], [_('Úprava loga', 'Logo edits'), '—'], [_('Embargo', 'Embargo'), _('po dohodě', 'by arrangement')]]]
      ],
      cmp: null,
      cat: 'Onhost', crumb: _('Tiskové centrum', 'Press centre'),
      kicker: _('Materiály · loga · kontakt pro média', 'Materials · logos · media contact'),
      title: _('Materiály pro novináře a partnery na jednom místě', 'Materials for journalists and partners in one place'),
      lead: _('Loga ve vektoru, fotky ze sálů, čísla, která můžete citovat, a telefon na člověka, který vám odpoví dnes. Bez registrace a bez schvalování textu.', 'Vector logos, photographs from the halls, numbers you may quote, and a phone number answered today. No signup, no copy approval.'),
      kpis: [['< 4 h', _('odpověď médiím v pracovní den', 'media response on a business day')], ['2014', _('rok založení', 'founded')], ['42', _('zaměstnanců', 'employees')]],
      chips: [_('Loga', 'Logos'), _('Fotografie', 'Photography'), _('Faktografie', 'Fact sheet'), _('Citace', 'Quotes'), _('Rozhovory', 'Interviews'), _('Tiskové zprávy', 'Press releases')],
      plansTitle: _('Materiály ke stažení', 'Materials to download'),
      docs: [
        [_('Logo balíček', 'Logo kit'), _('Vektor i rastr, světlá a tmavá varianta, ochranná zóna a minimální velikosti.', 'Vector and raster, light and dark, clear space and minimum sizes.'), 'v3.0', _('14. 2. 2026', '14 Feb 2026'), '4,2 MB'],
        [_('Faktografie', 'Fact sheet'), _('Historie, počty zákazníků a serverů, lokality, energetika a vedení firmy.', 'History, customer and server counts, sites, energy and leadership.'), 'Q3 2026', _('1. 7. 2026', '1 Jul 2026'), '212 kB'],
        [_('Fotografie ze sálů', 'Photographs from the halls'), _('Racky, rozvodna, chlazení a NOC. Bez stock fotek, s popiskami.', 'Racks, switchgear, cooling and the NOC. No stock, with captions.'), '2026', _('9. 4. 2026', '9 Apr 2026'), '68 MB'],
        [_('Portréty vedení', 'Leadership portraits'), _('Tiskové rozlišení, černobíle i barevně, s uvedením funkcí.', 'Print resolution, black and white or colour, with roles.'), '2026', _('9. 4. 2026', '9 Apr 2026'), '24 MB'],
        [_('Tiskové zprávy 2026', 'Press releases 2026'), _('Devět zpráv od ledna, česky i anglicky, ve formátu pro citaci.', 'Nine releases since January, Czech and English, quotable format.'), '—', _('průběžně', 'ongoing'), '—'],
        [_('Pravidla použití značky', 'Brand usage rules'), _('Co s logem dělat nesmíte a kdy nás musíte oslovit.', 'What not to do with the logo and when to contact us.'), 'v2.1', _('14. 2. 2026', '14 Feb 2026'), '76 kB']
      ],
      docsNote: _('Vše je použitelné v redakčním kontextu bez našeho souhlasu. Schválení textu nepožadujeme.', 'Everything is usable in editorial context without our approval. We do not demand copy approval.'),
      plansTitleOld: _('Co si můžete stáhnout', 'What you can download'),
      plansNote: _('Vše je ke stažení bez registrace a použitelné bez našeho souhlasu, pokud text není zavádějící.', 'Everything downloads without signup and may be used without our approval, provided the text is not misleading.'),
      plans: [
        { name: _('Značka', 'Brand'), tag: _('Ke stažení', 'Download'), price: 0, priceLabel: 'SVG + PNG', priceNote: _('loga a barvy', 'logos and colours'), ctaLabel: _('Stáhnout balíček', 'Download the kit'), specs: [_('Logo ve vektoru', 'Vector logo'), _('Světlá i tmavá varianta', 'Light and dark variants'), _('Ochranná zóna a minima', 'Clear space and minimums'), _('Barvy a typografie', 'Colours and typography'), _('Co s logem nedělat', 'What not to do with it'), _('Licence pro média', 'Media licence')] },
        { name: _('Fakta a čísla', 'Facts and figures'), tag: '', price: 0, priceLabel: 'PDF', priceNote: _('aktualizováno čtvrtletně', 'updated quarterly'), ctaLabel: _('Stáhnout přehled', 'Download the fact sheet'), specs: [_('Historie a milníky', 'History and milestones'), _('Počet zákazníků a serverů', 'Customers and servers'), _('Lokality a kapacita', 'Sites and capacity'), _('Energetika a PUE', 'Energy and PUE'), _('Vedení a struktura', 'Leadership and structure'), _('Citovatelné údaje', 'Quotable figures')] },
        { name: _('Kontakt pro média', 'Media contact'), tag: '', price: 0, priceLabel: '< 4 h', priceNote: _('odpověď v pracovní den', 'reply on a business day'), ctaLabel: _('Napsat tiskovému oddělení', 'Contact the press office'), specs: [_('Telefon a e-mail', 'Phone and email'), _('Rozhovory s vedením', 'Interviews with leadership'), _('Komentář k oboru', 'Industry commentary'), _('Exkurze do datacentra', 'Data centre tours'), _('Fotografie na míru', 'Bespoke photography'), _('Bez schvalování textu', 'No copy approval demanded')] }
      ],
      feats: [
        ['01', _('Nežádáme schválení textu', 'We do not demand copy approval'), _('Napište, co si myslíte. Opravíme jen fakta, ne názory.', 'Write what you think. We correct facts, not opinions.')],
        ['02', _('Čísla čtvrtletně', 'Numbers every quarter'), _('Faktografii aktualizujeme, ne aby zestárla o dva roky.', 'The fact sheet is refreshed, not left to age two years.')],
        ['03', _('Fotky ze skutečných sálů', 'Photos of the real halls'), _('Ne stock. Naše racky, naše kabely, náš nepořádek.', 'Not stock. Our racks, our cabling, our mess.')],
        ['04', _('Komentujeme i nepříjemné věci', 'We comment on the awkward things'), _('Ceny energie, výpadky, konsolidaci trhu. Nejen úspěchy.', 'Energy prices, outages, market consolidation. Not just wins.')],
        ['05', _('Exkurze i pro novináře', 'Tours for journalists too'), _('Ukážeme sál, rozvodnu i to, co ještě není hotové.', 'We show the hall, the switchgear and what is not finished yet.')],
        ['06', _('Odpovídáme dnes', 'We answer today'), _('V pracovní den do čtyř hodin, i když je otázka nepříjemná.', 'Within four hours on a business day, even for awkward questions.')]
      ],
      tech: ['SVG', 'PNG', 'PDF', 'CSV', _('Fotobanka', 'Photo library'), _('Tiskové zprávy', 'Press releases')],
      bench: [
        { label: _('Odpověď médiím', 'Media response'), pct: 100, note: '< 4 h' },
        { label: _('Tiskové zprávy 2025', 'Press releases in 2025'), pct: 22, note: '9' },
        { label: _('Rozhovory 2025', 'Interviews in 2025'), pct: 34, note: '14' }
      ],
      benchNote: _('Medián doby odpovědi na dotaz médií a počet výstupů za rok.', 'Median media response time and output counts per year.'),
      cases: [
        { t: _('Článek o českém hostingu', 'A piece on Czech hosting'), d: _('Faktografie, čísla a citace bez čekání na schválení.', 'Fact sheet, numbers and quotes without waiting for approval.'), m: _('Fakta', 'Facts') },
        { t: _('Reportáž z datacentra', 'A data centre report'), d: _('Exkurze, fotky a inženýr, který mluví srozumitelně.', 'A tour, photos and an engineer who speaks plainly.'), m: _('Kontakt', 'Contact') },
        { t: _('Partnerská tisková zpráva', 'A joint press release'), d: _('Loga ve vektoru a schválené znění do druhého dne.', 'Vector logos and agreed wording by the next day.'), m: _('Značka', 'Brand') }
      ],
      faq: [
        [_('Můžu použít vaše logo?', 'May I use your logo?'), _('Ano, v redakčním kontextu bez ptaní. Neupravujte barvy ani tvar.', 'Yes, in editorial context without asking. Do not alter colour or shape.')],
        [_('Poskytnete rozhovor?', 'Will you give an interview?'), _('Ano, včetně technických témat. Ozvěte se s termínem.', 'Yes, including technical topics. Send us a date.')],
        [_('Máte zprávy i anglicky?', 'Are press releases in English?'), _('Ano, obě jazykové verze vydáváme zároveň.', 'Yes — both language versions are published together.')],
        [_('Komentujete konkurenci?', 'Do you comment on competitors?'), _('O trhu ano, o konkrétních firmách ne.', 'On the market yes, on specific companies no.')],
        [_('Kdo je kontaktní osoba?', 'Who is the contact?'), _('Tiskové oddělení, telefon i e-mail najdete v balíčku.', 'The press office; phone and email are in the kit.')]
      ]
    }),

    'legal': C({
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
    }),

    'blog': C({
      panelsTitle: _('Jak blog vzniká', 'How the blog gets written'), panelsLead: _('Žádná redakce, žádné PR agentury. Píší lidé, kteří systémy staví a drží v provozu — a mají v ruce čísla.', 'No editorial desk, no PR agencies. Written by the people who build and run the systems, with the numbers in hand.'),
      panels: [
        [_('Kdo píše', 'Who writes'), _('Autoři jsou inženýři z NOC a vývoje, ne copywriteři.', 'Authors are NOC and engineering staff, not copywriters.'), [[_('Autorů', 'Authors'), '11'], [_('Z provozu', 'From operations'), '8'], [_('Externích', 'External'), '0'], [_('Placených PR', 'Sponsored'), '0']]],
        [_('Jak dlouho to trvá', 'How long it takes'), _('Text vzniká po incidentu nebo po měření, ne podle obsahového plánu.', 'A piece follows an incident or a measurement, not a content calendar.'), [[_('Od události k textu', 'Event to article'), _('5 dní', '5 days')], [_('Průměrná délka', 'Average length'), '12 min'], [_('Revize před vydáním', 'Reviews before publishing'), '2'], [_('Oprav po vydání', 'Post-publish fixes'), '3 %']]],
        [_('Co s tím můžete dělat', 'What you can do with it'), _('Konfigurace kopírujte, čísla citujte, překlady vítáme.', 'Copy the configs, quote the numbers, translations welcome.'), [[_('Paywall', 'Paywall'), '—'], [_('Registrace', 'Signup'), '—'], [_('Licence', 'Licence'), 'CC BY 4.0'], [_('RSS a newsletter', 'RSS and newsletter'), '✓']]]
      ],
      cmp: null,
      planGoto: 'blog',
      cat: _('Zdroje', 'Resources'), crumb: 'Blog',
      kicker: _('Provozní zápisky · případové studie', 'Field notes · case studies'),
      title: _('Zápisky z provozu, ne obsahový marketing', 'Notes from operations, not content marketing'),
      lead: _('Píšeme o tom, co jsme postavili, co se pokazilo a kolik to stálo. Konkrétní čísla, konfigurace ke zkopírování a postmortemy vlastních chyb.', 'We write about what we built, what went wrong and what it cost. Real numbers, copy-pasteable configs and postmortems of our own mistakes.'),
      kpis: [['96', _('článků za rok', 'articles a year')], ['0', _('placených PR článků', 'sponsored posts')], ['12 min', _('průměrná doba čtení', 'average read time')]],
      chips: [_('Provoz', 'Operations'), _('Případové studie', 'Case studies'), 'Postmortem', _('Benchmarky', 'Benchmarks'), _('Ceny', 'Pricing'), _('Energetika', 'Energy'), 'AI', 'Open source'],
      plansTitle: _('O čem píšeme', 'What we write about'),
      plansNote: _('Všechno je zdarma, bez registrace a bez paywallu. Odběr e-mailem jednou za dva týdny.', 'Everything is free, with no signup and no paywall. Email digest every fortnight.'),
      plans: [
        { name: _('Provozní zápisky', 'Field notes'), tag: _('Nejčtenější', 'Most read'), price: 0, priceLabel: '38', priceNote: _('článků ročně', 'articles a year'), ctaLabel: _('Číst zápisky', 'Read the notes'), goto: 'blog', specs: [_('Jak stavíme datacentrum', 'How we build a data centre'), _('Ladění PostgreSQL v provozu', 'Tuning PostgreSQL in production'), _('Anti-DDoS v praxi', 'Anti-DDoS in practice'), _('Náklady na energii', 'What energy actually costs'), _('Hardware, který jsme vrátili', 'Hardware we sent back'), _('Konfigurace ke zkopírování', 'Configs you can copy')] },
        { name: _('Případové studie', 'Case studies'), tag: '', price: 0, priceLabel: '24', priceNote: _('studií ročně', 'studies a year'), ctaLabel: _('Číst studie', 'Read the studies'), goto: 'blog', specs: [_('S čísly, ne s dojmy', 'With numbers, not impressions'), _('Vždy se souhlasem zákazníka', 'Always with customer consent'), _('Včetně toho, co nevyšlo', 'Including what did not work'), _('Rozpočet a návratnost', 'Budget and payback'), _('Architektura a diagramy', 'Architecture and diagrams'), _('Kontakt na referenci', 'A reference contact')] },
        { name: 'Postmortem', tag: '', price: 0, priceLabel: '8', priceNote: _('rozborů incidentů ročně', 'incident write-ups a year'), ctaLabel: _('Číst postmortemy', 'Read the postmortems'), goto: 'blog', specs: [_('Časová osa incidentu', 'Incident timeline'), _('Příčina, ne viník', 'Cause, not culprit'), _('Co jsme změnili', 'What we changed'), _('Do pěti dnů od incidentu', 'Within five days'), _('Bez „technických problémů“', 'No “technical difficulties”'), _('Otevřená diskuse', 'Open discussion')] }
      ],
      feats: [
        ['01', _('Žádné placené články', 'No sponsored posts'), _('Nikdy jsme žádný nevzali a nemáme to v plánu.', 'We have never taken one and do not plan to.')],
        ['02', _('Konfigurace ke zkopírování', 'Configs you can copy'), _('Ne screenshoty. Skutečné soubory, které u nás běží.', 'Not screenshots. The real files running in our estate.')],
        ['03', _('Píšeme i o vlastních chybách', 'We write about our own mistakes'), _('Postmortem má stejné místo jako oznámení nové funkce.', 'A postmortem gets the same space as a feature launch.')],
        ['04', _('Bez paywallu a registrace', 'No paywall, no signup'), _('Články čtete bez účtu a bez cookie lišty přes půl obrazovky.', 'Read without an account and without a half-screen cookie banner.')],
        ['05', _('Reference se dají ověřit', 'References can be checked'), _('U studií necháváme kontakt na zákazníka, který svolil.', 'Case studies carry a contact for the customer who agreed.')],
        ['06', _('Autoři jsou lidé z provozu', 'Authors work in operations'), _('Píše ten, kdo to stavěl nebo držel při životě ve tři ráno.', 'Written by whoever built it or kept it alive at 3 a.m.')]
      ],
      tech: ['PostgreSQL', 'Kubernetes', 'BGP', 'Prometheus', 'NVMe', 'EPYC', 'H100', 'BESS'],
      bench: [
        { label: _('Články 2025', 'Articles in 2025'), pct: 100, note: '96' },
        { label: _('Postmortemy 2025', 'Postmortems in 2025'), pct: 8, note: '8' },
        { label: _('Placené články celkem', 'Sponsored posts, ever'), pct: 0, note: '0' }
      ],
      benchNote: _('Počet publikovaných textů za kalendářní rok podle kategorie.', 'Published pieces per calendar year, by category.'),
      cases: [
        { t: _('Zvažujete to dělat sami', 'Deciding whether to do it yourself'), d: _('Zápisky říkají, kolik práce to doopravdy je.', 'The field notes say how much work it really is.'), m: _('Zápisky', 'Field notes') },
        { t: _('Připravujete rozpočet', 'Preparing a budget'), d: _('Studie mají rozpočty i návratnost, ne jen chválu.', 'Case studies carry budgets and payback, not just praise.'), m: _('Studie', 'Case studies') },
        { t: _('Vybíráte dodavatele', 'Choosing a vendor'), d: _('Postmortemy ukazují, jak se chováme, když se to pokazí.', 'Postmortems show how we behave when it goes wrong.'), m: 'Postmortem' }
      ],
      faq: [
        [_('Berete hostované články?', 'Do you accept guest posts?'), _('Od zákazníků ano, pokud mají čísla a nejsou to reklamy.', 'From customers yes, if they carry numbers and are not ads.')],
        [_('Můžu článek přeložit?', 'Can I translate an article?'), _('Ano, s uvedením zdroje. Napište nám, rádi odkážeme.', 'Yes, with attribution. Tell us and we will link back.')],
        [_('Proč píšete o chybách?', 'Why write about failures?'), _('Protože podle toho se pozná dodavatel víc než podle úspěchů.', 'Because that reveals more about a vendor than the wins do.')],
        [_('Máte newsletter?', 'Is there a newsletter?'), _('Jednou za čtrnáct dní, bez sledovacích pixelů.', 'Every fortnight, with no tracking pixels.')],
        [_('Kde najdu starší články?', 'Where are the older articles?'), _('V archivu, kompletně, i těch pět let starých.', 'In the archive — all of it, including five-year-old posts.')]
      ]
    }),

    'academy': C({
      panelsTitle: _('Jak kurzy probíhají', 'How the courses run'), panelsLead: _('Každý kurz je série úloh na skutečném serveru. Hodnotí je skript, závěrečnou práci člověk.', 'Every course is a series of tasks on a real server. A script grades them; a human grades the final piece.'),
      panels: [
        [_('Formát', 'Format'), _('Text, video a cvičení na vlastním serveru, který dostanete v ceně.', 'Text, video and exercises on your own server, included.'), [[_('Cvičení na kurz', 'Exercises per course'), '12–20'], [_('Video', 'Video'), _('2–4 h', '2–4 h')], [_('Cvičný server', 'Practice server'), _('v ceně', 'included')], [_('Termín', 'Deadline'), _('žádný', 'none')]]],
        [_('Hodnocení', 'Grading'), _('Úlohy kontroluje skript proti stavu serveru, ne kvíz o teorii.', 'Tasks are checked by a script against the server state, not a theory quiz.'), [[_('Automatické úlohy', 'Automated tasks'), '90 %'], [_('Ruční hodnocení', 'Human review'), _('závěrečná práce', 'final piece')], [_('Opakování pokusu', 'Retries'), _('bez limitu', 'unlimited')], [_('Certifikát', 'Certificate'), '✓']]],
        [_('Pro školy', 'For schools'), _('Hromadné účty, přehled pro učitele a úklid prostředí po semestru.', 'Bulk accounts, a teacher dashboard and end-of-term cleanup.'), [[_('Účtů najednou', 'Accounts at once'), '30+'], [_('Přehled pro učitele', 'Teacher view'), '✓'], [_('Materiály', 'Materials'), 'PDF + MD'], [_('Cena', 'Price'), _('0 Kč', 'Free')]]]
      ],
      cmp: null,
      planGoto: 'kb',
      cat: _('Zdroje', 'Resources'), crumb: _('Akademie', 'Academy'),
      kicker: _('Kurzy zdarma · cvičné servery v ceně', 'Free courses · practice servers included'),
      title: _('Naučíme vás provozovat servery, i když u nás nezůstanete', 'We will teach you to run servers, even if you leave us'),
      lead: _('Kurzy Linuxu, Dockeru, CI/CD a databází. Zdarma, včetně cvičného serveru na dobu kurzu a certifikátu, který někdo skutečně kontroluje.', 'Courses in Linux, Docker, CI/CD and databases. Free, including a practice server for the duration and a certificate somebody actually checks.'),
      kpis: [['9', _('kurzů zdarma', 'free courses')], ['2 400', _('absolventů', 'graduates')], [_('Zdarma', 'Free'), _('včetně cvičného serveru', 'practice server included')]],
      chips: ['Linux', 'Docker', 'CI/CD', 'PostgreSQL', _('Sítě', 'Networking'), _('Bezpečnost', 'Security'), 'Terraform', 'Kubernetes'],
      plansTitle: _('Úrovně kurzů', 'Course tracks'),
      plansNote: _('Všechno je zdarma. Cvičný server běží po dobu kurzu a pak se sám smaže.', 'Everything is free. The practice server runs for the length of the course, then deletes itself.'),
      plans: [
        { name: _('Základy', 'Foundations'), tag: _('Pro začátečníky', 'For beginners'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('4 kurzy, 18 hodin', '4 courses, 18 hours'), ctaLabel: _('Začít kurz', 'Start a course'), goto: 'kb', specs: [_('Linux od příkazové řádky', 'Linux from the command line'), _('SSH, klíče a bezpečnost', 'SSH, keys and safety'), _('Web server a certifikáty', 'Web server and certificates'), _('Zálohy a obnova', 'Backups and restores'), _('Cvičný server v ceně', 'Practice server included'), _('Certifikát', 'Certificate')] },
        { name: _('Provoz', 'Operations'), tag: _('Nejčastější', 'Most common'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('3 kurzy, 22 hodin', '3 courses, 22 hours'), ctaLabel: _('Začít kurz', 'Start a course'), goto: 'kb', specs: [_('Docker od nuly', 'Docker from scratch'), _('CI/CD a deploy', 'CI/CD and deploys'), _('PostgreSQL v provozu', 'PostgreSQL in production'), _('Monitoring a alerty', 'Monitoring and alerts'), _('Cvičný klastr', 'Practice cluster'), _('Certifikát', 'Certificate')] },
        { name: _('Pokročilé', 'Advanced'), tag: '', price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('2 kurzy, 16 hodin', '2 courses, 16 hours'), ctaLabel: _('Začít kurz', 'Start a course'), goto: 'kb', specs: [_('Kubernetes bez mýtů', 'Kubernetes without the myths'), _('Infrastruktura jako kód', 'Infrastructure as code'), _('Ladění výkonu', 'Performance tuning'), _('Incident management', 'Incident management'), _('Konzultace s inženýrem', 'A session with an engineer'), _('Certifikát', 'Certificate')] }
      ],
      feats: [
        ['01', _('Cvičný server je v ceně', 'The practice server is included'), _('Nemusíte nic zakládat ani platit. Po kurzu se smaže sám.', 'Nothing to set up or pay for. It deletes itself afterwards.')],
        ['02', _('Cvičení, ne kvízy', 'Exercises, not quizzes'), _('Úkol splníte na skutečném serveru, kontroluje ho skript.', 'You complete tasks on a real server, checked by a script.')],
        ['03', _('Certifikát někdo kontroluje', 'The certificate is checked'), _('Závěrečná úloha se hodnotí ručně, ne klikacím testem.', 'The final assignment is reviewed by hand, not by a click-through test.')],
        ['04', _('Učíme obecně, ne jen u nás', 'We teach the general case'), _('Co se naučíte, použijete i u konkurence. To je záměr.', 'What you learn works at a competitor too. That is deliberate.')],
        ['05', _('Materiály zůstávají', 'The materials stay yours'), _('Skripta v PDF i Markdownu si stáhnete a nezmizí vám.', 'Handbooks in PDF and Markdown, downloadable and permanent.')],
        ['06', _('Pro školy hromadně', 'Bulk access for schools'), _('Třicet účtů najednou, s přehledem pro učitele.', 'Thirty accounts at once, with a teacher dashboard.')]
      ],
      tech: ['Linux', 'Docker', 'Kubernetes', 'PostgreSQL', 'Terraform', 'Prometheus', 'Git', 'Nginx'],
      bench: [
        { label: _('Absolventi 2025', 'Graduates in 2025'), pct: 100, note: '2 400' },
        { label: _('Absolventi 2024', 'Graduates in 2024'), pct: 54, note: '1 300' },
        { label: _('Dokončí kurz', 'Course completion'), pct: 68, note: '68 %' }
      ],
      benchNote: _('Počet vydaných certifikátů za rok a podíl dokončených kurzů.', 'Certificates issued per year and the completion rate.'),
      cases: [
        { t: _('Junior v týmu', 'A junior on the team'), d: _('Za tři týdny umí SSH, zálohy a nasadit web.', 'In three weeks: SSH, backups and deploying a site.'), m: _('Základy', 'Foundations') },
        { t: _('Vývojář, co dostal on-call', 'A developer put on call'), d: _('Monitoring, alerty a incident management v praxi.', 'Monitoring, alerts and incident management, hands-on.'), m: _('Provoz', 'Operations') },
        { t: _('Škola', 'A school'), d: _('Třicet studentů, jedno zadání, přehled pro učitele.', 'Thirty students, one assignment, one teacher view.'), m: _('Základy', 'Foundations') }
      ],
      faq: [
        [_('Je to opravdu zdarma?', 'Is it really free?'), _('Ano, včetně cvičného serveru. Kartu nechceme.', 'Yes, practice server included. We do not want a card.')],
        [_('Musím být zákazník?', 'Do I have to be a customer?'), _('Ne. Stačí účet, který nic nestojí.', 'No. A free account is enough.')],
        [_('Jsou kurzy česky?', 'Are courses in Czech?'), _('Ano, česky i anglicky, včetně materiálů.', 'Yes — Czech and English, materials included.')],
        [_('Uznává certifikát někdo?', 'Is the certificate recognised?'), _('Zaměstnavatelé, kteří se nás ptali, ano. Formální akreditaci nemáme.', 'Employers who asked us, yes. We hold no formal accreditation.')],
        [_('Můžete kurz pro firmu?', 'Can you run it for our company?'), _('Ano, na míru a u vás. To už je placené.', 'Yes, tailored and on site. That one is paid.')]
      ]
    }),

    'changelog': C({
      panelsTitle: _('Jak vydáváme', 'How we ship'), panelsLead: _('Malé změny denně, zápis týdně a breaking change nikdy bez měsíčního předstihu.', 'Small changes daily, an entry weekly, and never a breaking change without a month of notice.'),
      panels: [
        [_('Rytmus', 'Cadence'), _('Nasazujeme průběžně, ale píšeme o tom jednou týdně v pondělí.', 'We deploy continuously but write it up once a week, on Monday.'), [[_('Nasazení denně', 'Deploys a day'), '8–14'], [_('Zápisy ročně', 'Entries a year'), '52'], [_('Breaking changes', 'Breaking changes'), '4'], [_('Vrácené změny', 'Rollbacks'), '3']]],
        [_('Co v zápisu je', 'What an entry contains'), _('Konkrétní endpoint, konkrétní dopad a odkaz do dokumentace.', 'The specific endpoint, the specific impact and a link to the docs.'), [[_('Dopad na API', 'API impact'), '✓'], [_('Odkaz do dokumentace', 'Docs link'), '✓'], [_('Migrace ze staré cesty', 'Migration path'), '✓'], [_('Marketingová slova', 'Marketing words'), '—']]],
        [_('Jak se to dozvíte', 'How you hear about it'), _('RSS okamžitě, e-mail týdně, webhook do Slacku hned po nasazení.', 'RSS instantly, email weekly, a Slack webhook right after deploy.'), [['RSS', _('okamžitě', 'instant')], [_('E-mail', 'Email'), _('týdně', 'weekly')], ['Webhook', _('okamžitě', 'instant')], [_('Filtr podle služby', 'Per-service filter'), '✓']]]
      ],
      cmp: null,
      cat: _('Pro vývojáře', 'For developers'), crumb: 'Changelog',
      kicker: _('Vydáváme každý týden · bez marketingu', 'Weekly releases · no marketing'),
      title: _('Co jsme vydali a co jsme rozbili', 'What we shipped, and what we broke'),
      lead: _('Každý týden jeden zápis: co je nové, co se změnilo a co jsme museli vrátit zpět. Změny, které se vás dotknou, hlásíme e-mailem třicet dní předem.', 'One entry a week: what is new, what changed and what we had to roll back. Breaking changes are emailed thirty days in advance.'),
      kpis: [['52', _('vydání za rok', 'releases a year')], ['30 ' + _('dní', 'days'), _('oznámení před změnou', 'notice before a breaking change')], ['3', _('vrácené změny za 2025', 'rollbacks in 2025')]],
      chips: [_('Týdně', 'Weekly'), 'RSS', _('E-mail', 'Email'), 'Webhook', _('Bez marketingu', 'No marketing'), _('Archiv 5 let', '5-year archive')],
      plansTitle: _('Jak se to dozvíte', 'How to follow it'),
      plansNote: _('Odběr je zdarma a nikam vás nepřihlašuje. Breaking changes chodí i těm, kdo nic neodebírají.', 'Subscribing is free and signs you up for nothing else. Breaking changes reach everyone regardless.'),
      plans: [
        { name: 'RSS', tag: _('Nejjednodušší', 'Simplest'), price: 0, priceLabel: 'RSS', priceNote: _('bez registrace', 'no signup'), ctaLabel: _('Otevřít feed', 'Open the feed'), specs: [_('Plný text zápisu', 'Full entry text'), _('Bez sledování', 'No tracking'), _('Archiv 5 let', '5-year archive'), _('Atom i JSON Feed', 'Atom and JSON Feed'), _('Kategorie změn', 'Change categories'), _('Bez reklamy', 'No advertising')] },
        { name: _('E-mail', 'Email'), tag: '', price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('jednou týdně, v pondělí', 'once a week, on Monday'), ctaLabel: _('Přihlásit odběr', 'Subscribe'), specs: [_('Souhrn za týden', 'Weekly digest'), _('Breaking changes zvlášť', 'Breaking changes separately'), _('Jen vaše služby', 'Only your services'), _('Odhlášení jedním klikem', 'One-click unsubscribe'), _('Bez sledovacích pixelů', 'No tracking pixels'), _('Česky i anglicky', 'Czech and English')] },
        { name: 'Webhook', tag: '', price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('do Slacku nebo vlastního nástroje', 'to Slack or your own tool'), ctaLabel: _('Nastavit webhook', 'Set up a webhook'), specs: [_('JSON s kategorií změny', 'JSON with a change category'), _('Filtr podle služby', 'Filter by service'), _('Podpis požadavku', 'Signed requests'), _('Opakování při chybě', 'Retries on failure'), _('Slack a Mattermost', 'Slack and Mattermost'), _('Historie doručení', 'Delivery history')] }
      ],
      feats: [
        ['01', _('Píšeme i o chybách', 'We write about failures too'), _('Vrácená změna má svůj zápis stejně jako nová funkce.', 'A rollback gets an entry just like a new feature.')],
        ['02', _('Třicet dní na přípravu', 'Thirty days to prepare'), _('Breaking change oznámíme a starou cestu držíme funkční.', 'A breaking change is announced and the old path keeps working.')],
        ['03', _('Bez slov jako „vylepšujeme“', 'No “we are improving things”'), _('Konkrétní čísla, konkrétní endpointy, konkrétní dopad.', 'Specific numbers, specific endpoints, specific impact.')],
        ['04', _('Odkaz na dokumentaci', 'A link to the docs'), _('Každá změna míří na stránku, kde je to popsané celé.', 'Every change links to the page describing it in full.')],
        ['05', _('Jen to, co se týká vás', 'Only what concerns you'), _('E-mail filtrujeme podle služeb, které skutečně máte.', 'The email is filtered to the services you actually use.')],
        ['06', _('Archiv pět let zpět', 'Five years of archive'), _('Užitečné při auditu i při hledání, kdy se co změnilo.', 'Useful for audits and for finding when something changed.')]
      ],
      tech: ['RSS', 'Atom', 'JSON Feed', 'Slack', 'Mattermost', 'Webhooky', 'OpenAPI', 'Git'],
      bench: [
        { label: _('Vydání 2025', 'Releases in 2025'), pct: 100, note: '52' },
        { label: _('Breaking changes 2025', 'Breaking changes in 2025'), pct: 8, note: '4' },
        { label: _('Vrácené změny 2025', 'Rollbacks in 2025'), pct: 6, note: '3' }
      ],
      benchNote: _('Počet zápisů v changelogu za kalendářní rok, včetně vrácených změn.', 'Changelog entries per calendar year, rollbacks included.'),
      cases: [
        { t: _('Platformní tým', 'A platform team'), d: _('Webhook do Slacku, breaking changes rovnou do backlogu.', 'A Slack webhook; breaking changes go straight into the backlog.'), m: 'Webhook' },
        { t: _('Agentura', 'An agency'), d: _('Týdenní e-mail místo hlídání osmi produktů.', 'A weekly email instead of watching eight products.'), m: _('E-mail', 'Email') },
        { t: _('Auditor', 'An auditor'), d: _('Archiv pěti let jako doklad o řízení změn.', 'Five years of archive as change-management evidence.'), m: 'RSS' }
      ],
      faq: [
        [_('Jak často vydáváte?', 'How often do you ship?'), _('Zápis je každý týden, nasazujeme ale i denně.', 'An entry every week, though we deploy daily.')],
        [_('Co je breaking change?', 'What counts as breaking?'), _('Cokoli, co může rozbít existující integraci nebo skript.', 'Anything that can break an existing integration or script.')],
        [_('Píšete i o výpadcích?', 'Do you write about outages?'), _('Ty patří na status stránku, changelog je o změnách.', 'Those belong on the status page; the changelog is about changes.')],
        [_('Můžu odebírat jen jednu službu?', 'Can I follow one service only?'), _('Ano, u e-mailu i webhooku. RSS má kategorie.', 'Yes, for email and webhooks. RSS has categories.')],
        [_('Je archiv veřejný?', 'Is the archive public?'), _('Ano, celý, bez přihlášení.', 'Yes — all of it, no login.')]
      ]
    })
  };
}
