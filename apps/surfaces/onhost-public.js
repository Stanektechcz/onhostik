/* onhost-public.js — veřejná data pro prezentační web.
 * Vytaženo z původních ploch Onhost-api.dc.html a Onhost-sla.dc.html, aby
 * sjednocená stránka nemusela obsah opisovat podruhé. Tvary zůstávají 1:1.
 * window.ONHOST_PUBLIC = { api: {...}, sla: {...} }
 */
(function () {
  'use strict';
  if (window.ONHOST_PUBLIC) return;
  var HOOKS = [
    {
      ev: 'service.incident',
      d: 'incident u vaší služby',
      retry: 'retry 24 h · exponenciálně',
      note: 'Posíláme jen incidenty u služeb, které skutečně máte. Pole affects_you je tam proto, aby váš skript nemusel hádat, jestli se ho to týká — a first_seen je čas, kdy jsme to zjistili my, ne kdy jsme to zveřejnili.',
      body: `{
  "event": "service.incident",
  "sent_at": "2026-08-24T02:14:09Z",
  "first_seen": "2026-08-24T02:11:38Z",
  "service": { "id": "srv_db_primary", "name": "db-primary", "region": "PRG1" },
  "severity": "P1",
  "affects_you": true,
  "summary": "Medián dotazu vzrostl z 34 ms na 210 ms",
  "engineer": { "name": "Petr Doležal", "role": "inženýr databází" },
  "ticket": "4821",
  "postmortem_due": "2026-08-27T00:00:00Z"
}`
    },
    {
      ev: 'backup.completed',
      d: 'záloha dokončena',
      retry: 'retry 24 h',
      note: 'Restore_estimate_seconds není odhad z ceníku, ale čas poslední skutečné obnovy téže databáze. Verify_status říká, že jsme zálohu opravdu otevřeli — záloha, kterou nikdo nezkusil obnovit, je jen naděje.',
      body: `{
  "event": "backup.completed",
  "sent_at": "2026-08-25T06:04:11Z",
  "service": { "id": "srv_db_primary", "name": "db-primary" },
  "size_bytes": 19783286784,
  "copies": [
    { "region": "PRG1", "immutable": false },
    { "region": "BRQ1", "immutable": true },
    { "region": "offline", "immutable": true }
  ],
  "verify_status": "opened_and_checked",
  "restore_estimate_seconds": 660,
  "restore_last_real_seconds": 660
}`
    },
    {
      ev: 'invoice.forecast',
      d: 'odhad faktury a limit',
      retry: 'retry 24 h',
      note: 'Odhad posíláme v průběhu měsíce, ne až s fakturou. Když překročíte limit, upozorníme — neškrtíme a nic nevypínáme; limit je pojistka proti chybě ve vaší automatizaci, ne proti nám.',
      body: `{
  "event": "invoice.forecast",
  "sent_at": "2026-08-25T00:12:00Z",
  "period": "2026-08",
  "forecast_czk": 23100,
  "previous_czk": 21800,
  "delta_reason": "gpu_hours",
  "credit_available_czk": 4200,
  "spend_limit_czk": null,
  "on_limit": "notify_only",
  "recommendation": {
    "action": "reserve_gpu",
    "saves_czk_per_month": 4000,
    "lowers_our_revenue": true
  }
}`
    },
    {
      ev: 'sla.credit_issued',
      d: 'kredit za SLA připsán',
      retry: 'retry 24 h',
      note: 'Tohle vám přijde, i když o kredit nepožádáte — a requested_by_customer je vždy false, protože žádat o něj nemusíte. Missed_by_seconds je skutečné zpoždění, ne zaokrouhlené na minuty směrem k nám.',
      body: `{
  "event": "sla.credit_issued",
  "sent_at": "2026-08-25T09:00:04Z",
  "reason": "response_time_missed",
  "priority": "P2",
  "promised_seconds": 3600,
  "actual_seconds": 3840,
  "missed_by_seconds": 240,
  "credit_czk": 1240,
  "requested_by_customer": false,
  "cashable": true,
  "audit_entry": "aud_9f31c7"
}`
    },
    {
      ev: 'maintenance.scheduled',
      d: 'plánovaný zásah',
      retry: 'retry 24 h',
      note: 'Movable_until je čas, do kterého si okno můžete posunout, a blackout_respected potvrzuje, že jsme se vyhnuli vašemu zakázanému oknu. Zásah bez dopadu na vás vám jako odstávku neposíláme vůbec.',
      body: `{
  "event": "maintenance.scheduled",
  "sent_at": "2026-08-29T08:00:00Z",
  "window_start": "2026-09-12T00:00:00Z",
  "window_end": "2026-09-12T02:00:00Z",
  "services": ["srv_mail_eu"],
  "expected_downtime_seconds": 0,
  "reason": "switch_replacement",
  "movable_until": "2026-09-10T00:00:00Z",
  "blackout_respected": { "from": "14:00", "to": "15:30" },
  "notice_days": 14
}`
    },
    {
      ev: 'access.requested',
      d: 'žádost o přístup k vaší službě',
      retry: 'retry 24 h',
      note: 'Bez vašeho potvrzení se náš inženýr do vaší služby nedostane. Expires_in_seconds je čtyři hodiny a session_recorded znamená, že celou relaci uvidíte v auditu — včetně toho, co v ní psal.',
      body: `{
  "event": "access.requested",
  "sent_at": "2026-08-25T11:41:52Z",
  "engineer": { "name": "Petr Doležal", "role": "inženýr databází" },
  "service": { "id": "srv_db_primary", "name": "db-primary" },
  "scope": "shell_readonly",
  "reason": "Tiket 4821 — kontrola plánu dotazu",
  "ticket": "4821",
  "expires_in_seconds": 14400,
  "session_recorded": true,
  "approval_required": true
}`
    }
  ];
  function apiData() {
    const M = { GET: '#1a7f37', POST: 'var(--color-accent-700)', PATCH: '#a06f00', DELETE: '#c1121f' };
    const ep = (m, p, d) => ({ m, p, d, c: M[m] });
    return {
      heads: [
        { k: 'Základ', v: 'REST + JSON', n: 'žádné SDK není povinné' },
        { k: 'Limit', v: '600 / min', n: 'zpomalí, nezavře spojení' },
        { k: 'Status API', v: 'bez klíče', n: 'a bez limitu' },
        { k: 'Verze', v: 'v1', n: 'v2 bez odstřižení v1' }
      ],
      auth: [
        { t: 'Klíč s rozsahem', d: 'Klíč vytvoříte v panelu, dostane rozsah (čtení, servery, fakturace) a datum expirace. Tajemství zobrazíme jednou — podruhé už ho neumíme přečíst ani my.', c: 'Authorization: Bearer onh_live_9f2c…' },
        { t: 'Limit zpomaluje', d: 'Nad 600 požadavků za minutu odpovídáme pomaleji a pošleme hlavičku s tím, kolik zbývá. Spojení nezavíráme, protože tvrdý limit rozbije zálohovací skript v nejhorší možnou chvíli.', c: 'X-RateLimit-Remaining: 148' },
        { t: 'Potvrzení u nevratných akcí', d: 'Smazání služby nebo zálohy potřebuje hlavičku s identifikátorem objektu. Skript, který ji nepošle, dostane chybu místo tichého úspěchu.', c: 'X-Confirm-Delete: srv_app-prod' },
        { t: 'Chyby v lidské řeči', d: 'Každá chyba má kód, větu s vysvětlením a odkaz na návod. Text píše inženýr, který endpoint provozuje, ne generátor z definice.', c: '{"error":"quota_exceeded","help":"/navody/kvoty"}' }
      ],
      endpoints: [
        ep('GET', '/v1/servers', 'seznam instancí s konfigurací, lokalitou a stavem'),
        ep('POST', '/v1/servers', 'nová instance · běží do 55 sekund, bez schvalování do 10 kusů'),
        ep('PATCH', '/v1/servers/{id}', 'změna velikosti · v okně, které vyberete, ne hned teď'),
        ep('DELETE', '/v1/servers/{id}', 'smazání · vyžaduje potvrzovací hlavičku'),
        ep('GET', '/v1/backups', 'body obnovy včetně druhé kopie a data testu obnovy'),
        ep('POST', '/v1/backups/{id}/restore', 'obnova · vrací odhad doby, ne jen identifikátor úlohy'),
        ep('GET', '/v1/dns/zones/{zone}', 'zóna se záznamy, TTL a stavem DNSSEC'),
        ep('GET', '/v1/invoices', 'faktury s rozpadem po položkách, včetně nulových'),
        ep('GET', '/v1/usage/forecast', 'odhad faktury do konce měsíce z vaší skutečné spotřeby'),
        ep('POST', '/v1/tickets', 'tiket s kontextem služby · SLA běží od přijetí, ne od přečtení'),
        ep('GET', '/v1/audit', 'kdo co kdy udělal · včetně zásahů naší podpory'),
        ep('POST', '/v1/webhooks', 'odběr událostí · retry 24 hodin, pak zpráva do panelu'),
        ep('GET', '/v1/export', 'export všech dat účtu · jeden požadavek, bez dotazu na důvod')
      ],
      examples: [
        { t: 'Server, který běží do 55 sekund', n: 'bez čekání na schválení a bez telefonátu od obchodu', lines: [
          { t: 'curl -X POST https://api.onhost.cz/v1/servers \\', c: '#d8d6d4' },
          { t: '  -H "Authorization: Bearer $ONHOST_TOKEN" \\', c: '#d8d6d4' },
          { t: '  -d \'{"plan":"epyc-8-32","site":"prg1","name":"app-prod-3"}\'', c: '#b8ff2e' },
          { t: '# 201 Created · ready_in_seconds: 55', c: 'rgba(216,214,212,.5)' }
        ] },
        { t: 'Kolik zaplatím do konce měsíce', n: 'stejné číslo, jaké vidíte v panelu — z jednoho zdroje', lines: [
          { t: 'curl https://api.onhost.cz/v1/usage/forecast \\', c: '#d8d6d4' },
          { t: '  -H "Authorization: Bearer $ONHOST_TOKEN"', c: '#d8d6d4' },
          { t: '{"forecast_czk":23100,"vs_last_month":"+6%","driver":"gpu"}', c: '#b8ff2e' },
          { t: '# limit útraty neškrtí, jen upozorní', c: 'rgba(216,214,212,.5)' }
        ] },
        { t: 'Stav služeb bez klíče', n: 'kdo si chce hlídat dostupnost sám, nepotřebuje naše svolení', lines: [
          { t: 'curl https://status.onhost.cz/api/v1/summary', c: '#d8d6d4' },
          { t: '{"status":"operational","sites":9,"open_incidents":0}', c: '#b8ff2e' },
          { t: '# bez tokenu, bez limitu, bez registrace', c: 'rgba(216,214,212,.5)' }
        ] },
        { t: 'Export infrastruktury do Terraformu', n: 'včetně stavu · odchod od nás má být jednoduchý', lines: [
          { t: 'onhost export terraform > main.tf', c: '#d8d6d4' },
          { t: '# 7 resources · servers, dns, backups, firewall', c: 'rgba(216,214,212,.5)' },
          { t: 'terraform plan', c: '#d8d6d4' },
          { t: 'No changes. Infrastructure matches configuration.', c: '#b8ff2e' }
        ] }
      ],
      errors: [
        { code: '429', slug: 'rate_limited', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#e0a100 30%,transparent)',
          d: 'Zpomalujeme, nezavíráme spojení. Retry-after je skutečný čas, po kterém to projde, ne obecných 60 sekund.',
          body: `{ "error": "rate_limited", "retry_after_seconds": 12,\n  "limit": 600, "window": "1m", "hint": "Rozložte dotazy, nebo si řekněte o vyšší limit — dáváme ho zdarma.",\n  "docs": "https://onhost.cz/api#limity" }` },
        { code: '409', slug: 'confirmation_required', fg: '#fff', bg: '#c1121f',
          d: 'Mazací operace bez potvrzovací hlavičky skončí chybou, ne tichým úspěchem. Odpověď vypíše, co přesně by zmizelo.',
          body: `{ "error": "confirmation_required",\n  "would_delete": ["srv_db_primary", "3 snapshots", "1 dns_zone"],\n  "header": "X-Onhost-Confirm: srv_db_primary",\n  "hint": "Skript, který maže bez potvrzení, jednou smaže něco jiného." }` },
        { code: '403', slug: 'access_not_approved', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#e0a100 30%,transparent)',
          d: 'Klíč má rozsah a i my sami potřebujeme vaše potvrzení. Odpověď říká, kdo má schválit a jak dlouho to platí.',
          body: `{ "error": "access_not_approved", "scope_required": "servers:write",\n  "key_scope": "servers:read", "approver": "owner",\n  "hint": "Vytvořte klíč s rozsahem servers:write — ten stávající nerozšiřujeme sami." }` },
        { code: '422', slug: 'would_change_invoice', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#e0a100 30%,transparent)',
          d: 'Operace, která zvýší vaši fakturu, se nevykoná potichu. Vrátíme rozdíl v koruně a čekáme na potvrzení.',
          body: `{ "error": "would_change_invoice", "delta_czk_per_month": 840,\n  "current_czk_per_month": 3260, "confirm": "X-Onhost-Accept-Cost: 840",\n  "hint": "Chceme, abyste to viděli dřív než na faktuře." }` },
        { code: '503', slug: 'maintenance_window', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#1a7f37 22%,transparent)',
          d: 'Odpověď nese konkrétní čas konce a tiket, ne obecné „technické potíže“. Retry-after je odhad, ne zdvořilost.',
          body: `{ "error": "maintenance_window", "ends_at": "2026-09-12T02:00:00Z",\n  "retry_after_seconds": 3400, "incident": null, "planned": true,\n  "hint": "Okno jste mohli posunout do 10. 9. — příště vám napíšeme dřív." }` },
        { code: '500', slug: 'our_fault', fg: '#fff', bg: '#c1121f',
          d: 'Když je chyba naše, napíšeme to a dáme vám číslo, se kterým vám na podpoře nikdo nebude tvrdit, že se nic nestalo.',
          body: `{ "error": "our_fault", "reference": "err_9f31c7",\n  "reported_to_engineering": true, "your_data_affected": false,\n  "hint": "Napište nám tuhle referenci. Vidíme u ní přesný stack i to, kdo ji řeší." }` }
      ],
      missing: [
        { t: 'POST /autoscale', tag: 'nikdy', d: 'Nemáme endpoint, kterým by si vaše aplikace sama zvětšila server. Změna, která zvýší fakturu bez vašeho vědomí, nemá být na jedno volání — kapacitu vám naplánujeme sedm týdnů dopředu.' },
        { t: 'DELETE bez potvrzení', tag: 'záměrně', d: 'Mazací volání vyžaduje potvrzovací hlavičku. Je to jediná nekompatibilní změna, kterou jsme udělali kvůli vám a ne kvůli sobě: skript bez ní dostane chybu místo tichého úspěchu.' },
        { t: 'Naše oficiální SDK', tag: 'nepotřebujete', d: 'Žádnou naši knihovnu do svého kódu nedáte. REST a JSON stačí a vendor knihovna v cizím kódu je malý zámek na dveřích, který jsme nechtěli namontovat.' },
        { t: 'GET /customers/{id}/data', tag: 'neexistuje', d: 'Přes API se k datům vašich zákazníků nedostanete ani vy z jiného účtu, ani my. Přístup do služby se potvrzuje jmenovitě a na čtyři hodiny, ne tokenem.' },
        { t: 'Webhook s marketingem', tag: 'nemáme co', d: 'Žádná událost typu nabídka, upsell nebo připomenutí. Posíláme incidenty, zálohy, faktury a přístupy — tedy věci, které se opravdu staly.' },
        { t: 'Endpoint, který jsme odstřihli', tag: '0', d: 'Za celou dobu jsme neodstranili žádnou verzi bez okna. Nekompatibilní změna má devadesát dní počítaných od chvíle, kdy je nová verze použitelná, ne od oznámení.' }
      ],
      idem: [
        { t: 'Idempotency-Key', d: 'Pošlete vlastní klíč u každého POST, který něco vytváří nebo účtuje. Druhé volání se stejným klíčem vrátí původní odpověď, ne novou instanci.', v: '24 h' },
        { t: 'Stejný klíč, jiné tělo', d: 'Vrátíme 409 a napíšeme, čím se tělo liší. Tiché přepsání by z chyby ve vašem skriptu udělalo chybu na vaší faktuře.', v: '409' },
        { t: 'Opakování po timeoutu', d: 'Doporučený postup: stejný klíč, exponenciální odstup, maximálně pětkrát. Nic dvakrát nezaložíte a nic dvakrát nezaplatíte.', v: 'bezpečné' },
        { t: 'DELETE a PUT', d: 'Idempotentní bez klíče — smazání už smazaného vrací 204, ne chybu. Skript, který uklízí, nemá padat na tom, že už je uklizeno.', v: '204' },
        { t: 'Co klíč nezakryje', d: 'Dvě různá volání se dvěma různými klíči založí dva servery. Idempotence chrání před opakováním, ne před chybou v cyklu — proto máme i limit na počet instancí za hodinu.', v: 'limit 20/h' }
      ],
      hooks: this.HOOKS.map((h, i) => ({
        ev: h.ev, d: h.d,
        on: () => this.setState({ hook: i }),
        style: 'text-align:left;border:0;border-bottom:1px solid var(--color-divider);cursor:pointer;padding:13px 15px;font-family:var(--font-body);'
          + (this.state.hook === i
            ? 'background:var(--color-text);color:var(--color-bg)'
            : 'background:transparent;color:var(--color-text)')
      })),
      hookUrl: 'https://vas-server.cz/hooks/onhost',
      hookRetry: this.HOOKS[this.state.hook].retry,
      hookBody: this.HOOKS[this.state.hook].body,
      hookNote: this.HOOKS[this.state.hook].note,
      changes: [
        { date: '20. 8. 2026', t: 'Potvrzovací hlavička u mazacích operací', tag: 'nekompatibilní', fg: '#fff', bg: '#c1121f', win: '90 dní', d: 'Jediná nekompatibilní změna, kterou jsme udělali kvůli bezpečnosti vašich dat, ne kvůli sobě: skript, který hlavičku nepošle, dostane chybu místo tichého úspěchu. Oznámeno 412 účtům, které mazací endpointy volají.' },
        { date: '12. 8. 2026', t: 'Zpřísnění limitu na exporty — vráceno', tag: 'vráceno', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#e0a100 26%,transparent)', win: '—', d: 'Rozbilo to dvěma zákazníkům noční zálohovací skript. Limit, který spadne ve tři ráno, není ochrana, ale výpadek — vrátili jsme ho do dvou dnů a napsali oběma dřív, než se ozvali.' },
        { date: '2. 8. 2026', t: 'Nové pole ready_in_seconds u vytvoření serveru', tag: 'kompatibilní', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#1a7f37 22%,transparent)', win: 'bez okna', d: 'Přidat pole smíme kdykoli — klient, který ho nečeká, ho ignoruje. Přejmenovat pole nesmíme nikdy bez devadesátidenního okna.' },
        { date: '18. 7. 2026', t: 'Přejmenované pole u fakturace', tag: 'nekompatibilní', fg: '#fff', bg: '#c1121f', win: '90 dní', d: 'Staré i nové pole vracíme současně celé okno. E-mail šel 34 účtům, které fakturační endpoint skutečně volají, a ne všem čtyřem tisícům.' },
        { date: '4. 7. 2026', t: 'Status API bez tokenu a bez limitu', tag: 'nové', fg: 'var(--color-text)', bg: 'color-mix(in srgb,#1a7f37 22%,transparent)', win: 'trvale', d: 'Kdo si chce hlídat dostupnost sám, nemá k tomu potřebovat naše svolení. Bez registrace, bez klíče, bez limitu — a bez toho, abychom sledovali, kdo se ptá.' },
        { date: 'trvale', t: 'v1 neodstřihneme', tag: 'slib', fg: '#fff', bg: 'var(--color-accent)', win: '12 měsíců', d: 'Po vydání v2 poběží v1 nejméně dvanáct měsíců, počítaných od chvíle, kdy je v2 skutečně použitelná. Okno počítané od oznámení je trik, ne lhůta.' }
      ],
      rules: [
        { t: 'Verze neodstřihujeme', d: 'v1 poběží i po vydání v2. Migrační okno je minimálně dvanáct měsíců a začíná dnem, kdy v2 vyjde — ne dnem, kdy ji oznámíme.' },
        { t: 'Změna se ohlásí předem', d: 'Nekompatibilní změna má 90 dní a jde do změnového logu i e-mailem těm, kdo daný endpoint skutečně volají. Ostatní nebudíme.' },
        { t: 'Limit nepoužíváme na tlačení', d: 'Vyšší limit nedostane ten, kdo si připlatí, ale ten, kdo ho potřebuje. Napište a povíme, jestli je problém na vaší nebo naší straně.' },
        { t: 'Klíče vidíte v auditu', d: 'Každý požadavek s klíčem je ve vašem auditu s časem a rozsahem. Klíč, který 90 dní nikdo nepoužil, sami označíme jako nepoužívaný.' }
      ]
    };
  }
  function slaData() {
    const rows = [
      ['Webhosting', '99,95 %', 'do 30 min', 'Nedostupnost webu z internetu. Chyba 500 z vaší aplikace do toho nepatří, chyba 502 z našeho PHP-FPM ano.'],
      ['Domény a DNS', '99,99 %', 'do 15 min', 'Odpověď našich autoritativních serverů. Prodleva registru při změně registrátora se nepočítá — nemáme ji v ruce.'],
      ['VPS', '99,95 %', 'do 30 min', 'Dostupnost virtuálu a jeho sítě. Váš vlastní systém a co v něm běží je na vás, ale poznáme rozdíl a řekneme ho.'],
      ['Herní servery', '99,9 %', 'do 30 min', 'Instance běží a je dosažitelná. Nekompatibilní plugin nebo mod není náš výpadek, s dohledáním pomůžeme.'],
      ['E-mail', '99,95 %', 'do 30 min', 'Příjem, odeslání i IMAP. Zdržení kvůli cizímu greylistingu se nepočítá, zpoždění v naší frontě nad 10 minut ano.'],
      ['Úložiště', '99,95 %', 'do 30 min', 'Čtení i zápis přes S3 API. Trvalost dat řešíme mimo SLA — tři kopie ve dvou lokalitách, bez výjimky.'],
      ['Housing', '99,99 %', 'do 15 min', 'Napájení, chlazení a konektivita racku. Vaše železo je vaše, ale vzdálené ruce jsou v ceně a jedeme nonstop.'],
      ['Klientský panel', '99,9 %', 'do 30 min', 'Přihlášení, správa služeb, fakturace. Nedostupný panel bereme jako výpadek, i když vaše služby běží.']
    ];
    const cells = [];
    rows.forEach((r) => {
      cells.push({ t: r[0], size: '15px', weight: '700', font: 'var(--font-heading)', color: 'var(--color-text)' });
      cells.push({ t: r[1], size: '15px', weight: '700', font: 'ui-monospace,Menlo,monospace', color: 'var(--color-text)' });
      cells.push({ t: r[2], size: '14px', weight: '400', font: 'var(--font-body)', color: 'var(--color-text)' });
      cells.push({ t: r[3], size: '13.5px', weight: '400', font: 'var(--font-body)', color: 'color-mix(in srgb,var(--color-text) 78%,transparent)' });
    });
    return {
      heads: [
        { k: 'Dostupnost', v: '99,95 %', n: 'většina služeb, měřeno zvenčí' },
        { k: 'Reakce na incident', v: 'do 30 min', n: 'nonstop, člověk ne robot' },
        { k: 'Kredit', v: 'bez žádosti', n: 'do 48 h po uzavření' },
        { k: 'Vyplaceno 2026', v: '318 400 Kč', n: 'zveřejňujeme čtvrtletně' }
      ],
      slaHead: [{ t: 'Služba' }, { t: 'Dostupnost' }, { t: 'Reakce' }, { t: 'Co se počítá jako výpadek' }],
      slaCells: cells,
      ladder: [
        { when: 'nad rámec SLA do 60 minut v měsíci', credit: '10 %' },
        { when: '1 až 4 hodiny v měsíci', credit: '25 %' },
        { when: '4 až 8 hodin v měsíci', credit: '50 %' },
        { when: 'nad 8 hodin v měsíci', credit: '100 %' },
        { when: 'ztráta dat naší vinou', credit: '100 % + 3 měsíce' }
      ],
      excludes: [
        { t: 'Odstávka ohlášená 7 dnů předem', d: 'Termín posíláme e-mailem a je v panelu. Okno je maximálně 2 hodiny a mezi 2:00 a 6:00. Neohlášená odstávka je výpadek.' },
        { t: 'Vaše aplikace a konfigurace', d: 'Chyba v kódu, plný disk kvůli logům nebo špatný záznam v DNS, který jste si nastavili. Napíšeme vám, co vidíme, a pomůžeme to najít.' },
        { t: 'DDoS nad kapacitu ochrany', d: 'Filtrujeme do 2 Tb/s. Nad tím se snažíme dál, ale negarantujeme to. Reálný objem a délku útoku vždy zveřejníme.' },
        { t: 'Zásah vyšší moci a cizí sítě', d: 'Přerušení tranzitu mimo naši infrastrukturu, výpadek registru domén, soudní příkaz. Jmenovitě, ne jako obecná klauzule.' },
        { t: 'Neplacená služba po 14 dnech', d: 'Do 14 dnů po splatnosti SLA platí normálně. Po nich službu pozastavíme a data držíme dalších 30 dnů.' }
      ],
      steps: [
        { n: '1', t: 'Incident vidí monitoring', d: 'Měření běží ze tří míst mimo naši síť. Výpadek se zapíše na statusovou stránku do 5 minut, i když ještě neznáme příčinu.' },
        { n: '2', t: 'Doba se sečte po minutách', d: 'Automat spočítá, které služby a kteří zákazníci byli zasaženi. Nezaokrouhlujeme dolů a nepracujeme s „procentem dostupnosti měsíce“.' },
        { n: '3', t: 'Kredit se pošle sám', d: 'Do 48 hodin je na účtu a v e-mailu s odkazem na zápis. Nikde není formulář, kterým byste o něj žádali.' },
        { n: '4', t: 'Nesouhlas řeší člověk', d: 'Když si myslíte, že měl být kredit vyšší, odpovězte na ten e-mail. Rozhoduje inženýr, který incident řešil, ne obchod.' }
      ]
    };
  }
  var ctx = { HOOKS: HOOKS, state: { hook: 0 }, setState: function () {} };
  window.ONHOST_PUBLIC = { api: apiData.call(ctx), sla: slaData.call(ctx), hooks: HOOKS };
})();
