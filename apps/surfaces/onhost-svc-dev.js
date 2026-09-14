// Onhost — data podstránek: pro vývojáře a produkty navíc.

export function devPages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviews = [
    { name: 'Ondřej Vrána', role: _('backend vývojář, Skladomat', 'backend developer, Skladomat'), text: _('Dokumentace má funkční příklady, ne jen popis parametrů. To je vzácnější, než by mělo být.', 'The docs have working examples, not just parameter descriptions. That is rarer than it should be.') },
    { name: 'Lucie Hájková', role: _('platform engineer, Retenza', 'platform engineer, Retenza'), text: _('Terraform provider pokrývá i věci, které nejsou v panelu. Celé prostředí máme v repozitáři.', 'The Terraform provider covers things the panel does not. Our whole environment lives in a repo.') },
    { name: 'Adam Kříž', role: _('maintainer open-source projektu', 'open-source maintainer'), text: _('Free tier pro veřejné repozitáře a nikdo mi po roce neposlal fakturu. Tolik k „zdarma“ jinde.', 'A free tier for public repos and no invoice after a year. So much for “free” elsewhere.') }
  ];

  const kb = [
    { title: _('První volání API a autentizace', 'Your first API call and auth'), read: '4 min' },
    { title: _('Terraform: od nuly k produkci', 'Terraform: from zero to production'), read: '9 min' },
    { title: _('CLI v CI/CD pipeline', 'The CLI inside a CI/CD pipeline'), read: '5 min' },
    { title: _('Webhooky a jejich ověřování', 'Webhooks and how to verify them'), read: '6 min' }
  ];

  const sla = {
    title: _('Nástroje, které nezastarají za rok', 'Tools that will not rot in a year'),
    lead: _('API má verze a staré rušíme s ročním předstihem. CLI, Terraform provider i dokumentace vznikají zároveň s funkcí, ne půl roku po ní.', 'The API is versioned and old versions retire with a year of notice. The CLI, the Terraform provider and the docs ship with the feature, not six months later.'),
    rows: [
      { k: '12 ' + _('měsíců', 'months'), v: _('oznámení před zrušením verze', 'notice before a version retires') },
      { k: '100 %', v: _('panelu dostupných přes API', 'of the panel available via API') },
      { k: '< 200 ms', v: _('medián odezvy API', 'median API response') },
      { k: _('Zdarma', 'Free'), v: _('free tier pro open source', 'free tier for open source') }
    ]
  };

  const migration = {
    title: _('Přechod z jiného poskytovatele po částech', 'Move off another provider piece by piece'),
    lead: _('Nemusíte přenášet všechno najednou. Napojíte jeden projekt, ověříte a zbytek následuje ve vlastním tempu.', 'You do not have to move everything at once. Connect one project, verify it, and move the rest at your own pace.'),
    steps: [
      { n: '01', t: _('Import projektu', 'Import a project'), d: _('Připojíte repozitář, my rozpoznáme stack a postavíme první build.', 'Connect the repo; we detect the stack and run the first build.') },
      { n: '02', t: _('Paralelní provoz', 'Run in parallel'), d: _('Nová i stará adresa běží vedle sebe, dokud nejste spokojeni.', 'Old and new addresses run side by side until you are satisfied.') },
      { n: '03', t: _('Přepnutí DNS', 'DNS switch'), d: _('Snížíme TTL, přepneme a sledujeme chybovost i latenci.', 'We lower TTL, switch, and watch error rate and latency.') },
      { n: '04', t: _('Zbytek portfolia', 'The rest of the portfolio'), d: _('Skriptem nebo Terraformem, podle toho, co vám vyhovuje.', 'By script or Terraform, whichever suits you.') }
    ]
  };

  const D = (o) => Object.assign({ config: false, roi: false, sla, migration, reviews, kb }, o);

  return {
    'managed': D({
      cmpTitle: _('Co která úroveň správy pokrývá', 'What each management tier covers'),
      config: true, roi: true, cfgBase: 690, cfgCpu: 180, cfgRam: 55, cfgDisk: 26,
      cat: _('Servery a hardware', 'Servers and hardware'), crumb: _('Managed servery', 'Managed servers'),
      kicker: _('Updaty · monitoring · zásahy 24/7', 'Updates · monitoring · 24/7 hands-on'),
      title: _('Server, na kterém nemusíte nic dělat vy', 'A server you never have to touch'),
      lead: _('Bereme si na starost operační systém, aktualizace, monitoring, zálohy i noční zásahy. Root vám zůstává, odpovědnost přebíráme my.', 'We take on the operating system, updates, monitoring, backups and night calls. You keep root; we take the responsibility.'),
      kpis: [['15 min', _('reakce na incident 24/7', 'incident response, 24/7')], ['99,99 %', _('dostupnost s kreditem', 'uptime, credited')], [_('0 zásahů', 'zero touches'), _('měsíčně na vaší straně', 'a month on your side')]],
      chips: ['Debian', 'Ubuntu', 'Rocky', 'Docker', 'Nginx', 'PostgreSQL', 'Grafana', 'Ansible'],
      plansTitle: _('Úrovně správy', 'Management tiers'),
      plansNote: _('Správu lze přidat k jakémukoli VPS, dedikovanému serveru i k hardwaru v colocation.', 'Management can be added to any VPS, dedicated server or colocated hardware.'),
      plans: [
        { name: _('Základ', 'Essential'), tag: '', price: 690, specs: [_('Aktualizace OS a záplaty', 'OS updates and patches'), _('Monitoring a alerty', 'Monitoring and alerts'), _('Zálohy denně', 'Daily backups'), _('Reakce do 60 minut', '60-minute response'), _('Firewall a fail2ban', 'Firewall and fail2ban'), _('Měsíční report', 'Monthly report')] },
        { name: _('Plná správa', 'Full management'), tag: _('Nejčastější', 'Most common'), price: 2490, specs: [_('Vše ze Základu', 'Everything in Essential'), _('Zásahy 24/7 bez příplatku', '24/7 hands-on, no surcharge'), _('Ladění výkonu a databáze', 'Performance and database tuning'), _('Zálohy po hodinách', 'Hourly backups'), _('Reakce do 15 minut', '15-minute response'), _('SLA 99,99 % s kreditem', '99.99% SLA with credits')] },
        { name: _('Na míru', 'Bespoke'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('podle počtu strojů', 'by machine count'), ctaLabel: _('Vyžádat nabídku', 'Request a quote'), specs: [_('Vlastní runbooky', 'Your own runbooks'), _('Jmenovaný inženýr', 'Named engineer'), _('Změnová okna podle vás', 'Change windows on your terms'), _('Compliance a audity', 'Compliance and audits'), _('Zastupitelnost dvou lidí', 'Two-person coverage'), _('Měsíční provozní schůzka', 'Monthly operations review')] }
      ],
      cmp: {
        cols: [_('Základ', 'Essential'), _('Plná správa', 'Full'), _('Na míru', 'Bespoke')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '690 Kč', '2 490 Kč', _('na dotaz', 'on request')],
          [_('Reakce', 'Response'), '60 min', '15 min', _('podle smlouvy', 'per contract')],
          [_('Noční zásahy', 'Night callouts'), _('za příplatek', 'surcharged'), _('v ceně', 'included'), _('v ceně', 'included')],
          [_('Zálohy', 'Backups'), _('denně', 'daily'), _('po hodinách', 'hourly'), _('na míru', 'bespoke')],
          [_('Ladění výkonu', 'Performance tuning'), '—', '✓', '✓'],
          ['SLA', '99,9 %', '99,99 %', _('na míru', 'bespoke')]
        ]
      },
      feats: [
        ['01', _('Root vám zůstává', 'You keep root'), _('Spravujeme, ale nezamykáme. Můžete si dělat, co potřebujete.', 'We manage it without locking it. Do whatever you need.')],
        ['02', _('Aktualizace v okně, které určíte', 'Updates in your window'), _('Záplaty testujeme na kopii a nasazujeme, když je klid.', 'Patches are tested on a copy and applied when it is quiet.')],
        ['03', _('Zásah, ne jen upozornění', 'A fix, not just an alert'), _('Ve tři ráno nechceme budit vás. Vyřešíme to a ráno pošleme zprávu.', 'At 3 a.m. we would rather not wake you. We fix it and report in the morning.')],
        ['04', _('Ladíme i databázi', 'We tune the database too'), _('Pomalé dotazy hledáme sami, ne až když si někdo stěžuje.', 'We hunt slow queries ourselves, not after somebody complains.')],
        ['05', _('Runbooky máte u sebe', 'The runbooks are yours'), _('Postupy píšeme do vašeho repozitáře. Když odejdete, zůstanou vám.', 'Procedures live in your repository. If you leave, they stay with you.')],
        ['06', _('Report bez balastu', 'A report without filler'), _('Co se stalo, co jsme udělali, co doporučujeme změnit.', 'What happened, what we did, what we suggest changing.')]
      ],
      tech: ['Ansible', 'Prometheus', 'Grafana', 'Docker', 'PostgreSQL', 'Nginx', 'Restic', 'Wazuh'],
      bench: [
        { label: _('Onhost — reakce na P1', 'Onhost — P1 response'), pct: 100, note: '15 min' },
        { label: _('Externí správce (typicky)', 'External admin (typical)'), pct: 25, note: '~2 h' },
        { label: _('Vlastní admin na telefonu', 'In-house admin on call'), pct: 45, note: '~45 min' }
      ],
      benchNote: _('Medián doby od alertu k prvnímu zásahu člověka, 12měsíční okno.', 'Median time from alert to first human action, 12-month window.'),
      cases: [
        { t: _('Firma bez IT oddělení', 'A company with no IT department'), d: _('Jeden server, aktualizace a zálohy bez vlastního správce.', 'One server, updates and backups without hiring an admin.'), m: _('Základ', 'Essential') },
        { t: _('Produkce s nočním provozem', 'Production that runs at night'), d: _('Zásahy 24/7 a ladění databáze místo interního on-call.', '24/7 hands-on and database tuning instead of internal on-call.'), m: _('Plná správa', 'Full') },
        { t: _('Regulovaná firma', 'A regulated company'), d: _('Změnová okna, audit a dva jmenovaní inženýři.', 'Change windows, audits and two named engineers.'), m: _('Na míru', 'Bespoke') }
      ],
      faq: [
        [_('Můžu si na server sáhnout sám?', 'Can I touch the server myself?'), _('Ano, root máte. Jen nám dejte vědět, ať se nepřekřížíme.', 'Yes, you have root. Just tell us so we do not collide.')],
        [_('Spravujete i servery jinde?', 'Do you manage servers elsewhere?'), _('Ano, i u jiného poskytovatele nebo ve vaší serverovně.', 'Yes — at another provider or in your own server room.')],
        [_('Co když zásah trvá dlouho?', 'What if a fix takes long?'), _('Platíte paušál, ne hodiny. Delší zásah nás bolí víc než vás.', 'You pay a flat fee, not hours. A long incident hurts us more than you.')],
        [_('Jaké OS podporujete?', 'Which operating systems?'), _('Debian, Ubuntu, Rocky a AlmaLinux. Windows Server po dohodě.', 'Debian, Ubuntu, Rocky and AlmaLinux. Windows Server by arrangement.')],
        [_('Jde správa zrušit?', 'Can I cancel management?'), _('Ano, měsíc po měsíci. Server i runbooky vám zůstanou.', 'Yes, month to month. The server and the runbooks stay yours.')]
      ]
    }),

    'storage': D({
      cmpTitle: _('Kapacita, vrstvy a odchozí data', 'Capacity, tiers and egress'),
      roi: true,
      cat: _('AI a data', 'AI and data'), crumb: _('Object storage', 'Object storage'),
      kicker: _('S3 kompatibilní · bez egress poplatků · EU', 'S3-compatible · no egress fees · EU'),
      title: _('Úložiště, ze kterého se data dají i dostat ven', 'Storage you can actually get your data out of'),
      lead: _('S3 API, tři kopie ve dvou lokalitách a nula korun za odchozí přenos. Zálohy, obrázky i datasety pro trénink na stejném endpointu.', 'The S3 API, three copies across two sites and zero charge for egress. Backups, images and training datasets on one endpoint.'),
      kpis: [['59 Kč', _('za TB měsíčně', 'per TB a month')], [_('0 Kč', 'Free'), _('za odchozí data', 'for egress')], ['11×9', _('trvanlivost dat', 'durability')]],
      chips: ['S3 API', 'Versioning', 'Immutable', 'CDN', 'Lifecycle', 'IAM', _('EU data', 'EU data'), 'Presigned URL'],
      plansTitle: _('Tarify úložiště', 'Storage plans'),
      plansNote: _('Účtujeme jen uložená data. Požadavky ani odchozí přenos nepočítáme.', 'You pay for stored data only. Requests and egress are not metered.'),
      plans: [
        { name: 'Start', tag: '', price: 59, specs: [_('1 TB dat', '1 TB of data'), _('S3 API a presigned URL', 'S3 API and presigned URLs'), _('Odchozí data zdarma', 'Free egress'), _('Verzování', 'Versioning'), _('2 lokality', '2 locations'), _('CDN za příplatek', 'CDN available')] },
        { name: _('Provoz', 'Production'), tag: _('Nejčastější', 'Most common'), price: 490, specs: [_('10 TB dat', '10 TB of data'), _('CDN v ceně', 'CDN included'), _('Immutable bucket', 'Immutable buckets'), _('Lifecycle pravidla', 'Lifecycle rules'), _('IAM a servisní účty', 'IAM and service accounts'), _('SLA 99,95 %', '99.95% SLA')] },
        { name: _('Archiv', 'Archive'), tag: '', price: 1890, specs: [_('100 TB dat', '100 TB of data'), _('Chladná vrstva', 'Cold tier'), _('Uzamčení podle WORM', 'WORM locking'), _('Auditní log přístupů', 'Access audit log'), _('Export na disk poštou', 'Disk export by post'), _('SLA 99,99 %', '99.99% SLA')] }
      ],
      cmp: {
        cols: ['Start', _('Provoz', 'Production'), _('Archiv', 'Archive')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '59 Kč', '490 Kč', '1 890 Kč'],
          [_('Kapacita', 'Capacity'), '1 TB', '10 TB', '100 TB'],
          [_('Odchozí data', 'Egress'), _('zdarma', 'free'), _('zdarma', 'free'), _('zdarma', 'free')],
          ['CDN', _('za příplatek', 'add-on'), _('v ceně', 'included'), _('v ceně', 'included')],
          ['WORM / immutable', '—', '✓', '✓'],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Odchozí data zdarma', 'Egress is free'), _('Odejít od nás nemá stát víc než zůstat. Přenos ven neúčtujeme.', 'Leaving should not cost more than staying. We do not bill outbound transfer.')],
        ['02', _('Opravdu S3', 'Genuinely S3'), _('Funguje s aws-cli, boto3, rclone, Veeam i Restic bez úprav.', 'Works with aws-cli, boto3, rclone, Veeam and Restic unchanged.')],
        ['03', _('Tři kopie ve dvou lokalitách', 'Three copies, two sites'), _('Praha a Brno, synchronně. Výpadek sálu data neohrozí.', 'Prague and Brno, synchronously. A hall outage cannot touch your data.')],
        ['04', _('Immutable pro zálohy', 'Immutable for backups'), _('Zamčený objekt nesmaže ani admin, ani ransomware.', 'A locked object cannot be deleted by an admin or by ransomware.')],
        ['05', _('CDN na stejném endpointu', 'CDN on the same endpoint'), _('Obrázky se servírují ze čtrnácti PoP bez další konfigurace.', 'Images are served from fourteen PoPs with no extra setup.')],
        ['06', _('Lifecycle pravidla', 'Lifecycle rules'), _('Stará data přesuneme do chladné vrstvy nebo smažeme automaticky.', 'Old data moves to the cold tier or is deleted automatically.')]
      ],
      tech: ['aws-cli', 'boto3', 'rclone', 'Restic', 'Veeam', 'MinIO client', 'Terraform', 'Cyberduck'],
      bench: [
        { label: _('Onhost — 10 TB + 5 TB egress', 'Onhost — 10 TB + 5 TB egress'), pct: 100, note: '490 Kč' },
        { label: _('Velký cloud — totéž', 'Hyperscaler — same'), pct: 12, note: '~4 100 Kč' },
        { label: _('Levné S3 v USA — totéž', 'Cheap US S3 — same'), pct: 48, note: '~1 020 Kč' }
      ],
      benchNote: _('Měsíční cena za 10 TB uložených dat a 5 TB odchozího přenosu, srpen 2026.', 'Monthly cost of 10 TB stored and 5 TB egress, August 2026.'),
      cases: [
        { t: _('Zálohy z Veeamu', 'Veeam backups'), d: _('Immutable bucket jako druhá kopie mimo hlavní lokalitu.', 'An immutable bucket as the off-site second copy.'), m: _('Provoz', 'Production') },
        { t: _('Obrázky e-shopu', 'Store imagery'), d: _('Origin pro CDN, presigned URL pro nahrávání z prohlížeče.', 'A CDN origin with presigned URLs for browser uploads.'), m: 'Start' },
        { t: _('Datasety pro trénink', 'Training datasets'), d: _('Sto terabajtů vedle GPU, bez poplatku za čtení.', 'A hundred terabytes next to the GPUs, with no read fees.'), m: _('Archiv', 'Archive') }
      ],
      faq: [
        [_('Opravdu neúčtujete egress?', 'Really no egress fees?'), _('Opravdu. Platíte jen uložená data, ne přenos ani požadavky.', 'Really. You pay for stored data, not transfer or requests.')],
        [_('Kde data leží?', 'Where does data live?'), _('V Praze a Brně. Nikam mimo EU se nereplikují.', 'In Prague and Brno. Nothing is replicated outside the EU.')],
        [_('Funguje to s Veeamem?', 'Does it work with Veeam?'), _('Ano, včetně immutable režimu podle S3 Object Lock.', 'Yes — including immutable mode via S3 Object Lock.')],
        [_('Jak rychle nahraju 50 TB?', 'How fast can I upload 50 TB?'), _('Po síti přes noc, nebo nám pošlete disky a nahrajeme je my.', 'Over the network overnight, or post us disks and we load them.')],
        [_('Umíte statický web z bucketu?', 'Can I host a static site from a bucket?'), _('Ano, včetně vlastní domény a certifikátu.', 'Yes — including a custom domain and certificate.')]
      ]
    }),

    'docs': D({
      cmpTitle: _('Kudy se do dokumentace pustit', 'Where to start in the documentation'),
      panelsTitle: _('Jak je dokumentace stavěná', 'How the documentation is built'), panelsLead: _('Píší ji inženýři, testuje ji CI a chybějící části doplňujeme na základě vašich hlášení.', 'Written by engineers, tested by CI, and gaps filled from your reports.'),
      panels: [
        [_('Rozsah', 'Coverage'), _('Každá služba má referenci, návod a spustitelný příklad.', 'Every service has a reference, a guide and a runnable example.'), [[_('Stránek', 'Pages'), '340'], [_('Návodů', 'Guides'), '86'], [_('Příkladů', 'Examples'), '412'], [_('Jazyky', 'Languages'), 'CS / EN']]],
        [_('Údržba', 'Maintenance'), _('Příklady běží v CI každou noc, rozbitý příklad shodí build.', 'Examples run in CI nightly; a broken example fails the build.'), [[_('Testováno v CI', 'CI-tested'), '100 %'], [_('Kontrola', 'Check frequency'), _('každou noc', 'nightly')], [_('Doplnění chybějícího', 'Gap filled in'), '< 7 ' + _('dní', 'days')], [_('Zdroj veřejný', 'Public source'), '✓']]],
        [_('Formáty', 'Formats'), _('Web, PDF, OpenAPI a Markdown v repozitáři, který si můžete naklonovat.', 'Web, PDF, OpenAPI and Markdown in a repo you can clone.'), [['OpenAPI', '3.1'], ['PDF', '✓'], ['Markdown', '✓'], [_('Offline kopie', 'Offline copy'), '✓']]]
      ],
      cmp: null,
      cat: _('Pro vývojáře', 'For developers'), crumb: _('Dokumentace', 'Documentation'),
      kicker: _('Příklady, které fungují · 340 stránek', 'Examples that run · 340 pages'),
      title: _('Dokumentace psaná lidmi, kteří to provozují', 'Documentation written by the people who run it'),
      lead: _('Každý příklad je testovaný v CI, takže nezastará. Když v dokumentaci něco chybí, napíšete nám a doplníme to do týdne — nebo pošlete pull request.', 'Every example is tested in CI, so it cannot rot. If something is missing, tell us and we add it within a week — or send a pull request.'),
      kpis: [['340', _('stránek dokumentace', 'pages of documentation')], ['100 %', _('příkladů testovaných v CI', 'of examples tested in CI')], ['< 7 ' + _('dní', 'days'), _('na doplnění chybějícího', 'to fill a documented gap')]],
      chips: ['REST API', 'CLI', 'Terraform', _('Webhooky', 'Webhooks'), 'SDK', _('Návody', 'Guides'), 'OpenAPI', 'PDF'],
      plansTitle: _('Kudy začít', 'Where to start'),
      plansNote: _('Celá dokumentace je veřejná a bez registrace. Zdroj je na našem Gitu, opravy vítáme.', 'The whole thing is public with no signup. The source is on our Git and fixes are welcome.'),
      plans: [
        { name: _('Rychlý start', 'Quickstart'), tag: _('Pro začátek', 'Start here'), price: 0, priceLabel: '15 min', priceNote: _('od registrace k běžící aplikaci', 'from signup to a running app'), ctaLabel: _('Otevřít rychlý start', 'Open the quickstart'), specs: [_('Založení účtu a projektu', 'Account and project setup'), _('První deploy z Gitu', 'First deploy from Git'), _('Vlastní doména a SSL', 'Custom domain and SSL'), _('Databáze za dvě minuty', 'A database in two minutes'), _('Logy a metriky', 'Logs and metrics'), _('Co dál', 'Where to go next')] },
        { name: _('Reference', 'Reference'), tag: '', price: 0, priceLabel: '340', priceNote: _('stránek s příklady', 'pages with examples'), ctaLabel: _('Otevřít referenci', 'Open the reference'), specs: [_('REST API endpointy', 'REST API endpoints'), _('CLI příkazy', 'CLI commands'), _('Terraform prostředky', 'Terraform resources'), _('Webhooky a události', 'Webhooks and events'), _('Limity a kvóty', 'Limits and quotas'), _('Chybové kódy', 'Error codes')] },
        { name: _('Návody podle úlohy', 'Task guides'), tag: '', price: 0, priceLabel: '86', priceNote: _('praktických návodů', 'hands-on guides'), ctaLabel: _('Procházet návody', 'Browse the guides'), specs: [_('Nasazení Next.js a Django', 'Deploying Next.js and Django'), _('Migrace DB bez výpadku', 'Zero-downtime DB migrations'), _('Blue-green a canary', 'Blue-green and canary'), _('Zálohy a obnova', 'Backups and restores'), _('Monitoring a alerty', 'Monitoring and alerts'), _('Bezpečné tajné klíče', 'Handling secrets safely')] }
      ],
      feats: [
        ['01', _('Příklady se spouští v CI', 'Examples run in CI'), _('Když se rozbije příklad, spadne nám build. Ne váš pátek.', 'If an example breaks, our build fails. Not your Friday.')],
        ['02', _('Česky i anglicky', 'Czech and English'), _('Obě verze píšeme, nepřekládáme strojově.', 'Both versions are written, not machine-translated.')],
        ['03', _('OpenAPI ke stažení', 'OpenAPI to download'), _('Vygenerujete si klienta v jazyce, který používáte.', 'Generate a client in whatever language you use.')],
        ['04', _('Chybějící doplníme do týdne', 'Gaps filled within a week'), _('Tlačítko „tohle mi tu chybí“ je na každé stránce.', 'A “this is missing” button sits on every page.')],
        ['05', _('Zdroj je veřejný', 'The source is public'), _('Dokumentace je v repozitáři, pull requesty vítáme a platíme kredity.', 'Docs live in a repo; pull requests are welcome and rewarded with credit.')],
        ['06', _('Bez registrace a cookie lišty', 'No signup, no cookie wall'), _('Dokumentaci nemusíte číst přihlášení.', 'You do not need an account to read the docs.')]
      ],
      tech: ['OpenAPI 3.1', 'Markdown', 'Postman', 'curl', 'Python', 'Go', 'TypeScript', 'PDF'],
      bench: [
        { label: _('Onhost — od registrace k deployi', 'Onhost — signup to deploy'), pct: 100, note: '15 min' },
        { label: _('Velký cloud — totéž', 'Hyperscaler — same'), pct: 24, note: '~60 min' },
        { label: _('Vlastní VPS od nuly', 'Bare VPS from scratch'), pct: 12, note: '~2 h' }
      ],
      benchNote: _('Medián času podle rychlého startu, měřeno na 40 nových účtech.', 'Median time following the quickstart, measured across 40 new accounts.'),
      cases: [
        { t: _('První den v týmu', 'Day one on a team'), d: _('Nový kolega si projde rychlý start a večer nasazuje.', 'A new colleague runs the quickstart and ships the same evening.'), m: _('Rychlý start', 'Quickstart') },
        { t: _('Integrace do interního nástroje', 'Integrating an internal tool'), d: _('OpenAPI, vygenerovaný klient a webhooky.', 'OpenAPI, a generated client and webhooks.'), m: _('Reference', 'Reference') },
        { t: _('Migrace databáze bez výpadku', 'A zero-downtime DB migration'), d: _('Návod krok za krokem včetně rollbacku.', 'A step-by-step guide including the rollback.'), m: _('Návody', 'Guides') }
      ],
      faq: [
        [_('Je dokumentace zdarma?', 'Is the documentation free?'), _('Ano, celá, bez účtu a bez limitu.', 'Yes — all of it, no account, no limits.')],
        [_('Můžu přispět?', 'Can I contribute?'), _('Ano, pull requestem. Za přijatý příspěvek posíláme kredit.', 'Yes, by pull request. Accepted contributions earn credit.')],
        [_('Máte SDK?', 'Do you have SDKs?'), _('Oficiálně pro Python, Go a TypeScript, další generujte z OpenAPI.', 'Officially Python, Go and TypeScript; generate others from OpenAPI.')],
        [_('Existuje offline verze?', 'Is there an offline copy?'), _('Ano, PDF export celé reference i návodů.', 'Yes — a PDF export of the reference and the guides.')],
        [_('Kdo dokumentaci píše?', 'Who writes it?'), _('Inženýři, kteří danou službu provozují. Ne externí agentura.', 'The engineers who run the service. Not an agency.')]
      ]
    }),

    'api': D({
      panelsTitle: _('Limity a přístupy', 'Limits and access'), panelsLead: _('API je u každého účtu, včetně nejmenšího. Platí se propustnost, ne přístup, a klíče mají rozsah.', 'The API comes with every account, even the smallest. You pay for throughput, not access, and keys are scoped.'),
      panels: [
        [_('Propustnost', 'Throughput'), _('Základ stačí na běžnou automatizaci, vyšší limit pro flotily a portály.', 'The default covers ordinary automation; the higher tier suits fleets and portals.'), [[_('V ceně účtu', 'Included'), '600 / min'], [_('Vyšší limit', 'Higher tier'), '6 000 / min'], [_('Partnerské API', 'Partner API'), _('bez limitu', 'unlimited')], [_('Cena vyššího limitu', 'Higher tier price'), '490 Kč']]],
        [_('Bezpečnost', 'Security'), _('Klíč s rozsahem, platností a auditem každého volání.', 'Scoped keys with an expiry and an audit of every call.'), [[_('Rozsah klíče', 'Key scope'), _('projekt, operace', 'project, operation')], [_('Platnost', 'Expiry'), _('volitelná', 'optional')], [_('Audit log', 'Audit log'), '90 ' + _('dní až 2 roky', 'days to 2 years')], [_('Podpis webhooků', 'Signed webhooks'), '✓']]],
        [_('Stabilita', 'Stability'), _('Verze rušíme s ročním předstihem a starou cestu držíme funkční.', 'Versions retire with a year of notice and the old path keeps working.'), [[_('Oznámení', 'Notice'), '12 ' + _('měsíců', 'months')], [_('Medián odezvy', 'Median response'), '182 ms'], ['Sandbox', _('partnerské API', 'partner API')], [_('SDK', 'SDKs'), 'Python, Go, TS']]]
      ],
      cat: _('Pro vývojáře', 'For developers'), crumb: _('API a CLI', 'API and CLI'),
      kicker: _('REST · CLI · webhooky · SDK', 'REST · CLI · webhooks · SDKs'),
      title: _('Všechno, co umí panel, umí i terminál', 'Everything the panel does, the terminal does'),
      lead: _('Žádná funkce není jen v grafickém rozhraní. API je verzované, CLI je jeden binární soubor a webhooky posílají události, na které se dá spolehnout.', 'No feature is panel-only. The API is versioned, the CLI is a single binary, and webhooks deliver events you can rely on.'),
      kpis: [['< 200 ms', _('medián odezvy API', 'median API response')], ['100 %', _('funkcí panelu přes API', 'of panel features via API')], ['12 ' + _('měsíců', 'months'), _('podpora staré verze', 'support for old versions')]],
      chips: ['REST', 'OpenAPI 3.1', 'CLI', _('Webhooky', 'Webhooks'), 'Python SDK', 'Go SDK', 'TypeScript SDK', _('Klíče s rozsahem', 'Scoped keys')],
      plansTitle: _('Limity podle tarifu', 'Rate limits by plan'),
      plansNote: _('API je součástí každého účtu, včetně toho nejmenšího. Platí se výkon, ne přístup.', 'The API is part of every account, including the smallest. You pay for capacity, not access.'),
      plans: [
        { name: _('V ceně účtu', 'Included'), tag: _('Každý účet', 'Every account'), price: 0, priceLabel: _('V ceně', 'Included'), priceNote: _('u všech tarifů', 'on every plan'), ctaLabel: _('Vytvořit API klíč', 'Create an API key'), specs: [_('600 požadavků / min', '600 requests / min'), _('Klíče s rozsahem práv', 'Scoped API keys'), _('Webhooky', 'Webhooks'), _('CLI a SDK', 'CLI and SDKs'), _('Terraform provider', 'Terraform provider'), _('Audit log 90 dní', '90-day audit log')] },
        { name: _('Vyšší limity', 'Higher limits'), tag: '', price: 490, specs: [_('6 000 požadavků / min', '6,000 requests / min'), _('Prioritní fronta', 'Priority queue'), _('Servisní účty bez limitu', 'Unlimited service accounts'), _('Webhooky s opakováním', 'Webhooks with retries'), _('Audit log 2 roky', '2-year audit log'), _('Podpora do 30 minut', '30-minute support')] },
        { name: _('Partnerské API', 'Partner API'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('pro resellery a integrace', 'for resellers and integrations'), ctaLabel: _('Domluvit integraci', 'Discuss an integration'), specs: [_('Bez limitu požadavků', 'No request limit'), _('Zakládání účtů a fakturace', 'Account creation and billing'), _('Sandbox prostředí', 'Sandbox environment'), _('Testovací data', 'Test fixtures'), _('Jmenovaný kontakt', 'Named contact'), _('Oznámení změn předem', 'Advance change notices')] }
      ],
      cmp: {
        cols: [_('V ceně', 'Included'), _('Vyšší limity', 'Higher limits'), _('Partnerské', 'Partner')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), _('0 Kč', 'Free'), '490 Kč', _('na dotaz', 'on request')],
          [_('Požadavky za minutu', 'Requests per minute'), '600', '6 000', _('bez limitu', 'unlimited')],
          [_('Webhooky', 'Webhooks'), '✓', _('s opakováním', 'with retries'), _('s opakováním', 'with retries')],
          ['Sandbox', '—', '—', '✓'],
          [_('Audit log', 'Audit log'), '90 ' + _('dní', 'days'), _('2 roky', '2 years'), _('2 roky', '2 years')],
          [_('Zakládání účtů', 'Account provisioning'), '—', '—', '✓']
        ]
      },
      feats: [
        ['01', _('Klíče s rozsahem práv', 'Scoped keys'), _('Klíč jen pro čtení, jen pro jeden projekt, jen na měsíc.', 'A read-only key, for one project, valid for a month.')],
        ['02', _('CLI je jeden soubor', 'The CLI is one file'), _('Bez runtime, bez závislostí. Zkopírujete ho do image a jede.', 'No runtime, no dependencies. Copy it into an image and go.')],
        ['03', _('Verze rušíme s ročním předstihem', 'Versions retire with a year of notice'), _('Nikdy vám nespadne integrace přes noc kvůli našemu úklidu.', 'Your integration will never break overnight because of our tidy-up.')],
        ['04', _('Webhooky s podpisem', 'Signed webhooks'), _('Každá událost je podepsaná, opakuje se a má idempotentní klíč.', 'Every event is signed, retried and idempotent.')],
        ['05', _('Terraform jako první třída', 'Terraform is first class'), _('Provider vydáváme zároveň s funkcí, ne o dva kvartály později.', 'The provider ships with the feature, not two quarters later.')],
        ['06', _('Audit log všeho', 'Everything is audited'), _('Kdo, kdy, odkud a čím klíčem. Exportovatelné do SIEM.', 'Who, when, from where and with which key. Exportable to your SIEM.')]
      ],
      tech: ['curl', 'Python', 'Go', 'TypeScript', 'Terraform', 'Ansible', 'GitHub Actions', 'OpenAPI 3.1'],
      bench: [
        { label: _('Onhost API — medián odezvy', 'Onhost API — median response'), pct: 100, note: '182 ms' },
        { label: _('Velký cloud — totéž volání', 'Hyperscaler — same call'), pct: 71, note: '~256 ms' },
        { label: _('Panel v prohlížeči', 'Panel in a browser'), pct: 40, note: '~450 ms' }
      ],
      benchNote: _('Vytvoření a smazání instance přes API, medián z 1 000 volání z Prahy.', 'Creating and deleting an instance via API, median of 1,000 calls from Prague.'),
      cases: [
        { t: _('Prostředí na každý PR', 'An environment per PR'), d: _('CI zavolá API, po merge ho zase smaže.', 'CI calls the API and deletes it again after merge.'), m: _('V ceně', 'Included') },
        { t: _('Interní portál', 'An internal portal'), d: _('Zakládání serverů z vlastního nástroje, bez našeho panelu.', 'Servers created from their own tool, bypassing our panel.'), m: _('Vyšší limity', 'Higher limits') },
        { t: _('Reseller platforma', 'A reseller platform'), d: _('Zakládání účtů, fakturace a sandbox pro testy.', 'Account provisioning, billing and a sandbox for tests.'), m: _('Partnerské', 'Partner') }
      ],
      faq: [
        [_('Je API zdarma?', 'Is the API free?'), _('Ano, u všech tarifů. Platí se jen vyšší limity.', 'Yes, on every plan. You only pay for higher limits.')],
        [_('Jak se autentizuje?', 'How does auth work?'), _('Bearer token s definovaným rozsahem a volitelnou platností.', 'A bearer token with a defined scope and optional expiry.')],
        [_('Máte sandbox?', 'Is there a sandbox?'), _('Pro partnerské API ano, s testovacími daty i platbami.', 'For the partner API yes, with test fixtures and payments.')],
        [_('Co když překročím limit?', 'What if I exceed the limit?'), _('Dostanete 429 a hlavičku s dobou čekání. Neúčtujeme pokuty.', 'You get a 429 and a retry-after header. No penalty fees.')],
        [_('Podporujete GraphQL?', 'Do you support GraphQL?'), _('Ne, a zatím to neplánujeme. REST pokrývá vše.', 'No, and it is not planned. REST covers everything.')]
      ]
    }),

    'terraform': D({
      panelsTitle: _('Co provider umí', 'What the provider covers'), panelsLead: _('Prostředky, stav a spolupráce v týmu. Provider i moduly jsou open source, platíte jen zdroje, které vytvoří.', 'Resources, state and team collaboration. The provider and modules are open source; you pay only for what they create.'),
      panels: [
        [_('Prostředky', 'Resources'), _('Pokrytí je úplné včetně fakturace, práv a DNS zón.', 'Coverage is complete, billing, permissions and DNS zones included.'), [[_('Typů prostředků', 'Resource types'), '84'], [_('Pokrytí služeb', 'Service coverage'), '100 %'], [_('Zpoždění za funkcí', 'Lag behind a feature'), '0 ' + _('dní', 'days')], [_('Import existujících', 'Import existing'), '✓']]],
        [_('Stav', 'State'), _('Držte ho u nás se zámky a verzováním, nebo kdekoli jinde.', 'Keep it with us with locking and versioning, or anywhere else.'), [[_('Backend', 'Backend'), 'S3 ' + _('kompatibilní', 'compatible')], [_('Zámky', 'Locking'), '✓'], [_('Verzování', 'Versioning'), '✓'], [_('Cena', 'Price'), '190 Kč']]],
        [_('Tým', 'Team'), _('Plán do pull requestu, politiky před apply a audit, kdo co změnil.', 'A plan in the pull request, policies before apply and an audit of who changed what.'), [[_('Plán v PR', 'Plan in PR'), '✓'], [_('Detekce driftu', 'Drift detection'), _('denně', 'daily')], [_('Poplatek za uživatele', 'Per-seat fee'), _('žádný', 'none')], ['OpenTofu', '✓']]]
      ],
      cat: _('Pro vývojáře', 'For developers'), crumb: 'Terraform',
      kicker: _('Oficiální provider · OpenTofu · moduly', 'Official provider · OpenTofu · modules'),
      title: _('Celá infrastruktura v repozitáři, ne v panelu', 'Your whole infrastructure in a repo, not in a panel'),
      lead: _('Oficiální provider pokrývá všechny služby včetně fakturace a práv. Funguje s Terraformem i OpenTofu a vydáváme ho zároveň s funkcí, ne později.', 'The official provider covers every service, billing and permissions included. It works with Terraform and OpenTofu, and ships with the feature, not after it.'),
      kpis: [['100 %', _('služeb pokrytých providerem', 'of services covered')], ['0 ' + _('dní', 'days'), _('zpoždění za novou funkcí', 'lag behind a new feature')], ['24', _('hotových modulů', 'ready-made modules')]],
      chips: ['Terraform', 'OpenTofu', _('Moduly', 'Modules'), _('Stav v S3', 'State in S3'), 'CI/CD', 'Import', 'Drift detection', _('Plán v PR', 'Plan in PR')],
      plansTitle: _('Jak se to používá', 'How teams use it'),
      plansNote: _('Provider je zdarma a open source. Platíte jen zdroje, které vytvoří.', 'The provider is free and open source. You pay only for the resources it creates.'),
      plans: [
        { name: 'Provider', tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('open source, Apache 2.0', 'open source, Apache 2.0'), ctaLabel: _('Otevřít v registry', 'Open in the registry'), specs: [_('Všechny služby', 'Every service'), _('Import existujících zdrojů', 'Import existing resources'), _('Detekce driftu', 'Drift detection'), _('Terraform i OpenTofu', 'Terraform and OpenTofu'), _('Příklady v repozitáři', 'Examples in the repo'), _('Semver a changelog', 'Semver and a changelog')] },
        { name: _('Moduly', 'Modules'), tag: _('Doporučeno', 'Recommended'), price: 0, priceLabel: '24', priceNote: _('hotových modulů zdarma', 'ready modules, free'), ctaLabel: _('Procházet moduly', 'Browse the modules'), specs: [_('Web s databází a CDN', 'Site with database and CDN'), _('Klastr pro Kubernetes', 'Kubernetes cluster'), _('GPU pro inference', 'GPU for inference'), _('Zálohy a DR lokalita', 'Backups and a DR site'), _('Herní server s proxy', 'Game server with a proxy'), _('Síť a firewall', 'Networking and firewall')] },
        { name: _('Stav a spolupráce', 'State and collaboration'), tag: '', price: 190, specs: [_('Stav v našem object storage', 'State in our object storage'), _('Zámky a verzování', 'Locking and versioning'), _('Plán do pull requestu', 'Plan posted into the PR'), _('Politiky před apply', 'Policies before apply'), _('Audit kdo co změnil', 'Audit of who changed what'), _('Bez poplatku za uživatele', 'No per-seat fee')] }
      ],
      cmp: {
        cols: ['Provider', _('Moduly', 'Modules'), _('Stav', 'State')],
        rows: [
          [_('Cena', 'Price'), _('Zdarma', 'Free'), _('Zdarma', 'Free'), '190 Kč'],
          [_('Licence', 'Licence'), 'Apache 2.0', 'Apache 2.0', '—'],
          ['OpenTofu', '✓', '✓', '✓'],
          [_('Zámky stavu', 'State locking'), '—', '—', '✓'],
          [_('Plán v PR', 'Plan in PR'), '—', '—', '✓'],
          [_('Poplatek za uživatele', 'Per-seat fee'), '—', '—', _('žádný', 'none')]
        ]
      },
      feats: [
        ['01', _('Bez zpoždění za panelem', 'No lag behind the panel'), _('Nová služba má prostředek v provideru ve stejný den.', 'A new service gets its resource in the provider the same day.')],
        ['02', _('Import toho, co už běží', 'Import what already runs'), _('Existující servery natáhnete do stavu bez opětovného vytváření.', 'Pull existing servers into state without recreating them.')],
        ['03', _('Plán rovnou v pull requestu', 'Plan straight in the pull request'), _('Recenzent vidí, co se změní, dřív než to schválí.', 'The reviewer sees what will change before approving it.')],
        ['04', _('Detekce driftu', 'Drift detection'), _('Když někdo sáhne do panelu, dozvíte se to ráno v reportu.', 'If somebody edits the panel, the morning report tells you.')],
        ['05', _('Moduly pro běžné případy', 'Modules for the usual cases'), _('Web, klastr, GPU nebo herní síť za pár řádků.', 'A site, a cluster, GPUs or a game fleet in a few lines.')],
        ['06', _('OpenTofu bereme vážně', 'OpenTofu is first class'), _('Testujeme obojí v CI, nejen Terraform.', 'We test both in CI, not just Terraform.')]
      ],
      tech: ['Terraform', 'OpenTofu', 'Terragrunt', 'GitHub Actions', 'GitLab CI', 'Atlantis', 'S3', 'Vault'],
      bench: [
        { label: _('Onhost — vytvoření prostředí', 'Onhost — provisioning an environment'), pct: 100, note: '1 min 40 s' },
        { label: _('Velký cloud — totéž', 'Hyperscaler — same'), pct: 33, note: '~5 min' },
        { label: _('Ruční klikání v panelu', 'Manual clicking'), pct: 9, note: '~18 min' }
      ],
      benchNote: _('Web, databáze, CDN a firewall z jednoho modulu, medián z 50 běhů.', 'A site, database, CDN and firewall from one module, median of 50 runs.'),
      cases: [
        { t: _('Prostředí pro každý tým', 'An environment per team'), d: _('Stejný modul, jiné proměnné, tři prostředí bez copy-paste.', 'The same module, different variables, three environments without copy-paste.'), m: _('Moduly', 'Modules') },
        { t: _('Audit změn', 'Change auditing'), d: _('Vše prochází pull requestem, nic se nemění potichu.', 'Everything goes through a PR; nothing changes quietly.'), m: _('Stav', 'State') },
        { t: _('Přechod z ručního provozu', 'Moving off manual ops'), d: _('Import čtyřiceti serverů do stavu za jedno odpoledne.', 'Forty servers imported into state in one afternoon.'), m: 'Provider' }
      ],
      faq: [
        [_('Je provider oficiální?', 'Is the provider official?'), _('Ano, vydáváme a udržujeme ho my, v registry i na Gitu.', 'Yes — we publish and maintain it, in the registry and on Git.')],
        [_('Funguje OpenTofu?', 'Does OpenTofu work?'), _('Ano, testujeme obě varianty v každém buildu.', 'Yes — both are tested in every build.')],
        [_('Kde držet stav?', 'Where should state live?'), _('V našem object storage se zámky, nebo kdekoli jinde.', 'In our object storage with locking, or anywhere else you prefer.')],
        [_('Umí to fakturaci?', 'Does it cover billing?'), _('Ano, včetně limitů útraty a přidělení nákladů projektům.', 'Yes — including spending caps and cost allocation per project.')],
        [_('Co když prostředek chybí?', 'What if a resource is missing?'), _('Napište nám. Chybějící prostředky doplňujeme do dvou týdnů.', 'Tell us. Missing resources are usually added within two weeks.')]
      ]
    }),

    'opensource': D({
      panelsTitle: _('Jak vracíme zpátky', 'How we give back'), panelsLead: _('Provozujeme cizí software a vyděláváme na tom. Část peněz proto posíláme tam, odkud přišel.', 'We run other people\u2019s software and make money from it, so part of that money goes back where it came from.'),
      panels: [
        [_('Peníze', 'Money'), _('Jedno procento obratu rozdělené mezi projekty, které skutečně provozujeme.', 'One percent of revenue, split between the projects we actually run.'), [[_('Podíl obratu', 'Share of revenue'), '1 %'], [_('Za rok 2025', 'In 2025'), '1,9 M Kč'], [_('Projektů', 'Projects'), '18'], [_('Rozpis veřejný', 'Public breakdown'), '✓']]],
        [_('Kód', 'Code'), _('Patche z provozu posíláme upstreamu, vlastní nástroje vydáváme pod Apache 2.0.', 'Operational patches go upstream; our own tools ship under Apache 2.0.'), [[_('Merge requestů 2025', 'Merged PRs in 2025'), '74'], [_('Vlastní repozitáře', 'Our own repos'), '9'], [_('Licence', 'Licence'), 'Apache 2.0'], [_('Odměna za chybu', 'Bug bounty'), _('kredit i peníze', 'credit or cash')]]],
        [_('Free tier', 'Free tier'), _('Veřejný repozitář stačí jako doklad. Kartu nechceme a fakturu nepošleme.', 'A public repo is proof enough. We want no card and send no invoice.'), [[_('Server', 'Server'), '2 vCPU / 4 GB'], ['CI/CD', _('v ceně', 'included')], [_('Doba platnosti', 'Duration'), _('bez omezení', 'unlimited')], [_('Schválení', 'Approval'), '< 5 ' + _('dní', 'days')]]]
      ],
      cmp: null,
      cat: _('Pro vývojáře', 'For developers'), crumb: _('Open source', 'Open source'),
      kicker: _('Provozujeme · přispíváme · vracíme', 'We run it · we contribute · we give back'),
      title: _('Stojíme na open source a platíme za to nájem', 'We stand on open source and we pay rent for it'),
      lead: _('Celá naše platforma běží na svobodném softwaru. Provozujeme ho pro vás na jeden klik, posíláme část zisku upstreamu a vlastní nástroje zveřejňujeme pod Apache 2.0.', 'Our entire platform runs on free software. We run it for you in one click, send part of our profit upstream and publish our own tools under Apache 2.0.'),
      kpis: [['1 %', _('obratu jde upstreamu', 'of revenue goes upstream')], ['18', _('projektů, které podporujeme', 'projects we fund')], [_('Zdarma', 'Free'), _('hosting pro veřejné repozitáře', 'hosting for public repos')]],
      chips: ['PostgreSQL', 'Docker', 'Kubernetes', 'Nextcloud', 'GitLab', 'Grafana', 'Valkey', 'Matomo', 'Mattermost', 'Keycloak'],
      plansTitle: _('Co u nás běží na jeden klik', 'One-click open source'),
      plansNote: _('Instalace, aktualizace i zálohy jsou na nás. Data zůstávají vaše a export je vždy možný.', 'Install, updates and backups are on us. The data stays yours and export is always available.'),
      plans: [
        { name: _('Nástroje pro tým', 'Team tools'), tag: _('Nejčastější', 'Most common'), price: 390, specs: ['Nextcloud', 'Mattermost', 'Vaultwarden', 'Keycloak', _('Zálohy denně', 'Daily backups'), _('Aktualizace na nás', 'Updates on us')] },
        { name: _('Vývojářský stack', 'Developer stack'), tag: '', price: 690, specs: ['GitLab', 'Woodpecker CI', 'Harbor', 'SonarQube', _('Runnery v ceně', 'Runners included'), _('SSO přes Keycloak', 'SSO via Keycloak')] },
        { name: _('Data a monitoring', 'Data and monitoring'), tag: '', price: 490, specs: ['PostgreSQL', 'Valkey', 'Grafana', 'Prometheus', 'Matomo', _('Metriky 13 měsíců', '13 months of metrics')] }
      ],
      feats: [
        ['01', _('Jedno procento obratu upstreamu', 'One percent of revenue upstream'), _('Rozděluje se mezi projekty, které skutečně provozujeme. Seznam zveřejňujeme.', 'Split between the projects we actually run. The list is public.')],
        ['02', _('Naše nástroje jsou veřejné', 'Our tools are public'), _('Terraform provider, CLI i agent pro monitoring pod Apache 2.0.', 'The Terraform provider, the CLI and the monitoring agent under Apache 2.0.')],
        ['03', _('Free tier pro veřejné repozitáře', 'Free tier for public repos'), _('Server, CI i doména zdarma. Bez karty a bez faktury po roce.', 'A server, CI and a domain, free. No card and no invoice after a year.')],
        ['04', _('Aktualizace neodkládáme', 'Updates are not deferred'), _('Bezpečnostní záplaty nasazujeme do 24 hodin od vydání.', 'Security patches ship within 24 hours of release.')],
        ['05', _('Data jsou vaše', 'The data is yours'), _('Export jedním příkazem, žádný proprietární formát.', 'One-command export, no proprietary format.')],
        ['06', _('Platíme za nahlášené chyby', 'We pay for reported bugs'), _('V našich open-source nástrojích, kreditem nebo penězi.', 'In our own open-source tools, in credit or in cash.')]
      ],
      tech: ['PostgreSQL', 'Docker', 'Kubernetes', 'GitLab', 'Nextcloud', 'Grafana', 'Keycloak', 'Valkey'],
      bench: [
        { label: _('Příspěvky upstreamu 2025', 'Upstream funding 2025'), pct: 100, note: '1,9 M Kč' },
        { label: '2024', pct: 58, note: '1,1 M Kč' },
        { label: '2023', pct: 26, note: '0,5 M Kč' }
      ],
      benchNote: _('Roční objem příspěvků projektům, které provozujeme, mimo vlastní vývoj.', 'Annual funding to the projects we run, excluding our own development.'),
      cases: [
        { t: _('Firma bez Microsoftu', 'A company without Microsoft'), d: _('Nextcloud, Mattermost a Keycloak místo licencí.', 'Nextcloud, Mattermost and Keycloak instead of licences.'), m: _('Nástroje pro tým', 'Team tools') },
        { t: _('Interní GitLab', 'Self-hosted GitLab'), d: _('Repozitáře a CI v EU, bez limitu uživatelů.', 'Repos and CI in the EU, with no seat limit.'), m: _('Vývojářský stack', 'Dev stack') },
        { t: _('Analytika bez Googlu', 'Analytics without Google'), d: _('Matomo a Grafana nad vlastními daty.', 'Matomo and Grafana over your own data.'), m: _('Data a monitoring', 'Data') }
      ],
      faq: [
        [_('Kolik posíláte upstreamu?', 'How much goes upstream?'), _('Jedno procento obratu, každý rok, se zveřejněným rozpisem.', 'One percent of revenue, every year, with a published breakdown.')],
        [_('Můžu si projekt spravovat sám?', 'Can I manage the project myself?'), _('Ano, root máte. Správu si můžete kdykoli vzít zpět.', 'Yes, you have root. You can take management back at any time.')],
        [_('Jak žádat o free tier?', 'How do I get the free tier?'), _('Odkazem na veřejný repozitář. Odpovídáme do pěti dnů.', 'Send a link to a public repo. We answer within five days.')],
        [_('Přispíváte i kódem?', 'Do you contribute code?'), _('Ano, hlavně patche z provozu — výkon, chyby, dokumentace.', 'Yes, mostly operational patches — performance, bugs, docs.')],
        [_('Co když projekt zanikne?', 'What if a project dies?'), _('Data vyexportujeme a pomůžeme s přechodem na náhradu.', 'We export your data and help you move to an alternative.')]
      ]
    })
  };
}
