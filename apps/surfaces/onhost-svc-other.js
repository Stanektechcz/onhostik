// Onhost — data podstránek: gamehosting, firemní stránky, program a podpora.

export function otherPages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const reviewsGame = [
    { name: 'Jakub „kubaN“ Novotný', role: _('admin serveru CraftPub', 'admin of CraftPub'), text: _('Dvě stě hráčů, modpack o 340 modech, a TPS se drží na dvaceti. Restart naplánuju v panelu a nemusím u toho být.', 'Two hundred players, a 340-mod modpack, and TPS stays at twenty. I schedule restarts in the panel and do not have to be there.') },
    { name: 'Eliška Radová', role: _('komunitní manažerka, RustCZ', 'community manager, RustCZ'), text: _('Přišel DDoS na 300 Gbps a hráči si toho nevšimli. Přesně za to platíme.', 'A 300 Gbps DDoS hit and players never noticed. That is exactly what we pay for.') },
    { name: 'Martin Poláček', role: _('esport organizace Fenix', 'esports org Fenix'), text: _('Servery na turnaj zapneme na tři dny a pak zrušíme. Faktura odpovídá realitě.', 'We spin up tournament servers for three days, then destroy them. The invoice matches reality.') }
  ];

  const reviewsCorp = [
    { name: 'Ing. Hana Dvořáková', role: _('IT ředitelka, Vitrum Group', 'IT director, Vitrum Group'), text: _('Smlouva má jasné SLA a telefon, který někdo zvedne. To u předchozího dodavatele nebylo.', 'The contract has a clear SLA and a phone number somebody answers. Our previous vendor had neither.') },
    { name: 'Pavel Hrdina', role: _('jednatel, Zelená Energie Vysočina', 'director, Zelená Energie Vysočina'), text: _('Odvod tepla z jejich datacentra ohřívá dvě sousední budovy. Tohle je infrastruktura, jak má být.', 'Waste heat from their data centre warms two neighbouring buildings. This is how infrastructure should work.') },
    { name: 'Klára Šimková', role: _('vývojářka, členka komunity', 'developer, community member'), text: _('Přišla jsem na jeden meetup, odešla se stipendiem na server pro open-source projekt.', 'I came to one meetup and left with a grant for an open-source project server.') }
  ];

  const kbGame = [
    { title: _('Instalace modpacku z CurseForge', 'Installing a CurseForge modpack'), read: '5 min' },
    { title: _('Plánované restarty a zálohy', 'Scheduled restarts and backups'), read: '4 min' },
    { title: _('Práva pro moderátory v panelu', 'Moderator roles in the panel'), read: '3 min' },
    { title: _('Ladění TPS u velkých světů', 'Tuning TPS on large worlds'), read: '8 min' }
  ];

  const kbCorp = [
    { title: _('Jak čteme SLA a kredity', 'How our SLA and credits work'), read: '4 min' },
    { title: _('Zpracování osobních údajů a DPA', 'Data processing and the DPA'), read: '6 min' },
    { title: _('Bezpečnostní incident: co děláme', 'Security incidents: what we do'), read: '5 min' },
    { title: _('Fakturace, kredit a DPH', 'Invoicing, credit and VAT'), read: '3 min' }
  ];

  const slaGame = {
    title: _('Hráči nepoznají, že něco řešíme', 'Players never notice we are working'),
    lead: _('Anti-DDoS profily ladíme pro každou hru zvlášť, restarty plánujeme mimo prime time a zálohy běží každou hodinu.', 'Anti-DDoS profiles are tuned per game, restarts are scheduled outside prime time and backups run hourly.'),
    rows: [
      { k: '1,2 Tbps', v: _('kapacita mitigace', 'mitigation capacity') },
      { k: '< 3 s', v: _('detekce útoku', 'attack detection') },
      { k: '20 TPS', v: _('držíme i na velkých světech', 'held even on large worlds') },
      { k: _('30 dní', '30 days'), v: _('historie záloh', 'backup history') }
    ]
  };

  const slaCorp = {
    title: _('Co máme na papíře, to platí', 'What is on paper is what happens'),
    lead: _('Smluvní SLA, jmenovaný kontakt a kredity vyplácené automaticky. Žádné výjimky psané malým písmem.', 'Contractual SLA, a named contact and credits paid automatically. No exceptions in small print.'),
    rows: [
      { k: '99,99 %', v: _('dostupnost s pokutou', 'uptime with penalties') },
      { k: '15 min', v: _('reakce na P1 24/7', 'P1 response, 24/7') },
      { k: '6', v: _('lokalit v Evropě', 'European locations') },
      { k: '62 %', v: _('energie z obnovitelných zdrojů', 'energy from renewables') }
    ]
  };

  const migrationGame = {
    title: _('Server přeneseme se světem i pluginy', 'We move the server with world and plugins'),
    lead: _('Pošlete přístup nebo archiv světa, my nasadíme stejnou verzi, pluginy i konfiguraci. Hráči jen změní adresu — nebo ani to, když necháte doménu.', 'Send access or a world archive and we deploy the same version, plugins and configuration. Players just change the address — or not even that, if you keep the domain.'),
    steps: [
      { n: '01', t: _('Archiv světa', 'World archive'), d: _('Stáhneme svět, pluginy a konfigurace z původního hostingu.', 'We pull the world, plugins and configs from the old host.') },
      { n: '02', t: _('Nasazení a test', 'Deploy and test'), d: _('Server naběhne na testovací adrese, projdete ho s moderátory.', 'The server boots on a test address for you and your moderators to check.') },
      { n: '03', t: _('Přepnutí adresy', 'Address switch'), d: _('Poslední sync světa a přepnutí DNS mimo prime time.', 'Final world sync and a DNS switch outside prime time.') },
      { n: '04', t: _('Dohled první týden', 'A week of watching'), d: _('Sledujeme TPS, chyby a útoky a hlásíme se sami.', 'We watch TPS, errors and attacks, and report proactively.') }
    ]
  };

  const migrationCorp = {
    title: _('Přechod řídíme jako projekt', 'We run the switch as a project'),
    lead: _('Jmenovaný vedoucí, plán s termíny, rollback v každém kroku. U větších přechodů děláme i zkušební cvičení, aby se nic nedělo poprvé v produkci.', 'A named lead, a dated plan, a rollback at every step. For larger moves we run a rehearsal so nothing happens for the first time in production.'),
    steps: [
      { n: '01', t: _('Vstupní audit', 'Initial audit'), d: _('Sepíšeme systémy, závislosti, rizika a okna pro změny.', 'We document systems, dependencies, risks and change windows.') },
      { n: '02', t: _('Plán a smlouva', 'Plan and contract'), d: _('SLA, odpovědnosti, kontaktní matice a termíny.', 'SLA, responsibilities, a contact matrix and dates.') },
      { n: '03', t: _('Cvičný přechod', 'Rehearsal'), d: _('Zkoušíme na kopii, měříme dobu a ladíme kroky.', 'We rehearse on a copy, time it and refine the steps.') },
      { n: '04', t: _('Přechod a předání', 'Cutover and handover'), d: _('Ostrý přechod, monitoring a runbook pro váš tým.', 'The real cutover, monitoring and a runbook for your team.') }
    ]
  };

  const G = (o) => Object.assign({ config: false, roi: false, sla: slaGame, migration: migrationGame, reviews: reviewsGame, kb: kbGame }, o);
  const C = (o) => Object.assign({ config: false, roi: false, sla: slaCorp, migration: migrationCorp, reviews: reviewsCorp, kb: kbCorp }, o);

  return {
    'minecraft': G({
      cmpTitle: _('Kolik hráčů který tarif unese', 'How many players each plan holds'),
      plansTitle: cs ? 'Tarify podle počtu hráčů' : 'Plans by player count', plansNote: cs ? 'RAM navyšujeme za provozu, server můžete na měsíc uspat.' : 'RAM scales live and you can suspend the server for a month.',
      cat: _('Hry', 'Games'), crumb: 'Minecraft hosting',
      kicker: _('Modpacky · Bedrock · Anti-DDoS', 'Modpacks · Bedrock · Anti-DDoS'),
      title: _('Minecraft server, který drží 20 TPS i s modpackem', 'A Minecraft server that holds 20 TPS with a modpack'),
      lead: _('Ryzen s vysokými takty, NVMe a paměť, kterou nepřeprodáváme. Modpacky z CurseForge i Modrinth na dva kliky, vlastní panel, konzole a zálohy po hodinách.', 'High-clock Ryzen, NVMe and memory we do not oversell. CurseForge and Modrinth modpacks in two clicks, your own panel, console and hourly backups.'),
      kpis: [['20 TPS', _('i na světě 40 GB', 'even on a 40 GB world')], ['40+', _('modpacků na klik', 'one-click modpacks')], ['1,2 Tbps', _('anti-DDoS', 'anti-DDoS')]],
      chips: ['Ryzen 9 7950X', 'Paper / Fabric / Forge', 'Bedrock', 'Geyser', _('Zálohy po hodinách', 'Hourly backups'), _('Vlastní panel', 'Your own panel')],
      plans: [
        { name: _('Parta', 'Squad'), tag: '', price: 149, specs: [_('do 10 hráčů', 'up to 10 players'), '4 GB RAM', '25 GB NVMe', _('Vanilla a lehké pluginy', 'Vanilla and light plugins'), _('Zálohy denně', 'Daily backups'), _('Subdoména zdarma', 'Free subdomain')] },
        { name: _('Komunita', 'Community'), tag: _('Nejoblíbenější', 'Most popular'), price: 449, specs: [_('do 60 hráčů', 'up to 60 players'), '12 GB RAM', '80 GB NVMe', _('Modpacky na klik', 'One-click modpacks'), _('Zálohy po hodinách', 'Hourly backups'), _('Práva pro moderátory', 'Moderator roles')] },
        { name: _('Síť serverů', 'Server network'), tag: '', price: 1290, specs: [_('bez limitu hráčů', 'no player cap'), '32 GB RAM', '250 GB NVMe', _('Velocity proxy a více světů', 'Velocity proxy, multiple worlds'), _('Vlastní doména', 'Custom domain'), _('Prioritní podpora', 'Priority support')] }
      ],
      cmp: {
        cols: [_('Parta', 'Squad'), _('Komunita', 'Community'), _('Síť serverů', 'Network')],
        rows: [
          [_('Doporučení hráči', 'Recommended players'), '10', '60', _('200+', '200+')],
          ['RAM', '4 GB', '12 GB', '32 GB'],
          [_('Úložiště', 'Storage'), '25 GB NVMe', '80 GB NVMe', '250 GB NVMe'],
          [_('Modpacky', 'Modpacks'), _('Ručně', 'Manual'), _('Na klik', 'One click'), _('Na klik + vlastní', 'One click + custom')],
          [_('Zálohy', 'Backups'), _('Denně, 7 dní', 'Daily, 7 days'), _('Každou hodinu, 30 dní', 'Hourly, 30 days'), _('Každou hodinu, 30 dní', 'Hourly, 30 days')],
          [_('Proxy a více světů', 'Proxy and multi-world'), '—', _('1 proxy', '1 proxy'), '✓'],
          [_('Anti-DDoS', 'Anti-DDoS'), '✓', '✓', _('Profil na míru', 'Custom profile')],
          [_('Podpora', 'Support'), _('Chat', 'Chat'), _('Chat, 30 min', 'Chat, 30 min'), _('Prioritní, 10 min', 'Priority, 10 min')]
        ]
      },
      feats: [
        ['01', _('Modpack za dva kliky', 'A modpack in two clicks'), _('CurseForge a Modrinth přímo v panelu, včetně verzí a přechodů mezi nimi.', 'CurseForge and Modrinth right in the panel, versions and upgrades included.')],
        ['02', _('Konzole a plánované restarty', 'Console and scheduled restarts'), _('Restart ve čtyři ráno s varováním hráčům, bez toho, abyste u toho byli.', 'A 4am restart with a player warning, without you being there.')],
        ['03', _('Zálohy po hodinách', 'Hourly backups'), _('Třicet dní historie a obnovení jedním klikem — i na jiný server.', 'Thirty days of history and one-click restore — even onto another server.')],
        ['04', _('Práva pro celý tým', 'Roles for the whole team'), _('Moderátoři restartují, ale nesmažou svět. Vy vidíte, kdo co udělal.', 'Moderators can restart but cannot delete the world. You see who did what.')],
        ['05', _('Java i Bedrock naráz', 'Java and Bedrock together'), _('Geyser nastavíme za vás, hráči z telefonu se připojí na stejný svět.', 'We set up Geyser so mobile players join the same world.')],
        ['06', _('Anti-DDoS ladený pro Minecraft', 'Anti-DDoS tuned for Minecraft'), _('Filtrujeme bot connecty a UDP flood, aniž bychom odřízli hráče.', 'We filter bot connects and UDP floods without cutting off players.')]
      ],
      tech: ['Paper', 'Purpur', 'Fabric', 'Forge', 'NeoForge', 'Velocity', 'BungeeCord', 'Geyser', 'CurseForge', 'Modrinth'],
      bench: [
        { label: _('Onhost Komunita (Ryzen 7950X)', 'Onhost Community (Ryzen 7950X)'), pct: 100, note: '20,0 TPS' },
        { label: _('Sdílený gamehosting', 'Shared game hosting'), pct: 62, note: '12,4 TPS' },
        { label: _('VPS bez ladění JVM', 'VPS with untuned JVM'), pct: 74, note: '14,8 TPS' }
      ],
      benchNote: _('Modpack o 340 modech, svět 40 GB, 45 hráčů online, měřeno hodinu ve špičce.', 'A 340-mod modpack, 40 GB world, 45 players online, measured for an hour at peak.'),
      cases: [
        { t: _('Server pro partu', 'Server for friends'), d: _('Vanilla nebo lehké pluginy, zapnete na měsíc, kdy hrajete.', 'Vanilla or light plugins, run it the month you play.'), m: _('Parta', 'Squad') },
        { t: _('Veřejná komunita', 'Public community'), d: _('Modpack, ekonomika, moderátoři, zálohy a plánované restarty.', 'A modpack, economy, moderators, backups and scheduled restarts.'), m: _('Komunita', 'Community') },
        { t: _('Síť s více světy', 'Multi-world network'), d: _('Lobby, survival, minihry přes Velocity proxy pod jednou adresou.', 'Lobby, survival and minigames behind a Velocity proxy on one address.'), m: _('Síť serverů', 'Network') }
      ],
      faq: [
        [_('Kolik RAM potřebuju?', 'How much RAM do I need?'), _('Vanilla do 10 hráčů 4 GB, modpack do 60 hráčů 12 GB. Když to nevyjde, RAM přidáme za provozu.', 'Vanilla for 10 players: 4 GB. A modpack for 60: 12 GB. If it is not enough we add RAM live.')],
        [_('Můžu nahrát vlastní svět?', 'Can I upload my own world?'), _('Ano, přes SFTP nebo z archivu v panelu. Velikost neomezujeme.', 'Yes, over SFTP or from an archive in the panel. No size limit.')],
        [_('Zvládne to velký modpack?', 'Will it run a big modpack?'), _('Ano, JVM ladíme podle modpacku. Na 340 modech držíme 20 TPS na tarifu Komunita.', 'Yes, we tune the JVM per modpack. We hold 20 TPS with 340 mods on the Community plan.')],
        [_('Je v ceně doména?', 'Is a domain included?'), _('Subdomena vasserver.onhost.cz ano, vlastní doménu připojíte nebo koupíte u nás.', 'A yourserver.onhost.cz subdomain, yes. Bring your own domain or buy one here.')],
        [_('Můžu server na měsíc vypnout?', 'Can I pause for a month?'), _('Ano, server uspíte a platíte jen úložiště. Svět zůstane nedotčený.', 'Yes, suspend it and pay only for storage. The world stays untouched.')]
      ]
    }),

    'games': G({
      cmpTitle: _('Sloty a hry podle tarifu', 'Slots and games by plan'),
      plansTitle: cs ? 'Tarify podle slotů' : 'Plans by slot count', plansNote: cs ? 'Na turnaje účtujeme po hodinách, bez měsíčního paušálu.' : 'Tournaments are billed hourly, with no monthly fee.',
      cat: _('Hry', 'Games'), crumb: _('Ostatní hry', 'Other games'),
      kicker: 'CS2 · Rust · ARK · Palworld',
      title: _('Čtyřicet her, jeden panel, jedna faktura', 'Forty games, one panel, one invoice'),
      lead: _('CS2, Rust, ARK, Valheim, Palworld, Enshrouded a další — instalace jedním klikem, konzole, plánované úlohy a anti-DDoS profil pro každý titul zvlášť.', 'CS2, Rust, ARK, Valheim, Palworld, Enshrouded and more — one-click installs, console, scheduled tasks and an anti-DDoS profile per title.'),
      kpis: [['40+', _('podporovaných her', 'supported games')], ['8 ms', _('ping z ČR a SK', 'ping from CZ and SK')], ['1,2 Tbps', _('anti-DDoS', 'anti-DDoS')]],
      chips: ['CS2', 'Rust', 'ARK: SA', 'Valheim', 'Palworld', 'Enshrouded', 'Garry\u2019s Mod', 'Terraria'],
      plans: [
        { name: _('Slot Start', 'Slot Start'), tag: '', price: 199, specs: [_('16 slotů', '16 slots'), '6 GB RAM', '40 GB NVMe', _('Instalace na klik', 'One-click install'), _('Zálohy denně', 'Daily backups'), _('Konzole a FTP', 'Console and FTP')] },
        { name: _('Slot Pro', 'Slot Pro'), tag: _('Doporučeno', 'Recommended'), price: 590, specs: [_('64 slotů', '64 slots'), '16 GB RAM', '120 GB NVMe', _('Mody a workshop', 'Mods and workshop'), _('Zálohy po hodinách', 'Hourly backups'), _('Plánované úlohy', 'Scheduled tasks')] },
        { name: _('Turnaj', 'Tournament'), tag: '', price: 890, specs: [_('bez limitu slotů', 'no slot cap'), '32 GB RAM', '250 GB NVMe', _('Účtování po hodinách', 'Hourly billing'), _('Nastavení na turnaj', 'Tournament tuning'), _('Technik na telefonu', 'Engineer on call')] }
      ],
      cmp: {
        cols: [_('Slot Start', 'Slot Start'), _('Slot Pro', 'Slot Pro'), _('Turnaj', 'Tournament')],
        rows: [
          [_('Sloty', 'Slots'), '16', '64', _('Bez limitu', 'Unlimited')],
          ['RAM', '6 GB', '16 GB', '32 GB'],
          [_('Mody a workshop', 'Mods and workshop'), _('Základní', 'Basic'), '✓', '✓'],
          [_('Zálohy', 'Backups'), _('Denně', 'Daily'), _('Každou hodinu', 'Hourly'), _('Každou hodinu', 'Hourly')],
          [_('Účtování', 'Billing'), _('Měsíčně', 'Monthly'), _('Měsíčně', 'Monthly'), _('Po hodinách', 'Hourly')],
          [_('Anti-DDoS profil', 'Anti-DDoS profile'), _('Standardní', 'Standard'), _('Podle hry', 'Per game'), _('Na míru', 'Bespoke')],
          [_('Podpora', 'Support'), _('Chat', 'Chat'), _('Chat, 30 min', 'Chat, 30 min'), _('Technik na telefonu', 'Engineer on call')]
        ]
      },
      feats: [
        ['01', _('Čtyřicet titulů na klik', 'Forty titles, one click'), _('Instalace, update i přechod na jinou hru bez SSH a bez čekání na podporu.', 'Install, update or switch games with no SSH and no waiting on support.')],
        ['02', _('Profil ochrany podle hry', 'Protection profile per game'), _('CS2 a Rust mají jiný provoz než Minecraft. Filtry ladíme zvlášť.', 'CS2 and Rust look nothing like Minecraft on the wire. We tune filters separately.')],
        ['03', _('Účtování po hodinách na turnaje', 'Hourly billing for tournaments'), _('Server na tři dny, pak zrušit. Bez měsíčního paušálu.', 'A server for three days, then gone. No monthly fee.')],
        ['04', _('Konzole, FTP a plánované úlohy', 'Console, FTP and scheduled tasks'), _('Restart, wipe, změna mapy — v čase, kdy nikdo nehraje.', 'Restart, wipe, map change — at a time when nobody is playing.')],
        ['05', _('Práva pro admin tým', 'Roles for your admin team'), _('Kdo smí wipe, kdo jen restart. Každá akce je v logu.', 'Who can wipe, who can only restart. Every action is logged.')],
        ['06', _('Nízký ping z Prahy a Brna', 'Low ping from Prague and Brno'), _('Osm milisekund z ČR, dvanáct ze Slovenska, peering v NIX.CZ.', 'Eight milliseconds from Czechia, twelve from Slovakia, peered at NIX.CZ.')]
      ],
      tech: ['SteamCMD', 'Pterodactyl-style panel', 'Rust Oxide', 'CS2 Metamod', 'ARK mods', 'Workshop', 'Docker'],
      bench: [
        { label: _('Onhost Praha (CS2, 128 tick)', 'Onhost Prague (CS2, 128 tick)'), pct: 100, note: _('8 ms ping', '8 ms ping') },
        { label: _('Hosting ve Frankfurtu', 'Hosting in Frankfurt'), pct: 42, note: '19 ms' },
        { label: _('Hosting v západní Evropě', 'Hosting in western Europe'), pct: 25, note: '32 ms' }
      ],
      benchNote: _('Medián pingu z deseti českých a slovenských sítí do herního serveru, červen 2026.', 'Median ping from ten Czech and Slovak networks to the game server, June 2026.'),
      cases: [
        { t: _('Rust wipe cyklus', 'Rust wipe cycle'), d: _('Plánované wipe, mody z Oxide, zálohy před každou změnou.', 'Scheduled wipes, Oxide mods, backups before every change.'), m: _('Slot Pro', 'Slot Pro') },
        { t: _('Esport turnaj', 'Esports tournament'), d: _('CS2 servery na víkend, 128 tick, technik na telefonu.', 'CS2 servers for the weekend, 128 tick, engineer on call.'), m: _('Turnaj', 'Tournament') },
        { t: _('Komunita pro pár desítek lidí', 'Community of a few dozen'), d: _('Valheim nebo Palworld, jeden panel, sdílená správa.', 'Valheim or Palworld, one panel, shared administration.'), m: _('Slot Start', 'Slot Start') }
      ],
      faq: [
        [_('Podporujete konkrétní hru?', 'Do you support a specific game?'), _('Seznam čtyřiceti titulů je v panelu. Když tam vaše hra není, přidáme ji na požádání, obvykle do tří dnů.', 'Forty titles are listed in the panel. If yours is missing we will add it on request, usually within three days.')],
        [_('Můžu si nahrát vlastní mody?', 'Can I upload my own mods?'), _('Ano, přes FTP nebo workshop. Panel umí i vlastní start parametry.', 'Yes, over FTP or the workshop. The panel supports custom start parameters too.')],
        [_('Jak funguje účtování po hodinách?', 'How does hourly billing work?'), _('Zapnete, hrajete, vypnete. Účtujeme každou započatou hodinu, úložiště se drží dál.', 'Turn it on, play, turn it off. We bill each started hour; storage persists.')],
        [_('Zvládnete 128 tick CS2?', 'Can you do 128-tick CS2?'), _('Ano, na Ryzenu s vysokým taktem. Konfiguraci pro turnaj nastavíme s vámi.', 'Yes, on high-clock Ryzen. We will set up the tournament config with you.')],
        [_('Co když nás někdo bude DDoSovat?', 'What if we get DDoSed?'), _('Filtrujeme automaticky do tří sekund. U turnajů držíme technika po celou dobu.', 'We filter automatically within three seconds. During tournaments an engineer stands by.')]
      ]
    }),

    'enterprise': C({
      cmpTitle: _('Co je v které úrovni spolupráce', 'What each engagement tier includes'),
      plansTitle: cs ? 'Úrovně spolupráce' : 'Engagement tiers', plansNote: cs ? 'Vždy pod smlouvou, s SLA a jmenovaným kontaktem.' : 'Always under contract, with an SLA and a named contact.',
      cat: 'Onhost', crumb: 'Enterprise a SLA',
      kicker: _('Smluvní SLA · jmenovaný kontakt · audit', 'Contractual SLA · named contact · audit'),
      title: _('Infrastruktura pod smlouvou, ne pod obchodními podmínkami', 'Infrastructure under contract, not under terms of service'),
      lead: _('Dedikovaná kapacita, smluvní SLA s pokutami, jmenovaný technický kontakt a podklady pro audit. Pro firmy, které musí umět odpovědět regulátorovi.', 'Dedicated capacity, a contractual SLA with penalties, a named technical contact and audit documentation. For companies that must answer to a regulator.'),
      kpis: [['99,99 %', _('smluvní dostupnost', 'contractual uptime')], ['15 min', _('reakce P1, 24/7', 'P1 response, 24/7')], ['NIS2', _('podklady připravené', 'documentation ready')]],
      chips: ['NIS2', 'ISO 27001', 'GDPR / DPA', _('Privátní cloud', 'Private cloud'), _('Vyhrazený hardware', 'Dedicated hardware'), _('Penetrační testy', 'Penetration tests')],
      plans: [
        { name: 'Business', tag: '', price: 9900, specs: [_('Vyhrazený hardware', 'Dedicated hardware'), 'SLA 99,95 %', _('Reakce do 30 minut', '30-minute response'), _('Kvartální reporty', 'Quarterly reports'), _('DPA a GDPR podklady', 'DPA and GDPR pack'), _('Faktura se splatností', 'Invoicing with terms')] },
        { name: 'Enterprise', tag: _('Nejčastější volba', 'Most chosen'), price: 34900, specs: [_('Privátní cloud', 'Private cloud'), 'SLA 99,99 %', _('Reakce do 15 minut', '15-minute response'), _('Jmenovaný technik', 'Named engineer'), _('DR lokalita', 'DR site'), _('Podklady pro NIS2 a ISO', 'NIS2 and ISO documentation')] },
        { name: _('Regulovaný sektor', 'Regulated sector'), tag: '', price: 0, priceLabel: _('Na dotaz', 'On request'), priceNote: _('podle rozsahu a auditů', 'by scope and audits'), ctaLabel: _('Vyžádat návrh', 'Request a proposal'), specs: [_('Návrh na míru', 'Bespoke design'), _('Oddělená infrastruktura', 'Segregated infrastructure'), _('Audit na místě', 'On-site audit'), _('Penetrační testy', 'Penetration tests'), _('Právní a compliance podpora', 'Legal and compliance support'), _('Cena podle rozsahu', 'Priced by scope')] }
      ],
      cmp: {
        cols: ['Business', 'Enterprise', _('Regulovaný sektor', 'Regulated')],
        rows: [
          ['SLA', '99,95 %', '99,99 %', _('Na míru', 'Bespoke')],
          [_('Reakce P1', 'P1 response'), '30 min', '15 min', _('Podle smlouvy', 'Per contract')],
          [_('Technický kontakt', 'Technical contact'), _('Tým podpory', 'Support team'), _('Jmenovaný technik', 'Named engineer'), _('Tým a eskalační matice', 'Team and escalation matrix')],
          [_('DR lokalita', 'DR site'), _('Doplněk', 'Add-on'), '✓', _('2 lokality', '2 sites')],
          [_('Audit a reporty', 'Audit and reporting'), _('Kvartálně', 'Quarterly'), _('Měsíčně', 'Monthly'), _('Na vyžádání kdykoli', 'On demand')],
          [_('Penetrační testy', 'Penetration tests'), '—', _('Ročně', 'Annual'), _('Dvakrát ročně', 'Twice a year')],
          [_('Fakturace', 'Invoicing'), _('Splatnost 14 dní', '14-day terms'), _('Splatnost 30 dní', '30-day terms'), _('Podle smlouvy', 'Per contract')]
        ]
      },
      feats: [
        ['01', _('SLA s pokutou, ne s výmluvou', 'An SLA with a penalty, not an excuse'), _('Nedodržení znamená kredit vyplacený automaticky, bez žádosti a bez vyjednávání.', 'A miss means credit paid automatically — no request, no negotiation.')],
        ['02', _('Jmenovaný technik', 'A named engineer'), _('Člověk, který zná vaši architekturu a jeho telefon máte v mobilu.', 'A person who knows your architecture and whose number is in your phone.')],
        ['03', _('Podklady pro audit', 'Audit documentation'), _('Politiky, logy, přístupy, testy obnovy — v podobě, kterou auditor přijme.', 'Policies, logs, access records, restore tests — in a form auditors accept.')],
        ['04', _('Připraveno na NIS2', 'NIS2 ready'), _('Hlášení incidentů, řízení dodavatelů a evidence aktiv máme popsané a ověřené.', 'Incident reporting, supplier management and asset registers are documented and verified.')],
        ['05', _('Oddělená infrastruktura', 'Segregated infrastructure'), _('Vlastní hardware, vlastní VLAN, vlastní úložiště. Sousedy nemáte.', 'Your own hardware, VLAN and storage. No neighbours.')],
        ['06', _('Exit plán ve smlouvě', 'An exit plan in the contract'), _('Předem popsané, jak dostanete data zpátky a v jakém formátu. Bez rukojmí.', 'Written up front: how you get your data back and in what format. No hostages.')]
      ],
      tech: ['VMware', 'Proxmox', 'Kubernetes', 'Veeam', 'Zabbix', 'Grafana', 'Terraform', 'Vault', 'Entra ID', 'Keycloak'],
      bench: [
        { label: _('Enterprise s DR lokalitou', 'Enterprise with a DR site'), pct: 100, note: _('99,99 % / RTO 30 min', '99.99% / 30-min RTO') },
        { label: _('Business bez DR', 'Business without DR'), pct: 63, note: _('99,95 % / RTO 4 h', '99.95% / 4-h RTO') },
        { label: _('Vlastní serverovna', 'In-house server room'), pct: 30, note: _('99,5 % / RTO 1 den', '99.5% / 1-day RTO') }
      ],
      benchNote: _('Naměřená dostupnost a doba obnovy za 12 měsíců u srovnatelně velkých zákazníků.', 'Measured uptime and recovery time over 12 months across similarly sized customers.'),
      cases: [
        { t: _('Zdravotnictví a finance', 'Healthcare and finance'), d: _('Data v ČR, oddělená infrastruktura, auditní stopa a DPA.', 'Data in the country, segregated infrastructure, audit trail and DPA.'), m: _('Regulovaný sektor', 'Regulated') },
        { t: _('Výroba a logistika', 'Manufacturing and logistics'), d: _('Systémy, kde hodina bez provozu znamená zastavenou linku.', 'Systems where an hour of downtime stops the line.'), m: 'Enterprise' },
        { t: _('Veřejná správa', 'Public sector'), d: _('Zadávací podmínky, NIS2, dostupnost s pokutou a exit plán.', 'Tender requirements, NIS2, uptime with penalties and an exit plan.'), m: 'Enterprise' }
      ],
      faq: [
        [_('Jak dlouho trvá nasazení?', 'How long is onboarding?'), _('Business dva týdny, Enterprise čtyři až šest týdnů podle rozsahu a auditů.', 'Business: two weeks. Enterprise: four to six weeks depending on scope and audits.')],
        [_('Podepíšete naši smlouvu?', 'Will you sign our contract?'), _('Ano, i vaši šablonu. Máme právní tým, který si projde odchylky od našich podmínek.', 'Yes, including your template. Our legal team reviews deviations from our standard terms.')],
        [_('Umíte doložit dostupnost?', 'Can you evidence uptime?'), _('Ano, měření je nezávislé a report dostáváte automaticky každý měsíc.', 'Yes, measurement is independent and the report arrives automatically every month.')],
        [_('Jak řešíte incidenty?', 'How do you handle incidents?'), _('Podle NIS2: klasifikace, hlášení do 24 hodin, postmortem do pěti dnů.', 'Per NIS2: classification, notification within 24 hours, postmortem within five days.')],
        [_('Co když budeme chtít odejít?', 'What if we want to leave?'), _('Exit plán je součástí smlouvy. Data předáme v otevřených formátech, bez poplatku.', 'The exit plan is part of the contract. We hand data over in open formats at no charge.')]
      ]
    }),

    'green': C({
      panelsTitle: _('Kam jde energie a teplo', 'Where the energy and heat go'), panelsLead: _('Datacentrum spotřebuje hodně proudu. Rozhoduje, odkud je a co uděláte s teplem, které vznikne.', 'A data centre draws a lot of power. What matters is where it comes from and what you do with the heat.'),
      panels: [
        [_('Zdroje energie', 'Energy sources'), _('Vlastní FVE na střeše, zbytek z certifikovaných obnovitelných zdrojů.', 'Rooftop solar of our own, the rest from certified renewables.'), [[_('Obnovitelné', 'Renewable'), '62 %'], [_('Vlastní FVE', 'Own solar'), '840 kWp'], [_('Baterie', 'Battery storage'), '2 MWh'], [_('Cíl 2030', '2030 target'), '90 %']]],
        [_('Odpadní teplo', 'Waste heat'), _('Teplo ze sálů ohřívá dvě sousední budovy místo vzduchu nad střechou.', 'Hall heat warms two neighbouring buildings instead of the air above the roof.'), [[_('Budov', 'Buildings'), '2'], [_('Dodané teplo 2025', 'Heat delivered in 2025'), '1 640 MWh'], [_('Úspora plynu', 'Gas saved'), '164 ' + _('tis. m³', 'k m³')], ['PUE', '1,18']]],
        [_('Komunita', 'Community'), _('Část zisku jde do fondu na komunitní energetiku a open-source projekty.', 'Part of the profit funds community energy and open-source projects.'), [[_('Fond 2025', 'Fund in 2025'), '2,4 M Kč'], [_('Podpořených projektů', 'Projects supported'), '31'], [_('Energetická společenství', 'Energy communities'), '6'], [_('Granty pro školy', 'School grants'), '14']]]
      ],
      cmp: null,
      plansTitle: cs ? 'Jak se zapojit' : 'Ways to take part', plansNote: cs ? 'Zelená infrastruktura je základ, ne balíček za příplatek.' : 'Green infrastructure is the default, not a paid add-on.',
      cat: 'Onhost', crumb: _('Zelená infrastruktura', 'Green infrastructure'),
      kicker: _('Komunitní energetika · BESS · odvod tepla', 'Community energy · BESS · heat recovery'),
      title: _('Datacentrum, které vrací teplo i energii zpátky do obce', 'A data centre that gives heat and power back to the community'),
      lead: _('Provozujeme vlastní bateriové systémy, odebíráme energii z komunitních obnovitelných zdrojů a odpadní teplo vracíme do teplovodní sítě. Ne proto, že to zní dobře — protože nám to snižuje náklady i riziko.', 'We run our own battery storage, buy energy from community renewables and return waste heat into the district heating loop. Not because it sounds good — because it lowers our cost and risk.'),
      kpis: [['1,18', 'PUE'], ['62 %', _('energie z obnovitelných zdrojů', 'energy from renewables')], ['2,4 GWh', _('tepla vráceno v 2025', 'heat returned in 2025')]],
      chips: [_('Komunitní energetika', 'Community energy'), 'BESS', _('Fotovoltaika', 'Solar'), _('Odvod tepla', 'Heat recovery'), _('Řízení špiček', 'Peak shaving'), _('Roční report', 'Annual report')],
      plans: [
        { name: _('Zelený hosting', 'Green hosting'), tag: _('V ceně', 'Included'), price: 0, priceLabel: _('V ceně', 'Included'), priceNote: _('u všech tarifů, bez příplatku', 'on every plan, no surcharge'), ctaLabel: _('Zobrazit tarify', 'See plans'), specs: [_('Bez příplatku u všech tarifů', 'No surcharge on any plan'), _('Certifikát o původu energie', 'Energy origin certificate'), _('Roční přehled spotřeby', 'Annual consumption report'), _('Údaje pro vaše ESG', 'Data for your ESG report'), _('Odvod tepla v lokalitě Praha', 'Heat recovery in Prague'), _('Bez greenwashingu', 'No greenwashing')] },
        { name: _('Komunitní podíl', 'Community share'), tag: '', price: 490, specs: [_('Podíl na komunitním zdroji', 'Share in a community source'), _('Doložený původ energie', 'Verified energy origin'), _('Kvartální výkaz', 'Quarterly statement'), _('Podpora lokálního projektu', 'Support for a local project'), _('Zmínka v našem reportu', 'Named in our report'), _('Zrušitelné kdykoli', 'Cancel anytime')] },
        { name: _('Energetický partner', 'Energy partner'), tag: '', price: 0, priceLabel: _('Podle projektu', 'Per project'), priceNote: _('technická studie zdarma', 'free technical study'), ctaLabel: _('Probrat projekt', 'Discuss the project'), specs: [_('Společný projekt BESS nebo FVE', 'Joint BESS or solar project'), _('Napojení na Electree', 'Electree integration'), _('Sdílení výkonu a špiček', 'Capacity and peak sharing'), _('Dlouhodobá cena energie', 'Long-term energy price'), _('Technická studie', 'Technical study'), _('Cena podle projektu', 'Priced per project')] }
      ],
      feats: [
        ['01', _('Baterie, které vyrovnávají síť', 'Batteries that steady the grid'), _('BESS systémy nesnižují jen naši cenu — poskytují službu výkonové rovnováhy distribuční soustavě.', 'Our BESS does not just cut our bill — it provides balancing services to the grid.')],
        ['02', _('Energie z komunitních zdrojů', 'Energy from community sources'), _('Odebíráme z lokálních fotovoltaik a družstev, ne z anonymního certifikátu.', 'We buy from local solar and cooperatives, not from an anonymous certificate.')],
        ['03', _('Teplo do teplovodní sítě', 'Heat into the district loop'), _('Odpadní teplo ohřívá okolní budovy. V roce 2025 to bylo 2,4 GWh.', 'Waste heat warms nearby buildings — 2.4 GWh of it in 2025.')],
        ['04', _('Propojení s Electree', 'Wired into Electree'), _('Spotřebu, výrobu i baterie řídíme společně s platformou Electree, včetně reakce na ceny.', 'Consumption, generation and storage are managed together with the Electree platform, price-reactive.')],
        ['05', _('Data pro váš ESG report', 'Data for your ESG report'), _('Dostanete čísla o spotřebě a emisích vaší infrastruktury, ne obecné prohlášení.', 'You get figures for your own infrastructure\u2019s consumption and emissions, not a general statement.')],
        ['06', _('Bez zeleného příplatku', 'No green surcharge'), _('Zelená infrastruktura je základ, ne balíček za příplatek. Nižší PUE platíme sami.', 'Green is the default, not an upsell. We pay for the lower PUE ourselves.')]
      ],
      tech: ['Electree', 'BESS', _('Fotovoltaika', 'Solar PV'), _('Teplovod', 'District heating'), 'Modbus', 'OCPP', 'Grafana'],
      bench: [
        { label: _('Onhost Praha (BESS + FVE + teplo)', 'Onhost Prague (BESS + solar + heat)'), pct: 100, note: 'PUE 1,18' },
        { label: _('Moderní komerční datacentrum', 'Modern commercial data centre'), pct: 74, note: 'PUE 1,40' },
        { label: _('Firemní serverovna', 'Company server room'), pct: 32, note: 'PUE 2,40' }
      ],
      benchNote: _('PUE za 12 měsíců včetně chlazení, rozvodů a ztrát; sloupec ukazuje účinnost, nižší PUE je lepší.', 'PUE over 12 months including cooling, distribution and losses; the bar shows efficiency — lower PUE is better.'),
      cases: [
        { t: _('Firma s ESG povinnostmi', 'A company with ESG obligations'), d: _('Doložitelná čísla o spotřebě infrastruktury pro výroční zprávu.', 'Verifiable infrastructure consumption figures for the annual report.'), m: _('Komunitní podíl', 'Community share') },
        { t: _('Obec nebo družstvo', 'A municipality or cooperative'), d: _('Společný projekt: naše zátěž stabilizuje váš zdroj, teplo jde do obce.', 'A joint project: our load stabilises your source, the heat goes to the town.'), m: _('Energetický partner', 'Energy partner') },
        { t: _('Provozovatel BESS', 'BESS operator'), d: _('Sdílení výkonu a obchodování na denním trhu přes Electree.', 'Capacity sharing and day-ahead trading through Electree.'), m: _('Energetický partner', 'Energy partner') }
      ],
      faq: [
        [_('Je zelený hosting dražší?', 'Does green hosting cost more?'), _('Ne. Nižší PUE a vlastní baterie nám náklady snižují, takže příplatek nedává smysl.', 'No. Lower PUE and our own batteries reduce our costs, so a surcharge makes no sense.')],
        [_('Jak doložíte původ energie?', 'How do you prove energy origin?'), _('Smlouvami s konkrétními zdroji a měřením. Výkaz dostanete ročně, na požádání kvartálně.', 'With contracts to named sources and metering. The statement is annual, or quarterly on request.')],
        [_('Co je Electree?', 'What is Electree?'), _('Platforma pro řízení komunitní energetiky, se kterou spolupracujeme na dispečinku a obchodování.', 'A community energy management platform we work with on dispatch and trading.')],
        [_('Kam jde odpadní teplo?', 'Where does the waste heat go?'), _('V Praze do teplovodní sítě pro okolní budovy, v Brně do provozu areálu.', 'In Prague, into the district heating loop for nearby buildings; in Brno, into the site\u2019s own systems.')],
        [_('Můžeme se zapojit se svým zdrojem?', 'Can we join with our own source?'), _('Ano, přesně o tom je Energetický partner. Začínáme technickou studií, ta je zdarma.', 'Yes — that is what the Energy partner track is for. It starts with a free technical study.')]
      ]
    }),

    'affiliate': C({
      panelsTitle: _('Jak se provize počítá', 'How commission is calculated'), panelsLead: _('Provize běží, dokud zákazník platí. Přehled je v reálném čase a výplatu neschvaluje obchodník.', 'Commission runs while the customer pays. The dashboard is real time and no salesperson approves the payout.'),
      panels: [
        [_('Sazby', 'Rates'), _('Základ dvacet procent, u tvůrců dvacet pět, u resellerů podle objemu.', 'Twenty percent as standard, twenty-five for creators, volume-based for resellers.'), [[_('Standard', 'Standard'), '20 %'], [_('Tvůrce', 'Creator'), '25 %'], ['Reseller', '25–45 %'], [_('Doživotně', 'Lifetime'), '✓']]],
        [_('Přiřazení', 'Attribution'), _('Cookie na devadesát dní, vlastní kupony a přiřazení i podle kuponu bez cookie.', 'A ninety-day cookie, your own coupons, and coupon-based attribution without cookies.'), [[_('Cookie', 'Cookie'), '90 ' + _('dní', 'days')], [_('Vlastní kupony', 'Own coupons'), '✓'], [_('Přiřazení bez cookie', 'Cookieless attribution'), '✓'], [_('Storno období', 'Clawback window'), '30 ' + _('dní', 'days')]]],
        [_('Výplata', 'Payout'), _('Měsíčně na účet, nebo do kreditu s desetiprocentním bonusem.', 'Monthly to your account, or as credit with a ten percent bonus.'), [[_('Minimum', 'Minimum'), '500 Kč'], [_('Frekvence', 'Frequency'), _('měsíčně', 'monthly')], [_('Bonus v kreditu', 'Credit bonus'), '+10 %'], [_('Medián výdělku', 'Median earnings'), '1 200 Kč']]]
      ],
      cmp: null,
      endTitle: _('Zapojte se a berte provizi z každé platby.', 'Join and earn on every payment.'),
      endLead: _('Registrace zabere pět minut, odkazy dostanete hned. Výplata měsíčně, nebo do kreditu s bonusem deset procent.', 'Signup takes five minutes and links are instant. Monthly payouts, or credit with a ten percent bonus.'),
      sla: {
        title: _('Jak se počítá provize', 'How commission is calculated'),
        lead: _('Provize běží, dokud zákazník platí — ne jen z první platby. Přehled je v reálném čase a výplata bez vyjednávání.', 'Commission runs while the customer pays — not just on the first invoice. Real-time dashboard, payouts without negotiation.'),
        rows: [
          { k: '25 %', v: _('z každé platby', 'of every payment') },
          { k: _('90 dní', '90 days'), v: _('platnost cookie', 'cookie window') },
          { k: '+10 %', v: _('bonus při výplatě v kreditu', 'bonus on credit payout') },
          { k: _('500 Kč', '500 CZK'), v: _('minimum pro výplatu', 'payout minimum') }
        ]
      },
      plansTitle: cs ? 'Úrovně programu' : 'Programme tiers', plansNote: cs ? 'Bez vstupních podmínek, výplata měsíčně, kdykoli lze odejít.' : 'No entry conditions, monthly payouts, leave anytime.',
      cat: _('Program', 'Programme'), crumb: _('Affiliate program', 'Affiliate programme'),
      kicker: _('25 % · doživotní provize · výplata měsíčně', '25% · lifetime commission · monthly payout'),
      title: _('Doporučte Onhost a berte provizi, dokud zákazník platí', 'Refer Onhost and earn while the customer stays'),
      lead: _('Dvacet pět procent z každé platby, ne jen z první. Vlastní odkazy, kupony a přehled v reálném čase. Výplata na účet nebo do kreditu s bonusem deset procent.', 'Twenty-five percent of every payment, not just the first. Your own links, coupons and a real-time dashboard. Payout to your account, or as credit with a ten percent bonus.'),
      kpis: [['25 %', _('z každé platby', 'of every payment')], [_('90 dní', '90 days'), _('platnost cookie', 'cookie window')], [_('1 200 Kč', '1,200 CZK'), _('medián měsíční provize', 'median monthly commission')]],
      chips: [_('Doživotní provize', 'Lifetime commission'), _('Vlastní kupony', 'Your own coupons'), _('Cookie 90 dní', '90-day cookie'), _('Výplata měsíčně', 'Monthly payout'), _('Bez minima', 'No minimum'), 'API'],
      plans: [
        { name: 'Standard', tag: _('Pro každého', 'For everyone'), price: 0, priceLabel: '20 %', priceNote: _('z každé platby, doživotně', 'of every payment, for life'), ctaLabel: _('Zapojit se', 'Join'), specs: [_('20 % z každé platby', '20% of every payment'), _('Cookie 90 dní', '90-day cookie'), _('Vlastní odkazy', 'Your own links'), _('Výplata od 500 Kč', 'Payout from 500 CZK'), _('Přehled v panelu', 'Panel dashboard'), _('Bez podmínek', 'No conditions')] },
        { name: _('Tvůrce', 'Creator'), tag: _('Nejčastější', 'Most common'), price: 0, priceLabel: '25 %', priceNote: _('z každé platby, doživotně', 'of every payment, for life'), ctaLabel: _('Zapojit se', 'Join'), specs: [_('25 % z každé platby', '25% of every payment'), _('Vlastní kupon se slevou', 'Your own discount coupon'), _('Materiály a bannery', 'Assets and banners'), _('Bonus 10 % při výplatě v kreditu', '10% bonus on credit payout'), _('Přednostní podpora', 'Priority support'), _('Od 5 zákazníků', 'From 5 customers')] },
        { name: 'Reseller', tag: '', price: 0, priceLabel: _('25–45 %', '25–45%'), priceNote: _('marže podle objemu', 'margin by volume'), ctaLabel: _('Chci reseller účet', 'Get a reseller account'), specs: [_('Marže podle objemu', 'Volume-based margin'), _('Vlastní ceník', 'Your own price list'), _('White-label panel', 'White-label panel'), _('Fakturace na vás', 'You invoice the client'), _('Technická podpora druhé úrovně', 'Second-line technical support'), _('Vlastní smlouva', 'Your own contract')] }
      ],
      feats: [
        ['01', _('Provize se neplatí jen raz', 'Commission does not stop after month one'), _('Dokud zákazník platí, dostáváte podíl. I za tři roky.', 'While the customer pays, you get a share. Three years in as well.')],
        ['02', _('Kupon, který lidé použijí', 'A coupon people actually use'), _('Vlastní kód se slevou pro vaše publikum — funguje lépe než banner.', 'Your own discount code for your audience — it converts better than a banner.')],
        ['03', _('Přehled v reálném čase', 'A real-time dashboard'), _('Kliky, registrace, konverze a čekající provize. Bez tabulek v e-mailu.', 'Clicks, signups, conversions and pending commission. No spreadsheets by email.')],
        ['04', _('Výplata bez tanečků', 'Payouts without ceremony'), _('Od 500 Kč, na bankovní účet nebo do kreditu s bonusem deset procent.', 'From 500 CZK, to your bank account or as credit with a ten percent bonus.')],
        ['05', _('Materiály, které nejsou trapné', 'Assets that are not embarrassing'), _('Bannery, popisy služeb a srovnávací tabulky připravené k použití.', 'Banners, service descriptions and comparison tables ready to use.')],
        ['06', _('API pro váš web', 'An API for your site'), _('Stav provizí a odkazů si natáhnete do vlastního dashboardu.', 'Pull commission and link status into your own dashboard.')]
      ],
      tech: ['UTM', _('Vlastní kupony', 'Custom coupons'), 'API', 'Webhooky', _('Postback', 'Postback'), _('Bannery', 'Banners')],
      bench: [
        { label: _('Tvůrce s 5 000 sledujícími', 'Creator with 5,000 followers'), pct: 100, note: _('4 200 Kč / měs.', '4,200 CZK / month') },
        { label: _('Blog o vývoji', 'Development blog'), pct: 43, note: _('1 800 Kč / měs.', '1,800 CZK / month') },
        { label: _('Doporučení mezi kolegy', 'Word of mouth'), pct: 14, note: _('600 Kč / měs.', '600 CZK / month') }
      ],
      benchNote: _('Medián měsíční provize po šesti měsících podle typu partnera, data za 2025.', 'Median monthly commission after six months by partner type, 2025 data.'),
      cases: [
        { t: _('Vývojář s publikem', 'Developer with an audience'), d: _('Tutoriály a šablony s vlastním kuponem v popisu.', 'Tutorials and templates with your coupon in the description.'), m: _('Tvůrce', 'Creator') },
        { t: _('Agentura, která hostuje klienty', 'Agency hosting clients'), d: _('Buď provize, nebo vlastní ceník a fakturace na vás.', 'Either commission, or your own price list and invoicing.'), m: 'Reseller' },
        { t: _('Komunitní správce', 'Community admin'), d: _('Doporučení serverů v komunitě, provize dokud lidé hrají.', 'Recommending servers in your community, commission while they play.'), m: 'Standard' }
      ],
      faq: [
        [_('Kdy dostanu první provizi?', 'When is my first payout?'), _('Po první platbě zákazníka a uplynutí 30denní lhůty na vrácení. Pak měsíčně.', 'After the customer\u2019s first payment and a 30-day refund window. Monthly after that.')],
        [_('Můžu doporučovat vlastní projekty?', 'Can I refer my own projects?'), _('Vlastní účty ne, ale klienty ano. Reseller program je pro tenhle případ vhodnější.', 'Not your own accounts, but clients yes. The reseller programme fits that better.')],
        [_('Platí provize i pro GPU a Enterprise?', 'Does commission apply to GPU and Enterprise?'), _('Ano, u GPU a rezervací platí 15 %. U Enterprise se řeší individuálně.', 'Yes — 15% for GPU and reservations. Enterprise is handled case by case.')],
        [_('Co když zákazník odejde?', 'What if the customer leaves?'), _('Provize se zastaví. Nic nedoplácíte, jen dál nechodí.', 'Commission stops. You owe nothing, it simply ends.')],
        [_('Musím mít IČO?', 'Do I need a company?'), _('Do 15 000 Kč ročně ne, nad to ano. Doklady připravíme.', 'Not under 15,000 CZK a year; above that, yes. We prepare the paperwork.')]
      ]
    }),

    'status': C({
      panelsTitle: _('Jak měříme dostupnost', 'How we measure uptime'), panelsLead: _('Měření zadáváme externí službě, aby čísla nešla upravit zevnitř. Tady je metodika.', 'Measurement is outsourced so the numbers cannot be edited from the inside. Here is the methodology.'),
      panels: [
        [_('Metodika', 'Methodology'), _('Kontrola každých 30 sekund z devíti evropských lokalit, výpadek se počítá od druhé chyby.', 'A check every 30 seconds from nine European locations; an outage counts from the second failure.'), [[_('Interval', 'Interval'), '30 s'], [_('Lokalit', 'Locations'), '9'], [_('Poskytovatel měření', 'Measured by'), _('externí', 'external')], [_('Historie', 'History'), _('24 měsíců', '24 months')]]],
        [_('Incidenty', 'Incidents'), _('Zveřejňujeme každý incident, který zákazníci mohli poznat, i dvouminutový.', 'We publish every incident customers could notice, even a two-minute one.'), [[_('Za 12 měsíců', 'Last 12 months'), '3'], [_('Nejdelší', 'Longest'), '14 min'], [_('Postmortem do', 'Postmortem within'), '5 ' + _('dní', 'days')], [_('Kredit', 'Credit'), _('automaticky', 'automatic')]]],
        [_('Upozornění', 'Alerts'), _('Vyberete si služby i kanál. Plánované práce hlásíme pět dní předem.', 'Choose your services and channel. Planned work is announced five days ahead.'), [[_('Kanály', 'Channels'), _('e-mail, SMS, webhook', 'email, SMS, webhook')], [_('Filtr podle služby', 'Per-service filter'), '✓'], [_('Odstávky předem', 'Maintenance notice'), '5 ' + _('dní', 'days')], [_('Cena', 'Price'), _('0 Kč', 'Free')]]]
      ],
      cmp: null,
      plansTitle: cs ? 'Co je k dispozici' : 'What is available', plansNote: cs ? 'Veřejná data i upozornění jsou zdarma, platí se jen vlastní monitoring.' : 'Public data and alerts are free; only your own monitoring is paid.',
      cat: _('Podpora', 'Support'), crumb: _('Stav služeb', 'Service status'),
      kicker: _('Nezávislé měření · historie 24 měsíců', 'Independent measurement · 24-month history'),
      title: _('Stav služeb, který nekreslíme my', 'Service status we do not draw ourselves'),
      lead: _('Dostupnost měří nezávislá služba z devíti míst v Evropě. Incidenty píšeme, i když byly krátké, a postmortem zveřejňujeme do pěti dnů.', 'Uptime is measured by an independent service from nine European locations. We publish incidents even when they were short, and postmortems within five days.'),
      kpis: [['99,993 %', _('dostupnost za 90 dní', 'uptime over 90 days')], ['3', _('incidenty za 12 měsíců', 'incidents in 12 months')], [_('5 dní', '5 days'), _('do zveřejnění postmortemu', 'to published postmortem')]],
      chips: [_('Web a panel', 'Web and panel'), 'API', _('Síť a peering', 'Network and peering'), _('DNS', 'DNS'), _('E-mail', 'Email'), _('Fakturace', 'Billing')],
      plans: [
        { name: _('Veřejná stránka', 'Public page'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('bez registrace', 'no account needed'), ctaLabel: _('Otevřít status', 'Open status'), specs: [_('Stav všech služeb', 'All service states'), _('Historie 24 měsíců', '24-month history'), _('Plánované odstávky', 'Planned maintenance'), _('Postmortemy', 'Postmortems'), _('RSS a JSON', 'RSS and JSON'), _('Bez registrace', 'No account needed')] },
        { name: _('Upozornění', 'Notifications'), tag: _('Zdarma', 'Free'), price: 0, priceLabel: _('Zdarma', 'Free'), priceNote: _('pro zákazníky Onhostu', 'for Onhost customers'), ctaLabel: _('Nastavit v panelu', 'Set up in the panel'), specs: [_('E-mail a SMS', 'Email and SMS'), _('Jen vaše služby', 'Only your services'), _('Webhook do Slacku', 'Slack webhook'), _('Předem o odstávkách', 'Advance maintenance notice'), _('Eskalace na telefon', 'Phone escalation'), _('Nastavitelné prahy', 'Configurable thresholds')] },
        { name: _('Monitoring vašich služeb', 'Monitoring for your services'), tag: '', price: 290, specs: [_('50 kontrol', '50 checks'), _('Z devíti lokalit', 'From nine locations'), _('Kontrola každých 30 s', '30-second interval'), _('Vlastní status stránka', 'Your own status page'), _('SLA reporty', 'SLA reports'), _('API a webhooky', 'API and webhooks')] }
      ],
      feats: [
        ['01', _('Měří to někdo jiný', 'Someone else measures it'), _('Nezávislá služba z devíti evropských lokalit. Čísla si nemůžeme upravit.', 'An independent service from nine European locations. We cannot edit the numbers.')],
        ['02', _('Incidenty píšeme všechny', 'We publish every incident'), _('I dvouminutový. Když to poznali zákazníci, patří to na status stránku.', 'Even a two-minute one. If customers noticed, it belongs on the status page.')],
        ['03', _('Postmortem do pěti dnů', 'Postmortem within five days'), _('Co se stalo, proč, co jsme změnili. Bez „technických problémů“.', 'What happened, why, what we changed. No “technical difficulties”.')],
        ['04', _('Upozornění jen na vaše služby', 'Alerts only for your services'), _('Nezajímá vás DNS, když máte gameserver? Vyberete si.', 'Not interested in DNS when you run a game server? Pick what matters.')],
        ['05', _('Odstávky dopředu', 'Maintenance in advance'), _('Plánované práce hlásíme minimálně pět dní předem, s okny na výběr.', 'Planned work is announced at least five days ahead, with window options.')],
        ['06', _('Monitoring i pro vaše služby', 'Monitoring for your services too'), _('Stejná infrastruktura, kterou hlídáme sebe, hlídá i vaše endpointy.', 'The same infrastructure that watches us can watch your endpoints.')]
      ],
      tech: ['Prometheus', 'Grafana', 'Zabbix', 'Statuspage API', 'Slack', 'PagerDuty', 'RSS', 'JSON API'],
      bench: [
        { label: _('Naměřená dostupnost 90 dní', 'Measured uptime, 90 days'), pct: 100, note: '99,993 %' },
        { label: _('Smluvní SLA', 'Contractual SLA'), pct: 99, note: '99,99 %' },
        { label: _('Průměr trhu (ČR hosting)', 'Market average (CZ hosting)'), pct: 96, note: '99,7 %' }
      ],
      benchNote: _('Dostupnost webu a panelu měřená z devíti lokalit, poslední 90denní okno.', 'Web and panel uptime measured from nine locations, last 90-day window.'),
      cases: [
        { t: _('Před podpisem smlouvy', 'Before you sign'), d: _('Podívejte se na dva roky historie, ne na marketingové číslo.', 'Look at two years of history, not a marketing figure.'), m: _('Veřejná stránka', 'Public page') },
        { t: _('Provozní tým', 'Operations team'), d: _('Webhook do Slacku a eskalace na telefon při P1.', 'A Slack webhook and phone escalation for P1.'), m: _('Upozornění', 'Notifications') },
        { t: _('Vlastní status pro klienty', 'Your own status page'), d: _('Agentura ukazuje klientům dostupnost jejich webů.', 'An agency showing clients the uptime of their sites.'), m: _('Váš monitoring', 'Your monitoring') }
      ],
      faq: [
        [_('Kdo dostupnost měří?', 'Who measures uptime?'), _('Nezávislá externí služba, kontrola každých 30 sekund z devíti lokalit v Evropě.', 'An independent external service, checking every 30 seconds from nine European locations.')],
        [_('Zveřejňujete i krátké incidenty?', 'Do you publish short incidents?'), _('Ano, všechny, které se dotkly zákazníků, bez ohledu na délku.', 'Yes — every one that affected customers, regardless of length.')],
        [_('Jak se dozvím o odstávce?', 'How will I hear about maintenance?'), _('E-mailem pět dní předem a v panelu. Volitelně i SMS nebo webhookem.', 'By email five days ahead and in the panel. Optionally SMS or webhook.')],
        [_('Dostanu kredit za výpadek automaticky?', 'Is outage credit automatic?'), _('Ano, přepočte se v nejbližší faktuře. Nemusíte o nic žádat.', 'Yes, applied to your next invoice. No request needed.')],
        [_('Můžu si stáhnout historii?', 'Can I export the history?'), _('Ano, JSON API i CSV za libovolné období.', 'Yes — JSON API or CSV for any period.')]
      ]
    }),

    'about': C({
      panelsTitle: _('Jak Onhost funguje', 'How Onhost works'), panelsLead: _('Firma bez investora, s vlastními sály a se zákazníky, kteří zůstávají. Tady jsou čísla za tím.', 'A company with no investor, our own halls and customers who stay. Here are the numbers behind it.'),
      panels: [
        [_('Vlastnictví a peníze', 'Ownership and money'), _('Sto procent drží zakladatelé. Rosteme z vlastního zisku, ne z kola investic.', 'Founders hold one hundred percent. We grow from profit, not from a funding round.'), [[_('Založeno', 'Founded'), '2014'], [_('Investoři', 'Investors'), '—'], [_('Ziskoví od', 'Profitable since'), '2017'], [_('Růst 2025', 'Growth in 2025'), '+18 %']]],
        [_('Infrastruktura', 'Infrastructure'), _('Dva vlastní sály v Praze, čtyři další lokality, hardware si stavíme sami.', 'Two of our own halls in Prague, four more sites, hardware we build ourselves.'), [[_('Lokality', 'Sites'), '6'], [_('Vlastní sály', 'Own halls'), '2'], [_('Serverů', 'Servers'), '3 400+'], [_('OZE', 'Renewables'), '62 %']]],
        [_('Zákazníci', 'Customers'), _('Od jednoho webu po klastry. Nikoho nedržíme smlouvou, ale zůstávají.', 'From one website to clusters. Nobody is locked in by contract, yet they stay.'), [[_('Zákazníků', 'Customers'), '2 400+'], [_('Po 3 letech', 'After 3 years'), '87 %'], [_('Podpora do', 'Support within'), '30 min'], [_('Hodnocení', 'Rating'), '4,9 / 5']]]
      ],
      cmp: null,
      endTitle: _('Přijďte se podívat do Prahy.', 'Come and see it in Prague.'),
      endLead: _('Návštěvy datacentra vedeme každý druhý čtvrtek. Ukážeme rozvaděče, baterie i odvod tepla.', 'We run data centre tours every other Thursday — switchgear, batteries and heat recovery included.'),
      sla: {
        title: _('Čísla, která o nás něco říkají', 'The numbers that say something about us'),
        lead: _('Nemáme investora, nepřeprodáváme cizí cloud a hardware si stavíme sami. To se pozná na tom, jak dlouho u nás lidé i zákazníci zůstávají.', 'No investor, no reselling someone else\u2019s cloud, our own hardware. It shows in how long customers and staff stay.'),
        rows: [
          { k: '2014', v: _('rok založení', 'founded') },
          { k: '87 %', v: _('zákazníků zůstává po 3 letech', 'customers stay past 3 years') },
          { k: '6', v: _('datacenter, dvě vlastní', 'data centres, two our own') },
          { k: '42', v: _('lidí, NOC 24/7 v Praze', 'people, 24/7 NOC in Prague') }
        ]
      },
      plansTitle: cs ? 'Naše lokality' : 'Our locations', plansNote: cs ? 'Šest datacenter, dvě z nich stavíme a provozujeme sami.' : 'Six data centres, two of them built and run by us.',
      cat: 'Onhost', crumb: _('O nás', 'About us'),
      kicker: _('Od 2014 · 42 lidí · 6 lokalit', 'Since 2014 · 42 people · 6 locations'),
      title: _('Firma, která si své datacentrum staví, ne přeprodává', 'A company that builds its own data centre, not resells one'),
      lead: _('Začali jsme jako dva lidi s jedním rackem v Praze. Dnes provozujeme šest lokalit, vlastní bateriové systémy a hardware, který si zákazníci mohou koupit. Nemáme investora, který by nám určoval ceny.', 'We started as two people with one rack in Prague. Today we run six locations, our own battery storage and hardware customers can buy outright. No investor sets our prices.'),
      kpis: [['2014', _('rok založení', 'founded')], ['42', _('lidí v týmu', 'people on the team')], ['12 800', _('aktivních zákazníků', 'active customers')]],
      chips: [_('Bez venkovního kapitálu', 'No outside capital'), _('Vlastní hardware', 'Own hardware'), _('6 lokalit', '6 locations'), _('NOC 24/7 v Praze', '24/7 NOC in Prague'), _('Komunitní fond', 'Community fund'), _('Open-source', 'Open source')],
      plans: [
        { name: _('Praha (PRG1, PRG2)', 'Prague (PRG1, PRG2)'), tag: _('Hlavní lokalita', 'Primary site'), price: 0, priceLabel: _('2 lokality', '2 sites'), priceNote: _('TIER III, provoz od 2014', 'TIER III, running since 2014'), ctaLabel: _('Přijít na návštěvu', 'Book a tour'), specs: [_('TIER III, 2N napájení', 'TIER III, 2N power'), _('Odvod tepla do teplovodu', 'Heat into district loop'), 'PUE 1,18', _('NOC 24/7 na místě', '24/7 NOC on site'), _('Peering NIX.CZ', 'NIX.CZ peering'), _('GPU a AI kapacita', 'GPU and AI capacity')] },
        { name: _('Brno, Ostrava', 'Brno, Ostrava'), tag: '', price: 0, priceLabel: _('2 lokality', '2 sites'), priceNote: _('záložní a DR provoz', 'backup and DR operations'), ctaLabel: _('Zjistit dostupnost', 'Check availability'), specs: [_('Záložní a DR lokality', 'Backup and DR sites'), _('2N napájení', '2N power'), _('Offsite zálohy', 'Offsite backups'), _('Privátní linka do Prahy', 'Private link to Prague'), _('Colocation i hosting', 'Colocation and hosting'), _('Remote hands', 'Remote hands')] },
        { name: _('Varšava, Frankfurt, Amsterdam', 'Warsaw, Frankfurt, Amsterdam'), tag: '', price: 0, priceLabel: _('3 lokality', '3 sites'), priceNote: _('anycast PoP a DR v EU', 'anycast PoPs and EU DR'), ctaLabel: _('Zjistit dostupnost', 'Check availability'), specs: [_('Evropský dosah', 'European reach'), _('CDN a anycast PoP', 'CDN and anycast PoPs'), _('Nízká latence do EU', 'Low EU latency'), _('DR pro enterprise', 'Enterprise DR'), _('Peering na lokálních IX', 'Local IX peering'), _('Partnerská správa', 'Partner-operated')] }
      ],
      feats: [
        ['01', _('Bez investora nad námi', 'No investor above us'), _('Firma patří lidem, kteří v ní pracují. Ceny neurčuje kvartální report.', 'The company belongs to the people who work in it. Quarterly reports do not set prices.')],
        ['02', _('Hardware, který si můžete koupit', 'Hardware you can buy'), _('Neděláme z pronájmu dogma. Chcete stroj do majetku? Prodáme ho.', 'Renting is not dogma here. Want the machine as an asset? We will sell it.')],
        ['03', _('NOC v Praze, ne v cizí časové zóně', 'A NOC in Prague, not in another time zone'), _('Ve tři ráno vám odpoví někdo, kdo je od serverů dvě patra.', 'At 3am you get someone two floors from the servers.')],
        ['04', _('Komunitní fond', 'The community fund'), _('Procento z obratu jde na open-source projekty, meetupy a stipendia.', 'A percentage of revenue goes to open-source projects, meetups and grants.')],
        ['05', _('Energetika jako součást stavby', 'Energy as part of the build'), _('Baterie, fotovoltaika a odvod tepla nejsou marketing, ale položka v rozpočtu.', 'Batteries, solar and heat recovery are budget lines, not marketing.')],
        ['06', _('Píšeme, jak to je', 'We write things as they are'), _('Postmortemy, ceny, limity i to, co neumíme. Nemáme oddělení na kulaté formulace.', 'Postmortems, prices, limits and what we cannot do. Nobody here rounds the edges.')]
      ],
      tech: ['NIX.CZ', 'Proxmox', 'Kubernetes', 'PostgreSQL', 'Terraform', 'Prometheus', 'Debian', 'Electree', 'BESS'],
      bench: [
        { label: _('Zákazníci, kteří zůstávají po 3 letech', 'Customers still with us after 3 years'), pct: 100, note: '87 %' },
        { label: _('Průměr v hostingu', 'Hosting industry average'), pct: 62, note: '54 %' },
        { label: _('Levné sdílené hostingy', 'Budget shared hosting'), pct: 36, note: '31 %' }
      ],
      benchNote: _('Retence zákazníků po 36 měsících, vlastní data 2022–2025 proti veřejným odhadům trhu.', 'Customer retention after 36 months, our data 2022–2025 against public market estimates.'),
      cases: [
        { t: _('Chcete se přijít podívat', 'You want to visit'), d: _('Návštěvy datacentra v Praze děláme každý druhý čtvrtek. Stačí se ohlásit.', 'We run Prague data centre tours every other Thursday. Just book a slot.'), m: _('Praha', 'Prague') },
        { t: _('Hledáte práci', 'You are looking for a job'), d: _('NOC, podpora, platformní tým. Nábor vedeme sami, bez agentur.', 'NOC, support, platform team. We hire directly, no agencies.'), m: _('Kariéra', 'Careers') },
        { t: _('Chcete spolupracovat', 'You want to partner'), d: _('Reseller, affiliate nebo energetický projekt. Ozvěte se přímo.', 'Reseller, affiliate or an energy project. Get in touch directly.'), m: _('Partnerství', 'Partnership') }
      ],
      faq: [
        [_('Kdo Onhost vlastní?', 'Who owns Onhost?'), _('Zakladatelé a zaměstnanci. Nemáme venture kapitál ani zahraniční majitele.', 'Founders and employees. No venture capital, no foreign owners.')],
        [_('Kde přesně máte servery?', 'Where exactly are the servers?'), _('Praha (dvě lokality), Brno, Ostrava, Varšava, Frankfurt, Amsterdam. Adresy dostanete ve smlouvě.', 'Prague (two sites), Brno, Ostrava, Warsaw, Frankfurt, Amsterdam. Addresses come with the contract.')],
        [_('Můžu se přijít podívat?', 'Can I come and see it?'), _('Ano, návštěvy v Praze každý druhý čtvrtek. Pro zákazníky Enterprise kdykoli po dohodě.', 'Yes, Prague tours every other Thursday. Enterprise customers anytime by arrangement.')],
        [_('Co je komunitní fond?', 'What is the community fund?'), _('Procento obratu na open-source, meetupy a stipendia. Rozdělení zveřejňujeme ročně.', 'A percentage of revenue for open source, meetups and grants. We publish the allocation annually.')],
        [_('Máte certifikace?', 'Do you hold certifications?'), _('ISO 27001 pro provoz a připravenost na NIS2. Dokumenty pošleme na požádání.', 'ISO 27001 for operations and NIS2 readiness. Documents on request.')]
      ]
    }),

    'careers': C({
      panelsTitle: _('Jak u nás vypadá práce', 'What the job actually looks like'), panelsLead: _('Bez investora, bez agentur a bez psychotestů. Rozhodují lidé, kteří systémy provozují.', 'No investor, no agencies, no personality tests. Decisions are made by the people running the systems.'),
      panels: [
        [_('Nábor', 'Hiring'), _('Dvě kola: technický pohovor a půlden s týmem. Odpověď do pěti dnů.', 'Two rounds: a technical interview and half a day with the team. An answer within five days.'), [[_('Kol', 'Rounds'), '2'], [_('Odpověď', 'Reply'), '5 ' + _('dní', 'days')], [_('Úkol na doma', 'Take-home task'), '—'], [_('Agentury', 'Agencies'), '—']]],
        [_('Podmínky', 'Terms'), _('On-call se platí zvlášť, vzdělávání se neschvaluje a hardware si vybíráte sami.', 'On-call is paid separately, learning needs no approval and you choose your hardware.'), [[_('On-call', 'On-call'), _('placený', 'paid')], [_('Vzdělávání ročně', 'Learning budget'), '30–50 ' + _('tis. Kč', 'k CZK')], [_('Dovolená', 'Holiday'), '25 ' + _('dní', 'days')], [_('Práce z domova', 'Remote'), _('3 dny v týdnu', '3 days a week')]]],
        [_('Tým', 'The team'), _('Čtyřicet dva lidí, NOC v Praze, průměrná doba u firmy přes čtyři roky.', 'Forty-two people, a Prague NOC, average tenure over four years.'), [[_('Lidí', 'People'), '42'], [_('Po 3 letech zůstává', 'Still here after 3 years'), '81 %'], [_('Otevřených pozic', 'Open roles'), '4'], [_('Průměrná doba', 'Average tenure'), '4,3 ' + _('roku', 'years')]]]
      ],
      cmp: null,
      endTitle: _('Pošlete životopis. Ozveme se do pěti dnů.', 'Send your CV. We reply within five days.'),
      endLead: _('Dvě kola, žádné agentury, žádné psychotesty. Když to nevyjde, řekneme proč.', 'Two rounds, no agencies, no personality tests. If it is a no, we tell you why.'),
      sla: {
        title: _('Jak se u nás pracuje', 'What working here looks like'),
        lead: _('Bez venkovního investora, takže o prioritách rozhodují lidé, kteří systémy provozují. On-call se platí, vzdělávání se neschvaluje.', 'No outside investor, so priorities are set by the people running the systems. On-call is paid and learning needs no approval.'),
        rows: [
          { k: '81 %', v: _('lidí tu zůstává po 3 letech', 'still here after 3 years') },
          { k: '4', v: _('otevřené pozice', 'open roles') },
          { k: '30–50', v: _('tis. Kč na vzdělávání ročně', 'k CZK learning budget a year') },
          { k: _('+ příplatek', '+ premium'), v: _('za on-call i za zásah', 'for on-call and for callouts') }
        ]
      },
      plansTitle: cs ? 'Otevřené pozice' : 'Open roles', plansNote: cs ? 'Nábor vedeme sami, dvě kola, odpověď do pěti dnů.' : 'We hire directly — two rounds, an answer within five days.',
      cat: 'Onhost', crumb: _('Kariéra', 'Careers'),
      kicker: _('42 lidí · žádné agentury · Praha nebo remote', '42 people · no agencies · Prague or remote'),
      title: _('Hledáme lidi, kteří chtějí rozumět celému stacku', 'We are looking for people who want to understand the whole stack'),
      lead: _('Od NOC po platformní tým. Nepřeprodáváme cizí cloud, takže se u nás dá naučit, jak infrastruktura opravdu funguje — od napájení po Kubernetes.', 'From the NOC to the platform team. We do not resell someone else\u2019s cloud, so you can learn how infrastructure actually works — from power distribution to Kubernetes.'),
      kpis: [['4', _('otevřené pozice', 'open roles')], [_('2 týdny', '2 weeks'), _('od pohovoru k nabídce', 'from interview to offer')], ['0', _('agentur v procesu', 'agencies involved')]],
      chips: [_('Praha nebo remote', 'Prague or remote'), _('4 dny v týnu možné', '4-day week possible'), _('Vzdělávací budget', 'Learning budget'), _('Hardware podle sebe', 'Hardware of your choice'), _('Podíl na výsledku', 'Profit share'), _('Bez oblečení na míru', 'No dress code')],
      plans: [
        { name: _('NOC technik', 'NOC engineer'), tag: _('Nabíráme', 'Hiring'), price: 0, priceLabel: _('55–75 tis. Kč', '55–75k CZK'), priceNote: _('měsíčně, dle zkušeností', 'per month, by experience'), ctaLabel: _('Poslat životopis', 'Send your CV'), specs: [_('Praha, směny 24/7', 'Prague, 24/7 shifts'), _('Linux, sítě, monitoring', 'Linux, networking, monitoring'), _('Zaškolení tři měsíce', 'Three-month training'), _('Přesah do platform týmu', 'Path into the platform team'), _('Juniory bereme', 'Juniors welcome')] },
        { name: _('Platformní inženýr', 'Platform engineer'), tag: _('Nabíráme', 'Hiring'), price: 0, priceLabel: _('90–140 tis. Kč', '90–140k CZK'), priceNote: _('měsíčně, dle zkušeností', 'per month, by experience'), ctaLabel: _('Poslat životopis', 'Send your CV'), specs: [_('Praha nebo remote', 'Prague or remote'), 'Kubernetes, Terraform, Go', _('Vlastní projekty na platformě', 'Own platform projects'), _('On-call s příplatkem', 'Paid on-call'), _('Konference v ceně', 'Conferences covered')] },
        { name: _('Technická podpora', 'Technical support'), tag: _('Nabíráme', 'Hiring'), price: 0, priceLabel: _('50–70 tis. Kč', '50–70k CZK'), priceNote: _('měsíčně, dle zkušeností', 'per month, by experience'), ctaLabel: _('Poslat životopis', 'Send your CV'), specs: [_('Praha nebo remote', 'Prague or remote'), _('PHP, Linux, DNS, e-mail', 'PHP, Linux, DNS, email'), _('Píšeme i dokumentaci', 'You write docs too'), _('Bez skriptů na odpovědi', 'No canned replies'), _('Kariérní posun do NOC', 'Path into the NOC')] }
      ],
      feats: [
        ['01', _('Vlastní infrastruktura znamená vlastní znalosti', 'Own infrastructure means real knowledge'), _('Nekonfigurujete cizí konzoli. Vidíte, jak jde proud od rozvaděče do virtuálky.', 'You are not clicking someone else\u2019s console. You see power go from the busbar to the VM.')],
        ['02', _('Nábor bez agentur', 'Hiring without agencies'), _('Píšete rovnou s tím, kdo bude váš vedoucí. Dvě kola, dva týdny.', 'You talk straight to your future lead. Two rounds, two weeks.')],
        ['03', _('On-call, který se platí', 'On-call that is paid'), _('Příplatek za držení telefonu i za skutečný zásah, ne jen dobré slovo.', 'Paid for carrying the phone and for actually being called, not just thanked.')],
        ['04', _('Čtyřdenní týden je možný', 'A four-day week is possible'), _('Individuálně, u většiny pozic. Neděláme z toho benefit do inzerátu.', 'Individually, for most roles. We do not use it as ad copy.')],
        ['05', _('Vzdělávací budget bez schvalování', 'Learning budget without approvals'), _('Do třiceti tisíc ročně si o kurzu nebo konferenci rozhodnete sami.', 'Up to thirty thousand a year, you decide on the course or conference yourself.')],
        ['06', _('Podíl na výsledku', 'A share of the result'), _('Firma nepatří fondu, takže se dá dělit se lidmi, co ji drží v provozu.', 'The company is not owned by a fund, so the result can be shared with the people running it.')]
      ],
      tech: ['Linux', 'Kubernetes', 'Terraform', 'Go', 'PostgreSQL', 'Prometheus', 'BGP', 'Proxmox', 'Ansible'],
      bench: [
        { label: _('Lidé, kteří u nás zůstávají po 3 letech', 'People still here after 3 years'), pct: 100, note: '81 %' },
        { label: _('Průměr v IT v ČR', 'Czech IT average'), pct: 66, note: '54 %' },
        { label: _('Velké outsourcingové firmy', 'Large outsourcing firms'), pct: 44, note: '36 %' }
      ],
      benchNote: _('Retence zaměstnanců po 36 měsících, vlastní data 2022–2025 proti veřejným průzkumům.', 'Employee retention after 36 months, our data 2022–2025 against public surveys.'),
      cases: [
        { t: _('Junior, který chce k železu', 'A junior who wants to touch hardware'), d: _('NOC je nejlepší škola infrastruktury, jakou v Česku najdete.', 'The NOC is the best infrastructure school in the country.'), m: 'NOC' },
        { t: _('Senior unavený z konzolí', 'A senior tired of consoles'), d: _('Platformní tým staví služby, ne tikety. Vlastní rozhodnutí, vlastní on-call.', 'The platform team builds services, not tickets. Own decisions, own on-call.'), m: _('Platforma', 'Platform') },
        { t: _('Člověk, který umí vysvětlovat', 'Someone who can explain'), d: _('Podpora u nás píše dokumentaci a mluví s vývojáři, ne s formulářem.', 'Support here writes documentation and talks to developers, not to a form.'), m: _('Podpora', 'Support') }
      ],
      faq: [
        [_('Jak vypadá pohovor?', 'What is the interview like?'), _('Dvě kola: technické s budoucím vedoucím a půlhodina o očekáváních. Bez psychotestů.', 'Two rounds: technical with your future lead and half an hour on expectations. No personality tests.')],
        [_('Berete juniory?', 'Do you hire juniors?'), _('Ano, do NOC a podpory pravidelně. Zaškolení trvá tři měsíce a je placené.', 'Yes, into the NOC and support regularly. Training takes three months and is paid.')],
        [_('Můžu pracovat plně remote?', 'Can I work fully remote?'), _('Platforma a podpora ano, NOC ne — tam se sedí u strojů.', 'Platform and support, yes. The NOC, no — that seat is next to the machines.')],
        [_('Jaké máte technologie?', 'What is the stack?'), _('Debian, Proxmox, Kubernetes, Terraform, Go, PostgreSQL, Prometheus. Bez legacy strašidel.', 'Debian, Proxmox, Kubernetes, Terraform, Go, PostgreSQL, Prometheus. No legacy skeletons.')],
        [_('Kdy se dozvím výsledek?', 'When will I hear back?'), _('Do pěti pracovních dnů po druhém kole, ať je odpověď jakákoli.', 'Within five business days of the second round, whatever the answer.')]
      ]
    }),

    'support': C({
      panelsTitle: _('Jak podpora funguje', 'How support works'), panelsLead: _('Odpovídá vám člověk, který ten systém provozuje. Žádná první linie, která přepošle ticket dál.', 'You get a reply from somebody who runs the system. No first line that just forwards the ticket.'),
      panels: [
        [_('Kanály a doby', 'Channels and times'), _('Telefon a chat běží nepřetržitě, e-mail vyřizujeme ve stejné frontě.', 'Phone and chat run around the clock; email sits in the same queue.'), [[_('Telefon', 'Phone'), '24/7'], [_('Chat', 'Chat'), _('medián 4 min', '4 min median')], [_('E-mail a tikety', 'Email and tickets'), _('medián 18 min', '18 min median')], [_('P1 s SLA', 'P1 with SLA'), '15 min']]],
        [_('Kdo odpovídá', 'Who answers'), _('Devět lidí v NOC v Praze, všichni s přístupem k produkci a právem zasáhnout.', 'Nine people in the Prague NOC, all with production access and the authority to act.'), [[_('Lidí v NOC', 'People in the NOC'), '9'], [_('První linie', 'First-line filter'), '—'], [_('Česky a anglicky', 'Czech and English'), '✓'], [_('Zásah místo rady', 'Fix, not advice'), '✓']]],
        [_('Eskalace', 'Escalation'), _('Když se problém nehne za hodinu, jde na jmenovaného inženýra a vy o tom víte.', 'If a problem does not move within an hour it goes to a named engineer and you are told.'), [[_('Eskalace po', 'Escalation after'), '60 min'], [_('Jmenovaný inženýr', 'Named engineer'), _('od Enterprise', 'from Enterprise')], [_('Noční zásah', 'Night callout'), _('v ceně', 'included')], [_('Postmortem', 'Postmortem'), _('do 5 dnů', 'within 5 days')]]]
      ],
      cmp: null,
      plansTitle: cs ? 'Úrovně podpory' : 'Support tiers', plansNote: cs ? 'Standardní podpora je u všech tarifů v ceně, bez příplatku.' : 'Standard support is included with every plan at no charge.',
      cat: _('Podpora', 'Support'), crumb: _('Kontakt a podpora', 'Contact and support'),
      kicker: _('24/7 · odpověď do 10 minut · lidi, ne skripty', '24/7 · 10-minute response · people, not scripts'),
      title: _('Podpora, která čte váš error log', 'Support that reads your error log'),
      lead: _('Chat, telefon, ticket i e-mail — všechno obsluhují techniku, kteří mají přístup k serverům. Žádná první úroveň, která jen kopíruje odkazy do dokumentace.', 'Chat, phone, tickets and email — all handled by engineers with server access. No first line that just pastes documentation links.'),
      kpis: [['8 min', _('medián první odpovědi', 'median first response')], ['92 %', _('vyřešeno v prvním kontaktu', 'resolved on first contact')], ['24/7', _('včetně svátků', 'holidays included')]],
      chips: [_('Chat v panelu', 'In-panel chat'), _('Telefon 24/7', 'Phone 24/7'), _('Tickety', 'Tickets'), _('E-mail', 'Email'), _('Sdílená obrazovka', 'Screen sharing'), _('Česky a anglicky', 'Czech and English')],
      plans: [
        { name: _('Standardní', 'Standard'), tag: _('V ceně', 'Included'), price: 0, priceLabel: _('V ceně', 'Included'), priceNote: _('u všech tarifů', 'on every plan'), ctaLabel: _('Napsat na chat', 'Open the chat'), specs: [_('Chat a e-mail 24/7', 'Chat and email 24/7'), _('Odpověď do 30 minut', 'Response within 30 minutes'), _('Telefon v pracovní dny', 'Phone on business days'), _('Knowledgebase a fórum', 'Knowledgebase and forum'), _('Migrace zdarma', 'Free migration'), _('Bez příplatku', 'No surcharge')] },
        { name: _('Prioritní', 'Priority'), tag: _('Nejčastější', 'Most common'), price: 990, specs: [_('Odpověď do 10 minut', 'Response within 10 minutes'), _('Telefon 24/7', 'Phone 24/7'), _('Přednost ve frontě', 'Queue priority'), _('Konzultace 2 h / měsíc', '2 h consulting / month'), _('Pomoc s laděním aplikace', 'Application tuning help'), _('Reporty incidentů', 'Incident reports')] },
        { name: _('Jmenovaný technik', 'Named engineer'), tag: '', price: 4900, specs: [_('Váš technik a jeho telefon', 'Your engineer and their number'), _('Reakce do 15 minut na P1', '15-minute P1 response'), _('Znalost vaší architektury', 'Knows your architecture'), _('Měsíční revize provozu', 'Monthly operations review'), _('Plánování změn s vámi', 'Change planning with you'), _('Součást Enterprise', 'Part of Enterprise')] }
      ],
      feats: [
        ['01', _('Odpovídá technik, ne skript', 'An engineer answers, not a script'), _('Ten, kdo vám odpoví, se může podívat do logu a restartovat službu.', 'Whoever answers can open your log and restart the service.')],
        ['02', _('Telefon, který někdo zvedne', 'A phone somebody answers'), _('Ve tři ráno taky. NOC sedí v Praze, ne v callcentru za mořem.', 'At 3am too. The NOC sits in Prague, not in an overseas call centre.')],
        ['03', _('Migrace a nastavení zdarma', 'Migration and setup at no cost'), _('Přesun webu, nastavení DNS, ladění PHP — v ceně, ne za hodinovku.', 'Moving a site, DNS setup, PHP tuning — included, not billed hourly.')],
        ['04', _('Sdílená obrazovka, když je to složité', 'Screen sharing when it gets messy'), _('Někdy je rychlejší se podívat spolu než psát patnáct zpráv.', 'Sometimes looking together beats fifteen messages.')],
        ['05', _('Dokumentace, kterou píšou stejní lidé', 'Docs written by the same people'), _('Když se objeví častý dotaz, vznikne článek. Podpora není sklad tajemství.', 'A frequent question becomes an article. Support is not a vault of secrets.')],
        ['06', _('Reporty incidentů bez ptaní', 'Incident reports without asking'), _('Po výpadku dostanete popis, co se stalo a co jsme změnili.', 'After an outage you get what happened and what we changed.')]
      ],
      tech: [_('Chat v panelu', 'Panel chat'), _('Telefon', 'Phone'), _('Ticket systém', 'Ticketing'), 'E-mail', 'Slack', _('Sdílená obrazovka', 'Screen share'), 'Status page'],
      bench: [
        { label: _('Onhost prioritní podpora', 'Onhost priority support'), pct: 100, note: _('8 min', '8 min') },
        { label: _('Onhost standardní podpora', 'Onhost standard support'), pct: 55, note: _('22 min', '22 min') },
        { label: _('Průměr českého hostingu', 'Czech hosting average'), pct: 14, note: _('2 h 40 min', '2 h 40 min') }
      ],
      benchNote: _('Medián doby do první lidské odpovědi, červen 2026, vlastní měření proti veřejným testům.', 'Median time to first human reply, June 2026, our measurement against public tests.'),
      cases: [
        { t: _('Web nejede a nevíte proč', 'The site is down and you do not know why'), d: _('Chat, technik se podívá do logu a řekne vám příčinu, ne obecnou radu.', 'Chat; an engineer reads the log and tells you the cause, not a generic tip.'), m: _('Standardní', 'Standard') },
        { t: _('E-shop ve špičce', 'A store at peak'), d: _('Prioritní fronta a telefon 24/7, když každá minuta stojí objednávky.', 'Priority queue and 24/7 phone when every minute costs orders.'), m: _('Prioritní', 'Priority') },
        { t: _('Firma s vlastním IT', 'A company with its own IT'), d: _('Jmenovaný technik, který zná vaši architekturu a plánuje změny s vámi.', 'A named engineer who knows your architecture and plans changes with you.'), m: _('Jmenovaný technik', 'Named engineer') }
      ],
      faq: [
        [_('Jak se ozvu nejrychleji?', 'What is the fastest channel?'), _('Chat v panelu. Medián osm minut, u prioritní podpory pod pět.', 'Panel chat. Median eight minutes, under five on priority support.')],
        [_('Mluvíte česky?', 'Do you speak Czech?'), _('Ano, celý tým. Anglicky také, další jazyky přes partnera.', 'Yes, the whole team. English too; other languages via a partner.')],
        [_('Pomůžete i s aplikací, ne jen se serverem?', 'Will you help with the app, not just the server?'), _('Do rozumné míry ano. Chybu v PHP nebo pomalý dotaz najdeme s vámi.', 'Within reason, yes. We will find a PHP error or a slow query with you.')],
        [_('Účtujete práci podpory?', 'Do you bill support work?'), _('Ne u běžných věcí. Rozsáhlé práce nabídneme dopředu s cenou a odhadem.', 'Not for routine work. Larger jobs get a quote and estimate up front.')],
        [_('Kde najdu telefon?', 'Where is the phone number?'), _('V hlavičce webu a v panelu. Prioritní zákazníci mají přímé číslo na NOC.', 'In the site header and in the panel. Priority customers get a direct NOC line.')]
      ]
    })
  };
}
