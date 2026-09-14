// Onhost — data podstránek: řešení podle publika (Řešení), produkty navíc.

export function morePages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviews = [
    { name: 'Ing. Tomáš Bednář', role: _('CTO, Skladomat', 'CTO, Skladomat'), text: _('Přešli jsme z velkého cloudu a platíme třetinu. Podpora zvedá telefon a mluví česky s někým, kdo tomu rozumí.', 'We moved off a hyperscaler and pay a third. Support answers the phone, in Czech, and knows the stack.') },
    { name: 'Petra Kolářová', role: _('vedoucí vývoje, Retenza', 'head of engineering, Retenza'), text: _('Preview prostředí ke každému PR změnilo revize. Produkťák si klikne na odkaz místo popisu v Jiře.', 'Preview environments for every PR changed our reviews. The PM clicks a link instead of reading a Jira description.') },
    { name: 'Marek Šrámek', role: _('provozní ředitel, Zelená Energie Vysočina', 'operations director, Zelená Energie Vysočina'), text: _('Řízení baterií nesnese výpadek. Za osmnáct měsíců jsme neměli jediný, který by se dotkl regulace.', 'Battery control cannot tolerate downtime. In eighteen months not one outage touched the control loop.') }
  ];

  const kb = [
    { title: _('Deploy z GitHubu za pět minut', 'Deploying from GitHub in five minutes'), read: '5 min' },
    { title: _('Přenos projektu z AWS nebo Azure', 'Moving a project off AWS or Azure'), read: '9 min' },
    { title: _('Práva v týmu a servisní účty', 'Team roles and service accounts'), read: '4 min' },
    { title: _('Monitoring a alerty do Slacku', 'Monitoring and Slack alerts'), read: '6 min' }
  ];

  const sla = {
    title: _('Jedna smlouva, jedna faktura, jeden telefon', 'One contract, one invoice, one phone number'),
    lead: _('Ať máte jeden web nebo dvě stě kontejnerů, platí stejné SLA a volá se na stejné číslo. Účet roste s vámi, migrovat mezi produkty nemusíte.', 'Whether you run one site or two hundred containers, the same SLA applies and you call the same number. The account grows with you; you never migrate between products.'),
    rows: [
      { k: '99,99 %', v: _('dostupnost s kreditem', 'uptime, credited') },
      { k: '30 min', v: _('reakce podpory 24/7', 'support response, 24/7') },
      { k: '6', v: _('lokalit v Evropě', 'European locations') },
      { k: _('0 Kč', 'Free'), v: _('migrace a konzultace', 'migration and consulting') }
    ]
  };

  const migration = {
    title: _('Přechod bez výpadku a bez faktury', 'A switch with no downtime and no invoice'),
    lead: _('Nejdřív audit, pak plán s termíny, pak přenos v okně, které vyberete vy. Rollback je připravený v každém kroku.', 'First an audit, then a dated plan, then the move in a window you choose. A rollback is ready at every step.'),
    steps: [
      { n: '01', t: _('Audit prostředí', 'Environment audit'), d: _('Projdeme aplikace, data, závislosti a rizika.', 'We go through applications, data, dependencies and risks.') },
      { n: '02', t: _('Plán a zkouška', 'Plan and rehearsal'), d: _('Přenos zkusíme na kopii a změříme, jak dlouho trvá.', 'We rehearse on a copy and measure how long it takes.') },
      { n: '03', t: _('Přepnutí', 'Cutover'), d: _('Ostrý přenos v okně, které vyberete, s naším dohledem.', 'The real move in your chosen window, with us watching.') },
      { n: '04', t: _('Týden dohledu', 'A week of watching'), d: _('Sledujeme chyby, latenci a náklady a hlásíme se sami.', 'We watch errors, latency and cost, and report proactively.') }
    ]
  };

  const S = (o) => Object.assign({ config: false, roi: false, sla, migration, reviews, kb }, o);

  return {
    'sol-dev': S({
      cmpTitle: _('Co který tarif vývojářskému týmu dá', 'What each plan gives an engineering team'),
      config: true, roi: true, cfgBase: 179, cfgCpu: 95, cfgRam: 42, cfgDisk: 22,
      cat: _('Řešení', 'Solutions'), crumb: _('Pro vývojáře', 'For developers'),
      kicker: _('Git · CI/CD · preview · rollback', 'Git · CI/CD · previews · rollback'),
      title: _('Prostředí, které nezdržuje vývoj', 'An environment that does not slow the work down'),
      lead: _('Push do Gitu, build na našich runnerech, nasazení do produkce za necelých devět sekund. Preview prostředí ke každému PR, rollback jedním příkazem a logy, které někdo skutečně čte.', 'Push to Git, build on our runners, ship to production in under nine seconds. A preview environment for every PR, one-command rollback and logs somebody actually reads.'),
      kpis: [['8,4 s', _('push → produkce', 'push → production')], [_('bez limitu', 'unmetered'), _('minuty CI/CD', 'CI/CD minutes')], [_('1 příkaz', 'one command'), _('rollback na předchozí verzi', 'rollback to the previous release')]],
      chips: ['Git', 'CI/CD', 'Docker', 'Preview', _('Rollback', 'Rollback'), 'CLI', 'Terraform', _('API', 'API')],
      plansTitle: _('Tarify pro vývojové týmy', 'Plans for engineering teams'),
      plans: [
        { name: 'Solo', tag: '', price: 179, specs: [_('2 vCPU / 4 GB', '2 vCPU / 4 GB'), _('3 projekty', '3 projects'), _('Preview prostředí', 'Preview environments'), _('Git repozitáře', 'Git repos'), _('CI/CD runner sdílený', 'Shared CI/CD runner'), _('Deploy z pushe', 'Deploy on push')] },
        { name: 'Team', tag: _('Nejčastější', 'Most common'), price: 690, specs: [_('8 vCPU / 16 GB', '8 vCPU / 16 GB'), _('Neomezeně projektů', 'Unlimited projects'), _('Vlastní runner', 'Dedicated runner'), _('Staging a produkce', 'Staging and production'), _('RBAC a audit log', 'RBAC and audit log'), _('Podpora do 30 minut', '30-minute support')] },
        { name: 'Scale', tag: '', price: 2490, specs: [_('32 vCPU / 64 GB', '32 vCPU / 64 GB'), _('Vyhrazené runnery', 'Dedicated runner fleet'), _('Privátní síť', 'Private networking'), _('SLA 99,99 %', '99.99% SLA'), _('Jmenovaný inženýr', 'Named engineer'), _('Terraform a API', 'Terraform and API')] }
      ],
      cmp: {
        cols: ['Solo', 'Team', 'Scale'],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '179 Kč', '690 Kč', '2 490 Kč'],
          [_('Projekty', 'Projects'), '3', _('bez limitu', 'unlimited'), _('bez limitu', 'unlimited')],
          [_('CI/CD minuty', 'CI/CD minutes'), _('sdílené', 'shared'), _('vlastní runner', 'own runner'), _('vyhrazená flotila', 'dedicated fleet')],
          [_('Preview prostředí', 'Preview environments'), '✓', '✓', '✓'],
          ['RBAC', '—', '✓', '✓'],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Deploy z pushe', 'Deploy on push'), _('Připojíte repozitář, my postavíme a nasadíme. Bez YAML na tři sta řádků.', 'Connect the repo, we build and ship. No three-hundred-line YAML.')],
        ['02', _('Preview ke každému PR', 'A preview for every PR'), _('Každý pull request dostane vlastní URL i databázi. Po merge se uklidí sám.', 'Every pull request gets its own URL and database, cleaned up after merge.')],
        ['03', _('Rollback jedním příkazem', 'One-command rollback'), _('Předchozích deset verzí drží připravených, přepnutí trvá pár sekund.', 'The last ten releases stay warm; switching takes seconds.')],
        ['04', _('Runnery na EPYC', 'Runners on EPYC'), _('Build cache mezi běhy, minuty se nepočítají a fronta se neplatí.', 'Build cache between runs, minutes are not metered and you never pay for the queue.')],
        ['05', _('Všechno přes API a CLI', 'Everything via API and CLI'), _('Co umí panel, umí i terminál. Terraform provider je oficiální.', 'Anything the panel does, the terminal does. The Terraform provider is official.')],
        ['06', _('Logy a metriky u sebe', 'Logs and metrics in one place'), _('Strukturované logy 30 dní zpět, metriky do Grafany, alerty do Slacku.', 'Structured logs for 30 days, metrics into Grafana, alerts into Slack.')]
      ],
      tech: ['Node.js', 'Python', 'Go', 'Rust', 'Docker', 'PostgreSQL', 'Redis / Valkey', 'Terraform'],
      bench: [
        { label: _('Onhost — push → produkce', 'Onhost — push → production'), pct: 100, note: '8,4 s' },
        { label: _('Typický cloud s CI', 'Typical cloud with CI'), pct: 34, note: '~25 s' },
        { label: _('Vlastní VPS s ručním deployem', 'Own VPS with manual deploy'), pct: 8, note: '~2 min' }
      ],
      benchNote: _('Medián doby od push po běžící verzi, aplikace v Node.js s cache, 500 běhů.', 'Median time from push to a running release, a Node.js app with cache, 500 runs.'),
      cases: [
        { t: _('Dvoučlenný tým', 'A team of two'), d: _('Staging i produkce z jednoho repozitáře, bez devops člověka.', 'Staging and production from one repo, with no devops hire.'), m: 'Solo' },
        { t: _('Produktové studio', 'A product studio'), d: _('Osm klientských projektů, RBAC podle klienta, jedna faktura.', 'Eight client projects, per-client RBAC, one invoice.'), m: 'Team' },
        { t: _('SaaS v růstu', 'A growing SaaS'), d: _('Vyhrazené runnery a privátní síť mezi službami.', 'Dedicated runners and private networking between services.'), m: 'Scale' }
      ],
      faq: [
        [_('Musím používat Docker?', 'Do I have to use Docker?'), _('Ne. Buildpack rozpozná Node, Python i Go sám, Dockerfile je volba, ne podmínka.', 'No. Our buildpack detects Node, Python and Go; a Dockerfile is an option, not a requirement.')],
        [_('Kolik stojí CI minuty?', 'What do CI minutes cost?'), _('Nic. Runner platíte paušálem, minuty ani fronty neúčtujeme.', 'Nothing. You pay for the runner; minutes and queues are not billed.')],
        [_('Jde nasadit monorepo?', 'Can I deploy a monorepo?'), _('Ano, každý balíček může mít vlastní službu i vlastní preview.', 'Yes — each package can have its own service and its own preview.')],
        [_('Máte staging databáze?', 'Do you provide staging databases?'), _('Ano, preview prostředí dostane kopii schématu s anonymizovanými daty.', 'Yes — a preview gets a schema copy with anonymised data.')],
        [_('Jak funguje rollback?', 'How does rollback work?'), _('Předchozí verze zůstávají připravené, přepnutí je jeden příkaz nebo klik.', 'Previous releases stay warm; switching is one command or one click.')]
      ]
    }),

    'sol-ai': S({
      cmpTitle: _('Karty, autoscaling a data podle tarifu', 'Cards, autoscaling and data by plan'),
      config: true, roi: true, cfgBase: 1900, cfgCpu: 210, cfgRam: 62, cfgDisk: 48,
      cat: _('Řešení', 'Solutions'), crumb: _('Pro AI týmy', 'For AI teams'),
      kicker: _('H100 · inference · automatizace procesů', 'H100 · inference · process automation'),
      title: _('AI, která běží v Praze a počítá se v korunách', 'AI that runs in Prague and bills in your currency'),
      lead: _('GPU po hodinách, inference endpoint s OpenAI-kompatibilním API a automatizace procesů, které dnes dělá někdo ručně. Data zůstávají v EU a prompty nelogujeme.', 'GPUs by the hour, an inference endpoint with an OpenAI-compatible API, and automation for processes somebody still does by hand. Data stays in the EU and prompts are never logged.'),
      kpis: [['39 Kč', _('hodina H100 80 GB', 'per H100 80 GB hour')], ['< 90 s', _('od objednávky po první token', 'from order to first token')], ['0', _('logovaných promptů', 'prompts logged')]],
      chips: ['H100', 'L40S', 'vLLM', 'pgvector', 'Qdrant', 'LangChain', _('EU data', 'EU data'), 'S3'],
      plansTitle: _('Tarify pro AI týmy', 'Plans for AI teams'),
      plans: [
        { name: _('Experiment', 'Experiment'), tag: '', price: 1900, specs: [_('1× L40S sdílená', '1× shared L40S'), _('100 GB NVMe', '100 GB NVMe'), _('Inference endpoint', 'Inference endpoint'), _('Vektorová databáze 5 GB', '5 GB vector database'), _('Jupyter a SSH', 'Jupyter and SSH'), _('Účtování po hodinách', 'Hourly billing')] },
        { name: _('Produkce', 'Production'), tag: _('Nejčastější', 'Most common'), price: 12900, specs: [_('1× H100 80 GB', '1× H100 80 GB'), _('2 TB NVMe', '2 TB NVMe'), _('Autoscaling z nuly', 'Scale-to-zero autoscaling'), _('Vektorová databáze 100 GB', '100 GB vector database'), _('Privátní síť', 'Private networking'), _('SLA 99,95 %', '99.95% SLA')] },
        { name: _('Klastr', 'Cluster'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('podle počtu karet', 'by card count'), ctaLabel: _('Vyžádat nabídku', 'Request a quote'), specs: [_('4–32× H100 s NVLink', '4–32× H100 with NVLink'), _('InfiniBand 400 Gb', '400 Gb InfiniBand'), _('Vyhrazené úložiště', 'Dedicated storage'), _('Rezervace kapacity', 'Capacity reservation'), _('Jmenovaný inženýr', 'Named engineer'), _('SLA na míru', 'Bespoke SLA')] }
      ],
      cmp: {
        cols: [_('Experiment', 'Experiment'), _('Produkce', 'Production'), _('Klastr', 'Cluster')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '1 900 Kč', '12 900 Kč', _('na dotaz', 'on request')],
          ['GPU', 'L40S', 'H100 80 GB', _('4–32× H100', '4–32× H100')],
          [_('Autoscaling', 'Autoscaling'), '—', _('z nuly', 'scale-to-zero'), _('rezervace', 'reserved')],
          [_('Vektorová DB', 'Vector DB'), '5 GB', '100 GB', _('na míru', 'bespoke')],
          [_('Data v EU', 'Data in the EU'), '✓', '✓', '✓'],
          ['SLA', '99,9 %', '99,95 %', _('na míru', 'bespoke')]
        ]
      },
      feats: [
        ['01', _('Karty v Praze, ne v Oregonu', 'Cards in Prague, not Oregon'), _('Latence do 8 ms z ČR a data, která nikdy neopustí EU.', 'Sub-8 ms latency from Czechia and data that never leaves the EU.')],
        ['02', _('Účtujeme po hodinách', 'Billed by the hour'), _('Trénink přes noc stojí jako noc, ne jako měsíc.', 'An overnight training run costs a night, not a month.')],
        ['03', _('OpenAI-kompatibilní API', 'OpenAI-compatible API'), _('Přepnete base URL a klient funguje dál. Bez přepisování kódu.', 'Change the base URL and your client keeps working. No rewrite.')],
        ['04', _('Autoscaling z nuly', 'Scale-to-zero'), _('Endpoint bez provozu neplatíte, první požadavek ho probudí za pár sekund.', 'An idle endpoint costs nothing; the first request wakes it in seconds.')],
        ['05', _('Automatizace procesů', 'Process automation'), _('Napojíme model na tickety, fakturaci nebo monitoring a měříme, co ušetřil.', 'We wire the model into tickets, billing or monitoring and measure what it saved.')],
        ['06', _('Prompty nelogujeme', 'Prompts are never logged'), _('Ani pro ladění. Co pošlete do endpointu, zmizí s odpovědí.', 'Not even for debugging. What you send is gone with the response.')]
      ],
      tech: ['vLLM', 'PyTorch', 'CUDA 12', 'Ollama', 'pgvector', 'Qdrant', 'LangChain', 'Triton'],
      bench: [
        { label: _('Onhost H100 — hodina', 'Onhost H100 — hourly'), pct: 100, note: '39 Kč' },
        { label: _('Velký cloud — hodina', 'Hyperscaler — hourly'), pct: 28, note: '~139 Kč' },
        { label: _('Vlastní karta (odpis 36 měs.)', 'Own card (36-month write-off)'), pct: 62, note: '~63 Kč' }
      ],
      benchNote: _('Cena za hodinu H100 80 GB včetně sítě a úložiště, srpen 2026.', 'Hourly cost of an H100 80 GB including networking and storage, August 2026.'),
      cases: [
        { t: _('Interní asistent nad dokumenty', 'An internal document assistant'), d: _('Embeddingy v pgvectoru, model na L40S, přístup jen z firemní sítě.', 'Embeddings in pgvector, the model on an L40S, access from the corporate network only.'), m: _('Experiment', 'Experiment') },
        { t: _('Klasifikace tiketů', 'Ticket triage'), d: _('Model třídí a odpovídá na 40 % dotazů, zbytek eskaluje.', 'The model sorts and answers 40% of tickets and escalates the rest.'), m: _('Produkce', 'Production') },
        { t: _('Trénink vlastního modelu', 'Training your own model'), d: _('Osm karet na tři týdny, pak zpět na jednu pro inference.', 'Eight cards for three weeks, then back to one for inference.'), m: _('Klastr', 'Cluster') }
      ],
      faq: [
        [_('Můžu si přinést vlastní model?', 'Can I bring my own model?'), _('Ano, jakýkoli model v HuggingFace nebo GGUF formátu, i zavřený.', 'Yes — any model in HuggingFace or GGUF format, including private ones.')],
        [_('Jak řešíte dostupnost karet?', 'How do you handle card availability?'), _('Volnou kapacitu ukazujeme v reálném čase, u klastru se dělá rezervace.', 'Free capacity is shown in real time; clusters are reserved in advance.')],
        [_('Je to vhodné pro osobní údaje?', 'Is this suitable for personal data?'), _('Ano, máme DPA, data zůstávají v EU a prompty se nikam neukládají.', 'Yes — we sign a DPA, data stays in the EU and prompts are not stored.')],
        [_('Umíte automatizaci na míru?', 'Do you build bespoke automation?'), _('Ano, začínáme workshopem a měřením procesu, ne nákupem karty.', 'Yes — we start with a workshop and process measurement, not a card purchase.')],
        [_('Co když model nestačí?', 'What if the model is not enough?'), _('Přejdeme na větší kartu bez migrace, endpoint zůstává stejný.', 'We move to a bigger card with no migration; the endpoint stays the same.')]
      ]
    }),

    'sol-energy': S({
      cmpTitle: _('Lokality, body měření a SLA', 'Sites, metering points and SLA'),
      config: false, roi: true,
      cat: _('Řešení', 'Solutions'), crumb: _('Energetika a BESS', 'Energy and BESS'),
      kicker: _('BESS · Electree · komunitní energetika', 'BESS · Electree · community energy'),
      title: _('Infrastruktura pro řízení baterií a komunitní energetiku', 'Infrastructure for battery control and community energy'),
      lead: _('Řídicí vrstva pro bateriová úložiště, měření a sdílení energie ve společenstvích. Provozujeme ji redundantně ve dvou lokalitách, protože regulační příkaz nesmí čekat na restart.', 'The control layer for battery storage, metering and energy sharing in communities. We run it redundantly across two sites, because a dispatch command cannot wait for a reboot.'),
      kpis: [['< 50 ms', _('odezva řídicí smyčky', 'control-loop latency')], ['99,99 %', _('dostupnost s pokutou', 'uptime with penalties')], ['2', _('lokality v aktivní replikaci', 'sites in active replication')]],
      chips: ['BESS', 'Electree', 'OCPP', 'Modbus', 'MQTT', _('Časové řady', 'Time series'), _('Sdílení energie', 'Energy sharing'), 'SCADA'],
      plansTitle: _('Úrovně nasazení', 'Deployment tiers'),
      plansNote: _('Vždy s DR lokalitou. Menší instalace zvládne Pilot, distribuce potřebuje Provoz.', 'Always with a DR site. Small installs fit Pilot; distribution operations need Production.'),
      plans: [
        { name: _('Pilot', 'Pilot'), tag: '', price: 4900, specs: [_('Jedna lokalita + záloha', 'One site plus backup'), _('Do 50 měřicích bodů', 'Up to 50 metering points'), _('Časové řady 2 roky', '2 years of time series'), _('MQTT a Modbus brány', 'MQTT and Modbus gateways'), _('Dashboardy v Grafaně', 'Grafana dashboards'), _('Podpora v pracovní době', 'Business-hours support')] },
        { name: _('Provoz', 'Production'), tag: _('Nejčastější', 'Most common'), price: 18900, specs: [_('Aktivní replikace 2 lokality', 'Active replication, 2 sites'), _('Do 2 000 měřicích bodů', 'Up to 2,000 metering points'), _('Časové řady 10 let', '10 years of time series'), _('Odezva řídicí smyčky < 50 ms', 'Sub-50 ms control loop'), _('SLA 99,99 % s pokutou', '99.99% SLA with penalties'), _('NOC 24/7 a eskalace', '24/7 NOC and escalation')] },
        { name: _('Distribuce', 'Distribution'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('podle počtu odběrných míst', 'by connection points'), ctaLabel: _('Domluvit studii', 'Book a study'), specs: [_('Tři a více lokalit', 'Three or more sites'), _('Bez limitu bodů', 'No point limit'), _('Oddělená regulační síť', 'Isolated control network'), _('Audit a penetrační testy', 'Audits and pen tests'), _('Jmenovaný inženýr', 'Named engineer'), _('Smlouva na míru', 'Bespoke contract')] }
      ],
      cmp: {
        cols: [_('Pilot', 'Pilot'), _('Provoz', 'Production'), _('Distribuce', 'Distribution')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '4 900 Kč', '18 900 Kč', _('na dotaz', 'on request')],
          [_('Lokality', 'Sites'), _('1 + záloha', '1 + backup'), '2', '3+'],
          [_('Měřicí body', 'Metering points'), '50', '2 000', _('bez limitu', 'unlimited')],
          [_('Historie dat', 'Data retention'), _('2 roky', '2 years'), _('10 let', '10 years'), _('na míru', 'bespoke')],
          ['SLA', '99,9 %', '99,99 %', _('na míru', 'bespoke')],
          [_('Podpora', 'Support'), _('pracovní doba', 'business hours'), '24/7', _('24/7 + inženýr', '24/7 + engineer')]
        ]
      },
      feats: [
        ['01', _('Regulace nečeká', 'Dispatch does not wait'), _('Řídicí smyčka běží aktivně ve dvou lokalitách, přepnutí je automatické.', 'The control loop runs active-active in two sites; failover is automatic.')],
        ['02', _('Protokoly, které používáte', 'The protocols you already use'), _('Modbus TCP, OCPP 2.0.1, MQTT, IEC 60870 a REST pro zbytek.', 'Modbus TCP, OCPP 2.0.1, MQTT, IEC 60870 and REST for the rest.')],
        ['03', _('Časové řady na deset let', 'Ten years of time series'), _('Komprimované, dotazovatelné a exportovatelné pro regulátora.', 'Compressed, queryable and exportable for the regulator.')],
        ['04', _('Oddělená regulační síť', 'Isolated control network'), _('Řízení nesdílí síť s webem ani s kancelářskými systémy.', 'Control traffic shares no network with web or office systems.')],
        ['05', _('Komunitní energetika', 'Community energy'), _('Rozúčtování sdílené výroby mezi členy včetně podkladů pro fakturaci.', 'Allocation of shared generation between members, with billing inputs.')],
        ['06', _('Teplo zpátky do sítě', 'Heat back into the grid'), _('Odpadní teplo z našich sálů ohřívá dvě sousední budovy v Praze.', 'Waste heat from our halls warms two neighbouring Prague buildings.')]
      ],
      tech: ['TimescaleDB', 'Grafana', 'MQTT', 'Modbus TCP', 'OCPP 2.0.1', 'Kubernetes', 'Prometheus', 'Electree'],
      bench: [
        { label: _('Onhost — odezva řídicí smyčky', 'Onhost — control-loop latency'), pct: 100, note: '< 50 ms' },
        { label: _('Veřejný cloud (Frankfurt)', 'Public cloud (Frankfurt)'), pct: 42, note: '~120 ms' },
        { label: _('Vlastní server v rozvodně', 'Own server in the substation'), pct: 88, note: '~55 ms' }
      ],
      benchNote: _('Medián doby od měření po vydaný povel, instalace BESS 2 MWh, 30denní okno.', 'Median time from measurement to issued command, a 2 MWh BESS install, 30-day window.'),
      cases: [
        { t: _('Bateriové úložiště u FVE', 'Storage next to a solar plant'), d: _('Dvě megawatthodiny, řízení nabíjení podle spotové ceny.', 'Two megawatt-hours, charge control driven by spot prices.'), m: _('Pilot', 'Pilot') },
        { t: _('Energetické společenství', 'An energy community'), d: _('Osmdesát domácností, sdílení výroby a měsíční rozúčtování.', 'Eighty households, shared generation and monthly allocation.'), m: _('Provoz', 'Production') },
        { t: _('Regionální distributor', 'A regional distributor'), d: _('Tři lokality, oddělená síť a audit podle NIS2.', 'Three sites, an isolated network and a NIS2 audit.'), m: _('Distribuce', 'Distribution') }
      ],
      faq: [
        [_('Umíte se napojit na Electree?', 'Do you integrate with Electree?'), _('Ano, je to náš partner a integrace je hotová, ne teoretická.', 'Yes — they are our partner and the integration is built, not theoretical.')],
        [_('Kde data fyzicky leží?', 'Where does the data physically live?'), _('V Praze a Brně, v našich vlastních sálech. Nikde jinde.', 'In Prague and Brno, in our own halls. Nowhere else.')],
        [_('Zvládnete NIS2?', 'Can you handle NIS2?'), _('Ano, včetně dokumentace, auditu a hlášení incidentů ve lhůtě.', 'Yes — including documentation, audits and incident reporting within deadlines.')],
        [_('Co když vypadne konektivita?', 'What if connectivity drops?'), _('Lokální brána drží poslední plán a po obnovení dosynchronizuje data.', 'The local gateway keeps the last schedule and resyncs when the link returns.')],
        [_('Děláte i hardware?', 'Do you supply hardware too?'), _('Ano, brány i servery do rozvoden dodáváme a spravujeme.', 'Yes — we supply and manage gateways and substation servers.')]
      ]
    }),

    'sol-agency': S({
      cmpTitle: _('Klienti, bílý štítek a marže', 'Clients, white label and margin'),
      cat: _('Řešení', 'Solutions'), crumb: _('Pro agentury', 'For agencies'),
      kicker: _('Bílý štítek · jedna faktura · RBAC', 'White label · one invoice · RBAC'),
      title: _('Klientské weby pod jedním účtem a jednou fakturou', 'Client sites under one account and one invoice'),
      lead: _('Každý klient má vlastní prostředí, vy máte jeden přehled a jednu fakturu. Reseller režim vám nechá vlastní ceník i logo v panelu, který klient uvidí.', 'Every client gets their own environment, you get one overview and one invoice. Reseller mode gives you your own pricing and your logo in the panel the client sees.'),
      kpis: [['1', _('faktura za všechny klienty', 'invoice for every client')], ['25–45 %', _('marže podle objemu', 'margin by volume')], ['< 5 min', _('založení nového klienta', 'to onboard a client')]],
      chips: ['WordPress', 'WooCommerce', _('Bílý štítek', 'White label'), 'RBAC', _('Staging', 'Staging'), _('Zálohy', 'Backups'), 'SLA', _('Migrace zdarma', 'Free migration')],
      plansTitle: _('Tarify pro agentury', 'Plans for agencies'),
      plans: [
        { name: 'Studio', tag: '', price: 690, specs: [_('10 klientských webů', '10 client sites'), _('Staging ke každému webu', 'Staging for every site'), _('Zálohy 30 dní', '30-day backups'), _('Přístupy pro tým', 'Team access'), _('Přeprodej za vlastní ceny', 'Resell at your prices'), _('Podpora do 30 minut', '30-minute support')] },
        { name: _('Agentura', 'Agency'), tag: _('Nejčastější', 'Most common'), price: 2490, specs: [_('50 klientských webů', '50 client sites'), _('Bílý štítek panelu', 'White-labelled panel'), _('Vlastní ceník a marže', 'Your pricing and margins'), _('RBAC podle klienta', 'Per-client RBAC'), _('Fakturace pod vaší hlavičkou', 'Invoices under your brand'), _('SLA 99,95 %', '99.95% SLA')] },
        { name: _('Síť', 'Network'), tag: '', price: 7900, specs: [_('Bez limitu webů', 'Unlimited sites'), _('Vlastní doména panelu', 'Your own panel domain'), _('API pro váš systém', 'API for your own system'), _('Jmenovaný kontakt', 'Named contact'), _('Prioritní fronta', 'Priority queue'), _('SLA 99,99 %', '99.99% SLA')] }
      ],
      cmp: {
        cols: ['Studio', _('Agentura', 'Agency'), _('Síť', 'Network')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '690 Kč', '2 490 Kč', '7 900 Kč'],
          [_('Weby', 'Sites'), '10', '50', _('bez limitu', 'unlimited')],
          [_('Bílý štítek', 'White label'), '—', '✓', _('vlastní doména', 'own domain')],
          [_('Vaše marže', 'Your margin'), '25 %', '35 %', '45 %'],
          ['RBAC', _('tým', 'team'), _('podle klienta', 'per client'), _('podle klienta', 'per client')],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Klient nevidí Onhost', 'The client never sees Onhost'), _('Vaše logo, vaše barvy, vaše doména panelu i vaše faktury.', 'Your logo, your colours, your panel domain and your invoices.')],
        ['02', _('Jedna faktura za všechno', 'One invoice for everything'), _('My účtujeme vám velkoobchod, vy klientům, co si nastavíte.', 'We bill you wholesale, you bill clients whatever you set.')],
        ['03', _('Staging u každého webu', 'Staging on every site'), _('Kopie na jeden klik, změny nasazujete až po schválení.', 'A one-click copy; changes ship only after approval.')],
        ['04', _('Práva podle klienta', 'Per-client permissions'), _('Externista uvidí jen ten web, na kterém pracuje.', 'A contractor sees only the site they work on.')],
        ['05', _('Migrace klientů zdarma', 'Free client migration'), _('Přeneseme i padesát webů najednou, po nocích a s testem.', 'We can move fifty sites at once, overnight, with testing.')],
        ['06', _('Podpora umí mluvit s klientem', 'Support can talk to the client'), _('Se souhlasem řešíme incident přímo s vaším klientem, pod vaší značkou.', 'With your consent we handle incidents directly with your client, under your brand.')]
      ],
      tech: ['WordPress', 'WooCommerce', 'Shoptet', 'PHP 8.4', 'LiteSpeed', 'MySQL', 'Redis', 'WP-CLI'],
      bench: [
        { label: _('Onhost — TTFB na WordPressu', 'Onhost — WordPress TTFB'), pct: 100, note: '84 ms' },
        { label: _('Sdílený hosting v ČR', 'Shared hosting in Czechia'), pct: 38, note: '~220 ms' },
        { label: _('VPS bez optimalizace', 'Unoptimised VPS'), pct: 61, note: '~140 ms' }
      ],
      benchNote: _('Medián TTFB pro WooCommerce s 2 000 produkty, měřeno z Prahy, 24 hodin.', 'Median TTFB for WooCommerce with 2,000 products, measured from Prague over 24 hours.'),
      cases: [
        { t: _('Malé studio', 'A small studio'), d: _('Deset webů, staging a zálohy bez vlastního serveru.', 'Ten sites, staging and backups without running a server.'), m: 'Studio' },
        { t: _('Agentura s podporou', 'An agency with a support desk'), d: _('Padesát klientů, vlastní ceník a panel pod svou doménou.', 'Fifty clients, own pricing and a panel on their own domain.'), m: _('Agentura', 'Agency') },
        { t: _('Síť poboček', 'A network of branches'), d: _('Weby zakládá jejich interní systém přes naše API.', 'Their internal system creates sites through our API.'), m: _('Síť', 'Network') }
      ],
      faq: [
        [_('Uvidí klient, že jde o Onhost?', 'Will the client know it is Onhost?'), _('Ne, pokud nechcete. Panel, doména i faktury nesou vaši značku.', 'Not unless you want them to. Panel, domain and invoices carry your brand.')],
        [_('Můžu si nastavit vlastní ceny?', 'Can I set my own prices?'), _('Ano, libovolné. My vám účtujeme velkoobchodní ceník.', 'Yes, whatever you like. We bill you at wholesale.')],
        [_('Jak převedu klienta jinam?', 'How do I hand a client over?'), _('Převodem účtu na dva kliky, bez migrace dat.', 'A two-click account transfer, no data migration.')],
        [_('Kdo řeší incidenty v noci?', 'Who handles night incidents?'), _('Náš NOC. Vy dostanete hlášení, klient nemusí nic vědět.', 'Our NOC. You get the report; the client need not know.')],
        [_('Je v tom i e-mail pro klienty?', 'Does it include client mail?'), _('Ano, mail hosting je součástí a jde přeprodávat stejně jako web.', 'Yes — mail hosting is included and resells just like hosting.')]
      ]
    }),

    'sol-eshop': S({
      cmpTitle: _('Produkty, špičky a zálohy podle tarifu', 'Products, peaks and backups by plan'),
      cat: _('Řešení', 'Solutions'), crumb: _('Pro e-shopy', 'For e-commerce'),
      kicker: _('Black Friday · Woo · Shoptet · Shopware', 'Black Friday · Woo · Shoptet · Shopware'),
      title: _('E-shop, který nespadne, když přijde kampaň', 'A store that stays up when the campaign lands'),
      lead: _('Výkon nastavený na špičku, ne na průměr. Automatické škálování před kampaní, cache, která rozumí košíku, a zálohy po hodinách, protože objednávky se nedají dohledat zpaměti.', 'Capacity sized for the peak, not the average. Automatic scaling ahead of a campaign, cache that understands the basket, and hourly backups, because orders cannot be reconstructed from memory.'),
      kpis: [['118 ms', _('TTFB na Black Friday', 'TTFB on Black Friday')], ['× 6', _('kapacita během kampaně', 'capacity during a campaign')], ['1 h', _('interval záloh objednávek', 'order backup interval')]],
      chips: ['WooCommerce', 'Shoptet', 'PrestaShop', 'Shopware', 'Redis', 'Varnish', _('Autoscaling', 'Autoscaling'), 'PCI DSS'],
      plansTitle: _('Tarify pro e-shopy', 'Plans for online stores'),
      plans: [
        { name: 'Start', tag: '', price: 249, specs: [_('Do 2 000 produktů', 'Up to 2,000 products'), _('4 vCPU / 8 GB', '4 vCPU / 8 GB'), _('Redis cache', 'Redis cache'), _('Zálohy po hodinách', 'Hourly backups'), _('SSL a CDN v ceně', 'SSL and CDN included'), _('Migrace zdarma', 'Free migration')] },
        { name: 'Growth', tag: _('Nejčastější', 'Most common'), price: 990, specs: [_('Do 50 000 produktů', 'Up to 50,000 products'), _('8 vCPU / 24 GB', '8 vCPU / 24 GB'), _('Autoscaling na kampaně', 'Campaign autoscaling'), _('Staging a testovací platby', 'Staging and test payments'), _('WAF a anti-bot', 'WAF and anti-bot'), _('SLA 99,95 %', '99.95% SLA')] },
        { name: _('Špička', 'Peak'), tag: '', price: 3900, specs: [_('Bez limitu produktů', 'No product limit'), _('24 vCPU / 64 GB', '24 vCPU / 64 GB'), _('Databáze na vlastním stroji', 'Database on its own machine'), _('Zálohy po 15 minutách', '15-minute backups'), _('Dohled během kampaně', 'Campaign war room'), _('SLA 99,99 %', '99.99% SLA')] }
      ],
      cmp: {
        cols: ['Start', 'Growth', _('Špička', 'Peak')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '249 Kč', '990 Kč', '3 900 Kč'],
          [_('Produkty', 'Products'), '2 000', '50 000', _('bez limitu', 'unlimited')],
          [_('Autoscaling', 'Autoscaling'), '—', '✓', '✓'],
          [_('Zálohy', 'Backups'), _('1 h', 'hourly'), _('1 h', 'hourly'), _('15 min', '15 min')],
          [_('Dohled při kampani', 'Campaign war room'), '—', _('na vyžádání', 'on request'), '✓'],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Kapacita předem, ne po pádu', 'Capacity before, not after'), _('Kampaň nahlásíte v panelu a výkon se zvedne den předem.', 'Announce the campaign in the panel and capacity rises a day ahead.')],
        ['02', _('Cache, která nerozbije košík', 'Cache that respects the basket'), _('Pravidla rozumí přihlášení, košíku i pokladně. Bez ručního ladění.', 'Rules understand login, basket and checkout. No manual tuning.')],
        ['03', _('Zálohy objednávek po hodinách', 'Hourly order backups'), _('Obnovíme jen databázi, jen tabulku, nebo celý e-shop.', 'Restore just the database, just a table, or the whole store.')],
        ['04', _('Ochrana proti botům', 'Bot protection'), _('Vykupovací skripty a scrapery odfiltrujeme dřív, než sáhnou na sklad.', 'Sniping scripts and scrapers are filtered before they touch inventory.')],
        ['05', _('Testovací platby na stagingu', 'Test payments on staging'), _('Kopie e-shopu s vypnutými platbami pro zkoušení změn.', 'A copy of the store with payments disabled, for trying changes.')],
        ['06', _('Prohlídka výkonu zdarma', 'A free performance review'), _('Před sezonou projdeme dotazy, cache a pluginy a pošleme závěr.', 'Before the season we review queries, cache and plugins and send findings.')]
      ],
      tech: ['WooCommerce', 'Shoptet', 'PrestaShop', 'Shopware', 'MySQL', 'Redis', 'Varnish', 'PHP 8.4'],
      bench: [
        { label: _('Onhost Growth — TTFB při špičce', 'Onhost Growth — peak TTFB'), pct: 100, note: '118 ms' },
        { label: _('Sdílený hosting při špičce', 'Shared hosting at peak'), pct: 21, note: '~560 ms' },
        { label: _('VPS bez cache', 'VPS without cache'), pct: 47, note: '~250 ms' }
      ],
      benchNote: _('WooCommerce, 12 000 produktů, 400 souběžných návštěvníků, měřeno z Prahy.', 'WooCommerce, 12,000 products, 400 concurrent visitors, measured from Prague.'),
      cases: [
        { t: _('Rodinný e-shop', 'A family store'), d: _('Dva tisíce produktů, sezona před Vánoci, bez vlastního IT.', 'Two thousand products, a Christmas season, no in-house IT.'), m: 'Start' },
        { t: _('Kampaňový prodej', 'Campaign-driven retail'), d: _('Šestinásobek provozu na 48 hodin, pak zpět.', 'Six times the traffic for 48 hours, then back down.'), m: 'Growth' },
        { t: _('Velkoobchod s B2B portálem', 'A wholesaler with a B2B portal'), d: _('Databáze na vlastním stroji a dohled během výprodeje.', 'A dedicated database machine and a war room during the sale.'), m: _('Špička', 'Peak') }
      ],
      faq: [
        [_('Zvládnete Shoptet?', 'Do you support Shoptet?'), _('Shoptet běží u nás jako napojení přes API a doménu; plný hosting umíme u Woo, Presty a Shopware.', 'Shoptet connects via API and domain; full hosting covers Woo, Presta and Shopware.')],
        [_('Jak rychle zvýšíte výkon?', 'How fast can capacity go up?'), _('Do deseti minut od nahlášení kampaně, bez restartu e-shopu.', 'Within ten minutes of announcing the campaign, with no store restart.')],
        [_('Máte PCI DSS?', 'Are you PCI DSS compliant?'), _('Infrastruktura ano. Karty ale doporučujeme nechat na bráně, ne u sebe.', 'The infrastructure is. We still recommend keeping cards at the gateway.')],
        [_('Umíte obnovit jednu objednávku?', 'Can you restore a single order?'), _('Ano, obnovujeme i jednotlivé tabulky z hodinových záloh.', 'Yes — we restore individual tables from hourly backups.')],
        [_('Kolik stojí migrace?', 'What does migration cost?'), _('Nic, včetně přenosu dat, e-mailů a přesměrování.', 'Nothing — including data, mail and redirects.')]
      ]
    }),

    'sol-gaming': S({
      cmpTitle: _('Sloty, RAM a ochrana podle tarifu', 'Slots, RAM and protection by plan'),
      config: true, roi: false, cfgBase: 149, cfgCpu: 80, cfgRam: 38, cfgDisk: 14,
      cat: _('Řešení', 'Solutions'), crumb: _('Pro herní komunity', 'For gaming communities'),
      kicker: _('Anti-DDoS · sítě serverů · panel pro moderátory', 'Anti-DDoS · server fleets · moderator panel'),
      title: _('Komunita, která neřeší, jestli server běží', 'A community that never wonders whether the server is up'),
      lead: _('Několik serverů pod jedním účtem, práva pro moderátory bez sdílení hesla, anti-DDoS profil laděný pro každou hru a restarty naplánované mimo prime time.', 'Several servers under one account, moderator roles without sharing a password, an anti-DDoS profile tuned per game, and restarts scheduled outside prime time.'),
      kpis: [['1,2 Tbps', _('kapacita mitigace', 'mitigation capacity')], ['20 TPS', _('i na velkých světech', 'even on large worlds')], ['< 12 ms', _('ping z Prahy', 'ping from Prague')]],
      chips: ['Minecraft', 'CS2', 'Rust', 'ARK', 'Palworld', _('Anti-DDoS', 'Anti-DDoS'), _('Moderátoři', 'Moderators'), _('Zálohy', 'Backups')],
      plansTitle: _('Tarify pro komunity', 'Plans for communities'),
      plans: [
        { name: _('Klub', 'Club'), tag: '', price: 149, specs: [_('1 server, 40 slotů', '1 server, 40 slots'), _('4 GB RAM', '4 GB RAM'), _('Anti-DDoS základní', 'Basic anti-DDoS'), _('Zálohy denně', 'Daily backups'), _('Panel pro moderátory', 'Moderator panel'), _('Subdoména zdarma', 'Free subdomain')] },
        { name: _('Komunita', 'Community'), tag: _('Nejčastější', 'Most common'), price: 590, specs: [_('4 servery, 300 slotů', '4 servers, 300 slots'), _('16 GB RAM', '16 GB RAM'), _('Anti-DDoS Pro s L7', 'Anti-DDoS Pro with L7'), _('Zálohy po hodinách', 'Hourly backups'), _('Role a audit log', 'Roles and audit log'), _('Vlastní doména', 'Your own domain')] },
        { name: _('Síť', 'Network'), tag: '', price: 2290, specs: [_('Bez limitu serverů', 'Unlimited servers'), _('64 GB RAM', '64 GB RAM'), _('Proxy a lobby síť', 'Proxy and lobby network'), _('Turnajové servery po hodinách', 'Hourly tournament servers'), _('Prioritní podpora', 'Priority support'), _('SLA 99,95 %', '99.95% SLA')] }
      ],
      cmp: {
        cols: [_('Klub', 'Club'), _('Komunita', 'Community'), _('Síť', 'Network')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '149 Kč', '590 Kč', '2 290 Kč'],
          [_('Servery', 'Servers'), '1', '4', _('bez limitu', 'unlimited')],
          ['RAM', '4 GB', '16 GB', '64 GB'],
          ['Anti-DDoS', _('základní', 'basic'), 'Pro (L7)', 'Pro (L7)'],
          [_('Zálohy', 'Backups'), _('denně', 'daily'), _('po hodinách', 'hourly'), _('po hodinách', 'hourly')],
          [_('Turnajové servery', 'Tournament servers'), '—', _('po hodinách', 'hourly'), _('po hodinách', 'hourly')]
        ]
      },
      feats: [
        ['01', _('Moderátoři bez sdíleného hesla', 'Moderators without a shared password'), _('Každý má svůj účet a práva. V logu je vidět, kdo co udělal.', 'Everyone gets their own account and role. The log shows who did what.')],
        ['02', _('Anti-DDoS laděný pro hru', 'Anti-DDoS tuned per game'), _('Filtr pro Minecraft se chová jinak než pro CS2. Nastavujeme ho my.', 'The Minecraft filter behaves differently to CS2. We set it up.')],
        ['03', _('Restarty mimo prime time', 'Restarts outside prime time'), _('Plán podle vaší křivky hráčů, ne podle našeho pracovního dne.', 'Scheduled around your player curve, not our working day.')],
        ['04', _('Turnaj na tři dny', 'A three-day tournament'), _('Server zapnete na víkend a pak zrušíte. Faktura odpovídá.', 'Spin a server up for the weekend, then destroy it. The invoice matches.')],
        ['05', _('Sítě a lobby', 'Fleets and lobbies'), _('Proxy před servery, hráči přeskakují mezi světy bez odpojení.', 'A proxy in front, so players hop between worlds without disconnecting.')],
        ['06', _('Zálohy světa po hodinách', 'Hourly world backups'), _('Griefing vrátíme zpět na konkrétní hodinu, ne na včerejšek.', 'Griefing is rolled back to a specific hour, not to yesterday.')]
      ],
      tech: ['Paper', 'Fabric', 'Forge', 'BungeeCord', 'SteamCMD', 'Pterodactyl', 'Docker', 'S3'],
      bench: [
        { label: _('Onhost Praha — ping z ČR', 'Onhost Prague — ping from Czechia'), pct: 100, note: '< 12 ms' },
        { label: _('Frankfurt', 'Frankfurt'), pct: 55, note: '~26 ms' },
        { label: _('Západní Evropa', 'Western Europe'), pct: 32, note: '~42 ms' }
      ],
      benchNote: _('Medián pingu z pěti českých sítí, měřeno v prime time po dobu týdne.', 'Median ping from five Czech networks, measured in prime time over a week.'),
      cases: [
        { t: _('Survival server pro kamarády', 'A survival server for friends'), d: _('Čtyřicet slotů, denní zálohy, panel pro dva moderátory.', 'Forty slots, daily backups, a panel for two moderators.'), m: _('Klub', 'Club') },
        { t: _('Komunita s několika světy', 'A community with several worlds'), d: _('Čtyři servery, role pro deset moderátorů a anti-DDoS Pro.', 'Four servers, roles for ten moderators and anti-DDoS Pro.'), m: _('Komunita', 'Community') },
        { t: _('Esport organizace', 'An esports org'), d: _('Turnajové servery po hodinách a proxy pro lobby.', 'Hourly tournament servers and a lobby proxy.'), m: _('Síť', 'Network') }
      ],
      faq: [
        [_('Přenesete existující server?', 'Will you move an existing server?'), _('Ano, včetně světa, pluginů a konfigurace. Zdarma.', 'Yes — world, plugins and configuration included. Free.')],
        [_('Můžu server na měsíc uspat?', 'Can I suspend a server for a month?'), _('Ano, data zůstanou a platíte jen za úložiště.', 'Yes — data stays and you pay only for storage.')],
        [_('Zvládne to modpack o 300 modech?', 'Will a 300-mod modpack run?'), _('Ano, jen si pohlídejte RAM. Navyšuje se za provozu.', 'Yes — just watch the RAM. It scales live.')],
        [_('Jak rychle reagujete na DDoS?', 'How fast do you react to a DDoS?'), _('Detekce do tří sekund, mitigace automaticky, bez vašeho zásahu.', 'Detection within three seconds, mitigation automatic, no action needed.')],
        [_('Máte panel v češtině?', 'Is the panel in Czech?'), _('Ano, panel i podpora. Moderátoři nemusí umět anglicky.', 'Yes — panel and support. Moderators need no English.')]
      ]
    }),

    'sol-startup': S({
      cmpTitle: _('Kredit, výkon a papíry pro investora', 'Credit, capacity and investor paperwork'),
      roi: true,
      cat: _('Řešení', 'Solutions'), crumb: _('Pro startupy', 'For startups'),
      kicker: _('Kredit 30 000 Kč · škálování bez migrace', '30,000 CZK credit · scaling without migration'),
      title: _('Infrastruktura, která vydrží od prototypu po sérii A', 'Infrastructure that survives from prototype to Series A'),
      lead: _('Začnete na jednom kontejneru, skončíte na klastru, a mezitím nemusíte nic migrovat. Kredit na první rok, ceny v korunách a faktura, kterou pochopí i investor.', 'Start on one container, end on a cluster, and migrate nothing in between. First-year credit, prices in your currency and an invoice an investor can read.'),
      kpis: [['30 000 Kč', _('kredit na první rok', 'first-year credit')], ['× 40', _('rozsah škálování bez migrace', 'scaling range without migration')], ['0', _('poplatků za odchozí data', 'egress fees')]],
      chips: [_('Kredit', 'Credit'), 'Docker', 'Postgres', 'S3', _('Autoscaling', 'Autoscaling'), _('Bez egress poplatků', 'No egress fees'), 'GPU', _('Mentoring', 'Mentoring')],
      plansTitle: _('Tarify pro startupy', 'Plans for startups'),
      plansNote: _('Kredit se čerpá na cokoli z katalogu. Nevyčerpaný zůstatek se převádí do dalšího měsíce.', 'Credit applies to anything in the catalogue. Unused balance rolls over.'),
      plans: [
        { name: _('Prototyp', 'Prototype'), tag: '', price: 179, specs: [_('2 vCPU / 4 GB', '2 vCPU / 4 GB'), _('Managed Postgres 10 GB', '10 GB managed Postgres'), _('Object storage 100 GB', '100 GB object storage'), _('Deploy z Gitu', 'Deploy from Git'), _('Doména zdarma na rok', 'Free domain for a year'), _('Kredit 5 000 Kč', '5,000 CZK credit')] },
        { name: _('Růst', 'Growth'), tag: _('Nejčastější', 'Most common'), price: 1290, specs: [_('8 vCPU / 16 GB', '8 vCPU / 16 GB'), _('Postgres s replikou', 'Postgres with a replica'), _('Autoscaling', 'Autoscaling'), _('Staging prostředí', 'Staging environment'), _('Kredit 30 000 Kč', '30,000 CZK credit'), _('Podpora do 30 minut', '30-minute support')] },
        { name: _('Série A', 'Series A'), tag: '', price: 6900, specs: [_('32 vCPU / 64 GB', '32 vCPU / 64 GB'), _('Privátní síť a VPN', 'Private networking and VPN'), _('GPU na vyžádání', 'GPU on demand'), _('SLA 99,99 %', '99.99% SLA'), _('Jmenovaný inženýr', 'Named engineer'), _('Podklady pro due diligence', 'Due-diligence documentation')] }
      ],
      cmp: {
        cols: [_('Prototyp', 'Prototype'), _('Růst', 'Growth'), _('Série A', 'Series A')],
        rows: [
          [_('Cena měsíčně', 'Monthly price'), '179 Kč', '1 290 Kč', '6 900 Kč'],
          [_('Kredit', 'Credit'), '5 000 Kč', '30 000 Kč', _('individuální', 'individual')],
          [_('Databáze', 'Database'), '10 GB', _('s replikou', 'with replica'), _('klastr', 'cluster')],
          [_('Odchozí data', 'Egress'), _('zdarma', 'free'), _('zdarma', 'free'), _('zdarma', 'free')],
          ['GPU', '—', _('po hodinách', 'hourly'), _('rezervace', 'reserved')],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Kredit bez podmínek', 'Credit with no strings'), _('Nechceme podíl ani exkluzivitu. Stačí, že stavíte něco vlastního.', 'We want no equity and no exclusivity. Building something of your own is enough.')],
        ['02', _('Bez poplatků za odchozí data', 'No egress fees'), _('Přenos ven neúčtujeme. Odejít od nás je levné schválně.', 'Outbound transfer is not billed. Leaving is deliberately cheap.')],
        ['03', _('Škálování bez migrace', 'Scaling without migration'), _('Ze dvou jader na dvaatřicet bez změny adresy a bez výpadku.', 'From two cores to thirty-two with no address change and no downtime.')],
        ['04', _('Faktura, kterou pochopí investor', 'An invoice an investor can read'), _('Položky podle projektů, export do CSV a DPH v pořádku.', 'Line items per project, CSV export and VAT handled properly.')],
        ['05', _('Podklady pro due diligence', 'Due-diligence documentation'), _('ISO 27001, DPA, popis architektury a záloh na vyžádání.', 'ISO 27001, a DPA, an architecture and backup description on request.')],
        ['06', _('Mentoring od NOC', 'Mentoring from the NOC'), _('Hodinu měsíčně projdeme architekturu a řekneme, co škrtnout.', 'One hour a month reviewing your architecture and what to cut.')]
      ],
      tech: ['Docker', 'PostgreSQL', 'Redis / Valkey', 'S3', 'Terraform', 'Grafana', 'Next.js', 'FastAPI'],
      bench: [
        { label: _('Onhost — měsíc provozu (8 vCPU)', 'Onhost — monthly (8 vCPU)'), pct: 100, note: '1 290 Kč' },
        { label: _('Velký cloud, srovnatelný výkon', 'Hyperscaler, comparable spec'), pct: 26, note: '~4 900 Kč' },
        { label: _('Velký cloud po vyčerpání kreditu', 'Hyperscaler after credits'), pct: 18, note: '~7 200 Kč' }
      ],
      benchNote: _('8 vCPU / 16 GB, 500 GB NVMe, 2 TB odchozích dat, ceník srpen 2026.', '8 vCPU / 16 GB, 500 GB NVMe, 2 TB egress, August 2026 list prices.'),
      cases: [
        { t: _('Dva zakladatelé a prototyp', 'Two founders and a prototype'), d: _('Jeden kontejner, databáze a doména. Kredit pokryl první tři měsíce.', 'One container, a database and a domain. Credit covered the first three months.'), m: _('Prototyp', 'Prototype') },
        { t: _('Pilotní zákazníci', 'Pilot customers'), d: _('Staging pro demo, replika databáze a autoscaling na kampaň.', 'Staging for demos, a database replica and autoscaling for the campaign.'), m: _('Růst', 'Growth') },
        { t: _('Po investici', 'Post-raise'), d: _('Privátní síť, GPU na modely a papíry pro due diligence.', 'Private networking, GPUs for models and paperwork for due diligence.'), m: _('Série A', 'Series A') }
      ],
      faq: [
        [_('Jak se o kredit žádá?', 'How do I apply for credit?'), _('Formulářem a krátkým popisem projektu. Odpověď do pěti dnů.', 'A form and a short project description. An answer within five days.')],
        [_('Chcete podíl ve firmě?', 'Do you want equity?'), _('Ne. Kredit je marketing, ne investice.', 'No. The credit is marketing, not an investment.')],
        [_('Co když vyrosteme rychle?', 'What if we grow fast?'), _('Zvětšíme stroj za provozu, u větších skoků nabídneme klastr.', 'We resize live; for bigger jumps we move you to a cluster.')],
        [_('Můžeme odejít?', 'Can we leave?'), _('Kdykoli, bez výpovědní lhůty a bez poplatku za export dat.', 'Any time, with no notice period and no data export fee.')],
        [_('Máte startupy jako reference?', 'Do you have startup references?'), _('Ano, rádi propojíme s někým z podobného oboru.', 'Yes — we are happy to connect you with someone in a similar field.')]
      ]
    }),

    'sol-edu': S({
      cmpTitle: _('Podmínky jednotlivých programů', 'Terms of each programme'),
      cat: _('Řešení', 'Solutions'), crumb: _('Školy a open source', 'Schools and open source'),
      kicker: _('Free tier · granty · výuka', 'Free tier · grants · teaching'),
      title: _('Zdarma pro školy, studenty a veřejné repozitáře', 'Free for schools, students and public repositories'),
      lead: _('Free tier pro veřejné open-source projekty, granty na servery pro školní výuku a hotové prostředí pro cvičení, které se po semestru samo uklidí.', 'A free tier for public open-source projects, grants for school infrastructure, and ready-made lab environments that clean themselves up after the term.'),
      kpis: [[_('Zdarma', 'Free'), _('pro veřejné repozitáře', 'for public repositories')], ['180', _('podpořených projektů', 'projects supported')], ['1,4 M Kč', _('rozdáno v grantech za 2025', 'granted in 2025')]],
      chips: [_('Free tier', 'Free tier'), _('Granty', 'Granty'), _('Výuka', 'Teaching'), 'GitLab', 'Jupyter', 'Docker', _('Akademie', 'Academy'), _('Meetupy', 'Meetups')],
      plansTitle: _('Programy', 'Programmes'),
      plansNote: _('Žádost vyřídíme do pěti pracovních dnů. Bez faktury, bez karty, bez automatického přechodu na placený tarif.', 'Applications are answered within five business days. No invoice, no card, no automatic upgrade to a paid plan.'),
      plans: [
        { name: _('Open source', 'Open source'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('pro veřejné repozitáře', 'for public repositories'), ctaLabel: _('Požádat o free tier', 'Apply for the free tier'), specs: [_('2 vCPU / 4 GB', '2 vCPU / 4 GB'), _('Git a CI/CD zdarma', 'Free Git and CI/CD'), _('Doména a SSL', 'Domain and SSL'), _('Bez časového omezení', 'No time limit'), _('Logo v našem seznamu', 'A logo in our directory'), _('Podpora přes Discord', 'Support via Discord')] },
        { name: _('Škola', 'School'), tag: '', price: 0, priceLabel: _('Grant', 'Grant'), priceNote: _('podle počtu studentů', 'by student count'), ctaLabel: _('Požádat o grant', 'Apply for a grant'), specs: [_('Prostředí pro cvičení', 'Lab environments'), _('Účty pro studenty', 'Student accounts'), _('Automatický úklid po semestru', 'Automatic end-of-term cleanup'), _('Materiály pro výuku', 'Teaching materials'), _('Přednáška od našeho inženýra', 'A lecture from our engineer'), _('Exkurze do datacentra', 'A data centre tour')] },
        { name: _('Student', 'Student'), tag: '', price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('s platným ISIC', 'with a valid student ID'), ctaLabel: _('Ověřit studium', 'Verify your studies'), specs: [_('1 vCPU / 2 GB', '1 vCPU / 2 GB'), _('Doména .cz na rok', 'A .cz domain for a year'), _('Kurzy akademie zdarma', 'Free academy courses'), _('Git repozitáře', 'Git repositories'), _('Portfolio hosting', 'Portfolio hosting'), _('Sleva 50 % po škole', '50% off after graduation')] }
      ],
      cmp: {
        cols: [_('Open source', 'Open source'), _('Škola', 'School'), _('Student', 'Student')],
        rows: [
          [_('Cena', 'Price'), _('Zdarma', 'Free'), _('Grant', 'Grant'), _('Zdarma', 'Free')],
          [_('Ověření', 'Verification'), _('veřejný repozitář', 'public repository'), _('smlouva se školou', 'school agreement'), _('ISIC', 'student ID')],
          [_('Výkon', 'Resources'), '2 vCPU / 4 GB', _('podle projektu', 'per project'), '1 vCPU / 2 GB'],
          [_('Doba platnosti', 'Duration'), _('bez omezení', 'unlimited'), _('školní rok', 'school year'), _('po dobu studia', 'while studying')],
          ['CI/CD', '✓', '✓', '✓'],
          [_('Podpora', 'Support'), 'Discord', _('e-mail a telefon', 'email and phone'), 'Discord']
        ]
      },
      feats: [
        ['01', _('Free tier bez pasti', 'A free tier with no trap'), _('Neplatíte nic a po roce vám nepřijde faktura. Kartu nechceme.', 'You pay nothing and no invoice arrives after a year. We do not want a card.')],
        ['02', _('Prostředí pro cvičení', 'Ready-made lab environments'), _('Jedno zadání, třicet identických kontejnerů, po semestru se smažou.', 'One assignment, thirty identical containers, deleted after the term.')],
        ['03', _('Přednáška zdarma', 'A free lecture'), _('Náš inženýr přijede ukázat, jak vypadá provoz datacentra doopravdy.', 'One of our engineers will show what running a data centre actually looks like.')],
        ['04', _('Granty čtyřikrát ročně', 'Grants four times a year'), _('Rozhodujeme my, ne komise. Odpověď do pěti dnů.', 'We decide, not a committee. An answer within five days.')],
        ['05', _('Vracíme upstreamu', 'We give back upstream'), _('Část zisku posíláme projektům, které provozujeme na svých serverech.', 'Part of our profit goes to the projects we run on our own servers.')],
        ['06', _('Akademie zdarma', 'A free academy'), _('Kurzy Linuxu, Dockeru a CI/CD, včetně cvičných serverů.', 'Linux, Docker and CI/CD courses, practice servers included.')]
      ],
      tech: ['GitLab', 'Jupyter', 'Docker', 'Kubernetes', 'PostgreSQL', 'Moodle', 'Nextcloud', 'Grafana'],
      bench: [
        { label: _('Podpořené projekty 2025', 'Projects supported in 2025'), pct: 100, note: '180' },
        { label: _('Projekty 2024', 'Projects in 2024'), pct: 63, note: '113' },
        { label: _('Projekty 2023', 'Projects in 2023'), pct: 31, note: '56' }
      ],
      benchNote: _('Počet aktivních free tier účtů a grantů na konci roku.', 'Active free-tier accounts and grants at year end.'),
      cases: [
        { t: _('Knihovna pro parsování dat', 'A data-parsing library'), d: _('Veřejný repozitář, CI na každý commit, nula korun měsíčně.', 'A public repo, CI on every commit, zero a month.'), m: _('Open source', 'Open source') },
        { t: _('Střední průmyslová škola', 'A technical secondary school'), d: _('Třicet studentů, prostředí pro Linux a sítě, úklid v červnu.', 'Thirty students, a Linux and networking lab, cleaned up in June.'), m: _('Škola', 'School') },
        { t: _('Studentské portfolio', 'A student portfolio'), d: _('Web, doména a Git zdarma po celou dobu studia.', 'A site, a domain and Git, free for the whole degree.'), m: _('Student', 'Student') }
      ],
      faq: [
        [_('Musí být projekt na GitHubu?', 'Does the project need to be on GitHub?'), _('Ne, stačí veřejný repozitář kdekoli, i u nás.', 'No — any public repository will do, including ours.')],
        [_('Co když projekt začne vydělávat?', 'What if the project starts earning?'), _('Ozvěte se, domluvíme placený tarif. Sami vás neodpojíme.', 'Get in touch and we will agree a paid plan. We will not cut you off.')],
        [_('Jak dlouho grant platí?', 'How long does a grant last?'), _('Školní rok, prodloužení je formalita, pokud se prostředí používá.', 'A school year; renewal is a formality if the environment is being used.')],
        [_('Můžeme dostat exkurzi?', 'Can we get a tour?'), _('Ano, do PRG1 vodíme školy každý druhý čtvrtek.', 'Yes — we take schools into PRG1 every other Thursday.')],
        [_('Podporujete i neziskovky?', 'Do you support non-profits?'), _('Ano, stejnou cestou jako školy. Napište nám, co děláte.', 'Yes, the same way as schools. Tell us what you do.')]
      ]
    })
  };
}
