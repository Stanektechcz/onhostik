// Onhost — data podstránek: web, domény, e-mail, bezpečnost, data
// Každá stránka: hero, KPI, tarify, srovnání, funkce, kompatibilita, benchmark,
// use-cases, FAQ, knowledgebase, recenze. cs=true → čeština, jinak angličtina
// (DE/PL/SK dědí anglickou vrstvu, dokud se nedoplní).

export function webPages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviewsWeb = [
    { name: 'Petra Kolářová', role: _('CTO, Bezvazásilky', 'CTO, Bezvazásilky'), text: _('Přesun 340 webů proběhl za jednu noc a bez jediného výpadku. Podpora odpovídá do deseti minut, i ve dvě ráno.', 'We moved 340 sites in one night with zero downtime. Support answers within ten minutes, even at 2am.') },
    { name: 'Martin Šrámek', role: _('vývojář na volné noze', 'freelance developer'), text: _('LiteSpeed a NVMe udělaly z WooCommerce, který se plazil, něco, co běží pod 400 ms.', 'LiteSpeed and NVMe turned a crawling WooCommerce into something that runs under 400 ms.') },
    { name: 'Jana Bednářová', role: _('marketing, Kavárna Zrno', 'marketing, Kavárna Zrno'), text: _('Chtěla jsem web, ne server. Kliknu, běží to, faktura přijde v PDF. Přesně to jsem potřebovala.', 'I wanted a website, not a server. One click, it runs, invoice arrives as PDF. Exactly what I needed.') }
  ];

  const kbWeb = [
    { title: _('Jak převést web na Onhost bez výpadku', 'Migrating a site to Onhost with zero downtime'), read: '6 min' },
    { title: _('Nastavení PHP verze a limitů', 'Setting the PHP version and limits'), read: '3 min' },
    { title: _('Let\u2019s Encrypt a vlastní certifikát', 'Let\u2019s Encrypt and custom certificates'), read: '4 min' },
    { title: _('Obnovení webu ze zálohy', 'Restoring a site from backup'), read: '5 min' }
  ];

  const sla = {
    title: _('Dostupnost, kterou máte na papíře', 'Uptime you have in writing'),
    lead: _('SLA je součástí smlouvy, ne marketingový slib. Když ji nedodržíme, kredit vracíme automaticky — nemusíte o nic žádat.', 'The SLA is part of the contract, not a marketing promise. If we miss it, credit is refunded automatically — no ticket needed.'),
    rows: [
      { k: '99,98 %', v: _('měřená dostupnost za 12 měsíců', 'measured uptime over 12 months') },
      { k: '< 10 min', v: _('první reakce podpory (24/7)', 'first support response (24/7)') },
      { k: '10×', v: _('kredit za každou hodinu nad limit', 'credit for every hour over the limit') },
      { k: 'TIER III', v: _('datacentra v Praze, Brně, Varšavě', 'data centres in Prague, Brno, Warsaw') }
    ]
  };

  const migration = {
    title: _('Migraci uděláme za vás. Zdarma.', 'We handle the migration. Free.'),
    lead: _('Pošlete přístupy ke starému hostingu, my přeneseme soubory, databáze, e-maily i cron. Web běží na obou stranách, dokud nepřepnete DNS.', 'Send us the old hosting credentials — we move files, databases, mailboxes and cron jobs. The site runs on both sides until you switch DNS.'),
    steps: [
      { n: '01', t: _('Přístupy a inventura', 'Credentials and inventory'), d: _('Zmapujeme domény, weby, schránky a naplánujeme okno.', 'We map domains, sites, mailboxes and plan the window.') },
      { n: '02', t: _('Kopie a testovací URL', 'Copy and staging URL'), d: _('Web běží na testovací adrese, projdete ho než se cokoli přepne.', 'The site runs on a staging URL you review before anything switches.') },
      { n: '03', t: _('Přepnutí DNS', 'DNS cutover'), d: _('Sync poslední změny, TTL na 300 s, přepnutí mimo špičku.', 'Final delta sync, TTL to 300 s, cutover off-peak.') },
      { n: '04', t: _('Kontrola a odpojení', 'Verify and decommission'), d: _('72 h monitoring, pak vám staré řešení pomůžeme zrušit.', '72 h monitoring, then we help you cancel the old provider.') }
    ]
  };

  const P = (o) => Object.assign({ config: false, roi: false, sla, migration, reviews: reviewsWeb, kb: kbWeb }, o);

  return {
    'web-hosting': P({
      cmpTitle: _('Co je v kterém tarifu webhostingu', 'What each web hosting plan includes'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('Webhosting', 'Web hosting'),
      kicker: _('NVMe · LiteSpeed · PHP 8.4', 'NVMe · LiteSpeed · PHP 8.4'),
      title: _('Webhosting, který nezpomalí v pátek večer', 'Web hosting that will not choke on Friday night'),
      lead: _('Sdílený hosting na NVMe discích a LiteSpeed serverech, s vlastními limity na projekt. Bez přeprodaných strojů, bez „neomezeně“ v hvězdičce.', 'Shared hosting on NVMe disks and LiteSpeed servers, with per-project limits. No oversold machines, no asterisked “unlimited”.'),
      kpis: [['0,4 s', _('medián TTFB v ČR', 'median TTFB in CZ')], ['99,98 %', _('dostupnost 2025', 'uptime in 2025')], ['12 800', _('webů na platformě', 'sites on the platform')]],
      chips: ['NVMe RAID10', 'LiteSpeed + QUIC', 'PHP 7.4 – 8.4', 'HTTP/3', _('Zálohy 30 dní', '30-day backups'), _('Certifikát v ceně', 'Free certificate')],
      plans: [
        { name: 'Start', tag: '', price: 89, specs: [_('1 web', '1 site'), '10 GB NVMe', _('5 schránek', '5 mailboxes'), _('1 databáze', '1 database'), _('Zálohy 7 dní', '7-day backups'), _('Certifikát zdarma', 'Free certificate')] },
        { name: 'Standard', tag: _('Nejoblíbenější', 'Most popular'), price: 189, specs: [_('10 webů', '10 sites'), '50 GB NVMe', _('50 schránek', '50 mailboxes'), _('Neomezeně databází', 'Unlimited databases'), _('Zálohy 30 dní', '30-day backups'), _('Staging na klik', 'One-click staging')] },
        { name: 'Profi', tag: '', price: 449, specs: [_('50 webů', '50 sites'), '200 GB NVMe', _('Neomezeně schránek', 'Unlimited mailboxes'), _('Dedikovaný PHP worker', 'Dedicated PHP worker'), _('Zálohy 90 dní', '90-day backups'), 'WAF + CDN'] }
      ],
      cmp: {
        cols: ['Start', 'Standard', 'Profi'],
        rows: [
          [_('Weby', 'Sites'), '1', '10', '50'],
          [_('NVMe prostor', 'NVMe storage'), '10 GB', '50 GB', '200 GB'],
          [_('PHP workery', 'PHP workers'), '2', '6', _('12 dedikovaných', '12 dedicated')],
          [_('Přenos dat', 'Traffic'), _('Neměřený', 'Unmetered'), _('Neměřený', 'Unmetered'), _('Neměřený', 'Unmetered')],
          [_('Zálohy', 'Backups'), _('7 dní', '7 days'), _('30 dní', '30 days'), _('90 dní', '90 days')],
          ['SSH / WP-CLI', '—', '✓', '✓'],
          [_('Staging prostředí', 'Staging environment'), '—', '✓', '✓'],
          ['WAF + CDN', '—', _('Doplněk', 'Add-on'), _('V ceně', 'Included')],
          [_('Podpora', 'Support'), _('E-mail, 4 h', 'Email, 4 h'), _('Chat, 30 min', 'Chat, 30 min'), _('Prioritní, 10 min', 'Priority, 10 min')]
        ]
      },
      feats: [
        ['01', _('Limity na projekt, ne na server', 'Per-project limits, not per server'), _('Každý web má vlastní CPU a RAM strop. Cizí web vás nepoloží.', 'Every site gets its own CPU and RAM ceiling. A neighbour cannot take you down.')],
        ['02', _('LiteSpeed a cache bez ladění', 'LiteSpeed and cache without tuning'), _('Objektová cache, QUIC a Brotli zapnuté od začátku, ne jako placený doplněk.', 'Object cache, QUIC and Brotli on from the start, not as a paid extra.')],
        ['03', _('Staging na jeden klik', 'One-click staging'), _('Kopie webu, otestujete update, sloučíte zpět. Bez FTP archeologie.', 'Clone the site, test the update, merge back. No FTP archaeology.')],
        ['04', _('Zálohy, které jdou obnovit', 'Backups you can actually restore'), _('Denní snapshoty souborů i DB, obnovení po jednotlivých souborech.', 'Daily file and DB snapshots, restore down to a single file.')],
        ['05', _('WAF proti botům a skenerům', 'WAF against bots and scanners'), _('Pravidla pro WordPress, Presta i Laravel, ne jeden filtr na všechno.', 'Rules for WordPress, Presta and Laravel — not one filter for everything.')],
        ['06', _('Podpora, která umí PHP', 'Support that knows PHP'), _('Odpovídají lidé, kteří čtou váš error log, ne skript s odkazem na dokumentaci.', 'Answers from people who read your error log, not a script linking the docs.')]
      ],
      tech: ['WordPress', 'WooCommerce', 'Laravel', 'Symfony', 'PrestaShop', 'Shopware', 'Nette', 'Drupal', 'Joomla', 'Node.js'],
      bench: [
        { label: 'Onhost NVMe + LiteSpeed', pct: 100, note: '412 ms' },
        { label: _('Průměr ČR (Apache, SATA SSD)', 'CZ average (Apache, SATA SSD)'), pct: 41, note: '1 010 ms' },
        { label: _('Levný hosting s přeprodejem', 'Cheap oversold hosting'), pct: 24, note: '1 720 ms' }
      ],
      benchNote: _('WooCommerce, 1 200 produktů, studená cache, měřeno z Prahy, 30denní medián.', 'WooCommerce, 1,200 products, cold cache, measured from Prague, 30-day median.'),
      cases: [
        { t: _('Firemní web a blog', 'Company site and blog'), d: _('WordPress s tématem na míru, formuláře, newsletter, GDPR lišta.', 'WordPress with a custom theme, forms, newsletter, GDPR banner.'), m: _('Start od 89 Kč', 'From 89 CZK') },
        { t: _('Agentura s portfoliem klientů', 'Agency with a client portfolio'), d: _('Deset až padesát webů pod jedním účtem, oddělené přístupy pro klienty.', 'Ten to fifty sites in one account, separate client access.'), m: _('Standard / Profi', 'Standard / Profi') },
        { t: _('E-shop do 5 000 produktů', 'Shop up to 5,000 products'), d: _('Woo nebo Presta s objektovou cache a dedikovaným workerem.', 'Woo or Presta with object cache and a dedicated worker.'), m: _('Profi + CDN', 'Profi + CDN') }
      ],
      faq: [
        [_('Co znamená „neměřený přenos“?', 'What does “unmetered traffic” mean?'), _('Neúčtujeme datový tok. Fair use řešíme jen u projektů, které jedou přes 2 TB měsíčně — tam se ozveme a nabídneme CDN nebo VPS.', 'We do not bill data transfer. Fair use only comes up above 2 TB a month, where we get in touch and offer CDN or a VPS.')],
        [_('Můžu si zvolit verzi PHP?', 'Can I choose the PHP version?'), _('Ano, 7.4 až 8.4 pro každý web zvlášť, včetně vlastních hodnot memory_limit a max_execution_time.', 'Yes — 7.4 to 8.4 per site, including your own memory_limit and max_execution_time.')],
        [_('Jak funguje výpovědní lhůta?', 'How does cancellation work?'), _('Bez lhůty. Zrušíte v panelu, nevyužitou částku vrátíme jako kredit nebo na účet.', 'None. Cancel in the panel and we return the unused amount as credit or to your account.')],
        [_('Zvládne hosting návštěvnický špičkový provoz?', 'Will it handle a traffic spike?'), _('Do 20 000 návštěv denně bez zásahu. Nad to doporučíme CDN nebo VPS a pomůžeme s přechodem.', 'Up to 20,000 visits a day with no changes. Above that we recommend CDN or a VPS and help you move.')],
        [_('Je v ceně e-mail?', 'Is email included?'), _('Ano, IMAP schránky s antispamem a webmailem. Pro firmy s 25+ uživateli doporučujeme samostatný Mail hosting.', 'Yes — IMAP mailboxes with antispam and webmail. For 25+ users we recommend standalone Mail hosting.')]
      ]
    }),

    'wordpress': P({
      cmpTitle: _('WordPress tarify vedle sebe', 'WordPress plans side by side'),
      cat: _('Web a domény', 'Web and domains'), crumb: 'WordPress hosting',
      kicker: _('Managed WordPress · WP-CLI · Staging', 'Managed WordPress · WP-CLI · Staging'),
      title: _('WordPress, o který se nemusíte starat', 'WordPress you do not have to babysit'),
      lead: _('Aktualizace jádra i pluginů s vizuální kontrolou, objektová cache, WAF pravidla přímo pro WordPress. Když update rozbije web, vrátíme ho zpět automaticky.', 'Core and plugin updates with visual checks, object cache, WordPress-specific WAF rules. If an update breaks the site, we roll it back automatically.'),
      kpis: [['0,3 s', _('LCP po zapnutí cache', 'LCP with cache on')], ['4 200', _('spravovaných instalací', 'managed installs')], ['0', _('rozbitých updatů v 2025', 'broken updates in 2025')]],
      chips: ['WP-CLI', _('Automatické updaty', 'Automatic updates'), _('Visual regression', 'Visual regression'), 'Redis object cache', _('Staging + merge', 'Staging + merge'), _('Rollback 1 klik', '1-click rollback')],
      plans: [
        { name: 'WP Solo', tag: '', price: 149, specs: [_('1 instalace', '1 install'), '20 GB NVMe', _('Automatické updaty', 'Automatic updates'), 'Redis cache', _('Zálohy 30 dní', '30-day backups'), _('Certifikát zdarma', 'Free certificate')] },
        { name: 'WP Studio', tag: _('Pro agentury', 'For agencies'), price: 490, specs: [_('10 instalací', '10 installs'), '100 GB NVMe', _('Staging ke každé', 'Staging for each'), _('Visual regression testy', 'Visual regression tests'), _('Zálohy 90 dní', '90-day backups'), _('Klientské přístupy', 'Client access roles')] },
        { name: 'WP Scale', tag: '', price: 1290, specs: [_('30 instalací', '30 installs'), '300 GB NVMe', _('Dedikované workery', 'Dedicated workers'), 'CDN + WAF', _('Zálohy 180 dní', '180-day backups'), _('SLA 99,99 %', '99.99% SLA')] }
      ],
      cmp: {
        cols: ['WP Solo', 'WP Studio', 'WP Scale'],
        rows: [
          [_('Instalace', 'Installs'), '1', '10', '30'],
          [_('Prostor', 'Storage'), '20 GB', '100 GB', '300 GB'],
          [_('Staging', 'Staging'), _('1 prostředí', '1 environment'), _('Ke každému webu', 'Per site'), _('Ke každému webu', 'Per site')],
          [_('Visual regression', 'Visual regression'), '—', '✓', '✓'],
          ['Redis object cache', '✓', '✓', _('Dedikovaná instance', 'Dedicated instance')],
          [_('CDN a WAF', 'CDN and WAF'), _('Doplněk', 'Add-on'), _('Doplněk', 'Add-on'), _('V ceně', 'Included')],
          [_('Zálohy', 'Backups'), _('30 dní', '30 days'), _('90 dní', '90 days'), _('180 dní', '180 days')],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Updaty s vizuální kontrolou', 'Updates with a visual check'), _('Před a po každém updatu uděláme screenshoty klíčových stránek. Rozdíl nad prahem = rollback a ticket.', 'We screenshot key pages before and after each update. A difference over the threshold means rollback and a ticket.')],
        ['02', _('Objektová cache bez pluginu', 'Object cache without a plugin'), _('Redis běží na úrovni platformy — žádný další plugin, který půjde rozbít.', 'Redis runs at platform level — no extra plugin to break.')],
        ['03', _('WAF pravidla pro WordPress', 'WordPress-aware WAF'), _('Blokujeme xmlrpc útoky, enumeraci uživatelů a známé CVE v pluginech dřív, než stihnete aktualizovat.', 'We block xmlrpc attacks, user enumeration and known plugin CVEs before you get around to updating.')],
        ['04', _('Staging s návratem změn', 'Staging with merge back'), _('Klonujte web, upravte, slučte databázi selektivně po tabulkách.', 'Clone the site, edit, merge the database selectively by table.')],
        ['05', 'WP-CLI a SSH', _('Plný přístup, deploy skriptem i z CI. Nezamykáme vás do panelu.', 'Full access, deploy by script or CI. We do not lock you in a panel.')],
        ['06', _('Klientské role', 'Client roles'), _('Klient vidí jen svůj web a fakturu, ne zbytek portfolia agentury.', 'The client sees only their site and invoice, not the rest of your portfolio.')]
      ],
      tech: ['WooCommerce', 'Elementor', 'ACF', 'Yoast', 'WPML', 'Gravity Forms', 'Bricks', 'WP Rocket', 'Query Monitor'],
      bench: [
        { label: _('Onhost managed WP', 'Onhost managed WP'), pct: 100, note: '286 ms' },
        { label: _('WP na běžném sdíleném hostingu', 'WP on generic shared hosting'), pct: 33, note: '870 ms' },
        { label: _('WP bez objektové cache', 'WP without object cache'), pct: 46, note: '620 ms' }
      ],
      benchNote: _('TTFB, WooCommerce s 900 produkty, 50 souběžných uživatelů, medián z 30 dní.', 'TTFB, WooCommerce with 900 products, 50 concurrent users, 30-day median.'),
      cases: [
        { t: _('Agenturní portfolio', 'Agency portfolio'), d: _('Deset klientských webů, jednotné updaty, oddělené fakturace.', 'Ten client sites, unified updates, separate invoicing.'), m: 'WP Studio' },
        { t: _('Redakce a magazín', 'Newsroom and magazine'), d: _('Vysoký provoz na článcích, plná cache, editoři bez omezení.', 'High article traffic, full-page cache, no limits for editors.'), m: 'WP Scale' },
        { t: _('WooCommerce e-shop', 'WooCommerce store'), d: _('Košík mimo cache, Redis pro sessions, denní zálohy objednávek.', 'Cart outside cache, Redis sessions, daily order backups.'), m: 'WP Scale + CDN' }
      ],
      faq: [
        [_('Můžu si instalovat vlastní pluginy?', 'Can I install my own plugins?'), _('Ano, bez blacklistu. Jen u pluginů, které si dělají vlastní cache, doporučíme vypnout duplicitní funkce.', 'Yes, no blacklist. For plugins with their own caching we will suggest turning off the duplicate features.')],
        [_('Co když update rozbije web?', 'What if an update breaks the site?'), _('Vrátíme instalaci na stav před updatem do dvou minut a pošleme vám diff screenshotů.', 'We roll back to the pre-update state within two minutes and send you the screenshot diff.')],
        [_('Přenesete existující WordPress?', 'Will you move an existing WordPress?'), _('Ano, zdarma a bez výpadku, včetně multisite instalací.', 'Yes — free and with no downtime, multisite included.')],
        [_('Je součástí licence na placené pluginy?', 'Are premium plugin licences included?'), _('Ne, licence zůstávají vaše. Umíme je ale spravovat centrálně a hlídat expiraci.', 'No, licences stay yours. We can manage them centrally and watch expiry dates.')],
        [_('Zvládne to 100 000 návštěv denně?', 'Will it handle 100,000 visits a day?'), _('Ano na WP Scale s CDN. Pro vyšší provoz stavíme dedikovanou konfiguraci na VPS nebo klastru.', 'Yes on WP Scale with CDN. Above that we build a dedicated setup on a VPS or cluster.')]
      ]
    }),

    'eshop': P({
      cmpTitle: _('Kolik e-shopu který tarif utáhne', 'How much store each plan can carry'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('E-shop hosting', 'E-commerce hosting'),
      kicker: _('Woo · Shoptet · Presta · Shopware', 'Woo · Shoptet · Presta · Shopware'),
      title: _('Hosting pro e-shop, který má vydržet Black Friday', 'Store hosting built for Black Friday'),
      lead: _('Košík mimo cache, dedikované PHP workery, škálování na hodiny. Kapacitu navýšíme na kampaň a po ní zase snížíme — platíte jen za dobu, kdy ji potřebujete.', 'Cart outside cache, dedicated PHP workers, hourly scaling. We raise capacity for the campaign and lower it after — you pay only while you need it.'),
      kpis: [['1 900', _('objednávek/h na Boost', 'orders/h on Boost')], ['0,5 s', _('doba do platby', 'time to checkout')], ['99,99 %', _('dostupnost v kampani', 'uptime during campaigns')]],
      chips: [_('Škálování na hodiny', 'Hourly scaling'), 'Redis sessions', _('Fronta objednávek', 'Order queue'), _('Zálohy každou hodinu', 'Hourly backups'), 'PCI-DSS ready', _('Platební brány', 'Payment gateways')],
      plans: [
        { name: 'Shop Start', tag: '', price: 390, specs: [_('do 1 000 produktů', 'up to 1,000 products'), '60 GB NVMe', _('4 PHP workery', '4 PHP workers'), 'Redis', _('Zálohy 4× denně', 'Backups 4× a day'), _('Certifikát zdarma', 'Free certificate')] },
        { name: 'Shop Growth', tag: _('Nejčastější volba', 'Most chosen'), price: 990, specs: [_('do 20 000 produktů', 'up to 20,000 products'), '200 GB NVMe', _('12 workerů', '12 workers'), _('CDN v ceně', 'CDN included'), _('Zálohy každou hodinu', 'Hourly backups'), _('Staging', 'Staging')] },
        { name: 'Shop Peak', tag: '', price: 2490, specs: [_('bez limitu produktů', 'no product limit'), '600 GB NVMe', _('Autoscaling workerů', 'Worker autoscaling'), 'WAF + CDN + DDoS', _('Zálohy 15 min', '15-min backups'), _('SLA 99,99 % a technik na kampaň', '99.99% SLA and a campaign engineer')] }
      ],
      cmp: {
        cols: ['Shop Start', 'Shop Growth', 'Shop Peak'],
        rows: [
          [_('Produkty', 'Products'), '1 000', '20 000', _('Bez limitu', 'Unlimited')],
          [_('PHP workery', 'PHP workers'), '4', '12', _('Autoscaling 8–48', 'Autoscaling 8–48')],
          [_('Objednávky / hodina', 'Orders / hour'), '180', '850', '1 900+'],
          ['Redis / Valkey', '✓', '✓', _('Dedikovaná instance', 'Dedicated instance')],
          [_('CDN', 'CDN'), _('Doplněk', 'Add-on'), _('V ceně', 'Included'), _('V ceně + DDoS', 'Included + DDoS')],
          [_('Zálohy', 'Backups'), _('4× denně', '4× a day'), _('Každou hodinu', 'Hourly'), _('Každých 15 minut', 'Every 15 minutes')],
          [_('Kampaňový režim', 'Campaign mode'), '—', _('Na vyžádání', 'On request'), _('Technik po dobu kampaně', 'Engineer on standby')],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Košík nikdy z cache', 'Cart never cached'), _('Cache pravidla píšeme pro každou platformu zvlášť, aby zákazníkovi nezmizel obsah košíku.', 'We write cache rules per platform so nobody loses their cart contents.')],
        ['02', _('Kampaňové škálování', 'Campaign scaling'), _('Nahlásíte termín, my navýšíme workery a databázi na hodiny. Účtujeme jen využitou dobu.', 'Tell us the date; we scale workers and the database by the hour. You pay only for the hours used.')],
        ['03', _('Fronta na objednávky', 'Order queue'), _('Špička neztratí objednávku — zapíše se do fronty a doběhne, i když je databáze pod tlakem.', 'A spike never loses an order — it queues and completes even under database pressure.')],
        ['04', _('Zálohy po 15 minutách', '15-minute backups'), _('Na Peak obnovíte stav e-shopu s přesností na čtvrthodinu, včetně objednávek.', 'On Peak you can restore the shop to a 15-minute granularity, orders included.')],
        ['05', _('Připraveno na PCI-DSS', 'PCI-DSS ready'), _('Segmentovaná síť, logy s retencí 12 měsíců, podklady pro auditora dodáme.', 'Segmented network, 12-month log retention, auditor documentation provided.')],
        ['06', _('Napojení na brány a dopravce', 'Gateways and carriers'), _('Comgate, GoPay, Stripe, Zásilkovna, PPL, Balíkovna — testujeme, že webhooky dojdou.', 'Comgate, GoPay, Stripe, Zásilkovna, PPL, Balíkovna — we test that webhooks arrive.')]
      ],
      tech: ['WooCommerce', 'PrestaShop', 'Shopware 6', 'Magento 2', 'Shoptet API', 'Medusa', 'Saleor', 'Comgate', 'GoPay', 'Zásilkovna'],
      bench: [
        { label: 'Shop Peak (autoscaling)', pct: 100, note: _('1 900 obj./h', '1,900 orders/h') },
        { label: 'Shop Growth', pct: 45, note: _('850 obj./h', '850 orders/h') },
        { label: _('Typický sdílený hosting', 'Typical shared hosting'), pct: 11, note: _('210 obj./h', '210 orders/h') }
      ],
      benchNote: _('Zátěžový test: WooCommerce, 12 000 produktů, dokončený checkout s platební bránou.', 'Load test: WooCommerce, 12,000 products, completed checkout with payment gateway.'),
      cases: [
        { t: _('Sezónní e-shop', 'Seasonal store'), d: _('Devět měsíců klid, tři měsíce plný výkon. Platíte podle skutečnosti.', 'Nine quiet months, three at full power. You pay for what you use.'), m: 'Growth + Peak' },
        { t: _('B2B velkoobchod', 'B2B wholesale'), d: _('Ceníky na zákazníka, ERP synchronizace, žádná anonymní cache.', 'Per-customer price lists, ERP sync, no anonymous cache.'), m: 'Shop Growth' },
        { t: _('Marketplace s dodavateli', 'Marketplace with vendors'), d: _('Tisíce feedů denně, oddělené workery pro import a pro zákazníky.', 'Thousands of feeds a day, separate workers for imports and shoppers.'), m: 'Shop Peak' }
      ],
      faq: [
        [_('Zvládnete Black Friday?', 'Can you handle Black Friday?'), _('Ano. Termín nám nahlaste dva týdny dopředu, uděláme zátěžový test a na kampaň držíme technika.', 'Yes. Give us the date two weeks ahead — we run a load test and keep an engineer on standby.')],
        [_('Hostujete Shoptet?', 'Do you host Shoptet?'), _('Shoptet je uzavřená platforma, ale hostujeme napojené služby — feedy, PIM, API mezivrstvy, sklad.', 'Shoptet is a closed platform, but we host the services around it — feeds, PIM, API middleware, warehouse.')],
        [_('Jak řešíte platební data?', 'How do you handle payment data?'), _('Karty u vás nikdy neleží — jdou přímo do brány. Segmentaci sítě a logy pro audit dodáme.', 'Card data never lands on your server — it goes straight to the gateway. We supply network segmentation and audit logs.')],
        [_('Migrace e-shopu bez zavření?', 'Migration without closing the shop?'), _('Ano. Kopie, testovací provoz, dosync objednávek a přepnutí DNS v noci. Průměrně 4 minuty nedostupnosti košíku.', 'Yes. Copy, staging traffic, order delta sync and a night DNS switch. Cart unavailable for four minutes on average.')],
        [_('Umíte ERP a sklad?', 'Do you support ERP and warehouse systems?'), _('Pomo, Money S5, ABRA, Helios i vlastní API — máme na to připravené runnery a frontu.', 'Pomo, Money S5, ABRA, Helios or a custom API — we have runners and a queue ready.')]
      ]
    }),

    'mail': P({
      cmpTitle: _('Schránky, archiv a limity podle tarifu', 'Mailboxes, archive and limits by plan'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('Mail hosting', 'Mail hosting'),
      kicker: _('IMAP · DKIM · Archiv 10 let', 'IMAP · DKIM · 10-year archive'),
      title: _('Firemní e-mail, který dojde do doručené pošty', 'Company email that lands in the inbox'),
      lead: _('Schránky na vaší doméně, SPF, DKIM a DMARC nastavené za vás, antispam s 99,7% záchytem. Data v Praze, žádné skenování obsahu.', 'Mailboxes on your domain, SPF, DKIM and DMARC set up for you, antispam catching 99.7%. Data in Prague, no content scanning.'),
      kpis: [['99,7 %', _('záchyt spamu', 'spam caught')], ['0,02 %', _('falešně pozitivních', 'false positives')], ['10 let', _('archivace a eDiscovery', 'archiving and eDiscovery')]],
      chips: ['IMAP / SMTP', 'SPF + DKIM + DMARC', _('Sdílené kalendáře', 'Shared calendars'), _('Webmail v CZ', 'Webmail in CZ'), _('Antivirus', 'Antivirus'), _('Archiv a export', 'Archive and export')],
      plans: [
        { name: _('Schránka', 'Mailbox'), tag: '', price: 39, specs: [_('1 uživatel', '1 user'), '25 GB', 'IMAP, SMTP, webmail', _('Antispam a antivirus', 'Antispam and antivirus'), _('Kalendář a kontakty', 'Calendar and contacts'), _('Mobilní sync', 'Mobile sync')] },
        { name: 'Team', tag: _('Pro firmy', 'For companies'), price: 89, specs: [_('1 uživatel', '1 user'), '100 GB', _('Sdílené schránky', 'Shared mailboxes'), _('Skupinové kalendáře', 'Group calendars'), _('Podpisy centrálně', 'Central signatures'), _('Archiv 3 roky', '3-year archive')] },
        { name: 'Compliance', tag: '', price: 169, specs: [_('1 uživatel', '1 user'), '250 GB', _('Archiv 10 let', '10-year archive'), 'eDiscovery a legal hold', _('DLP pravidla', 'DLP rules'), _('S/MIME', 'S/MIME')] }
      ],
      cmp: {
        cols: [_('Schránka', 'Mailbox'), 'Team', 'Compliance'],
        rows: [
          [_('Prostor na uživatele', 'Storage per user'), '25 GB', '100 GB', '250 GB'],
          [_('Sdílené schránky', 'Shared mailboxes'), '—', '✓', '✓'],
          [_('Kalendáře a kontakty', 'Calendars and contacts'), _('Osobní', 'Personal'), _('Skupinové', 'Group'), _('Skupinové + delegace', 'Group + delegation')],
          [_('Archivace', 'Archiving'), '—', _('3 roky', '3 years'), _('10 let', '10 years')],
          ['eDiscovery / legal hold', '—', '—', '✓'],
          [_('DLP a pravidla obsahu', 'DLP and content rules'), '—', _('Základní', 'Basic'), _('Plné', 'Full')],
          [_('Migrace schránek', 'Mailbox migration'), _('Zdarma', 'Free'), _('Zdarma', 'Free'), _('Zdarma + projektový vedoucí', 'Free + project lead')]
        ]
      },
      feats: [
        ['01', _('Doručitelnost jako služba', 'Deliverability as a service'), _('Nastavíme SPF, DKIM, DMARC a sledujeme reputaci IP. Když se něco zhorší, víme to dřív než vy.', 'We set SPF, DKIM, DMARC and watch IP reputation. If it degrades, we know before you do.')],
        ['02', _('Antispam bez čtení obsahu', 'Antispam without reading content'), _('Filtrujeme podle reputace, hlaviček a příloh. Obsah zpráv nepoužíváme k ničemu jinému.', 'We filter on reputation, headers and attachments. Message content is never used for anything else.')],
        ['03', _('Migrace ze Microsoft 365 nebo Google', 'Migration from Microsoft 365 or Google'), _('Přeneseme schránky, kalendáře i kontakty, uživatel si nemusí nic přenastavovat.', 'We move mailboxes, calendars and contacts — users reconfigure nothing.')],
        ['04', _('Archiv, který obstojí u soudu', 'An archive that holds up in court'), _('Neměnitelný archiv s časovými značkami, export v PST i EML.', 'Immutable, timestamped archive with PST and EML export.')],
        ['05', _('Podpisy pro celou firmu', 'Company-wide signatures'), _('Šablona centrálně, doplní se jméno a pozice z adresáře.', 'One central template that fills in name and role from the directory.')],
        ['06', _('Data v České republice', 'Data in the Czech Republic'), _('Primární i zálohové úložiště v Praze a Brně. Žádný přenos mimo EU.', 'Primary and backup storage in Prague and Brno. No transfer outside the EU.')]
      ],
      tech: ['Outlook', 'Thunderbird', 'Apple Mail', 'iOS / Android', 'Roundcube', 'CalDAV', 'CardDAV', 'ActiveSync'],
      bench: [
        { label: _('Onhost antispam', 'Onhost antispam'), pct: 100, note: '99,7 %' },
        { label: _('Filtr bez reputační vrstvy', 'Filter without reputation layer'), pct: 88, note: '94,1 %' },
        { label: _('Výchozí filtr sdíleného hostingu', 'Default shared-hosting filter'), pct: 76, note: '86,3 %' }
      ],
      benchNote: _('Vzorek 4,2 mil. zpráv za červen 2026, ručně ověřený vzorek 5 000 zpráv pro falešně pozitivní.', 'Sample of 4.2M messages in June 2026, hand-verified 5,000-message sample for false positives.'),
      cases: [
        { t: _('Firma do 50 lidí', 'Company under 50 people'), d: _('Schránky, sdílené kalendáře, jeden správce, faktura na IČO.', 'Mailboxes, shared calendars, one admin, invoice to the company.'), m: 'Team' },
        { t: _('Advokátní kancelář', 'Law firm'), d: _('Archivace deset let, legal hold, S/MIME podpisy.', 'Ten-year archiving, legal hold, S/MIME signing.'), m: 'Compliance' },
        { t: _('Odchod z Microsoft 365', 'Leaving Microsoft 365'), d: _('Migrace 200 schránek za jeden víkend, uživatelé bez zásahu.', '200 mailboxes migrated in one weekend, no user action.'), m: 'Team + migrace' }
      ],
      faq: [
        [_('Přeneseme schránky bez ztráty pošty?', 'Will migration lose any mail?'), _('Ne. Kopírujeme inkrementálně, stará i nová schránka běží paralelně, dokud nepřepnete MX.', 'No. We copy incrementally and run old and new in parallel until you switch MX.')],
        [_('Kolik e-mailů můžu poslat?', 'How many emails can I send?'), _('500 zpráv na hodinu na uživatele. Pro hromadné rozesílky nabízíme oddělené SMTP s vlastní IP.', '500 messages an hour per user. For bulk campaigns we offer separate SMTP with a dedicated IP.')],
        [_('Máte webmail v češtině?', 'Is webmail available in Czech?'), _('Ano, včetně kalendáře, kontaktů a mobilní verze.', 'Yes, including calendar, contacts and a mobile layout.')],
        [_('Podporujete dvoufaktor?', 'Do you support two-factor auth?'), _('Ano, TOTP i hardwarové klíče, vynutitelné pro celou organizaci.', 'Yes — TOTP and hardware keys, enforceable org-wide.')],
        [_('Co když nám někdo spoofuje domény?', 'What if someone spoofs our domain?'), _('DMARC v režimu reject a týdenní reporty. Pokusy uvidíte v panelu.', 'DMARC in reject mode plus weekly reports. Attempts show up in the panel.')]
      ]
    }),

    'domains': P({
      cmpTitle: _('Co dostanete ke každé doméně', 'What comes with every domain'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('Domény a DNS', 'Domains and DNS'),
      kicker: _('420+ koncovek · Anycast DNS', '420+ TLDs · Anycast DNS'),
      title: _('Domény a DNS na infrastruktuře, kterou vidíte', 'Domains and DNS on infrastructure you can see'),
      lead: _('Registrace, transfer i správa DNS s anycast sítí v šesti lokalitách. DNSSEC na klik, historie změn, náhled před uložením — a žádné skryté poplatky za transfer.', 'Registration, transfers and DNS on an anycast network in six locations. One-click DNSSEC, change history, preview before saving — and no hidden transfer fees.'),
      kpis: [['9 ms', _('medián odezvy DNS v ČR', 'median DNS response in CZ')], ['420+', _('koncovek', 'TLDs')], [_('0 Kč', 'Free'), _('za transfer domény', 'domain transfer fee')]],
      chips: ['Anycast', 'DNSSEC', _('Náhled změn', 'Change preview'), _('Historie a rollback', 'History and rollback'), 'API + Terraform', _('WHOIS ochrana', 'WHOIS privacy')],
      plans: [
        { name: '.cz', tag: _('Nejžádanější', 'Most wanted'), price: 179, unitYear: true, specs: [_('Registrace na rok', 'One year registration'), _('DNS hosting v ceně', 'DNS hosting included'), _('WHOIS ochrana', 'WHOIS privacy'), _('Přesměrování a e-mail alias', 'Redirects and email alias'), 'DNSSEC', _('Automatická obnova', 'Auto-renew')] },
        { name: '.com', tag: '', price: 289, unitYear: true, specs: [_('Registrace na rok', 'One year registration'), _('DNS hosting v ceně', 'DNS hosting included'), _('WHOIS ochrana', 'WHOIS privacy'), _('Zámek transferu', 'Transfer lock'), 'DNSSEC', _('Automatická obnova', 'Auto-renew')] },
        { name: _('DNS Pro', 'DNS Pro'), tag: '', price: 149, specs: [_('Neomezeně zón', 'Unlimited zones'), _('Anycast v 6 lokalitách', 'Anycast in 6 locations'), _('Geo a failover záznamy', 'Geo and failover records'), _('Sekundární DNS', 'Secondary DNS'), 'API a Terraform', _('Monitoring zóny', 'Zone monitoring')] }
      ],
      cmp: {
        cols: [_('Zdarma s hostingem', 'Free with hosting'), 'DNS Pro', _('DNS Enterprise', 'DNS Enterprise')],
        rows: [
          [_('Zóny', 'Zones'), '25', _('Neomezeně', 'Unlimited'), _('Neomezeně', 'Unlimited')],
          [_('Anycast lokality', 'Anycast locations'), '3', '6', _('6 + vlastní PoP', '6 + custom PoP')],
          ['DNSSEC', '✓', '✓', '✓'],
          [_('Geo směrování', 'Geo routing'), '—', '✓', '✓'],
          [_('Failover a health check', 'Failover and health checks'), '—', '✓', _('S SLA', 'With SLA')],
          [_('Sekundární DNS', 'Secondary DNS'), '—', '✓', '✓'],
          ['API / Terraform', _('Čtení', 'Read'), _('Plné', 'Full'), _('Plné + webhooky', 'Full + webhooks')],
          [_('Dotazy měsíčně', 'Monthly queries'), '5 mil.', '100 mil.', _('Bez limitu', 'Unlimited')]
        ]
      },
      feats: [
        ['01', _('Náhled před uložením', 'Preview before saving'), _('Editor ukáže diff záznamů a upozorní na chybějící MX nebo rozbité SPF, než potvrdíte.', 'The editor shows a record diff and warns about missing MX or broken SPF before you confirm.')],
        ['02', _('Historie a návrat', 'History and rollback'), _('Každá změna zóny má autora, čas a jedno tlačítko zpět.', 'Every zone change has an author, timestamp and a single undo button.')],
        ['03', _('Anycast v šesti lokalitách', 'Anycast in six locations'), _('Praha, Brno, Varšava, Frankfurt, Amsterdam, Ashburn. Dotaz obslouží nejbližší uzel.', 'Prague, Brno, Warsaw, Frankfurt, Amsterdam, Ashburn. The nearest node answers.')],
        ['04', _('Transfer bez poplatku', 'No-fee transfers'), _('Přesun domény od jiného registrátora je zdarma, prodloužení o rok se přičte.', 'Moving a domain from another registrar is free and adds a year.')],
        ['05', _('Hromadná správa', 'Bulk management'), _('Stovky domén ve tabulce: obnova, kontakty, nameservery, DNSSEC jednou akcí.', 'Hundreds of domains in one table: renewals, contacts, nameservers, DNSSEC in one action.')],
        ['06', _('Infrastruktura jako kód', 'Infrastructure as code'), _('Terraform provider a REST API — zóny ve gitu, ne v panelu.', 'Terraform provider and REST API — zones in git, not in a panel.')]
      ],
      tech: ['Terraform', 'cert-manager', 'ACME DNS-01', 'Cloudflare import', 'BIND zone import', 'Route 53 import', 'External DNS'],
      bench: [
        { label: _('Onhost anycast (Praha)', 'Onhost anycast (Prague)'), pct: 100, note: '9 ms' },
        { label: _('Globální poskytovatel (Frankfurt)', 'Global provider (Frankfurt)'), pct: 45, note: '20 ms' },
        { label: _('DNS u registrátora domén', 'Registrar bundled DNS'), pct: 20, note: '46 ms' }
      ],
      benchNote: _('Medián odezvy autoritativního dotazu z deseti českých sítí, červen 2026.', 'Median authoritative query response from ten Czech networks, June 2026.'),
      cases: [
        { t: _('Portfolio domén', 'Domain portfolio'), d: _('Sto domén, hromadná obnova, jeden ceník, jedna faktura.', 'A hundred domains, bulk renewal, one price list, one invoice.'), m: _('DNS zdarma', 'DNS free') },
        { t: _('Multiregionální web', 'Multi-region site'), d: _('Geo směrování na nejbližší server, failover na druhou lokalitu.', 'Geo routing to the nearest server, failover to a second location.'), m: 'DNS Pro' },
        { t: _('Kubernetes a certifikáty', 'Kubernetes and certificates'), d: _('ACME DNS-01 přes API, certifikáty se obnovují bez zásahu.', 'ACME DNS-01 over API, certificates renew unattended.'), m: 'DNS Pro' }
      ],
      faq: [
        [_('Jak dlouho trvá transfer?', 'How long does a transfer take?'), _('U .cz do jedné hodiny po potvrzení, u gTLD obvykle pět dní podle pravidel registru.', 'Under an hour for .cz after confirmation; gTLDs typically five days per registry rules.')],
        [_('Zůstane doména moje?', 'Do I keep ownership?'), _('Ano, jste vždy držitel. Odchod kdykoli, autorizační kód vydáme okamžitě a zdarma.', 'Yes, you are always the registrant. Leave anytime — we issue the auth code immediately and free.')],
        [_('Umíte sekundární DNS k cizímu primáru?', 'Can you be secondary to an external primary?'), _('Ano, AXFR i IXFR s TSIG. Funguje i naopak — jsme primár, cizí služba sekundár.', 'Yes, AXFR and IXFR with TSIG. It works the other way too.')],
        [_('Co se stane, když zapomenu obnovit?', 'What if I forget to renew?'), _('Upozorníme 30, 14 a 3 dny předem, a automatická obnova je zapnutá defaultně.', 'We notify 30, 14 and 3 days ahead, and auto-renew is on by default.')],
        [_('Podporujete IDN a nové koncovky?', 'Do you support IDN and new TLDs?'), _('Ano, včetně české diakritiky a koncovek typu .dev, .ai nebo .app.', 'Yes, including Czech diacritics and TLDs like .dev, .ai or .app.')]
      ]
    }),

    'ssl': P({
      cmpTitle: _('Typy certifikátů a k čemu jsou', 'Certificate types and what they are for'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('SSL certifikáty', 'SSL certificates'),
      kicker: _('DV · OV · EV · Wildcard', 'DV · OV · EV · Wildcard'),
      title: _('Certifikáty, které se obnoví samy', 'Certificates that renew themselves'),
      lead: _('Let\u2019s Encrypt v ceně každého hostingu, komerční certifikáty s pojištěním a ověřením firmy. Automatická obnova, hlídání expirace u domén i mimo Onhost.', 'Let\u2019s Encrypt included with every hosting plan, commercial certificates with warranty and company validation. Auto-renewal and expiry monitoring, even for domains outside Onhost.'),
      kpis: [[_('0 Kč', 'Free'), _('DV certifikát v ceně', 'DV certificate included')], ['4 min', _('vystavení DV', 'DV issuance')], ['0', _('propadlých certifikátů', 'expired certificates')]],
      chips: ['ACME / HTTP-01', 'DNS-01', _('Wildcard', 'Wildcard'), _('Pojištění do 1,5 mil. $', 'Warranty up to $1.5M'), _('Hlídání expirace', 'Expiry monitoring'), 'OCSP stapling'],
      plans: [
        { name: _('DV zdarma', 'DV free'), tag: _('V ceně hostingu', 'Included'), price: 0, priceLabel: _('V ceně', 'Included'), priceNote: _('u každého hostingu', 'with every hosting plan'), ctaLabel: _('Zapnout v panelu', 'Enable in the panel'), unitYear: true, specs: [_('Ověření domény', 'Domain validation'), _('Obnova automaticky', 'Automatic renewal'), _('Neomezeně certifikátů', 'Unlimited certificates'), _('Wildcard přes DNS-01', 'Wildcard via DNS-01'), 'HTTP/3 + HSTS', _('Instalace na klik', 'One-click install')] },
        { name: 'OV Business', tag: '', price: 1490, unitYear: true, specs: [_('Ověření firmy', 'Organisation validation'), _('Pojištění 1 mil. $', '$1M warranty'), _('Vystavení do 2 dnů', 'Issued within 2 days'), _('Zelený audit log', 'Audit log'), _('Podpora s prioritou', 'Priority support'), _('Site seal', 'Site seal')] },
        { name: 'Wildcard OV', tag: '', price: 3990, unitYear: true, specs: [_('*.vasedomena.cz', '*.yourdomain.com'), _('Neomezeně subdomén', 'Unlimited subdomains'), _('Pojištění 1,5 mil. $', '$1.5M warranty'), _('Vystavení do 2 dnů', 'Issued within 2 days'), _('Instalace zdarma', 'Free installation'), _('Hlídání expirace', 'Expiry monitoring')] }
      ],
      cmp: {
        cols: [_('DV zdarma', 'DV free'), 'OV Business', 'Wildcard OV'],
        rows: [
          [_('Ověření', 'Validation'), _('Doména', 'Domain'), _('Firma', 'Organisation'), _('Firma', 'Organisation')],
          [_('Doba vystavení', 'Issuance time'), '4 min', _('1–2 dny', '1–2 days'), _('1–2 dny', '1–2 days')],
          [_('Subdomény', 'Subdomains'), _('Jednotlivě', 'Individually'), _('Jednotlivě', 'Individually'), _('Všechny', 'All')],
          [_('Pojištění', 'Warranty'), '—', '1 mil. $', '1,5 mil. $'],
          [_('Automatická obnova', 'Auto-renewal'), '✓', '✓', '✓'],
          [_('Vhodné pro', 'Best for'), _('Weby, API, staging', 'Sites, APIs, staging'), _('E-shopy, banky, portály', 'Shops, banks, portals'), _('SaaS s subdomény na klienta', 'SaaS with per-client subdomains')]
        ]
      },
      feats: [
        ['01', _('Obnova bez zásahu', 'Hands-off renewal'), _('ACME klient běží na platformě, nová verze certifikátu se nasadí 30 dní před expirací.', 'The ACME client runs on the platform and deploys a new certificate 30 days before expiry.')],
        ['02', _('Wildcard přes DNS-01', 'Wildcard over DNS-01'), _('Nemusíte otevírat port 80 ani parkovat subdomény — ověří se přes naše DNS API.', 'No need to open port 80 or park subdomains — validation runs through our DNS API.')],
        ['03', _('Hlídání i cizích certifikátů', 'Monitoring for external certificates'), _('Zadáte domény hostované jinde, my hlásíme expiraci, slabé šifry a chybějící řetěz.', 'Add domains hosted elsewhere and we report expiry, weak ciphers and missing chains.')],
        ['04', _('Ověření firmy s námi', 'We handle organisation validation'), _('Podklady pro certifikační autoritu připravíme a komunikaci vedeme za vás.', 'We prepare the CA paperwork and handle the correspondence for you.')],
        ['05', _('Silné výchozí nastavení', 'Strong defaults'), _('TLS 1.3, HSTS, OCSP stapling a moderní šifry bez ručního ladění.', 'TLS 1.3, HSTS, OCSP stapling and modern ciphers with no manual tuning.')],
        ['06', _('Certifikáty i pro VPS', 'Certificates for your VPS too'), _('Vystavíme a doručíme přes API, nasadíte je na vlastní server nebo do balanceru.', 'Issued and delivered over API for your own server or load balancer.')]
      ],
      tech: ['Let\u2019s Encrypt', 'Sectigo', 'DigiCert', 'certbot', 'cert-manager', 'acme.sh', 'Traefik', 'nginx', 'HAProxy'],
      bench: [
        { label: _('DV přes DNS-01 u Onhostu', 'DV via DNS-01 at Onhost'), pct: 100, note: '4 min' },
        { label: _('DV ruční instalace jinde', 'Manual DV elsewhere'), pct: 12, note: _('35 min práce', '35 min of work') },
        { label: _('OV s naší asistencí', 'OV with our assistance'), pct: 55, note: _('1–2 dny', '1–2 days') }
      ],
      benchNote: _('Doba od žádosti do funkčního HTTPS, medián za červen 2026.', 'Time from request to working HTTPS, median for June 2026.'),
      cases: [
        { t: _('Web a API', 'Site and API'), d: _('DV certifikáty automaticky pro produkci i staging.', 'Automatic DV certificates for production and staging.'), m: _('Zdarma', 'Free') },
        { t: _('E-shop s platbami', 'Store with payments'), d: _('OV certifikát s ověřenou firmou a pojištěním pro brány.', 'OV certificate with validated company and gateway-friendly warranty.'), m: 'OV Business' },
        { t: _('SaaS s subdoménami', 'SaaS with subdomains'), d: _('Wildcard pro *.app.klient.cz, obnova přes API bez lidí.', 'Wildcard for *.app.client.com, renewal over API with no humans.'), m: 'Wildcard OV' }
      ],
      faq: [
        [_('Je zdarma certifikát dost dobrý?', 'Is a free certificate good enough?'), _('Pro většinu webů ano — šifruje stejně silně. Rozdíl je v ověření firmy a pojištění, které řeší e-shopy a finance.', 'For most sites, yes — the encryption is identical. The difference is organisation validation and warranty, which matter for shops and finance.')],
        [_('Instalujete certifikát i na cizí server?', 'Do you install on external servers?'), _('U OV a Wildcard ano, instalaci provedeme na vašem VPS nebo balanceru v rámci ceny.', 'For OV and Wildcard, yes — installation on your VPS or balancer is included.')],
        [_('Co když certifikát vyprší?', 'What if a certificate expires?'), _('Nevyprší. Obnova běží 30 dní předem, a když selže, dostanete alert do panelu a e-mailem.', 'It will not. Renewal runs 30 days ahead, and if it fails you get a panel and email alert.')],
        [_('Podporujete ECC?', 'Do you support ECC?'), _('Ano, ECDSA P-256 i RSA 2048/4096, dual cert konfigurace na vyžádání.', 'Yes — ECDSA P-256 and RSA 2048/4096, dual-cert setups on request.')],
        [_('Vydáte certifikát pro interní doménu?', 'Can you issue for an internal domain?'), _('Pro veřejné TLD ano. Pro interní jména nabízíme privátní CA v rámci Enterprise.', 'For public TLDs, yes. For internal names we offer a private CA under Enterprise.')]
      ]
    }),

    'cdn': P({
      cmpTitle: _('Kapacita mitigace a pravidla WAF', 'Mitigation capacity and WAF rules'),
      cat: _('Web a domény', 'Web and domains'), crumb: _('CDN a DDoS ochrana', 'CDN and DDoS protection'),
      kicker: _('1,2 Tbps · 14 PoP · WAF', '1.2 Tbps · 14 PoPs · WAF'),
      title: _('CDN a ochrana, která filtruje dřív, než to poznáte', 'CDN and protection that filters before you notice'),
      lead: _('Statika ze čtrnácti lokalit, mitigace útoků do 1,2 Tbps a WAF s pravidly pro konkrétní aplikace. Provoz zůstává v Evropě, logy máte k dispozici.', 'Static assets from fourteen locations, mitigation up to 1.2 Tbps and a WAF with application-specific rules. Traffic stays in Europe and logs are yours.'),
      kpis: [['1,2 Tbps', _('kapacita mitigace', 'mitigation capacity')], ['< 3 s', _('detekce útoku', 'attack detection')], ['14', _('PoP lokalit', 'PoP locations')]],
      chips: ['HTTP/3 + QUIC', 'Brotli', _('Image optimalizace', 'Image optimisation'), _('WAF (OWASP + vlastní)', 'WAF (OWASP + custom)'), _('Bot management', 'Bot management'), _('Logy do S3', 'Logs to S3')],
      plans: [
        { name: 'CDN Start', tag: '', price: 190, specs: [_('1 TB / měs.', '1 TB / mo'), _('14 PoP', '14 PoPs'), 'HTTP/3, Brotli', _('Základní WAF', 'Basic WAF'), _('DDoS L3/L4 v ceně', 'L3/L4 DDoS included'), _('Certifikát zdarma', 'Free certificate')] },
        { name: 'Shield', tag: _('Doporučeno', 'Recommended'), price: 890, specs: [_('10 TB / měs.', '10 TB / mo'), _('WAF s vlastními pravidly', 'WAF with custom rules'), _('Bot management', 'Bot management'), _('L7 mitigace', 'L7 mitigation'), _('Image optimalizace', 'Image optimisation'), _('Logy v reálném čase', 'Real-time logs')] },
        { name: 'Shield Max', tag: '', price: 2890, specs: [_('50 TB / měs.', '50 TB / mo'), _('Dedikované scrubbing centrum', 'Dedicated scrubbing centre'), 'BGP anycast /24', _('Vlastní pravidla na míru', 'Bespoke rule tuning'), _('SLA 99,99 %', '99.99% SLA'), _('Technik na útok', 'Engineer during attacks')] }
      ],
      cmp: {
        cols: ['CDN Start', 'Shield', 'Shield Max'],
        rows: [
          [_('Přenos v ceně', 'Included traffic'), '1 TB', '10 TB', '50 TB'],
          [_('DDoS L3 / L4', 'DDoS L3 / L4'), '✓', '✓', '✓'],
          [_('DDoS L7', 'DDoS L7'), _('Základní', 'Basic'), '✓', _('Vlastní profily', 'Custom profiles')],
          ['WAF', 'OWASP CRS', _('OWASP + vlastní', 'OWASP + custom'), _('Ladění na aplikaci', 'Per-app tuning')],
          [_('Bot management', 'Bot management'), '—', '✓', _('S ML skóre', 'With ML scoring')],
          [_('Logy', 'Logs'), _('24 h v panelu', '24 h in panel'), _('Real-time export', 'Real-time export'), _('Real-time + 12 měs. retence', 'Real-time + 12-month retention')],
          ['BGP anycast /24', '—', '—', '✓'],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Mitigace na okraji sítě', 'Mitigation at the edge'), _('Útok se zastaví v PoP, váš server o něm většinou nikdy neuslyší.', 'The attack stops in the PoP; your server usually never hears about it.')],
        ['02', _('WAF pravidla pro váš stack', 'WAF rules for your stack'), _('WordPress, Presta, Laravel, Node API — profily se liší, ne jeden filtr na všechno.', 'WordPress, Presta, Laravel, Node APIs — profiles differ, not one filter for everything.')],
        ['03', _('Bot management se skóre', 'Bot management with scoring'), _('Scrapery a credential stuffing dostanou challenge, vyhledávače projdou.', 'Scrapers and credential stuffing get a challenge; search engines pass.')],
        ['04', _('Optimalizace obrázků', 'Image optimisation'), _('AVIF a WebP na hraně, změna velikosti podle zařízení, bez pluginů.', 'AVIF and WebP at the edge, device-based resizing, no plugins.')],
        ['05', _('Logy, které jsou vaše', 'Logs that belong to you'), _('Streamujte je do vlastního S3, Lokiho nebo SIEM. Nezamykáme data.', 'Stream them to your own S3, Loki or SIEM. We do not lock data in.')],
        ['06', _('Provoz v Evropě', 'Traffic in Europe'), _('Evropské PoP jako výchozí, mimo EU jen když si to zapnete.', 'European PoPs by default; outside the EU only if you enable it.')]
      ],
      tech: ['nginx', 'Cloudflare migrace', 'Fastly migrace', 'Terraform', 'Prometheus', 'Grafana Loki', 'Elastic SIEM'],
      bench: [
        { label: _('Se CDN Shield (Brno → Praha)', 'With CDN Shield (Brno → Prague)'), pct: 100, note: '38 ms' },
        { label: _('Bez CDN, origin v Praze', 'No CDN, origin in Prague'), pct: 34, note: '112 ms' },
        { label: _('CDN s PoP jen ve Frankfurtu', 'CDN with Frankfurt-only PoP'), pct: 61, note: '62 ms' }
      ],
      benchNote: _('Doručení statického assetu 250 kB, medián z deseti českých a slovenských sítí.', 'Delivery of a 250 kB static asset, median from ten Czech and Slovak networks.'),
      cases: [
        { t: _('Gameserver pod útokem', 'Game server under attack'), d: _('UDP flood na 400 Gbps odfiltrovaný bez ztráty hráčů.', 'A 400 Gbps UDP flood filtered without losing players.'), m: 'Shield' },
        { t: _('Zpravodajský web', 'News site'), d: _('Nárazový provoz na článcích ze čtrnácti PoP, origin v klidu.', 'Article traffic spikes served from fourteen PoPs, origin idle.'), m: 'CDN Start' },
        { t: _('API pro mobilní aplikaci', 'Mobile app API'), d: _('Bot skóre, rate limity a WAF proti zneužití endpointů.', 'Bot scoring, rate limits and WAF against endpoint abuse.'), m: 'Shield Max' }
      ],
      faq: [
        [_('Účtujete provoz během útoku?', 'Do you bill attack traffic?'), _('Ne. Filtrovaný škodlivý provoz se do vašeho objemu nepočítá.', 'No. Filtered malicious traffic does not count towards your volume.')],
        [_('Můžu si nechat vlastní origin jinde?', 'Can my origin stay elsewhere?'), _('Ano, origin může být kdekoli — u nás, u konkurence, i ve vaší serverovně.', 'Yes, the origin can be anywhere — with us, with a competitor, or in your own rack.')],
        [_('Jak dlouho trvá nasazení?', 'How long does onboarding take?'), _('U CDN typicky 20 minut, u BGP anycast /24 pět pracovních dní.', 'Typically 20 minutes for CDN, five business days for BGP anycast /24.')],
        [_('Umíte chránit i jiné protokoly než HTTP?', 'Do you protect non-HTTP protocols?'), _('Ano, TCP i UDP profily pro hry, VoIP a herní API.', 'Yes — TCP and UDP profiles for games, VoIP and game APIs.')],
        [_('Vidím, co se blokuje?', 'Can I see what gets blocked?'), _('Ano, každý zásah má pravidlo, IP, zemi i důvod. Pravidla si můžete vypnout.', 'Yes — every action logs the rule, IP, country and reason. You can disable rules.')]
      ]
    }),

    'backup': P({
      cmpTitle: _('Body obnovy, retence a rychlost návratu', 'Restore points, retention and recovery speed'),
      cat: _('Data a bezpečnost', 'Data and security'), crumb: _('Zálohy a obnova', 'Backups and DR'),
      kicker: _('3-2-1 · Immutable · RPO 15 min', '3-2-1 · Immutable · 15-min RPO'),
      title: _('Zálohy, které jste opravdu vyzkoušeli obnovit', 'Backups you have actually restored'),
      lead: _('Tři kopie, dvě média, jedna offsite lokalita — a měsíční automatický test obnovy s protokolem. Ransomware nemá jak zálohy přepsat, jsou neměnné.', 'Three copies, two media, one offsite location — plus a monthly automated restore test with a report. Ransomware cannot overwrite them; they are immutable.'),
      kpis: [['15 min', 'RPO'], ['< 30 min', _('RTO pro VPS', 'RTO for a VPS')], ['100 %', _('úspěšných testů obnovy', 'successful restore tests')]],
      chips: [_('Neměnné snapshoty', 'Immutable snapshots'), _('Offsite Brno / Varšava', 'Offsite Brno / Warsaw'), _('Šifrování AES-256', 'AES-256 encryption'), _('Test obnovy měsíčně', 'Monthly restore test'), _('Obnova po souborech', 'File-level restore'), 'API' ],
      plans: [
        { name: 'Backup Basic', tag: '', price: 90, specs: ['100 GB', _('Denní snapshoty', 'Daily snapshots'), _('Retence 14 dní', '14-day retention'), _('Obnova po souborech', 'File-level restore'), _('Šifrování v klidu', 'Encryption at rest'), _('Panel a API', 'Panel and API')] },
        { name: 'Backup Pro', tag: _('Nejčastější volba', 'Most chosen'), price: 290, specs: ['1 TB', _('Snapshoty každou hodinu', 'Hourly snapshots'), _('Retence 90 dní', '90-day retention'), _('Offsite kopie', 'Offsite copy'), _('Neměnný režim', 'Immutable mode'), _('Měsíční test obnovy', 'Monthly restore test')] },
        { name: 'DR Site', tag: '', price: 1990, specs: [_('Replika celé infrastruktury', 'Full infrastructure replica'), 'RPO 15 min', _('RTO do 30 min', 'RTO under 30 min'), _('Druhá lokalita', 'Second location'), _('Plán obnovy a cvičení', 'DR plan and drills'), _('SLA na obnovu', 'Restore SLA')] }
      ],
      cmp: {
        cols: ['Backup Basic', 'Backup Pro', 'DR Site'],
        rows: [
          [_('Frekvence', 'Frequency'), _('Denně', 'Daily'), _('Každou hodinu', 'Hourly'), _('Průběžná replikace', 'Continuous replication')],
          ['RPO', '24 h', '1 h', '15 min'],
          ['RTO', _('4 h', '4 h'), _('1 h', '1 h'), _('30 min', '30 min')],
          [_('Retence', 'Retention'), _('14 dní', '14 days'), _('90 dní', '90 days'), _('365 dní', '365 days')],
          [_('Offsite kopie', 'Offsite copy'), '—', '✓', _('Aktivní lokalita', 'Active site')],
          [_('Neměnnost', 'Immutability'), '—', '✓', '✓'],
          [_('Test obnovy', 'Restore testing'), _('Ruční', 'Manual'), _('Měsíčně automaticky', 'Monthly, automated'), _('Čtvrtletní cvičení s protokolem', 'Quarterly drill with report')]
        ]
      },
      feats: [
        ['01', _('Neměnné snapshoty', 'Immutable snapshots'), _('Ani root, ani my zálohu v retenčním okně nesmažeme. Ransomware taky ne.', 'Neither root nor we can delete a backup inside the retention window. Nor can ransomware.')],
        ['02', _('Test obnovy, ne slib', 'Restore tests, not promises'), _('Každý měsíc zálohu obnovíme do izolovaného prostředí a pošleme protokol.', 'Every month we restore into an isolated environment and send you the report.')],
        ['03', _('Obnova po jednotlivých souborech', 'File-level restore'), _('Nemusíte vracet celý server kvůli jedné tabulce nebo jednomu PDF.', 'No need to roll back a whole server for one table or one PDF.')],
        ['04', _('Offsite v jiném městě', 'Offsite in another city'), _('Kopie v Brně nebo Varšavě, minimálně 200 km od primární lokality.', 'A copy in Brno or Warsaw, at least 200 km from the primary site.')],
        ['05', _('Šifrování s vaším klíčem', 'Encryption with your key'), _('AES-256 a možnost vlastního KMS klíče — bez klíče nedostaneme data ani my.', 'AES-256 with optional customer-managed KMS keys — without the key even we cannot read data.')],
        ['06', _('Plán obnovy na papíře', 'A written DR plan'), _('U DR Site dostanete dokument s rolemi, kroky a telefonním číslem, které fungují ve 3 ráno.', 'DR Site includes a document with roles, steps and a phone number that works at 3am.')]
      ],
      tech: ['Proxmox Backup', 'Restic', 'Borg', 'Veeam', 'MySQL / MariaDB', 'PostgreSQL', _('S3 kompatibilní', 'S3 compatible'), 'Kubernetes Velero'],
      bench: [
        { label: _('Obnova 200 GB VPS (DR Site)', 'Restore 200 GB VPS (DR Site)'), pct: 100, note: '11 min' },
        { label: _('Obnova 200 GB (Backup Pro)', 'Restore 200 GB (Backup Pro)'), pct: 38, note: '29 min' },
        { label: _('Ruční obnova ze staženého archivu', 'Manual restore from a downloaded archive'), pct: 8, note: '2 h 20 min' }
      ],
      benchNote: _('Měřeno na 200 GB obrazu VPS s databází, průměr z deseti obnov v červnu 2026.', 'Measured on a 200 GB VPS image with database, average of ten restores in June 2026.'),
      cases: [
        { t: _('Ochrana proti ransomwaru', 'Ransomware protection'), d: _('Neměnné zálohy v druhé lokalitě, obnova bez placení výkupného.', 'Immutable backups in a second location, recovery without paying a ransom.'), m: 'Backup Pro' },
        { t: _('Compliance a audit', 'Compliance and audit'), d: _('Retence, protokoly testů obnovy a šifrování pro NIS2 a ISO 27001.', 'Retention, restore-test reports and encryption for NIS2 and ISO 27001.'), m: 'Backup Pro / DR' },
        { t: _('Kritický provoz', 'Critical operations'), d: _('Objednávky nebo výroba, kde hodina bez systému stojí víc než záloha.', 'Orders or production where an hour of downtime costs more than the backup.'), m: 'DR Site' }
      ],
      faq: [
        [_('Jsou zálohy v ceně hostingu?', 'Are backups included in hosting?'), _('Základní denní zálohy ano, u všech tarifů. Placené plány přidávají frekvenci, retenci, offsite a neměnnost.', 'Basic daily backups are included in every plan. Paid plans add frequency, retention, offsite copies and immutability.')],
        [_('Můžu si zálohu stáhnout?', 'Can I download a backup?'), _('Ano, kdykoli, v otevřených formátech. Nedržíme vás v proprietárním balíku.', 'Yes, anytime, in open formats. No proprietary lock-in.')],
        [_('Zálohujete i servery mimo Onhost?', 'Do you back up servers outside Onhost?'), _('Ano, agentem nebo přes S3 endpoint — funguje i pro on-premise stroje.', 'Yes, via agent or S3 endpoint — it works for on-premise machines too.')],
        [_('Jak zjistím, že záloha funguje?', 'How do I know a backup works?'), _('Dostanete měsíční protokol s časem obnovy a kontrolními součty. Test si můžete spustit i sami.', 'You get a monthly report with restore time and checksums. You can also run a test yourself.')],
        [_('Kdo má přístup k datům?', 'Who has access to the data?'), _('Nikdo bez vašeho klíče. Přístupy našich techniků jsou logované a časově omezené.', 'Nobody without your key. Our engineers\u2019 access is logged and time-limited.')]
      ]
    }),

    'database': P({
      cmpTitle: _('Databázové klastry podle zátěže', 'Database clusters by workload'),
      cat: _('Data a bezpečnost', 'Data and security'), crumb: _('Managed databáze', 'Managed databases'),
      kicker: _('PostgreSQL · MySQL · Valkey', 'PostgreSQL · MySQL · Valkey'),
      title: _('Databáze bez nočních služeb', 'Databases without night shifts'),
      lead: _('PostgreSQL, MySQL, Valkey a ClickHouse s automatickým failoverem, point-in-time obnovou a upgrady bez výpadku. Ladíme dotazy s vámi, ne za příplatek.', 'PostgreSQL, MySQL, Valkey and ClickHouse with automatic failover, point-in-time recovery and zero-downtime upgrades. Query tuning included, not billed extra.'),
      kpis: [['< 20 s', _('automatický failover', 'automatic failover')], ['1 s', _('granularita PITR', 'PITR granularity')], ['99,99 %', _('dostupnost v HA', 'HA uptime')]],
      chips: ['PostgreSQL 17', 'MySQL 8.4', 'Valkey / Redis', 'ClickHouse', 'pgvector', 'PgBouncer', _('PITR', 'PITR'), _('Read replicas', 'Read replicas')],
      plans: [
        { name: 'DB Start', tag: '', price: 390, specs: ['2 vCPU / 4 GB', '80 GB NVMe', _('Denní zálohy', 'Daily backups'), _('PITR 7 dní', '7-day PITR'), _('Jedna instance', 'Single instance'), _('Panel a metriky', 'Panel and metrics')] },
        { name: 'DB HA', tag: _('Doporučeno', 'Recommended'), price: 1290, specs: ['4 vCPU / 16 GB', '400 GB NVMe', _('Primár + replika', 'Primary + replica'), _('Automatický failover', 'Automatic failover'), _('PITR 30 dní', '30-day PITR'), 'PgBouncer + read replica'] },
        { name: 'DB Scale', tag: '', price: 3490, specs: ['16 vCPU / 64 GB', '2 TB NVMe', _('3 uzly', '3 nodes'), _('Čtecí repliky ve 2 lokalitách', 'Read replicas in 2 locations'), _('PITR 90 dní', '90-day PITR'), _('Ladění dotazů s naším DBA', 'Query tuning with our DBA')] }
      ],
      cmp: {
        cols: ['DB Start', 'DB HA', 'DB Scale'],
        rows: [
          [_('Uzly', 'Nodes'), '1', '2', '3+'],
          [_('Automatický failover', 'Automatic failover'), '—', _('< 20 s', '< 20 s'), _('< 20 s', '< 20 s')],
          ['PITR', _('7 dní', '7 days'), _('30 dní', '30 days'), _('90 dní', '90 days')],
          [_('Čtecí repliky', 'Read replicas'), '—', '1', _('Až 5, i jiná lokalita', 'Up to 5, cross-region')],
          [_('Connection pooling', 'Connection pooling'), _('Doplněk', 'Add-on'), '✓', '✓'],
          [_('Upgrade bez výpadku', 'Zero-downtime upgrade'), '—', '✓', '✓'],
          [_('Podpora ladění', 'Tuning support'), _('Doporučení v panelu', 'Panel recommendations'), _('Konzultace 2 h / měs.', '2 h / month consulting'), _('DBA na projekt', 'Dedicated DBA time')],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Failover, který si ověříte', 'Failover you can verify'), _('Přepnutí primáru na repliku otestujete jedním klikem, i v pracovní den.', 'Promote a replica with one click and test it any working day.')],
        ['02', _('Point-in-time obnova', 'Point-in-time recovery'), _('Vrátíte databázi na sekundu před tím, než někdo pustil DELETE bez WHERE.', 'Roll back to the second before someone ran DELETE without WHERE.')],
        ['03', _('Pomalé dotazy na očích', 'Slow queries in plain sight'), _('Panel ukáže top dotazy, chybějící indexy a návrh opravy s odhadem zrychlení.', 'The panel shows top queries, missing indexes and a suggested fix with expected gain.')],
        ['04', _('Upgrady bez okna', 'Upgrades without a window'), _('Minor verze průběžně, major s replikou a přepnutím do 20 sekund.', 'Minor versions continuously, major versions via replica with a 20-second switch.')],
        ['05', _('pgvector pro AI', 'pgvector for AI'), _('Vektorové indexy vedle relačních dat, bez další databáze v architektuře.', 'Vector indexes next to relational data — no extra database in the architecture.')],
        ['06', _('Bez odchodového poplatku', 'No egress penalty'), _('Dump si kdykoli stáhnete, replikaci k sobě nastavíme na požádání.', 'Download a dump anytime; we will set up replication to your own host on request.')]
      ],
      tech: ['PostgreSQL', 'MySQL', 'MariaDB', 'Valkey', 'Redis', 'ClickHouse', 'pgvector', 'PgBouncer', 'Prisma', 'Doctrine'],
      bench: [
        { label: 'DB Scale (16 vCPU, NVMe)', pct: 100, note: '18 400 TPS' },
        { label: 'DB HA (4 vCPU)', pct: 34, note: '6 200 TPS' },
        { label: _('MySQL na sdíleném hostingu', 'MySQL on shared hosting'), pct: 6, note: '1 100 TPS' }
      ],
      benchNote: _('pgbench, 64 klientů, read-write mix, medián z pěti běhů.', 'pgbench, 64 clients, read-write mix, median of five runs.'),
      cases: [
        { t: _('E-shop v HA', 'Store in HA'), d: _('Objednávky nesmí spadnout — replika a failover pod dvacet sekund.', 'Orders cannot stop — replica and sub-20-second failover.'), m: 'DB HA' },
        { t: _('Analytika a reporty', 'Analytics and reporting'), d: _('ClickHouse na eventy, čtecí repliky pro BI, produkce bez zátěže.', 'ClickHouse for events, read replicas for BI, production untouched.'), m: 'DB Scale' },
        { t: _('AI aplikace s vektory', 'AI app with vectors'), d: _('pgvector vedle relačních dat, jeden systém k provozování.', 'pgvector next to relational data — one system to operate.'), m: 'DB HA' }
      ],
      faq: [
        [_('Můžu se připojit zvenčí?', 'Can I connect from outside?'), _('Ano, přes TLS a povolené IP nebo privátní síť. Veřejný endpoint je vypnutý defaultně.', 'Yes, over TLS with IP allowlists or a private network. The public endpoint is off by default.')],
        [_('Provedete migraci z našeho serveru?', 'Will you migrate from our server?'), _('Ano, logickou replikací s minimálním okamžikem přepnutí, obvykle pod minutu.', 'Yes, via logical replication with a cutover usually under a minute.')],
        [_('Řešíte ladění dotazů?', 'Do you help with query tuning?'), _('Ano. Na HA dvě hodiny konzultace měsíčně, na Scale vyhrazený čas našeho DBA.', 'Yes. HA includes two consulting hours a month; Scale includes dedicated DBA time.')],
        [_('Podporujete rozšíření?', 'Do you support extensions?'), _('PostGIS, pgvector, TimescaleDB, pg_cron a další — seznam je v dokumentaci.', 'PostGIS, pgvector, TimescaleDB, pg_cron and more — the list is in the docs.')],
        [_('Co když potřebuji vlastní verzi?', 'What if I need a specific version?'), _('Držíme tři poslední major verze. Starší verzi provozujeme jen v rámci Enterprise.', 'We keep the last three major versions. Older ones only under Enterprise.')]
      ]
    }),

    'migration': P({
      cmpTitle: _('Rozsah migrace podle typu projektu', 'Migration scope by project type'),
      plansTitle: cs ? 'Typy migrace' : 'Migration types', plansNote: cs ? 'Všechny varianty jsou zdarma, liší se rozsahem a plánováním.' : 'All variants are free; they differ in scope and planning.',
      cat: _('Služby', 'Services'), crumb: _('Migrace zdarma', 'Free migration'),
      kicker: _('Bez výpadku · Bez faktury', 'Zero downtime · Zero cost'),
      title: _('Přesuneme vás. Vy jen řeknete kdy.', 'We move you. You just say when.'),
      lead: _('Weby, e-shopy, e-maily, databáze i celé VPS. Naši technici to dělají denně — průměrná nedostupnost při přepnutí je pod pět minut, a účtujeme za to nula.', 'Sites, stores, mailboxes, databases, whole VPS instances. Our engineers do this daily — average cutover downtime is under five minutes, and we charge nothing.'),
      kpis: [['4,8 min', _('průměrná nedostupnost', 'average downtime')], ['2 800', _('migrací v 2025', 'migrations in 2025')], [_('0 Kč', 'Free'), _('cena migrace', 'migration cost')]],
      chips: [_('Weby a e-shopy', 'Sites and stores'), _('E-mailové schránky', 'Mailboxes'), _('Databáze', 'Databases'), _('Celé VPS', 'Whole VPS'), _('DNS a certifikáty', 'DNS and certificates'), _('Víkend i noc', 'Nights and weekends')],
      plans: [
        { name: _('Standardní migrace', 'Standard migration'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('do 5 pracovních dnů', 'within 5 business days'), ctaLabel: _('Chci migraci', 'Request migration'), specs: [_('Do 10 webů', 'Up to 10 sites'), _('Do 50 schránek', 'Up to 50 mailboxes'), _('Testovací URL', 'Staging URL'), _('Přepnutí DNS s vámi', 'DNS cutover with you'), _('72 h monitoring', '72 h monitoring'), _('Do 5 pracovních dnů', 'Within 5 business days')] },
        { name: _('Projektová migrace', 'Project migration'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('s vedoucím projektu', 'with a project lead'), ctaLabel: _('Naplánovat projekt', 'Plan the project'), specs: [_('Neomezeně webů', 'Unlimited sites'), _('Vedoucí projektu', 'Project lead'), _('Zátěžový test po přesunu', 'Post-move load test'), _('Noční nebo víkendové okno', 'Night or weekend window'), _('Plán s termíny', 'Timeline document'), _('Rollback plán', 'Rollback plan')] },
        { name: _('Migrace infrastruktury', 'Infrastructure migration'), tag: _('Na míru', 'Bespoke'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('rozsah podle auditu', 'scope set by audit'), ctaLabel: _('Vyžádat audit', 'Request an audit'), specs: [_('VPS, klastry, Kubernetes', 'VPS, clusters, Kubernetes'), _('Souběžný provoz', 'Parallel operation'), _('Replikace databází', 'Database replication'), _('Postupné přepínání služeb', 'Service-by-service cutover'), _('Technik na telefonu', 'Engineer on call'), _('SLA na okno', 'Window SLA')] }
      ],
      cmp: {
        cols: [_('Standardní', 'Standard'), _('Projektová', 'Project'), _('Infrastruktura', 'Infrastructure')],
        rows: [
          [_('Rozsah', 'Scope'), _('Do 10 webů', 'Up to 10 sites'), _('Neomezeně', 'Unlimited'), _('Celá infrastruktura', 'Whole estate')],
          [_('Cena', 'Price'), _('Zdarma', 'Free'), _('Zdarma', 'Free'), _('Zdarma', 'Free')],
          [_('Vedoucí projektu', 'Project lead'), '—', '✓', '✓'],
          [_('Okno mimo provoz', 'Off-hours window'), _('Na dohodu', 'By agreement'), '✓', '✓'],
          [_('Zátěžový test', 'Load test'), '—', '✓', '✓'],
          [_('Rollback plán', 'Rollback plan'), _('Základní', 'Basic'), '✓', _('Detailní, s cvičením', 'Detailed, with a drill')],
          [_('Doba realizace', 'Lead time'), _('Do 5 dnů', 'Within 5 days'), _('2–3 týdny', '2–3 weeks'), _('Podle rozsahu', 'Scope dependent')]
        ]
      },
      feats: [
        ['01', _('Souběžný provoz', 'Parallel running'), _('Staré i nové řešení běží současně, dokud vy sami neřeknete přepnout.', 'Old and new run side by side until you say cut over.')],
        ['02', _('Testovací URL před přepnutím', 'Staging URL before cutover'), _('Web zkontrolujete na naší adrese, včetně objednávek a formulářů.', 'You check the site on our address, orders and forms included.')],
        ['03', _('Přepnutí mimo špičku', 'Off-peak cutover'), _('V noci, v pondělí ve 4 ráno, o víkendu. Termín vybíráte vy.', 'At night, Monday 4am, over the weekend. You pick the slot.')],
        ['04', _('Nic se neztratí', 'Nothing gets lost'), _('Poslední dosync objednávek a e-mailů děláme až po přepnutí DNS.', 'The final order and mail delta sync runs after the DNS switch.')],
        ['05', _('Rollback plán', 'A rollback plan'), _('Když se něco nepovede, jsme do deseti minut zpět na původním řešení.', 'If something goes wrong we are back on the old setup within ten minutes.')],
        ['06', _('Monitoring 72 hodin', '72 hours of monitoring'), _('Tři dny sledujeme chyby, výkon a doručitelnost e-mailů a hlásíme se sami.', 'For three days we watch errors, performance and mail deliverability, and report proactively.')]
      ],
      tech: ['cPanel', 'Plesk', 'DirectAdmin', 'WordPress', 'PrestaShop', 'Microsoft 365', 'Google Workspace', 'AWS', 'Hetzner', 'Wedos'],
      bench: [
        { label: _('Migrace webu s Onhostem', 'Site migration with Onhost'), pct: 100, note: _('4,8 min výpadek', '4.8 min downtime') },
        { label: _('Migrace vlastními silami', 'Do-it-yourself migration'), pct: 12, note: _('40 min výpadek', '40 min downtime') },
        { label: _('Přenos přes zálohu a upload', 'Backup-and-upload transfer'), pct: 5, note: _('2 h výpadek', '2 h downtime') }
      ],
      benchNote: _('Průměr z 2 800 migrací v roce 2025, měřeno jako nedostupnost hlavní domény.', 'Average across 2,800 migrations in 2025, measured as primary domain unavailability.'),
      cases: [
        { t: _('Agentura s 300 weby', 'Agency with 300 sites'), d: _('Postupně po dávkách, klienti nic nepoznali, jeden vedoucí projektu.', 'Batch by batch, clients noticed nothing, one project lead.'), m: _('Projektová', 'Project') },
        { t: _('Odchod z Microsoft 365', 'Leaving Microsoft 365'), d: _('200 schránek, kalendáře a kontakty za jeden víkend.', '200 mailboxes, calendars and contacts in one weekend.'), m: _('Standardní', 'Standard') },
        { t: _('Návrat z veřejného cloudu', 'Coming back from public cloud'), d: _('Kubernetes klastr a databáze z AWS do Prahy, po službách.', 'Kubernetes cluster and databases from AWS to Prague, service by service.'), m: _('Infrastruktura', 'Infrastructure') }
      ],
      faq: [
        [_('Opravdu je to zdarma?', 'Is it really free?'), _('Ano, u všech tarifů. Nemáme za migraci žádný poplatek ani podmínku minimální délky.', 'Yes, on every plan. There is no migration fee and no minimum term condition.')],
        [_('Co když migrace nevyjde?', 'What if the migration fails?'), _('Zůstanete na starém řešení a nic neplatíte. Rollback máme připravený vždy.', 'You stay on the old setup and pay nothing. A rollback is always prepared.')],
        [_('Musím dát heslo k FTP?', 'Do I have to hand over my FTP password?'), _('Ne, stačí dočasný účet. Přístupy jsou logované a po migraci je smažeme.', 'No, a temporary account is enough. Access is logged and deleted after the migration.')],
        [_('Migrujete i z cPanelu a Plesku?', 'Do you migrate from cPanel and Plesk?'), _('Ano, včetně automatického importu účtů, databází a schránek.', 'Yes, including automated import of accounts, databases and mailboxes.')],
        [_('Jak dlouho to trvá?', 'How long does it take?'), _('Jeden web do 24 hodin, deset webů do pěti dnů, velká infrastruktura podle plánu.', 'One site within 24 hours, ten sites within five days, large estates per plan.')]
      ]
    })
  };
}
