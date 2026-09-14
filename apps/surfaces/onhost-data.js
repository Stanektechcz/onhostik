// Onhost — datová vrstva prototypu.
// Každá funkce vrací data ve tvaru, v jakém je má vracet API (viz docs-backend-handoff.md).
// Napojení na backend = nahradit tělo funkce fetchem; komponenta se nemění.
// cs = true → česká jazyková vrstva, jinak anglická.
window.ONHOST_DATA = {
  catalog: function (cs) {
    return [
      { code: 'WEB', cat: 'web', price: 89, cs: ['Webhosting', 'NVMe, PHP 8.4, LiteSpeed a instalace WordPressu na dva kliky.', 'Nejoblíbenější'], en: ['Web hosting', 'NVMe, PHP 8.4, LiteSpeed and a two-click WordPress install.', 'Most popular'] },
      { code: 'GAME', cat: 'game', price: 149, cs: ['Gamehosting', 'Minecraft, CS2, Rust i Palworld s vlastním panelem a konzolí.', 'Anti-DDoS'], en: ['Game hosting', 'Minecraft, CS2, Rust and Palworld with your own panel and console.', 'Anti-DDoS'] },
      { code: 'VPS', cat: 'dev', price: 249, cs: ['VPS / VDS', 'Dedikované jádra AMD EPYC, root přístup, snapshoty po hodinách.', ''], en: ['VPS / VDS', 'Dedicated AMD EPYC cores, root access, hourly snapshots.', ''] },
      { code: 'MGD', cat: 'firm', price: 690, cs: ['Managed hosting', 'Updaty, monitoring a ladění necháš na nás. Ty řešíš produkt.', 'S SLA'], en: ['Managed hosting', 'Updates, monitoring and tuning are on us. You ship product.', 'With SLA'] },
      { code: 'GIT', cat: 'dev', price: 119, cs: ['Git repo hosting', 'Privátní repozitáře, review, CI runnery v EU. Import z GitHubu.', ''], en: ['Git repo hosting', 'Private repos, reviews, EU-based CI runners. Import from GitHub.', ''] },
      { code: 'DEV', cat: 'dev', price: 179, cs: ['Devhosting', 'Python, Docker, Next.js, Node. Preview prostředí ke každému PR.', 'Nové'], en: ['Devhosting', 'Python, Docker, Next.js, Node. A preview env for every PR.', 'New'] },
      { code: 'S3', cat: 'firm', price: 59, cs: ['Object storage', 'S3 kompatibilní úložiště s CDN. Platíš jen za to, co leží.', ''], en: ['Object storage', 'S3-compatible storage with CDN. Pay only for what sits there.', ''] },
      { code: 'DB', cat: 'firm', price: 199, cs: ['Databáze', 'Postgres, MySQL a Redis jako služba — se zálohou a replikou.', ''], en: ['Databases', 'Postgres, MySQL and Redis as a service — backups and replicas.', ''] }
    ];
  },

  plans: function (cs) {
    return [
      { key: 'start', price: 149, hi: false, cs: ['Start', '', 'Pro první projekt nebo portfolio.', ['2 vCPU / 4 GB RAM', '80 GB NVMe', 'Neomezené SSL', 'Denní záloha 7 dní', 'Podpora e-mailem']], en: ['Start', '', 'For a first project or a portfolio.', ['2 vCPU / 4 GB RAM', '80 GB NVMe', 'Unlimited SSL', 'Daily backup, 7 days', 'E-mail support']] },
      { key: 'pro', price: 449, hi: true, cs: ['Pro', 'Nejčastější volba', 'Pro produkční weby a aplikace.', ['6 vCPU / 16 GB RAM', '320 GB NVMe', 'CI/CD runner v ceně', 'Hodinové zálohy 30 dní', 'Podpora do 30 minut']], en: ['Pro', 'Most chosen', 'For production sites and apps.', ['6 vCPU / 16 GB RAM', '320 GB NVMe', 'CI/CD runner included', 'Hourly backups, 30 days', 'Support within 30 min']] },
      { key: 'scale', price: 1290, hi: false, cs: ['Scale', 'SLA 99,99 %', 'Pro týmy s reálným trafficem.', ['16 vCPU / 64 GB RAM', '1 TB NVMe + replika', 'Dedikovaný runner a cache', 'Managed monitoring', 'Telefonní podpora 24/7']], en: ['Scale', 'SLA 99.99%', 'For teams with real traffic.', ['16 vCPU / 64 GB RAM', '1 TB NVMe + replica', 'Dedicated runner and cache', 'Managed monitoring', '24/7 phone support']] }
    ];
  },

  compare: function (cs) {
    return (cs ? [
      ['vCPU', '2', '6', '16'], ['RAM', '4 GB', '16 GB', '64 GB'], ['NVMe disk', '80 GB', '320 GB', '1 TB'],
      ['Přenos dat', cs ? 'neomezeně' : 'unlimited', 'neomezeně', 'neomezeně'], ['Zálohy', '7 dní', '30 dní', '30 dní + replika'],
      ['CI/CD runner', '—', '1× shared', cs ? '2× dedikovaný' : '2× dedicated'], ['Anti-DDoS', '100 Gbps', '600 Gbps', '1,2 Tbps'],
      ['SLA', '99,9 %', '99,95 %', '99,99 %'], [cs ? 'Reakce podpory' : 'Support response', '8 h', '30 min', '10 min / telefon']
    ] : [
      ['vCPU', '2', '6', '16'], ['RAM', '4 GB', '16 GB', '64 GB'], ['NVMe disk', '80 GB', '320 GB', '1 TB'],
      ['Transfer', 'unlimited', 'unlimited', 'unlimited'], ['Backups', '7 days', '30 days', '30 days + replica'],
      ['CI/CD runner', '—', '1× shared', '2× dedicated'], ['Anti-DDoS', '100 Gbps', '600 Gbps', '1.2 Tbps'],
      ['SLA', '99.9%', '99.95%', '99.99%'], ['Support response', '8 h', '30 min', '10 min / phone']
    ]);
  },

  status: function (cs) {
    return [
      { n: cs ? 'Webhosting cluster' : 'Web hosting cluster', u: '99,998 %', bad: [] },
      { n: cs ? 'Gameservery (PRG)' : 'Game servers (PRG)', u: '99,991 %', bad: [11] },
      { n: 'VPS / VDS', u: '100 %', bad: [] },
      { n: 'Git & CI runners', u: '99,982 %', bad: [4, 5] },
      { n: cs ? 'Panel & API' : 'Panel & API', u: '99,999 %', bad: [] },
      { n: 'Object storage', u: '99,996 %', bad: [19] }
    ];
  },

  locations: function (cs) {
    return [
      ['PRG', 'Praha', 'Česko', '4 ms', 1], ['BRQ', 'Brno', 'Česko', '7 ms', 1], ['FRA', 'Frankfurt', cs ? 'Německo' : 'Germany', '11 ms', 1],
      ['AMS', 'Amsterdam', cs ? 'Nizozemsko' : 'Netherlands', '14 ms', 1], ['WAW', cs ? 'Varšava' : 'Warsaw', cs ? 'Polsko' : 'Poland', '18 ms', 1],
      ['IAD', 'Ashburn', 'USA', '92 ms', 0]
    ];
  },

  changelog: function (cs) {
    return cs ? [
          ['25. 8.', 'panel', 'PANEL', 'Blueprints a plánované akce v okně', 'Uložené konfigurace k opakovanému nasazení a možnost naplánovat restart nebo resize do okna, které si vyberete.'],
          ['22. 8.', 'fix', 'OPRAVA', 'Migrační skript přenáší i částečné indexy', 'Naše chyba: skript vynechával indexy se starší syntaxí, což u jednoho zákazníka zpomalilo dotazy z 34 na 210 ms. Kredit jsme vrátili sami.'],
          ['18. 8.', 'infra', 'INFRA', 'Nový uzel v PRG2, evakuace hv-07', 'Live migrace 14 instancí bez odstávky. Zákazníci nedostali ani alert — nebylo co hlásit.'],
          ['14. 8.', 'panel', 'PANEL', 'Oznámení v prohlížeči a noční klid', 'V noci vzbudí jen incident u vaší služby. Ostatní čeká do rána, protože to není hodné vzbudit.'],
          ['12. 8.', 'api', 'API', 'Endpoint /v2/servers vrací stav provisioning', 'Terraform už nemusí čekat na pevný timeout. Upozornil na to zákazník na fóru, dostal za to kredit.'],
          ['08. 8.', 'game', 'GAME', 'Palworld a Enshrouded v one-click instalaci', 'Import světa z jiného hostingu děláme my. Sloty jde přesouvat mezi servery bez restartu.'],
          ['02. 8.', 'fix', 'OPRAVA', 'Panel padal při 400+ DNS záznamech', 'Zdrojem byl náš render, ne vaše zóna. Opraveno do 36 hodin od nahlášení.'],
          ['29. 7.', 'infra', 'INFRA', 'Anti-DDoS filtruje 480 Gbps v Praze', 'Ne v cizí scrubbing centrále. Latenci hráčů to nezmění, což je celý smysl.']
        ] : [
          ['25 Aug', 'panel', 'PANEL', 'Blueprints and scheduled actions', 'Saved configurations for redeployment, plus scheduling a restart or resize into a window you pick.'],
          ['22 Aug', 'fix', 'FIX', 'The migration script now carries partial indexes', 'Our mistake: the script skipped indexes with older syntax, which slowed one customer’s queries from 34 to 210 ms. We returned the credit ourselves.'],
          ['18 Aug', 'infra', 'INFRA', 'New node in PRG2, hv-07 evacuated', 'Live-migrated 14 instances with no downtime. Customers did not even get an alert — there was nothing to report.'],
          ['14 Aug', 'panel', 'PANEL', 'Browser notifications and quiet hours', 'At night only an incident on your own service wakes you. The rest waits until morning, because it is not worth waking for.'],
          ['12 Aug', 'api', 'API', '/v2/servers now returns a provisioning state', 'Terraform no longer needs a hard timeout. A customer pointed it out on the forum and earned credit for it.'],
          ['08 Aug', 'game', 'GAME', 'Palworld and Enshrouded in one-click installs', 'We import worlds from other hosts ourselves. Slots move between servers without a restart.'],
          ['02 Aug', 'fix', 'FIX', 'The panel crashed with 400+ DNS records', 'The cause was our rendering, not your zone. Fixed within 36 hours of the report.'],
          ['29 Jul', 'infra', 'INFRA', 'Anti-DDoS scrubs 480 Gbps in Prague', 'Not in a foreign scrubbing centre. It does not change player latency, which is the entire point.']
        ];
  }
};
