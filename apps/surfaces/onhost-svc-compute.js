// Onhost — data podstránek: VPS, dedikovaný hardware, colocation, GPU, AI, dev nástroje
// Stejná struktura jako onhost-svc-web.js. Stránky s config:true mají konfigurátor,
// stránky s roi:true kalkulačku návratnosti proti vlastnímu železu.

export function computePages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviews = [
    { name: 'Tomáš Vrána', role: _('DevOps lead, Skladomat', 'DevOps lead, Skladomat'), text: _('Šestnáct VPS, jeden Terraform stav, žádné klikání v panelu. API se chová přesně jak dokumentace slibuje.', 'Sixteen VPS instances, one Terraform state, zero panel clicking. The API behaves exactly as documented.') },
    { name: 'Lucie Hájková', role: _('CTO, Retenza AI', 'CTO, Retenza AI'), text: _('H100 v Praze znamená, že trénujeme na vlastních datech a nemusíme řešit, kam odtekly.', 'An H100 in Prague means we train on our own data without wondering where it went.') },
    { name: 'Ondřej Marek', role: _('vedoucí infrastruktury, Elektro Kříž', 'head of infrastructure, Elektro Kříž'), text: _('Přenesli jsme dvanáct racků z vlastní serverovny a poprvé nás v pátek nikdo nevolal.', 'We moved twelve racks out of our own server room and for the first time nobody called on a Friday.') }
  ];

  const kb = [
    { title: _('První kroky s VPS: SSH klíče a firewall', 'First steps with a VPS: SSH keys and firewall'), read: '7 min' },
    { title: _('Terraform provider onhost od nuly', 'The onhost Terraform provider from scratch'), read: '9 min' },
    { title: _('Snapshoty, obnova a klonování serveru', 'Snapshots, restore and server cloning'), read: '5 min' },
    { title: _('Jak měříme výkon vCPU a NVMe', 'How we measure vCPU and NVMe performance'), read: '6 min' }
  ];

  const sla = {
    title: _('Dostupnost s pokutou, ne s hvězdičkou', 'Uptime with a penalty, not an asterisk'),
    lead: _('Každá minuta nad limit se přepočítá na kredit automaticky. Hardware držíme v rezervě, takže výměna disku neznamená výpadek služby.', 'Every minute over the limit converts to credit automatically. We keep spare hardware, so a disk swap does not mean an outage.'),
    rows: [
      { k: '99,99 %', v: _('dostupnost sítě a napájení', 'network and power uptime') },
      { k: '< 15 min', v: _('reakce na incident P1', 'P1 incident response') },
      { k: '2× N+1', v: _('napájení a chlazení', 'power and cooling') },
      { k: '10 Gbit/s', v: _('konektivita v základu', 'connectivity as standard') }
    ]
  };

  const migration = {
    title: _('Přeneseme servery i celé racky', 'We move servers and whole racks'),
    lead: _('Z veřejného cloudu, od konkurence nebo z vlastní serverovny. Souběžný provoz, replikace dat, přepnutí po službách — a rollback připravený po celou dobu.', 'From public cloud, a competitor or your own server room. Parallel operation, data replication, service-by-service cutover — with a rollback ready the whole time.'),
    steps: [
      { n: '01', t: _('Audit a plán', 'Audit and plan'), d: _('Zmapujeme stroje, závislosti a okna, ve kterých se smí přepínat.', 'We map machines, dependencies and the windows when cutover is allowed.') },
      { n: '02', t: _('Souběžný provoz', 'Parallel operation'), d: _('Nové prostředí běží vedle starého, data se replikují průběžně.', 'The new environment runs beside the old one with continuous replication.') },
      { n: '03', t: _('Přepnutí po službách', 'Service-by-service cutover'), d: _('Nejdřív méně kritické, pak databáze. Každý krok jde vrátit.', 'Less critical first, databases last. Every step is reversible.') },
      { n: '04', t: _('Optimalizace a předání', 'Tuning and handover'), d: _('Doladíme výkon, předáme runbook a monitoring vašemu týmu.', 'We tune performance and hand over the runbook and monitoring.') }
    ]
  };

  const P = (o) => Object.assign({ config: false, roi: false, sla, migration, reviews, kb }, o);

  return {
    'vps': P({
      cmpTitle: _('Výkon a limity jednotlivých VPS', 'Performance and limits of each VPS'),
      cat: _('Servery a hardware', 'Servers and hardware'), crumb: 'VPS / VDS',
      kicker: _('AMD EPYC · NVMe · root do 55 s', 'AMD EPYC · NVMe · root in 55 s'),
      title: _('VPS s dedikovanými jádry, ne s přeprodaným výkonem', 'A VPS with dedicated cores, not oversold capacity'),
      lead: _('Jádra AMD EPYC vyhrazená jen vám, NVMe v RAID10, snapshoty po hodinách a plný root. Konfiguraci měníte za provozu a platíte po hodinách.', 'AMD EPYC cores reserved for you alone, NVMe in RAID10, hourly snapshots and full root. Resize live and pay by the hour.'),
      kpis: [['55 s', _('od objednávky k rootu', 'from order to root')], ['0 %', _('přeprodej CPU', 'CPU oversubscription')], ['99,99 %', _('dostupnost 2025', 'uptime in 2025')]],
      chips: ['AMD EPYC 9004', 'NVMe RAID10', 'KVM', _('IPv4 + /64 IPv6', 'IPv4 + /64 IPv6'), _('Snapshoty', 'Snapshots'), 'Terraform + API'],
      config: true, roi: true, cfgBase: 120, cfgCpu: 62, cfgRam: 34, cfgDisk: 26,
      plans: [
        { name: 'VPS 2', tag: '', price: 249, specs: ['2 vCPU EPYC', '4 GB RAM', '80 GB NVMe', _('Neměřený přenos', 'Unmetered traffic'), _('Denní snapshot', 'Daily snapshot'), _('1 IPv4 + /64', '1 IPv4 + /64')] },
        { name: 'VPS 4', tag: _('Nejoblíbenější', 'Most popular'), price: 590, specs: ['4 vCPU EPYC', '8 GB RAM', '160 GB NVMe', _('Neměřený přenos', 'Unmetered traffic'), _('Snapshoty po hodinách', 'Hourly snapshots'), _('Firewall a DDoS', 'Firewall and DDoS')] },
        { name: 'VPS 8', tag: '', price: 1290, specs: ['8 vCPU EPYC', '32 GB RAM', '400 GB NVMe', '10 Gbit/s', _('Snapshoty po hodinách', 'Hourly snapshots'), _('Privátní síť', 'Private network')] }
      ],
      cmp: {
        cols: ['VPS 2', 'VPS 4', 'VPS 8'],
        rows: [
          ['vCPU', '2', '4', '8'],
          ['RAM', '4 GB', '8 GB', '32 GB'],
          ['NVMe', '80 GB', '160 GB', '400 GB'],
          [_('Síť', 'Network'), '1 Gbit/s', '2,5 Gbit/s', '10 Gbit/s'],
          [_('Snapshoty', 'Snapshots'), _('Denně', 'Daily'), _('Každou hodinu', 'Hourly'), _('Každou hodinu', 'Hourly')],
          [_('Privátní síť', 'Private network'), _('Doplněk', 'Add-on'), '✓', '✓'],
          [_('DDoS ochrana', 'DDoS protection'), _('Základní', 'Basic'), _('Rozšířená', 'Advanced'), _('Rozšířená', 'Advanced')],
          [_('Změna velikosti za provozu', 'Live resize'), '✓', '✓', '✓'],
          ['SLA', '99,9 %', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Jádra jen pro vás', 'Cores only for you'), _('Nepřeprodáváme CPU. Steal time držíme pod 0,5 %, a měříme to veřejně.', 'We do not oversell CPU. Steal time stays under 0.5%, and we publish the measurement.')],
        ['02', _('Root za necelou minutu', 'Root in under a minute'), _('Server naklonujeme z obrazu, klíč nasadíme, IP přiřadíme — bez ticketu.', 'We clone from an image, deploy your key and assign an IP — no ticket needed.')],
        ['03', _('Snapshoty a klonování', 'Snapshots and cloning'), _('Snapshot před updatem, klon na testování, obnovení do tří minut.', 'Snapshot before an update, clone for testing, restore within three minutes.')],
        ['04', _('Privátní síť mezi servery', 'Private network between servers'), _('VLAN bez poplatku za přenos, ideální pro databázi mimo internet.', 'VLAN with no transfer fee — ideal for keeping a database off the internet.')],
        ['05', _('API, Terraform, CLI', 'API, Terraform, CLI'), _('Celý životní cyklus serveru scriptovatelný. Panel je jen jeden z klientů.', 'The whole server lifecycle is scriptable. The panel is just one client.')],
        ['06', _('Účtování po hodinách', 'Hourly billing'), _('Zapnete na tři dny testu a zaplatíte tři dny. Bez závazku a bez aktivačních poplatků.', 'Spin one up for a three-day test and pay three days. No commitment, no setup fees.')]
      ],
      tech: ['Debian', 'Ubuntu', 'Rocky Linux', 'AlmaLinux', 'Windows Server', 'Docker', 'K3s', 'Terraform', 'Ansible', 'cloud-init'],
      bench: [
        { label: _('Onhost VPS 4 (EPYC 9354)', 'Onhost VPS 4 (EPYC 9354)'), pct: 100, note: '1 842 pts' },
        { label: _('Srovnatelný VPS u globálního cloudu', 'Comparable VPS at a global cloud'), pct: 71, note: '1 310 pts' },
        { label: _('VPS s přeprodanými jádry', 'VPS with oversold cores'), pct: 43, note: '790 pts' }
      ],
      benchNote: _('Geekbench 6 multi-core, 4 vCPU, medián z deseti běhů v různých denních hodinách.', 'Geekbench 6 multi-core, 4 vCPU, median of ten runs across different times of day.'),
      cases: [
        { t: _('Aplikace v Dockeru', 'Dockerised application'), d: _('Node nebo Python API s databází, deploy z CI, snapshot před releasem.', 'A Node or Python API with a database, CI deploys, snapshot before release.'), m: 'VPS 4' },
        { t: _('Databáze mimo internet', 'Database off the internet'), d: _('Privátní síť, žádná veřejná IP, přístup jen z aplikačních serverů.', 'Private network, no public IP, access only from app servers.'), m: 'VPS 8' },
        { t: _('Testovací prostředí', 'Test environment'), d: _('Naklonujete produkci, zkusíte migraci, server po hodinách zrušíte.', 'Clone production, try the migration, destroy the server hours later.'), m: 'VPS 2' }
      ],
      faq: [
        [_('Můžu měnit konfiguraci později?', 'Can I resize later?'), _('Ano, RAM a CPU za provozu, disk s krátkým restartem. Cena se přepočítá od dané hodiny.', 'Yes — RAM and CPU live, disk with a short reboot. Pricing recalculates from that hour.')],
        [_('Máte Windows licence?', 'Do you offer Windows licences?'), _('Ano, Windows Server 2022 a 2025 jako doplněk s měsíční licencí.', 'Yes, Windows Server 2022 and 2025 as an add-on with a monthly licence.')],
        [_('Jak je to s IPv4 adresami?', 'What about IPv4 addresses?'), _('Jedna v ceně, další za 45 Kč měsíčně. IPv6 /64 je zdarma vždy.', 'One included, extras at 45 CZK a month. An IPv6 /64 is always free.')],
        [_('Provozujete Kubernetes?', 'Do you run Kubernetes?'), _('K3s nebo Talos si nasadíte sami, nebo použijete náš managed klastr v rámci Enterprise.', 'Deploy K3s or Talos yourself, or use our managed cluster under Enterprise.')],
        [_('Co když překročím přenos?', 'What if I exceed traffic limits?'), _('Nic. Přenos neúčtujeme, jen u trvale saturované linky nabídneme vyšší port.', 'Nothing. We do not bill transfer; if a link stays saturated we will offer a bigger port.')]
      ]
    }),

    'dedicated': P({
      cmpTitle: _('Sestavy dedikovaných strojů', 'Dedicated machine configurations'),
      cat: _('Servery a hardware', 'Servers and hardware'), crumb: _('Dedikované servery', 'Dedicated servers'),
      kicker: _('EPYC · Ryzen · nákup i pronájem', 'EPYC · Ryzen · buy or rent'),
      title: _('Celý stroj jen pro vás — pronájem, nebo do vlastnictví', 'A whole machine for you — rent it or own it'),
      lead: _('Vybraný hardware skladem do 24 hodin, nebo sestavíme na míru do pěti dnů. Můžete si ho pronajmout, splácet po 24 měsíců, nebo koupit a nechat u nás v racku.', 'Selected hardware in stock within 24 hours, or built to order in five days. Rent it, pay it off over 24 months, or buy it and keep it in our rack.'),
      kpis: [['24 h', _('dodání ze skladu', 'delivery from stock')], ['3 roky', _('záruka a náhradní díly', 'warranty and spares')], ['-38 %', _('proti pronájmu při koupi', 'vs renting when you buy')]],
      chips: ['AMD EPYC 9004', 'Ryzen 9 7950X', 'NVMe / SATA / SAS', 'IPMI', _('Vlastní RAID', 'Your own RAID'), _('Koupě i pronájem', 'Buy or rent')],
      config: true, roi: true, cfgBase: 1900, cfgCpu: 210, cfgRam: 62, cfgDisk: 48,
      plans: [
        { name: 'Ryzen Compute', tag: _('Nejlepší cena za výkon', 'Best value'), price: 2490, specs: ['Ryzen 9 7950X, 16 c / 32 t', '128 GB DDR5 ECC', '2× 2 TB NVMe', _('1 Gbit/s neměřeně', '1 Gbit/s unmetered'), 'IPMI a KVM konzole', _('Dodání do 24 h', 'Delivered within 24 h')] },
        { name: 'EPYC Standard', tag: '', price: 5900, specs: ['EPYC 9354, 32 c / 64 t', '256 GB DDR5 ECC', '4× 3,84 TB NVMe', '10 Gbit/s', _('Hardwarový RAID', 'Hardware RAID'), _('Náhradní díly do 4 h', 'Spares within 4 h')] },
        { name: _('EPYC na míru', 'EPYC bespoke'), tag: '', price: 12900, specs: [_('2× EPYC 9554, 128 jader', '2× EPYC 9554, 128 cores'), _('do 3 TB DDR5 ECC', 'up to 3 TB DDR5 ECC'), _('do 24 disků', 'up to 24 drives'), _('25 Gbit/s', '25 Gbit/s'), _('Vlastní specifikace', 'Your specification'), _('Sestavení do 5 dnů', 'Built within 5 days')] }
      ],
      cmp: {
        cols: ['Ryzen Compute', 'EPYC Standard', _('EPYC na míru', 'EPYC bespoke')],
        rows: [
          [_('Jádra / vlákna', 'Cores / threads'), '16 / 32', '32 / 64', _('až 128 / 256', 'up to 128 / 256')],
          ['RAM', '128 GB DDR5 ECC', '256 GB DDR5 ECC', _('až 3 TB', 'up to 3 TB')],
          [_('Disky', 'Drives'), '2× NVMe', '4× NVMe', _('až 24 pozic', 'up to 24 bays')],
          [_('Síť', 'Network'), '1 Gbit/s', '10 Gbit/s', '25 Gbit/s'],
          ['IPMI / KVM', '✓', '✓', '✓'],
          [_('Možnost koupě', 'Purchase option'), '✓', '✓', '✓'],
          [_('Splátky 24 měsíců', '24-month instalments'), '✓', '✓', '✓'],
          [_('Výměna dílu', 'Part replacement'), _('do 8 h', 'within 8 h'), _('do 4 h', 'within 4 h'), _('do 4 h, díly na skladě', 'within 4 h, spares on site')],
          ['SLA', '99,95 %', '99,99 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Pronájem, splátky, nebo koupě', 'Rent, instalments or purchase'), _('Stejný stroj, tři způsoby platby. Při koupi platíte jen housing a konektivitu.', 'Same machine, three ways to pay. When you buy it, you pay only housing and connectivity.')],
        ['02', _('Hardware, který si vyberete', 'Hardware you choose'), _('Procesor, disky, RAID kontroler, síťovka. Žádné „srovnatelné konfigurace“.', 'CPU, drives, RAID controller, NIC. No “comparable configurations”.')],
        ['03', _('Náhradní díly v budově', 'Spares in the building'), _('Disky, paměti i celé zdroje držíme na skladě v každé lokalitě.', 'Drives, memory and whole PSUs are stocked at every location.')],
        ['04', _('IPMI a KVM přístup', 'IPMI and KVM access'), _('Vlastní instalace, vlastní ISO, přístup k BIOSu. Nezamykáme vás.', 'Your own installs, your own ISOs, BIOS access. No lock-in.')],
        ['05', _('Chlazení pro trvalý boost', 'Cooling for sustained boost'), _('Stroje ladíme na trvalý výkon, ne na krátký peak v benchmarku.', 'We tune machines for sustained throughput, not a brief benchmark peak.')],
        ['06', _('Vykoupíme i váš starý hardware', 'We buy your old hardware too'), _('Funkční servery ze vaší serverovny odkoupíme nebo přijmeme jako protiúčet.', 'We will buy working servers from your room, or take them as part-exchange.')]
      ],
      tech: ['AMD EPYC', 'AMD Ryzen', 'Intel Xeon', 'Proxmox', 'VMware', 'TrueNAS', 'Kubernetes', 'Windows Server', 'ZFS'],
      bench: [
        { label: _('EPYC 9354 (32 jader)', 'EPYC 9354 (32 cores)'), pct: 100, note: '14 900 pts' },
        { label: _('Ryzen 9 7950X (16 jader)', 'Ryzen 9 7950X (16 cores)'), pct: 62, note: '9 200 pts' },
        { label: _('Xeon Silver 4310 (12 jader)', 'Xeon Silver 4310 (12 cores)'), pct: 27, note: '4 020 pts' }
      ],
      benchNote: _('Geekbench 6 multi-core na plné konfiguraci, průměr ze tří strojů každého typu.', 'Geekbench 6 multi-core on full configurations, average of three machines per type.'),
      cases: [
        { t: _('Virtualizační host', 'Virtualisation host'), d: _('Proxmox s desítkami VM, dedikovaná paměť, vlastní ZFS pool.', 'Proxmox with dozens of VMs, dedicated memory, your own ZFS pool.'), m: 'EPYC Standard' },
        { t: _('Vlastní hardware v našem racku', 'Your hardware in our rack'), d: _('Koupíte stroj, my ho provozujeme a hlídáme. Odpisujete si vlastní majetek.', 'You buy the machine, we run and monitor it. You depreciate your own asset.'), m: _('Koupě + housing', 'Purchase + housing') },
        { t: _('Herní a simulační zátěž', 'Game and simulation workloads'), d: _('Vysoké takty Ryzenu tam, kde záleží na jednom vlákně.', 'High Ryzen clocks where single-thread performance decides.'), m: 'Ryzen Compute' }
      ],
      faq: [
        [_('Jak funguje koupě serveru?', 'How does buying a server work?'), _('Vystavíme fakturu na hardware, stroj je váš majetek. Platíte pak jen housing, konektivitu a případný servis.', 'We invoice the hardware and the machine is your asset. After that you pay only housing, connectivity and optional service.')],
        [_('Můžu splácet?', 'Can I pay in instalments?'), _('Ano, 12 nebo 24 měsíců bez navýšení. Po doplacení přechází stroj do vašeho vlastnictví.', 'Yes, 12 or 24 months with no markup. Once paid off, ownership transfers to you.')],
        [_('Co když se hardware porouchá?', 'What if hardware fails?'), _('U pronájmu měníme do čtyř hodin bez poplatku. U koupených strojů v rámci tříleté záruky také.', 'On rentals we replace within four hours at no cost. Purchased machines are covered by the three-year warranty.')],
        [_('Dodáte konkrétní model disku nebo karty?', 'Can you source a specific drive or card?'), _('Ano, do pěti pracovních dnů. Cenu i dostupnost potvrdíme dopředu.', 'Yes, within five business days. We confirm price and availability up front.')],
        [_('Můžu si server odvézt?', 'Can I take the server away?'), _('Vlastní stroj kdykoli. Připravíme ho, smažeme disky podle vašeho pokynu a vydáme protokol.', 'Your own machine, anytime. We prepare it, wipe drives to your instruction and issue a report.')]
      ]
    }),

    'colocation': P({
      cmpTitle: _('Prostor, napájení a konektivita v racku', 'Rack space, power and connectivity'),
      cat: _('Servery a hardware', 'Servers and hardware'), crumb: 'Colocation',
      kicker: _('TIER III · 2N napájení · 6 lokalit', 'TIER III · 2N power · 6 locations'),
      title: _('Vaše železo v našem racku, i s komunitní energií', 'Your hardware in our rack, with community power'),
      lead: _('Od jedné U po celý rack, s 2N napájením, chlazením N+1 a přístupem 24/7. Část energie bereme z komunitních zdrojů a bateriových systémů — a odvod tepla vracíme do sítě.', 'From a single U to a whole rack, with 2N power, N+1 cooling and 24/7 access. Part of the energy comes from community sources and battery storage — and waste heat goes back into the grid.'),
      kpis: [['1,18', 'PUE'], ['2N', _('napájení a UPS', 'power and UPS')], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')]],
      chips: ['TIER III', '2N UPS', _('Dieselagregát', 'Diesel generator'), _('BESS baterie', 'BESS storage'), _('Přístup 24/7', '24/7 access'), _('Odvod tepla', 'Heat recovery')],
      roi: true, roiModel: 'housing',
      plans: [
        { name: '1U', tag: '', price: 990, specs: [_('1U v šířce 19"', '1U in a 19" rack'), '300 W', _('2× napájení', 'Dual power feeds'), '1 Gbit/s / 10 TB', _('1 IPv4 + /64', '1 IPv4 + /64'), _('Přístup na ohlášení', 'Access on request')] },
        { name: _('Čtvrt racku', 'Quarter rack'), tag: _('Nejčastější volba', 'Most chosen'), price: 4900, specs: ['10U', '2,5 kW', _('2N napájení', '2N power'), '10 Gbit/s / 100 TB', _('Vlastní switch', 'Your own switch'), _('Přístup 24/7 na kartu', '24/7 badge access')] },
        { name: _('Celý rack', 'Full rack'), tag: '', price: 18900, specs: ['42U', '7 kW', _('2N + generátor', '2N + generator'), _('25 Gbit/s / neměřeně', '25 Gbit/s / unmetered'), _('Uzamčený rack', 'Locked cabinet'), _('Cross-connect v ceně', 'Cross-connect included')] }
      ],
      cmp: {
        cols: ['1U', _('Čtvrt racku', 'Quarter rack'), _('Celý rack', 'Full rack')],
        rows: [
          [_('Prostor', 'Space'), '1U', '10U', '42U'],
          [_('Příkon v ceně', 'Included power'), '300 W', '2,5 kW', '7 kW'],
          [_('Napájení', 'Power feeds'), '2×', '2N', _('2N + generátor', '2N + generator')],
          [_('Konektivita', 'Connectivity'), '1 Gbit/s', '10 Gbit/s', '25 Gbit/s'],
          [_('Přenos', 'Transfer'), '10 TB', '100 TB', _('Neměřený', 'Unmetered')],
          [_('Fyzický přístup', 'Physical access'), _('Na ohlášení', 'On request'), _('24/7 na kartu', '24/7 badge'), _('24/7 na kartu', '24/7 badge')],
          [_('Remote hands', 'Remote hands'), _('30 min / měs.', '30 min / month'), _('2 h / měs.', '2 h / month'), _('5 h / měs.', '5 h / month')],
          [_('Cross-connect', 'Cross-connect'), _('Doplněk', 'Add-on'), '1 ×', _('V ceně, 4 ×', 'Included, 4 ×')]
        ]
      },
      feats: [
        ['01', _('Napájení, které nepustí', 'Power that does not blink'), _('Dvě nezávislé trasy, UPS 2N, dieselagregát s palivem na 72 hodin.', 'Two independent feeds, 2N UPS, diesel generator with 72 hours of fuel.')],
        ['02', _('Baterie a komunitní energie', 'Batteries and community energy'), _('Bateriové systémy vyrovnávají špičky a část energie odebíráme z lokálních obnovitelných zdrojů.', 'Battery storage flattens peaks and part of our energy comes from local renewables.')],
        ['03', _('Teplo, které nevyhodíme', 'Heat we do not waste'), _('Odvádíme ho do teplovodní sítě pro okolní budovy, ne do vzduchu nad Prahou.', 'It goes into the district heating loop for nearby buildings, not into the Prague sky.')],
        ['04', _('Remote hands, kdy nemůžete přijet', 'Remote hands when you cannot come'), _('Restart, výměna disku, přezapojení kabelu — do 30 minut od ticketu.', 'Reboot, disk swap, recabling — within 30 minutes of the ticket.')],
        ['05', _('Fyzická bezpečnost', 'Physical security'), _('Dvoufaktorový vstup, kamerový záznam 90 dní, uzamčené racky, logovaný přístup.', 'Two-factor entry, 90-day camera retention, locked cabinets, logged access.')],
        ['06', _('Konektivita bez závislosti', 'Connectivity without lock-in'), _('Pět tranzitů, peering v NIX.CZ a možnost přivést vlastního operátora.', 'Five transits, NIX.CZ peering and the option to bring your own carrier.')]
      ],
      tech: ['NIX.CZ', 'BGP', 'Cross-connect', 'Dark fiber', 'IPMI', 'Smart PDU', 'Zabbix', 'Prometheus'],
      bench: [
        { label: _('Onhost Praha (BESS + obnovitelné)', 'Onhost Prague (BESS + renewables)'), pct: 100, note: 'PUE 1,18' },
        { label: _('Průměrné evropské datacentrum', 'Average European data centre'), pct: 62, note: 'PUE 1,55' },
        { label: _('Vlastní serverovna ve firmě', 'In-house company server room'), pct: 32, note: 'PUE 2,4' }
      ],
      benchNote: _('PUE měřené za 12 měsíců včetně chlazení a rozvodů; nižší je lepší, sloupec ukazuje účinnost.', 'PUE measured over 12 months including cooling and distribution; lower is better, the bar shows efficiency.'),
      cases: [
        { t: _('Odchod z vlastní serverovny', 'Leaving your own server room'), d: _('Přestěhujeme racky, zapojíme, otestujeme. Vy zrušíte klimatizaci v suterénu.', 'We move the racks, cable them and test. You cancel the basement air-con.'), m: _('Celý rack', 'Full rack') },
        { t: _('Záložní lokalita', 'Secondary site'), d: _('Druhá lokalita 200 km daleko pro DR, propojená privátní linkou.', 'A second site 200 km away for DR, joined by a private link.'), m: _('Čtvrt racku', 'Quarter rack') },
        { t: _('Specifický hardware', 'Specialised hardware'), d: _('Appliance, storage nebo HSM, které nemůže běžet ve virtuálu.', 'An appliance, storage array or HSM that cannot run virtualised.'), m: '1U' }
      ],
      faq: [
        [_('Můžu si přivézt vlastní rack?', 'Can I bring my own cabinet?'), _('Ano, pokud odpovídá 19" standardu a hloubce. Umístění a napájení potvrdíme dopředu.', 'Yes, if it matches the 19" standard and depth. We confirm placement and power in advance.')],
        [_('Jak je to s přístupem?', 'How does access work?'), _('U racku a čtvrtracku 24/7 na kartu s dvoufaktorem, u 1U na ohlášení do 2 hodin.', 'Full and quarter racks get 24/7 two-factor badge access; 1U is on request within 2 hours.')],
        [_('Účtujete spotřebu podle měřiče?', 'Is power metered?'), _('Ano, smart PDU měří na zásuvku. V ceně je limit, nad něj platíte skutečnou spotřebu.', 'Yes, smart PDUs meter per outlet. There is an included limit; above it you pay actual usage.')],
        [_('Zajistíte i stěhování?', 'Do you handle the move?'), _('Ano, včetně převozu, montáže a zapojení. U větších projektů s plánem po krocích.', 'Yes, including transport, mounting and cabling. Larger projects get a step-by-step plan.')],
        [_('Můžu použít vlastního operátora?', 'Can I use my own carrier?'), _('Ano, přivedeme cross-connect do vašeho racku. Cenu určuje operátor, my účtujeme jen port.', 'Yes, we bring a cross-connect to your rack. The carrier sets their price; we bill only the port.')]
      ]
    }),

    'gpu': P({
      cmpTitle: _('Karty a jejich limity', 'The cards and their limits'),
      cat: _('AI a data', 'AI and data'), crumb: _('GPU instance', 'GPU instances'),
      kicker: _('H100 · L40S · účtování po hodinách', 'H100 · L40S · hourly billing'),
      title: _('H100 v Praze. Vaše data zůstanou v Česku.', 'H100s in Prague. Your data stays in the country.'),
      lead: _('Trénink i inference na vyhrazených kartách, bez čekání ve frontě a bez odesílání dat mimo EU. Zapnete na dvě hodiny, zaplatíte dvě hodiny.', 'Training and inference on dedicated cards, no queue and no data leaving the EU. Run it for two hours, pay for two hours.'),
      kpis: [['7 / 24', _('volných karet H100', 'H100 cards free')], ['90 s', _('start instance', 'instance start')], ['0', _('přenosů dat mimo EU', 'transfers outside the EU')]],
      chips: ['H100 80 GB SXM', 'L40S 48 GB', 'NVLink', 'InfiniBand 400G', 'PyTorch / CUDA 12', _('Účtování po hodinách', 'Hourly billing')],
      config: true, cfgBase: 4200, cfgCpu: 340, cfgRam: 48, cfgDisk: 34,
      hourly: [24.9, 78, 540],
      plans: [
        { name: 'L40S', tag: _('Pro inference', 'For inference'), price: 17900, specs: ['1× L40S 48 GB', '16 vCPU EPYC', '128 GB RAM', '1 TB NVMe', _('Bez závazku, účtování po hodinách', 'No commitment, hourly billing')] },
        { name: 'H100', tag: _('Nejžádanější', 'Most wanted'), price: 56000, specs: ['1× H100 80 GB SXM', '32 vCPU EPYC', '256 GB RAM', '4 TB NVMe', 'NVLink ready'] },
        { name: 'H100 ×8', tag: '', price: 398000, specs: ['8× H100 80 GB SXM', '128 vCPU EPYC', '2 TB RAM', '30 TB NVMe', 'InfiniBand 400G', _('Rezervace na měsíc a víc', 'Monthly reservations and up')] }
      ],
      cmp: {
        cols: ['L40S', 'H100', 'H100 ×8'],
        rows: [
          ['VRAM', '48 GB', '80 GB HBM3', '640 GB HBM3'],
          [_('Vhodné pro', 'Best for'), _('Inference, fine-tuning do 13B', 'Inference, fine-tuning to 13B'), _('Trénink do 70B, LoRA', 'Training to 70B, LoRA'), _('Trénink od základu', 'Training from scratch')],
          [_('Propojení karet', 'Interconnect'), '—', 'NVLink', 'NVLink + InfiniBand 400G'],
          [_('Minimální doba', 'Minimum term'), _('1 hodina', '1 hour'), _('1 hodina', '1 hour'), _('1 měsíc', '1 month')],
          [_('Úložiště v ceně', 'Included storage'), '1 TB NVMe', '4 TB NVMe', '30 TB NVMe'],
          [_('Data v ČR', 'Data in CZ'), '✓', '✓', '✓']
        ]
      },
      feats: [
        ['01', _('Karty jen pro vás', 'Cards reserved for you'), _('Žádné dělení GPU mezi zákazníky, žádná fronta na spuštění jobu.', 'No GPU sharing between customers, no queue to start a job.')],
        ['02', _('Data zůstanou v ČR', 'Data stays in the country'), _('Trénovací sady, checkpointy i logy leží v Praze. Smlouva o zpracování je součástí.', 'Training sets, checkpoints and logs live in Prague. A data processing agreement is included.')],
        ['03', _('Prostředí připravené', 'A ready environment'), _('CUDA 12, PyTorch, vLLM a Jupyter v obrazu. Nemusíte řešit ovladače.', 'CUDA 12, PyTorch, vLLM and Jupyter in the image. No driver archaeology.')],
        ['04', _('Účtování po hodinách', 'Hourly billing'), _('Job doběhne, instanci zrušíte, platba se zastaví. Checkpointy zůstanou na úložišti.', 'The job finishes, you destroy the instance, billing stops. Checkpoints stay on storage.')],
        ['05', _('Rezervace na kampaň', 'Reservations for a campaign'), _('Potřebujete osm karet na tři týdny? Rezervujeme termín a garantujeme dostupnost.', 'Need eight cards for three weeks? We reserve the slot and guarantee availability.')],
        ['06', _('Napojení na naši AI vrstvu', 'Wired into our AI layer'), _('Model vytrénujete a rovnou vystavíte přes Inference API bez přesouvání dat.', 'Train the model and serve it through the Inference API with no data movement.')]
      ],
      tech: ['PyTorch', 'CUDA 12', 'vLLM', 'Ollama', 'JAX', 'Hugging Face', 'Ray', 'Jupyter', 'Weights & Biases', 'DeepSpeed'],
      bench: [
        { label: _('H100 80 GB (fine-tune 13B, LoRA)', 'H100 80 GB (13B LoRA fine-tune)'), pct: 100, note: '3 h 40 min' },
        { label: 'L40S 48 GB', pct: 38, note: '9 h 50 min' },
        { label: _('A100 40 GB (starší generace)', 'A100 40 GB (previous generation)'), pct: 54, note: '6 h 55 min' }
      ],
      benchNote: _('Fine-tuning modelu 13B na 2 mil. vzorcích, bf16, identická data a hyperparametry.', 'Fine-tuning a 13B model on 2M samples, bf16, identical data and hyperparameters.'),
      cases: [
        { t: _('Fine-tuning na firemních datech', 'Fine-tuning on company data'), d: _('Model se učí z interních dokumentů, které nesmí opustit firmu.', 'The model learns from internal documents that cannot leave the company.'), m: 'H100' },
        { t: _('Provoz vlastního modelu', 'Serving your own model'), d: _('vLLM na L40S, stabilní latence, cena známá dopředu.', 'vLLM on an L40S, steady latency, predictable cost.'), m: 'L40S' },
        { t: _('Výzkumný běh', 'Research run'), d: _('Osm karet na tři týdny, InfiniBand, po skončení nula nákladů.', 'Eight cards for three weeks over InfiniBand, then zero cost.'), m: 'H100 ×8' }
      ],
      faq: [
        [_('Jak dlouho čekám na kartu?', 'How long do I wait for a card?'), _('L40S okamžitě, H100 podle dostupnosti — aktuální počet volných karet je na této stránce.', 'L40S immediately, H100 subject to availability — the live free-card count is on this page.')],
        [_('Můžu si přinést vlastní obraz?', 'Can I bring my own image?'), _('Ano, Docker i vlastní ISO. Podporujeme i váš privátní registr.', 'Yes, Docker or your own ISO. We support your private registry too.')],
        [_('Účtujete i vypnutou instanci?', 'Do you bill a stopped instance?'), _('Ne, jen úložiště s checkpointy. GPU se přestane účtovat okamžitě.', 'No, only the storage holding checkpoints. GPU billing stops immediately.')],
        [_('Kde přesně data leží?', 'Where exactly does data live?'), _('V lokalitě Praha, s replikou v Brně, pokud si ji zapnete. Nikdy mimo ČR bez vašeho pokynu.', 'In the Prague site, with a Brno replica if you enable it. Never outside the country without your instruction.')],
        [_('Umíte poradit s modelem?', 'Can you advise on the model?'), _('Ano. K rezervaci nad měsíc patří dvě hodiny konzultace s naším AI týmem.', 'Yes. Reservations over a month include two hours with our AI team.')]
      ]
    }),

    'inference': P({
      cmpTitle: _('Propustnost a limity endpointu', 'Endpoint throughput and limits'),
      cat: _('AI a data', 'AI and data'), crumb: 'Inference API',
      kicker: _('OpenAI-kompatibilní · EU · bez logování promptů', 'OpenAI-compatible · EU · no prompt logging'),
      title: _('Váš model za HTTPS endpointem do deseti minut', 'Your model behind an HTTPS endpoint in ten minutes'),
      lead: _('Nahrajete model, my ho vystavíme jako OpenAI-kompatibilní API s autoscalingem, měřením tokenů a limity na klíč. Prompty nelogujeme a na nich netrénujeme.', 'Upload a model and we expose it as an OpenAI-compatible API with autoscaling, token metering and per-key limits. We do not log prompts and never train on them.'),
      kpis: [['180 ms', _('medián první token', 'median time to first token')], ['10 min', _('od nahrání k endpointu', 'from upload to endpoint')], ['0', _('logovaných promptů', 'prompts logged')]],
      chips: [_('OpenAI kompatibilní', 'OpenAI-compatible'), 'vLLM', _('Autoscaling na nulu', 'Scale to zero'), _('Limity na klíč', 'Per-key limits'), _('Měření tokenů', 'Token metering'), 'Streaming'],
      plans: [
        { name: 'Dev', tag: '', price: 490, specs: [_('1 model', '1 model'), _('2 mil. tokenů / měs.', '2M tokens / month'), _('Autoscaling na nulu', 'Scale to zero'), _('3 API klíče', '3 API keys'), _('Sdílená L40S', 'Shared L40S'), _('Komunitní podpora', 'Community support')] },
        { name: 'Production', tag: _('Doporučeno', 'Recommended'), price: 3900, specs: [_('5 modelů', '5 models'), _('50 mil. tokenů / měs.', '50M tokens / month'), _('Vyhrazená L40S', 'Dedicated L40S'), _('Neomezeně klíčů', 'Unlimited keys'), _('Metriky a alerty', 'Metrics and alerts'), 'SLA 99,95 %'] },
        { name: 'Dedicated', tag: '', price: 24900, specs: [_('Neomezeně modelů', 'Unlimited models'), _('Bez limitu tokenů', 'No token cap'), _('Vyhrazená H100', 'Dedicated H100'), _('Privátní síť', 'Private network'), _('Vlastní domény endpointů', 'Custom endpoint domains'), 'SLA 99,99 %'] }
      ],
      cmp: {
        cols: ['Dev', 'Production', 'Dedicated'],
        rows: [
          [_('Modely', 'Models'), '1', '5', _('Neomezeně', 'Unlimited')],
          [_('Tokeny v ceně', 'Included tokens'), '2 mil.', '50 mil.', _('Bez limitu', 'Uncapped')],
          [_('Hardware', 'Hardware'), _('Sdílená L40S', 'Shared L40S'), _('Vyhrazená L40S', 'Dedicated L40S'), _('Vyhrazená H100', 'Dedicated H100')],
          [_('Autoscaling', 'Autoscaling'), _('Na nulu', 'To zero'), _('1–8 replik', '1–8 replicas'), _('Podle rezervace', 'Per reservation')],
          [_('Rychlostní limity', 'Rate limits'), '20 req/s', '200 req/s', _('Na míru', 'Bespoke')],
          [_('Logování promptů', 'Prompt logging'), _('Nikdy', 'Never'), _('Nikdy', 'Never'), _('Nikdy', 'Never')],
          ['SLA', '—', '99,95 %', '99,99 %']
        ]
      },
      feats: [
        ['01', _('Kompatibilní s tím, co už máte', 'Compatible with what you already have'), _('Změníte base_url a klíč. Knihovny pro OpenAI fungují bez dalších úprav.', 'Change base_url and the key. OpenAI client libraries work unchanged.')],
        ['02', _('Autoscaling včetně nuly', 'Autoscaling including zero'), _('Když nikdo neposílá dotazy, neplatíte GPU. Náběh je pod deset sekund.', 'When nobody queries, you pay no GPU. Cold start is under ten seconds.')],
        ['03', _('Limity a rozpočty na klíč', 'Per-key limits and budgets'), _('Každý tým i každá aplikace má vlastní klíč, strop tokenů a alert.', 'Every team and app gets its own key, token ceiling and alert.')],
        ['04', _('Prompty nelogujeme', 'We do not log prompts'), _('Účtujeme počty tokenů, ne obsah. Zpracování dat máte popsané ve smlouvě.', 'We meter token counts, not content. Data processing is spelled out in the contract.')],
        ['05', _('Metriky, které něco říkají', 'Metrics that mean something'), _('Latence p50 i p99, tokeny na endpoint, chyby podle typu, export do Prometheu.', 'p50 and p99 latency, tokens per endpoint, errors by class, Prometheus export.')],
        ['06', _('Vedle vaší aplikace', 'Next to your application'), _('Endpoint v privátní síti se vaším VPS, bez cesty přes veřejný internet.', 'The endpoint sits on a private network with your VPS, never crossing the public internet.')]
      ],
      tech: ['vLLM', 'OpenAI SDK', 'LangChain', 'LlamaIndex', 'Hugging Face', 'Llama', 'Mistral', 'Qwen', 'Prometheus', 'n8n'],
      bench: [
        { label: _('Onhost Praha (vLLM, L40S)', 'Onhost Prague (vLLM, L40S)'), pct: 100, note: _('180 ms první token', '180 ms first token') },
        { label: _('Globální API z Frankfurtu', 'Global API from Frankfurt'), pct: 55, note: '330 ms' },
        { label: _('Globální API z USA', 'Global API from the US'), pct: 26, note: '690 ms' }
      ],
      benchNote: _('Medián doby do prvního tokenu, model 8B, dotaz 512 tokenů, měřeno z Prahy.', 'Median time to first token, 8B model, 512-token prompt, measured from Prague.'),
      cases: [
        { t: _('Interní asistent nad dokumenty', 'Internal document assistant'), d: _('RAG nad firemními daty, prompty nikam neodcházejí.', 'RAG over company data with prompts that never leave.'), m: 'Production' },
        { t: _('Funkce v produktu', 'A feature in your product'), d: _('Sumarizace nebo klasifikace s předvídatelnou cenou za tisíc requestů.', 'Summarisation or classification with a predictable cost per thousand requests.'), m: 'Production' },
        { t: _('Automatizace procesů', 'Process automation'), d: _('n8n workflow, které čte tickety, třídí je a navrhuje odpověď.', 'An n8n workflow that reads tickets, triages them and drafts replies.'), m: 'Dev' }
      ],
      faq: [
        [_('Jaké modely podporujete?', 'Which models do you support?'), _('Cokoli, co běží ve vLLM nebo Ollamě — Llama, Mistral, Qwen, Gemma i vlastní fine-tune.', 'Anything that runs on vLLM or Ollama — Llama, Mistral, Qwen, Gemma or your own fine-tune.')],
        [_('Můžu použít vlastní fine-tune?', 'Can I use my own fine-tune?'), _('Ano, nahrajete adaptér nebo celé váhy. Verzování a rollback endpointu jsou v panelu.', 'Yes, upload an adapter or full weights. Endpoint versioning and rollback are in the panel.')],
        [_('Jak se účtuje?', 'How is it billed?'), _('Paušál za tarif plus tokeny nad limit. Cenu za milion tokenů vidíte v panelu předem.', 'A plan fee plus tokens over the allowance. The per-million-token price is shown up front.')],
        [_('Je to vhodné pro citlivá data?', 'Is it suitable for sensitive data?'), _('Ano, včetně zdravotnických a finančních. Data zůstávají v ČR, prompty nelogujeme, DPA je součástí.', 'Yes, including healthcare and finance. Data stays in the country, prompts are not logged, a DPA is included.')],
        [_('Zvládne to špičku?', 'Will it handle a spike?'), _('Production škáluje do osmi replik automaticky, Dedicated podle rezervovaných karet.', 'Production autoscales to eight replicas; Dedicated scales with your reserved cards.')]
      ]
    }),

    'vectordb': P({
      cmpTitle: _('Kapacita indexu a rychlost dotazů', 'Index capacity and query speed'),
      cat: _('AI a data', 'AI and data'), crumb: _('Vektorová databáze', 'Vector database'),
      kicker: 'pgvector · Qdrant · HNSW',
      title: _('Vektory vedle vašich dat, ne v cizí cloudové službě', 'Vectors next to your data, not in someone else\u2019s cloud'),
      lead: _('pgvector v PostgreSQL nebo samostatný Qdrant, oboje spravované, se zálohami a metrikami. Embeddingy můžete počítat na našich GPU a nikam je neposílat.', 'pgvector inside PostgreSQL or standalone Qdrant, both managed, with backups and metrics. Compute embeddings on our GPUs and never ship them anywhere.'),
      kpis: [['14 ms', _('p95 dotaz nad 5 mil. vektorů', 'p95 query over 5M vectors')], ['98,6 %', 'recall@10'], ['0', _('odchodových poplatků', 'egress fees')]],
      chips: ['pgvector', 'Qdrant', 'HNSW', _('Hybridní hledání', 'Hybrid search'), _('Filtrování metadat', 'Metadata filtering'), _('Zálohy a PITR', 'Backups and PITR')],
      plans: [
        { name: 'Vector Start', tag: '', price: 690, specs: [_('do 1 mil. vektorů', 'up to 1M vectors'), '2 vCPU / 8 GB', '80 GB NVMe', 'pgvector nebo Qdrant', _('Denní zálohy', 'Daily backups'), _('Panel a metriky', 'Panel and metrics')] },
        { name: 'Vector Pro', tag: _('Doporučeno', 'Recommended'), price: 2490, specs: [_('do 20 mil. vektorů', 'up to 20M vectors'), '8 vCPU / 32 GB', '500 GB NVMe', _('Replika pro čtení', 'Read replica'), 'PITR 30 dní', _('Hybridní hledání', 'Hybrid search')] },
        { name: 'Vector Scale', tag: '', price: 8900, specs: [_('nad 100 mil. vektorů', '100M+ vectors'), '32 vCPU / 128 GB', '2 TB NVMe', _('Sharding a 3 uzly', 'Sharding, 3 nodes'), _('GPU indexace', 'GPU indexing'), 'SLA 99,99 %'] }
      ],
      cmp: {
        cols: ['Vector Start', 'Vector Pro', 'Vector Scale'],
        rows: [
          [_('Vektory', 'Vectors'), '1 mil.', '20 mil.', '100 mil.+'],
          [_('Výkon', 'Compute'), '2 vCPU / 8 GB', '8 vCPU / 32 GB', '32 vCPU / 128 GB'],
          [_('Index', 'Index'), 'HNSW', 'HNSW + IVF', _('HNSW, sharding', 'HNSW, sharded')],
          [_('Repliky', 'Replicas'), '—', '1', _('Až 5', 'Up to 5')],
          [_('Hybridní hledání', 'Hybrid search'), _('Základní', 'Basic'), '✓', '✓'],
          ['PITR', _('7 dní', '7 days'), _('30 dní', '30 days'), _('90 dní', '90 days')],
          [_('GPU indexace', 'GPU indexing'), '—', _('Na vyžádání', 'On request'), '✓']
        ]
      },
      feats: [
        ['01', _('Jedna databáze místo dvou', 'One database instead of two'), _('pgvector drží vektory vedle relačních dat — jeden backup, jedna transakce, jeden systém.', 'pgvector keeps vectors beside relational data — one backup, one transaction, one system.')],
        ['02', _('Qdrant, když potřebujete škálovat', 'Qdrant when you need scale'), _('Sharding, filtrování a kolekce na miliardy vektorů, spravované námi.', 'Sharding, filtering and collections for billions of vectors, managed by us.')],
        ['03', _('Embeddingy u nás', 'Embeddings computed here'), _('Vypočítáte je na našich GPU a rovnou uložíte. Data neopustí Prahu.', 'Compute them on our GPUs and store them directly. Data never leaves Prague.')],
        ['04', _('Hybridní hledání', 'Hybrid search'), _('Kombinace klíčových slov a vektorů s vlastní vahou, včetně reranku.', 'Keyword and vector scoring with your own weighting, reranking included.')],
        ['05', _('Zálohy a obnova na sekundu', 'Backups and second-level restore'), _('Point-in-time obnova platí i pro indexy, nemusíte je počítat znovu.', 'Point-in-time recovery covers indexes too — no need to rebuild them.')],
        ['06', _('Bez odchodových poplatků', 'No egress fees'), _('Dump kolekce si stáhnete kdykoli. Nedržíme vaše embeddingy jako rukojmí.', 'Download a collection dump anytime. We do not hold your embeddings hostage.')]
      ],
      tech: ['PostgreSQL', 'pgvector', 'Qdrant', 'LangChain', 'LlamaIndex', 'Haystack', 'sentence-transformers', 'OpenAI SDK'],
      bench: [
        { label: _('Vector Pro (HNSW, NVMe)', 'Vector Pro (HNSW, NVMe)'), pct: 100, note: '14 ms p95' },
        { label: _('Sdílená cloudová vektorová služba', 'Shared cloud vector service'), pct: 47, note: '30 ms p95' },
        { label: _('pgvector bez ladění indexu', 'pgvector with untuned index'), pct: 22, note: '64 ms p95' }
      ],
      benchNote: _('5 mil. vektorů dimenze 1024, recall@10 nad 98 %, 50 souběžných dotazů.', '5M vectors at 1024 dimensions, recall@10 above 98%, 50 concurrent queries.'),
      cases: [
        { t: _('Vyhledávání v dokumentaci', 'Search over documentation'), d: _('Firemní znalostní báze s filtrováním podle oprávnění.', 'A company knowledge base with permission-aware filtering.'), m: 'Vector Start' },
        { t: _('Doporučování produktů', 'Product recommendations'), d: _('Podobné produkty a personalizace v e-shopu, latence pod 20 ms.', 'Similar items and personalisation in a store, latency under 20 ms.'), m: 'Vector Pro' },
        { t: _('RAG pro zákaznickou podporu', 'RAG for customer support'), d: _('Tickety, dokumentace a e-maily v jednom indexu vedle Inference API.', 'Tickets, docs and emails in one index next to the Inference API.'), m: 'Vector Pro' }
      ],
      faq: [
        [_('pgvector, nebo Qdrant?', 'pgvector or Qdrant?'), _('Do deseti milionů vektorů obvykle pgvector — méně systémů. Nad to Qdrant kvůli shardingu.', 'Under ten million vectors, usually pgvector — fewer moving parts. Above that, Qdrant for sharding.')],
        [_('Umíte přepočítat index při změně modelu?', 'Can you reindex when the model changes?'), _('Ano, přepočet na GPU spustíme na pozadí a přepneme až po dokončení.', 'Yes, we run the GPU reindex in the background and switch over when it completes.')],
        [_('Podporujete filtrování podle metadat?', 'Do you support metadata filtering?'), _('Ano, včetně kombinace s právy uživatele, takže výsledky respektují přístupy.', 'Yes, including combining with user permissions so results respect access rights.')],
        [_('Jak zálohujete vektory?', 'How are vectors backed up?'), _('Stejně jako databázi: snapshoty, PITR a offsite kopie, včetně indexů.', 'Like any database: snapshots, PITR and an offsite copy, indexes included.')],
        [_('Můžu to provozovat u sebe?', 'Can I run it on my own hardware?'), _('Ano, dodáme stejnou konfiguraci na váš VPS nebo dedikovaný server jako Enterprise službu.', 'Yes, we deliver the same configuration on your VPS or dedicated server as an Enterprise service.')]
      ]
    }),

    'devhosting': P({
      cmpTitle: _('Prostředí pro vývoj podle velikosti týmu', 'Dev environments by team size'),
      cat: _('Pro vývojáře', 'For developers'), crumb: 'Devhosting',
      kicker: _('Node · Python · Go · Docker', 'Node · Python · Go · Docker'),
      title: _('Deploy z gitu, ne z FTP klienta', 'Deploy from git, not from an FTP client'),
      lead: _('Push do main a aplikace je za dvě minuty venku, včetně certifikátu, migrací a health checku. Preview prostředí ke každému pull requestu automaticky.', 'Push to main and the app is live in two minutes, certificate, migrations and health check included. A preview environment for every pull request, automatically.'),
      kpis: [['1 min 50 s', _('medián doby deploye', 'median deploy time')], ['0', _('výpadků při deployi', 'downtime on deploy')], ['30 s', _('rollback na předchozí verzi', 'rollback to previous build')]],
      chips: ['Node 22', 'Python 3.13', 'Go', 'PHP 8.4', 'Docker', _('Preview prostředí', 'Preview environments'), _('Rollback', 'Rollback'), _('Cron a workery', 'Cron and workers')],
      plans: [
        { name: 'Dev Hobby', tag: '', price: 290, specs: [_('2 aplikace', '2 apps'), '1 vCPU / 2 GB', _('1 preview prostředí', '1 preview environment'), _('Logy 7 dní', '7-day logs'), _('Certifikát zdarma', 'Free certificate'), _('Deploy z GitHubu', 'Deploy from GitHub')] },
        { name: 'Dev Team', tag: _('Nejoblíbenější', 'Most popular'), price: 990, specs: [_('10 aplikací', '10 apps'), '4 vCPU / 8 GB', _('Preview ke každému PR', 'Preview per PR'), _('Logy 30 dní', '30-day logs'), _('Workery a cron', 'Workers and cron'), _('Role pro tým', 'Team roles')] },
        { name: 'Dev Scale', tag: '', price: 2990, specs: [_('Neomezeně aplikací', 'Unlimited apps'), '16 vCPU / 32 GB', _('Autoscaling replik', 'Replica autoscaling'), _('Logy 90 dní a export', '90-day logs with export'), _('Privátní síť', 'Private network'), 'SLA 99,95 %'] }
      ],
      cmp: {
        cols: ['Dev Hobby', 'Dev Team', 'Dev Scale'],
        rows: [
          [_('Aplikace', 'Apps'), '2', '10', _('Neomezeně', 'Unlimited')],
          [_('Výkon', 'Compute'), '1 vCPU / 2 GB', '4 vCPU / 8 GB', '16 vCPU / 32 GB'],
          [_('Preview prostředí', 'Preview environments'), '1', _('Ke každému PR', 'Per PR'), _('Ke každému PR', 'Per PR')],
          [_('Workery a cron', 'Workers and cron'), '—', '✓', '✓'],
          [_('Autoscaling', 'Autoscaling'), '—', _('2 repliky', '2 replicas'), _('Až 12 replik', 'Up to 12 replicas')],
          [_('Logy', 'Logs'), _('7 dní', '7 days'), _('30 dní', '30 days'), _('90 dní + export', '90 days + export')],
          [_('Privátní síť k databázi', 'Private link to database'), '—', '✓', '✓'],
          ['SLA', '—', '99,9 %', '99,95 %']
        ]
      },
      feats: [
        ['01', _('Deploy bez výpadku', 'Zero-downtime deploys'), _('Nová verze naběhne, projde health checkem, pak se přepne provoz. Když spadne, zůstane stará.', 'The new build boots, passes health checks, then traffic switches. If it fails, the old one stays.')],
        ['02', _('Preview ke každému PR', 'A preview per pull request'), _('Vlastní URL, vlastní databáze ze snapshotu, po sloučení se samo uklidí.', 'Its own URL, its own database from a snapshot, cleaned up after merge.')],
        ['03', _('Logy a metriky bez nastavování', 'Logs and metrics without setup'), _('Strukturované logy, latence a chybovost hned po prvním deployi.', 'Structured logs, latency and error rate from the first deploy.')],
        ['04', _('Tajemství pod kontrolou', 'Secrets under control'), _('Šifrované proměnné podle prostředí, historie změn, přístup podle role.', 'Encrypted per-environment variables, change history, role-based access.')],
        ['05', _('Workery, cron a fronty', 'Workers, cron and queues'), _('Dlouhé úlohy mimo webový proces, s vlastním škálováním.', 'Long-running jobs outside the web process, scaled separately.')],
        ['06', _('Bez zamčení do platformy', 'No platform lock-in'), _('Standardní Dockerfile. Stejný obraz spustíte na svém VPS bez úprav.', 'A standard Dockerfile. The same image runs on your own VPS unchanged.')]
      ],
      tech: ['Next.js', 'Nuxt', 'Django', 'FastAPI', 'Laravel', 'NestJS', 'Go', 'Rails', 'Docker', 'GitHub Actions'],
      bench: [
        { label: _('Onhost devhosting (Next.js)', 'Onhost devhosting (Next.js)'), pct: 100, note: '1 min 50 s' },
        { label: _('Vlastní CI + rsync na VPS', 'Own CI + rsync to a VPS'), pct: 32, note: '5 min 40 s' },
        { label: _('Ruční deploy přes SSH', 'Manual SSH deploy'), pct: 12, note: '15 min +' }
      ],
      benchNote: _('Doba od push do main po produkční provoz, Next.js aplikace se 120 stránkami, medián 50 deployů.', 'Time from push to main until live, a Next.js app with 120 pages, median of 50 deploys.'),
      cases: [
        { t: _('SaaS ve dvou lidech', 'Two-person SaaS'), d: _('Frontend, API a worker v jednom projektu, preview pro každou feature.', 'Frontend, API and worker in one project, a preview per feature.'), m: 'Dev Team' },
        { t: _('Agentura s klientskými projekty', 'Agency with client projects'), d: _('Deset aplikací, oddělená prostředí, klient vidí jen svoje.', 'Ten apps, separated environments, clients see only theirs.'), m: 'Dev Team' },
        { t: _('Interní nástroje', 'Internal tools'), d: _('Admin, reporty a integrace na privátní síti bez veřejné IP.', 'Admin, reporting and integrations on a private network with no public IP.'), m: 'Dev Scale' }
      ],
      faq: [
        [_('Podporujete monorepo?', 'Do you support monorepos?'), _('Ano, více aplikací z jednoho repozitáře s vlastními build kroky a cestami.', 'Yes, several apps from one repository with their own build steps and paths.')],
        [_('Můžu použít vlastní Dockerfile?', 'Can I use my own Dockerfile?'), _('Ano, nebo nechte buildpack. Obojí funguje, obojí umí cache.', 'Yes, or let the buildpack handle it. Both work, both cache.')],
        [_('Jak se připojím k databázi?', 'How do I connect to a database?'), _('Přes privátní síť k naší managed databázi, nebo k vlastní instanci na VPS.', 'Over the private network to our managed database, or to your own VPS instance.')],
        [_('Co s cron úlohami?', 'What about cron jobs?'), _('Definujete je v konfiguraci projektu, běží jako oddělené procesy s vlastními logy.', 'Define them in the project config; they run as separate processes with their own logs.')],
        [_('Umíte deploy z GitLabu nebo Bitbucketu?', 'Can you deploy from GitLab or Bitbucket?'), _('Ano, i z našeho Git hostingu nebo přes webhook z libovolného CI.', 'Yes, including our own Git hosting or a webhook from any CI.')]
      ]
    }),

    'git': P({
      cmpTitle: _('Repozitáře, uživatelé a runnery', 'Repositories, seats and runners'),
      cat: _('Pro vývojáře', 'For developers'), crumb: _('Git hosting', 'Git hosting'),
      kicker: _('Privátní repozitáře · EU · bez limitu uživatelů', 'Private repos · EU · no seat limits'),
      title: _('Git v Evropě, bez placení za každého člena týmu', 'Git in Europe, without paying per seat'),
      lead: _('Neomezené privátní repozitáře, code review, protected branches a runnery pro CI. Platíte za prostor a výkon, ne za lidi.', 'Unlimited private repositories, code review, protected branches and CI runners. You pay for storage and compute, not for people.'),
      kpis: [['0 Kč', _('za dalšího člena týmu', 'per extra team member')], ['4 ms', _('odezva git operací', 'git operation latency')], ['100 %', _('data v EU', 'data in the EU')]],
      chips: ['Git LFS', _('Chráněné větve', 'Protected branches'), _('Code review', 'Code review'), _('Podepsané commity', 'Signed commits'), 'Webhooky', _('Zrcadlení', 'Mirroring')],
      plans: [
        { name: 'Git Solo', tag: '', price: 149, specs: [_('Neomezeně repozitářů', 'Unlimited repos'), '20 GB', _('3 uživatelé', '3 users'), 'Git LFS 5 GB', _('Webhooky a API', 'Webhooks and API'), _('Zrcadlení z GitHubu', 'Mirroring from GitHub')] },
        { name: 'Git Team', tag: _('Nejoblíbenější', 'Most popular'), price: 490, specs: [_('Neomezeně repozitářů', 'Unlimited repos'), '200 GB', _('Neomezeně uživatelů', 'Unlimited users'), 'Git LFS 50 GB', _('Chráněné větve a review', 'Protected branches and review'), _('SSO', 'SSO')] },
        { name: 'Git Enterprise', tag: '', price: 1490, specs: [_('Neomezeně repozitářů', 'Unlimited repos'), '1 TB', _('Neomezeně uživatelů', 'Unlimited users'), 'Git LFS 500 GB', _('Audit log a SCIM', 'Audit log and SCIM'), 'SLA 99,95 %'] }
      ],
      cmp: {
        cols: ['Git Solo', 'Git Team', 'Git Enterprise'],
        rows: [
          [_('Uživatelé', 'Users'), '3', _('Neomezeně', 'Unlimited'), _('Neomezeně', 'Unlimited')],
          [_('Prostor', 'Storage'), '20 GB', '200 GB', '1 TB'],
          ['Git LFS', '5 GB', '50 GB', '500 GB'],
          [_('Chráněné větve', 'Protected branches'), _('Základní', 'Basic'), '✓', '✓'],
          [_('Povinné review', 'Required reviews'), '—', '✓', '✓'],
          ['SSO / SAML', '—', 'SSO', 'SAML + SCIM'],
          [_('Audit log', 'Audit log'), '—', _('90 dní', '90 days'), _('2 roky', '2 years')],
          [_('CI runnery', 'CI runners'), _('Doplněk', 'Add-on'), _('2 v ceně', '2 included'), _('6 v ceně', '6 included')]
        ]
      },
      feats: [
        ['01', _('Neplatíte za lidi', 'You do not pay per person'), _('Přidání juniora nebo externisty nezvedne fakturu. Účtujeme prostor a runnery.', 'Adding a junior or a contractor does not raise the invoice. We bill storage and runners.')],
        ['02', _('Code review, co stačí', 'Code review that is enough'), _('Komentáře v diffu, povinní schvalovatelé, blokování merge při padajícím CI.', 'Inline comments, required approvers, merge blocked on failing CI.')],
        ['03', _('Zrcadlení tam i zpět', 'Two-way mirroring'), _('Držte GitHub jako veřejné okno a náš repozitář jako pravdu — nebo naopak.', 'Keep GitHub as the public window and our repo as the source of truth — or the reverse.')],
        ['04', _('Runnery vedle repozitáře', 'Runners next to the repo'), _('CI běží ve stejné lokalitě, checkout velkého repozitáře trvá sekundy.', 'CI runs in the same location; checking out a big repo takes seconds.')],
        ['05', _('Podepsané commity a tagy', 'Signed commits and tags'), _('Vynutíte GPG nebo SSH podpisy pro chráněné větve a release.', 'Enforce GPG or SSH signatures on protected branches and releases.')],
        ['06', _('Audit a odchod bez drama', 'Audit and a clean exit'), _('Kompletní audit log a export celé organizace jedním příkazem.', 'A full audit log and a one-command export of the whole organisation.')]
      ],
      tech: ['Git', 'Git LFS', 'GitHub mirror', 'GitLab import', 'Gitea', 'SSH', 'GPG', 'Renovate', 'Semantic release'],
      bench: [
        { label: _('Clone 2 GB repozitáře (Praha)', 'Clone a 2 GB repo (Prague)'), pct: 100, note: '18 s' },
        { label: _('Globální git hosting z EU', 'Global git hosting from the EU'), pct: 43, note: '42 s' },
        { label: _('Globální git hosting z USA', 'Global git hosting from the US'), pct: 21, note: '86 s' }
      ],
      benchNote: _('Clone repozitáře 2 GB včetně LFS objektů, linka 1 Gbit/s, medián deseti běhů.', 'Cloning a 2 GB repo including LFS objects over a 1 Gbit/s link, median of ten runs.'),
      cases: [
        { t: _('Tým, který nechce platit za sedadla', 'A team that will not pay per seat'), d: _('Patnáct lidí, dvacet externistů, faktura se nezmění.', 'Fifteen people, twenty contractors, the invoice stays the same.'), m: 'Git Team' },
        { t: _('Kód, který nesmí opustit EU', 'Code that cannot leave the EU'), d: _('Veřejná správa a zdravotnictví s auditní stopou.', 'Public sector and healthcare with an audit trail.'), m: 'Git Enterprise' },
        { t: _('Velká binární data', 'Large binary assets'), d: _('Herní assety nebo CAD v LFS vedle CI runnerů.', 'Game assets or CAD in LFS next to the CI runners.'), m: 'Git Team' }
      ],
      faq: [
        [_('Přeneseme repozitáře z GitHubu?', 'Can we import from GitHub?'), _('Ano, včetně issues, pull requestů a nastavení. Import spustíte sami z panelu.', 'Yes, including issues, pull requests and settings. Start the import yourself from the panel.')],
        [_('Podporujete GitHub Actions syntaxi?', 'Do you support GitHub Actions syntax?'), _('Naše CI umí kompatibilní podmnožinu. Složitější workflow doladíme s vámi.', 'Our CI supports a compatible subset. We will help adapt more complex workflows.')],
        [_('Jak řešíte přístupy?', 'How is access managed?'), _('Role na repozitář i na skupinu, SSO na Team, SAML a SCIM na Enterprise.', 'Per-repo and per-group roles, SSO on Team, SAML and SCIM on Enterprise.')],
        [_('Je tam limit na velikost repozitáře?', 'Is there a repo size limit?'), _('Jen celkový prostor tarifu. Jednotlivý repozitář může být klidně 100 GB.', 'Only the plan\u2019s total storage. A single repository can be 100 GB.')],
        [_('Můžeme si to hostovat sami?', 'Can we self-host it?'), _('Ano, jako Enterprise instalaci na vašem serveru, s naší podporou a updaty.', 'Yes, as an Enterprise install on your own server with our support and updates.')]
      ]
    }),

    'ci': P({
      cmpTitle: _('Runnery, cache a souběžné buildy', 'Runners, cache and parallel builds'),
      cat: _('Pro vývojáře', 'For developers'), crumb: _('CI/CD runnery', 'CI/CD runners'),
      kicker: _('EPYC · cache · minuty se nepočítají', 'EPYC · cache · minutes not metered'),
      title: _('Buildy, u kterých nekoukáte na minuty', 'Builds where you stop watching the clock'),
      lead: _('Dedikované runnery na EPYC s NVMe cache. Neúčtujeme minuty — platíte za runner, který můžete vytížit na sto procent, dvacet čtyři hodin denně.', 'Dedicated EPYC runners with NVMe cache. We do not meter minutes — you pay for a runner you can saturate a hundred percent of the time.'),
      kpis: [['3,4×', _('rychlejší než hosted runner', 'faster than a hosted runner')], ['0', _('účtovaných minut', 'metered minutes')], ['92 %', _('zásah cache', 'cache hit rate')]],
      chips: ['AMD EPYC', 'NVMe cache', 'Docker-in-Docker', _('Matice buildů', 'Build matrices'), _('Vlastní obrazy', 'Custom images'), _('Podepisování artefaktů', 'Artifact signing')],
      config: true, cfgBase: 190, cfgCpu: 74, cfgRam: 28, cfgDisk: 22,
      plans: [
        { name: 'Runner S', tag: '', price: 390, specs: ['4 vCPU / 8 GB', '100 GB NVMe cache', _('1 souběžný job', '1 concurrent job'), _('Bez limitu minut', 'No minute cap'), _('Docker v runneru', 'Docker in runner'), _('Logy 30 dní', '30-day logs')] },
        { name: 'Runner M', tag: _('Doporučeno', 'Recommended'), price: 1190, specs: ['8 vCPU / 32 GB', '400 GB NVMe cache', _('4 souběžné joby', '4 concurrent jobs'), _('Sdílená cache mezi joby', 'Shared cache across jobs'), _('Vlastní obrazy', 'Custom images'), _('Artefakty 90 dní', '90-day artifacts')] },
        { name: 'Runner XL', tag: '', price: 3490, specs: ['32 vCPU / 128 GB', '2 TB NVMe cache', _('16 souběžných jobů', '16 concurrent jobs'), _('GPU runner na vyžádání', 'GPU runner on request'), _('Privátní síť', 'Private network'), 'SLA 99,95 %'] }
      ],
      cmp: {
        cols: ['Runner S', 'Runner M', 'Runner XL'],
        rows: [
          [_('Výkon', 'Compute'), '4 vCPU / 8 GB', '8 vCPU / 32 GB', '32 vCPU / 128 GB'],
          [_('Souběžné joby', 'Concurrent jobs'), '1', '4', '16'],
          [_('NVMe cache', 'NVMe cache'), '100 GB', '400 GB', '2 TB'],
          [_('Účtované minuty', 'Metered minutes'), _('Žádné', 'None'), _('Žádné', 'None'), _('Žádné', 'None')],
          [_('Vlastní obrazy', 'Custom images'), '—', '✓', '✓'],
          [_('GPU runner', 'GPU runner'), '—', _('Doplněk', 'Add-on'), _('Doplněk', 'Add-on')],
          [_('Artefakty', 'Artifacts'), _('30 dní', '30 days'), _('90 dní', '90 days'), _('365 dní', '365 days')],
          ['SLA', '—', '99,9 %', '99,95 %']
        ]
      },
      feats: [
        ['01', _('Minuty nehlídáte', 'Nobody watches the minutes'), _('Runner je váš. Vytížíte ho na sto procent a cena se nezmění.', 'The runner is yours. Saturate it and the price stays the same.')],
        ['02', _('Cache, která opravdu funguje', 'Cache that actually helps'), _('NVMe cache pro node_modules, Docker layery i Gradle — přetrvává mezi joby.', 'NVMe cache for node_modules, Docker layers and Gradle — it persists between jobs.')],
        ['03', _('Vedle vašeho registru', 'Next to your registry'), _('Runner, registr obrazů a deploy target v jedné lokalitě. Bez čekání na síť.', 'Runner, image registry and deploy target in one location. No network wait.')],
        ['04', _('Vlastní obrazy runneru', 'Your own runner images'), _('Nemusíte v každém buildu instalovat stejné nástroje. Připravíte obraz jednou.', 'Stop installing the same tools every build. Bake the image once.')],
        ['05', _('Bezpečné tajemství', 'Secrets done right'), _('Šifrované proměnné, maskování v logu, izolace mezi joby a projekty.', 'Encrypted variables, log masking, isolation between jobs and projects.')],
        ['06', _('Podepsané artefakty', 'Signed artifacts'), _('Obrazy i balíčky podepisujeme, SBOM generujeme automaticky.', 'Images and packages are signed and an SBOM is generated automatically.')]
      ],
      tech: ['GitHub Actions', 'GitLab CI', 'Woodpecker', 'Drone', 'Docker Buildx', 'Trivy', 'Cosign', 'Playwright', 'Gradle', 'pnpm'],
      bench: [
        { label: _('Runner M (EPYC, NVMe cache)', 'Runner M (EPYC, NVMe cache)'), pct: 100, note: '2 min 10 s' },
        { label: _('Hosted runner 2 vCPU', 'Hosted 2 vCPU runner'), pct: 29, note: '7 min 20 s' },
        { label: _('Runner bez cache', 'Runner without cache'), pct: 48, note: '4 min 30 s' }
      ],
      benchNote: _('Build monorepa (pnpm, 8 balíčků, Playwright testy), medián z padesáti běhů.', 'Monorepo build (pnpm, 8 packages, Playwright tests), median of fifty runs.'),
      cases: [
        { t: _('Monorepo s testy', 'Monorepo with tests'), d: _('Matice buildů, Playwright na čtyřech jobech současně.', 'Build matrices, Playwright across four concurrent jobs.'), m: 'Runner M' },
        { t: _('Docker obrazy pro produkci', 'Production Docker images'), d: _('Buildx s cache, podpis, scan a push do našeho registru.', 'Buildx with cache, signing, scanning and push to our registry.'), m: 'Runner M' },
        { t: _('Mobilní a nativní buildy', 'Mobile and native builds'), d: _('Dlouhé kompilace, kde hosted runner stojí víc než vlastní stroj.', 'Long compiles where a hosted runner costs more than your own machine.'), m: 'Runner XL' }
      ],
      faq: [
        [_('Funguje to s GitHub Actions?', 'Does it work with GitHub Actions?'), _('Ano, registrujeme se jako self-hosted runner. Workflow zůstane, jen změníte runs-on.', 'Yes, we register as a self-hosted runner. Keep the workflow and change runs-on.')],
        [_('Máte GitLab CI?', 'What about GitLab CI?'), _('Ano, i Woodpecker a Drone. Runner umí víc executorů.', 'Yes, plus Woodpecker and Drone. The runner supports several executors.')],
        [_('Jak je to s izolací?', 'How is isolation handled?'), _('Každý job běží v čisté VM nebo kontejneru, cache je oddělená podle projektu.', 'Every job runs in a clean VM or container, with cache separated per project.')],
        [_('Umíte GPU buildy?', 'Can you do GPU builds?'), _('Ano, L40S runner jako doplněk pro ML testy a kompilaci CUDA.', 'Yes, an L40S runner add-on for ML tests and CUDA compilation.')],
        [_('Co když potřebuji víc jobů jen občas?', 'What if I need more jobs only sometimes?'), _('Přidáte runner na hodiny. Účtujeme po hodinách, takže špička nestojí měsíční paušál.', 'Add a runner by the hour. Hourly billing means a spike does not cost a monthly fee.')]
      ]
    })
  };
}
