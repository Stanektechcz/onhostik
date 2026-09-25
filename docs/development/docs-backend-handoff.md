# Onhost — předání frontendu do backendu

> **Historický dokument — stav k 6. 9. 2026; aktuální stav: `docs/context/CURRENT_STATE.md`.** Předání prototypu do backendu. Aktuální platforma: Laravel 13 (13.30.1, modulární monolit `onhost-platform`), PHP 8.3, PostgreSQL 16 v produkci; živý aaPanel běží ve verzi 8.0.6 (ověřeno 2026-09-13, `docs/runbooks/preproduction-audit.md` §4). Plochy se nemění (ADR-0005); napojení na data je v `app/Http/Support/SurfaceRenderer.php` a `apps/surfaces/api/*`, soupis švů v `docs/ui/data-seams.md` a `docs/ui/template-inventory.md`.

Stav k 6. 9. 2026. Prototyp je **pět ploch**: `Onhost.dc.html` (prezentační web včetně
stavu služeb, změnového logu, SLA, API, dokumentace a lidí), `Onhost-app.dc.html` (klientská
zóna), `Onhost-admin.dc.html` (administrace pro všechny interní role), `Onhost-partner.dc.html`
(partnerský portál) a `Onhost-mobil.dc.html` (mobilní aplikace). `Onhost-widgets.dc.html` je
knihovna komponent pro vývojáře, ne produkt. Obsah blogu a knowledgebase je v `onhost-content.js`,
katalogové listy služeb v `onhost-svc-*.js`, live data v `onhost-live-adapter.js`.

## 1. Routování (hotové)

Prototyp má hash routování. Zdroj pravdy je `ROUTES` v logice `Onhost.dc.html`;
`parseHash()` čte URL při načtení i při `hashchange`, `syncHash()` zapisuje URL při změně `view`.
Při implementaci stačí mapu přenést 1:1 na server-side cesty (`/ceny`, `/blog/<slug>`, …).

| URL | view | poznámka |
| --- | --- | --- |
| `#/` | home | |
| `#/ceny` | pricing | |
| `#/sluzby` | services | katalog |
| `#/sluzba/<id>` | svc | detail služby, `id` = klíč z `onhost-svc-*.js` |
| `#/webhosting` | web | |
| `#/gamehosting` | game | |
| `#/blog` | blog | filtr kategorie je jen ve stavu, ne v URL |
| `#/blog/<slug>` | article (blog) | |
| `#/znalostni-baze` | kb | hledání a kategorie jen ve stavu |
| `#/znalostni-baze/<slug>` | article (kb) | |
| `#/reseller` | reseller | |
| `#/jak-fungujeme` | trust | |
| `#/ceny-a-sla` | fair | |
| `#/technika` | tech | |
| `#/verejne-zakazky` | tender | |
| `#/kosik` | checkout | prázdný košík má vlastní stav obrazovky |
| `#/prihlaseni`, `#/registrace`, `#/obnova-hesla` | login/register/reset | |

Doplnit při implementaci: kanonické URL bez hashe, `<title>`/meta/OG per route,
sitemap, hreflang pro CS/EN (jazyk je teď jen stav `lang`).

## 2. Datová vrstva (hotová)

`onhost-data.js` je jediný bod, kde leží data marketingového webu. Každá funkce bere `cs`
(jazyk) a vrací data ve tvaru, v jakém je má vracet API — napojení = nahradit tělo funkce
fetchem nebo předplnit `window.ONHOST_DATA` ze serveru; komponenta se nemění.

| Funkce | Endpoint | Tvar |
| --- | --- | --- |
| `catalog(cs)` | `GET /api/catalog` | `{ code, cat, price, cs[], en[] }[]` |
| `plans(cs)` | `GET /api/plans` | `{ key, price, hi, cs[name,badge,desc,feats[]], en[] }[]` |
| `compare(cs)` | `GET /api/plans/compare` | `[parametr, start, pro, scale][]` |
| `status(cs)` | `GET /api/status` | `{ n, u, bad[] }[]` (`bad` = indexy dní s incidentem) |
| `locations(cs)` | `GET /api/locations` | `[code, city, country, ping, live][]` |
| `changelog(cs)` | `GET /api/changelog` | `[date, tag, tagLabel, title, body][]` |

Katalogy služeb zůstávají v `onhost-svc-*.js`, blog a KB v `onhost-content.js`, live data
v `onhost-live-adapter.js`. Zbytek textů (nadpisy, FAQ, reference, ukázková faktura) je copy,
ne data k API — zůstává v komponentě.

## 3. Datové kontrakty k vytažení z UI

Vše níže je dnes v prototypu jako literál v `renderVals()`. Pořadí odpovídá závislostem.

**Katalog služeb** — `GET /api/services`, `GET /api/services/{id}`
`{ id, crumb, kicker, title, lead, kpis[], feats[], plans[{name, price, per, desc, feats[], badge}],
config{sliders, addons}, bench[], cases[], reviews[], faq[], docs? }`
Dnes: `onhost-svc-web|compute|dev|corp|other|more.js`.

**Tarify a ceny** — `GET /api/plans?period=month|year&currency=CZK|EUR`
Ceny se v UI přepočítávají klientsky (`period`, `currency`, DPH 21 %). Server musí vracet
netto cenu za jednotku a období; přepočet DPH nechat na jednom místě (dnes `mny()`/`czk()`).

**Objednávka** — `POST /api/cart`, `POST /api/orders`
`{ items[{ sku, name, qty, commit: 1|12|24, unitNet }], credit, promo, vatRate, guest, customer{...} }`
→ `{ orderId, net, vat, total, eta }`. Košík je dnes ve stavu `cart` bez persistence.

**Fakturace** — `GET /api/invoices`, `GET /api/invoices/{id}` (ukázková faktura na ceníku:
`invRows`, `invTotals`).

**Blog a knowledgebase** — `GET /api/posts?cat=`, `GET /api/posts/{slug}`,
`GET /api/kb?q=&cat=`, `GET /api/kb/{slug}`
`{ slug, cat, title, excerpt, body[[heading, paragraph]], date|updated, read, author }`
Dnes: `onhost-content.js`. Hledání v KB je klientský substring — nahradit fulltextem.

**Stav služeb / NOC** — `GET /api/status`, `GET /api/incidents`, `GET /api/postmortems`
Adaptér už existuje: `onhost-live-adapter.js` (viz `docs-live-data.md`).

**Sklad a dostupnost** — `GET /api/stock` (`stock` na `#/technika`: name, spec, price, unit, avail, stav).

**Changelog** — `GET /api/changelog?tag=` (`clItems`, tagy `panel|fix|infra`).

**Reseller** — `GET /api/reseller/tiers`, `POST /api/reseller/apply`.

**Tendry** — `POST /api/tender/request` (odešle sadu dokumentů z `tdDocs`).

**Kontaktní a poptávkové formuláře** — `POST /api/leads` (migrace, konzultace, enterprise).

## 4. Co ještě frontend nemá a backend to bude potřebovat

Pozn.: detail služby (`#/sluzba/<id>`) je hotový včetně konfigurátoru, ROI kalkulačky
a kotevní lišty. Konfigurátor i ROI počítají klientsky z koeficientů v datech služby
(`cfgBase`, `cfgCpu`, `cfgRam`, `cfgDisk`, `capexMult`, `roiModel`) — server je musí vracet
s katalogem, jinak se cena rozejde s objednávkou.

1. **Stavy formulářů** — hotovo pro přihlášení, registraci, obnovu hesla a checkout:
   validace na blur i při odeslání, chyby u polí, průběh na tlačítku, potvrzovací stav
   (registrace → session, checkout → číslo objednávky). Pravidla jsou v `authRules()` a `coRules()`;
   server musí vracet chyby po polích (`{ field: message }`), aby se zobrazily na stejném místě.
2. **Session** — prototyp drží `onhost.session` v localStorage (e-mail, jméno, čas); hlavička podle
   něj přepíná na jméno + Odhlásit. Nahradit tokenem s expirací a chráněnými routami.
3. **Persistence košíku** — `onhost.cart` v localStorage (items, commit, promo, čas). Při napojení
   nahradit serverovým košíkem, localStorage nechat jen jako cache pro anonymní návštěvníky.
4. **Platby** — `payOpts` je jen výběr; chybí návrat z platební brány (success/pending/failed URL).
5. **Jazyk a měna** — dnes stav; potřeba cookie/URL segment a překladové soubory.
6. **SEO a sdílení** — title/meta/OG/JSON-LD per route, canonical, robots.
7. **Přístupnost** — projít focus stavy v dropdownech, mega menu a v košíkovém panelu.

## 5. Poznámky k UI, které se nesmí rozbít

- `oh-split`, `oh-cards`, `oh-cards3`, `oh-mega`, `oh-drawer` nesou responzivní chování
  (sesypání na mobilu, nulování odsazení u sesypaných polovin).
- Košíkový panel má `z-index: 120`, mega menu 60/70, přepínač vzhledu 90.
- Vizuální styl je vázaný na design systém Modernist: nulové rádiusy, 2px linky,
  Archivo, akcent jen pro primární akci a malé zvýraznění.


## Sdílená session mezi plochami (onhost-shell.js)

Prototyp drží mezi plochami jedno místo pravdy v `localStorage`, API `window.OnhostSession`.
Backend má tyto tři klíče nahradit skutečnými endpointy.

| Klíč | Obsah | Náhrada v produkci |
| --- | --- | --- |
| `onhost.session` | `{email, name, since}` | `GET /v1/me` (cookie / bearer) |
| `onhost.cart` | `{items[], commit, promo, promoOk, saved}` | `GET/PUT /v1/cart` |
| `onhost.handoff` | jednorázový kontext přechodu mezi plochami | není potřeba — server drží stav sám |

### Handoff (jednorázové předání kontextu)

`OnhostSession.handoff(payload)` zapíše, `OnhostSession.takeHandoff()` přečte a smaže.
Používané typy:

- `{kind:'order', id, email, name, count, total, commit, items[]}` — web po dokončení objednávky → klientský panel (pás „Objednávka z webu“) a transakční maily (předvyplní šablonu Vítejte).
- `{kind:'mail', tpl, vars{}, note}` — NOC (incidentní rozesílka) a partnerský portál (potvrzení výplaty) → plocha Transakční maily; `tpl` je id šablony, `vars` přepíší ukázková data.

Odběr změn: `OnhostSession.on(fn)` (volá se i při změně v jiném tabu přes `storage` event).

## Sjednocení do čtyř ploch (26 → 4)

Prototyp je od 6. 9. 2026 rozdělený na čtyři plochy. Původních 26 zůstává na disku
a v přepínači ve skupině **Archiv** — jsou to tytéž soubory, jen se do nich vstupuje
z modulu, ne z rozcestníku.

| Plocha | Soubor | Co obsahuje | Laravel oblast |
| --- | --- | --- | --- |
| Prezentační web | `Onhost.dc.html` | Marketing, ceník, katalog 49 služeb s konfigurátorem, blog, knowledgebase, dokumentace (`#/dokumentace`), API reference (`#/api`), stav služeb (`#/stav`), změnový log (`#/zmeny`), SLA (`#/ceny-a-sla`), lidé (`#/lide`), technika, veřejné zakázky, košík, registrace | Catalog, Content, Public status, Ordering |
| Klientská sekce | `Onhost-app.dc.html` | Služby, konzole a logy, panely (aaPanel, ISPConfig, Proxmox, Pterodactyl), zálohy, domény a DNS s dvoufázovou publikací, fakturace, tickety, asistent, API tokeny, nastavení účtu a týmu | Services, Provisioning, Domains, Billing, Support |
| Administrátorská sekce | `Onhost-admin.dc.html` | Osmnáct sekcí pro role Admin / L1 / L2 / vedoucí směny / obchod / vedení / editor / infrastruktura: fronta práce, tikety, zákazníci, objednávky, služby, uzly, incidenty, fakturace, doklady, tarify, lidé, chat, znalosti, šablony mailů, audit, reporty, nastavení | Ordering, Provisioning, Incidents, Billing, Reporting, Notifications |
| Partnerský portál | `Onhost-partner.dc.html` | Přehled, klienti, provize, výplaty se samofakturací, whitelabel, marketingové materiály | Billing, Platform |
| Mobilní aplikace | `Onhost-mobil.dc.html` | Výstrahy z incidentů a nezaplacených dokladů, metriky, konzole, doklady, push a lock screen | Incidents, Billing, Notifications |

### Routování ploch

Každá sekce má vlastní hash, který se v Laravelu překlápí 1:1 na cestu:

| Plocha | Hash | Laravel cesta |
| --- | --- | --- |
| `Onhost.dc.html` | `#/ceny`, `#/sluzby`, `#/sluzba/{id}`, `#/blog/{slug}`, `#/znalostni-baze/{slug}`, `#/dokumentace`, `#/api`, `#/stav`, `#/zmeny`, `#/lide`, `#/ceny-a-sla`, `#/technika`, `#/kosik` | `/`, `/ceny`, `/sluzby/{slug}`, `/blog/{slug}`, `/napoveda/{slug}`, `/dokumentace`, `/api`, `/stav`, `/zmeny`, `/lide`, `/sla`, `/technika`, `/kosik` |
| `Onhost-app.dc.html` | `?tab=` + 26 sekcí (`overview`, `servers`, `svcdesk`, `billing`, `tickets`, `domains`, `backups`, `api`, `settings`…), `&cat=` pro service desk | `/klient/{sekce}` |
| `Onhost-admin.dc.html` | `#/prehled`, `#/fronta`, `#/tiket/{id}`, `#/zakaznici`, `#/objednavky`, `#/sluzby`, `#/uzly`, `#/incidenty`, `#/fakturace`, `#/doklady`, `#/tarify`, `#/lide`, `#/chat`, `#/znalosti`, `#/maily`, `#/audit`, `#/reporty`, `#/nastaveni` | `/sprava/{sekce}[/{id}]` |
| `Onhost-partner.dc.html` | `#/prehled`, `#/klienti[/{id}]`, `#/provize`, `#/vyplaty`, `#/whitelabel`, `#/materialy` | `/partner/{sekce}` |

### Jak hostování modulů funguje

- Modul se načte s `?embed=1`. Shell v tom režimu vynechá přepínač, gate i paletu
  (`window.OnhostEmbedded`), protože chrome patří hostiteli.
- Hostitel po načtení skryje hlavičku modulu (a v klientské sekci i jeho boční
  navigaci `.oh-aside`) — navigaci nese rail hostitele.
- Klientský panel bere sekci z URL: `Onhost-app.dc.html?tab=billing`, u service desku
  ještě `&cat=panels`. V produkci to jsou běžné routy (`/panel/fakturace`,
  `/panel/panely`), ne query parametry.
- V produkci se z modulů stanou komponenty jedné SPA; `?embed=1` a skrývání hlavičky
  je jen lešení prototypu, aby se nic nemuselo přepisovat dvakrát.

## Provozní store (onhost-store.js) — sdílená datová vrstva

`window.OnhostStore` je jedno místo pravdy pro provozní entity napříč plochami. Persistence
je dvouvrstvá: plný snapshot leží v IndexedDB (`onhost` / `ops` / klíč `snapshot`),
`localStorage['onhost.ops']` nese jen zkrácený hot set (300 objednávek, 300 ticketů, 400 faktur,
60 zápisů logu) kvůli synchronnímu prvnímu vykreslení — plochy kreslí hned a plná data dotečou z IDB
(`ready(fn)`). Cross-tab přes `storage` event na `onhost.ops.stamp`; příjemce si dotáhne plný snapshot
z IDB, nikdy zkrácenou cache. Bez IndexedDB store degraduje na samotný localStorage a `size().backend`
to řekne. Odběr `OnhostStore.on(fn)`. Shell ho dopl­ní automaticky, pokud si ho plocha nenačte sama.

| Entita | Stavy | API |
| --- | --- | --- |
| Objednávka | `nova → zaplaceno → provisioning → aktivni → pozastaveno/zruseno` | `orders(f)`, `order(id)`, `createOrder(p)`, `setOrderState(id,to,who)` |
| Faktura | `vystavena → zaplacena / po_splatnosti → stornovana` | `invoices(f)`, `invoice(id)`, `issueInvoice(orderId)`, `payInvoice(id,method)`, `remindInvoice(id)`, `creditNote(id,reason)`, `slaCredit(incId,pct)`, `dunningRun()`, `revenue()` |
| Ticket | `otevreny ↔ ceka → vyreseny` | `tickets(f)`, `ticket(id)`, `createTicket(p)`, `replyTicket(id,from,text)`, `setTicketState(id,to)` |
| Incident | `vysetrovani → identifikovano → monitoring → vyreseno` | `incidents(f)`, `createIncident(p)`, `setIncidentState(id,to,note)` |
| Notifikace | `aud: internal \| customer`, `read` | `notifs(aud)`, `unread(aud)`, `markRead(aud,id)` |
| Mail | `queued → sent` | `mails()`, `queueMail(tpl,to,subject,vars,ref)`, `sendMail(id)`, `sendAllQueued()` |
| Audit log | append-only, 120 záznamů | `log()`, `counts()`, `reset()` |

Přechody mají definované vedlejší efekty (v `ORDER_FX` a v `set*State`): zápis do auditu,
notifikace pro zákazníka i pro interní tým a zařazení mailu do fronty. Backend má tuto logiku
přenést na server (stavový automat + event bus), UI se nemění.

Plochy s dlouhými seznamy (`Onhost-provoz` objednávky a tickety, `Onhost-fakturace` doklady) kreslí
jen okno 40 řádků se stránkovačem a přesným rozsahem („41–80 z 609"); filtr okno resetuje na první
stranu. Backend tomu odpovídá `?limit=40&offset=` a hlavičkou s celkovým počtem — UI už s odpovědí
po stranách počítá.


| --- | --- |
| `orders` | `GET/POST /v1/orders`, `POST /v1/orders/{id}/transition` |
| `tickets` | `GET/POST /v1/tickets`, `POST /v1/tickets/{id}/messages` |
| `incidents` | `GET/POST /v1/incidents`, `POST /v1/incidents/{id}/updates` |
| `notifs` | `GET /v1/notifications?audience=`, `POST /v1/notifications/read` |
| `mails` | `GET /v1/outbox`, `POST /v1/outbox/{id}/send` |
| `invoices` | `GET/POST /v1/invoices`, `POST /v1/invoices/{id}/pay`, `POST /v1/invoices/{id}/reminder`, `POST /v1/invoices/{id}/credit-note`, `POST /v1/sla-credits`, `GET /v1/revenue` |

### Fakturace — pravidla, která musí převézt server

- **Vystavení**: `createOrder` zároveň vystaví fakturu (`FV-YYYY-NNNN`) — netto z položek objednávky, DPH 21 %, splatnost 14 dní.
- **Párování platby**: `payInvoice` převádí i objednávku (`nova → zaplaceno`, `pozastaveno → aktivni`); přechod objednávky na `zaplaceno` naopak označí otevřenou fakturu jako zaplacenou — obojí musí být idempotentní.
- **Sweep splatnosti**: při každém načtení store překlápí `vystavena → po_splatnosti` po termínu (na serveru cron).
- **Dávka upomínek** (`dunningRun`): upomínka ke každé faktuře po splatnosti; nad 30 dní pozastavení aktivní služby.
- **Opravný doklad** (`creditNote`): originál → `stornovana`, nový doklad `DK-YYYY-NNNN` se zápornými řádky.
- **SLA kredit** (`slaCredit`): procento z měsíční ceny aktivních/instalovaných objednávek, default podle severity (p1 25 %, p2 10 %, p3 5 %), doklad `DK-…` s vazbou `sla: INC-…`.
- **`revenue()`**: MRR/ARR z aktivních objednávek, obrat a pohledávky za 30 dní, průměrný doklad. Na serveru počítat z ledgeru, ne z UI.

### Kdo store čte a píše

| Plocha | Čte | Píše |
| --- | --- | --- |
| `Onhost.dc.html` (web) | — | `createOrder` po dokončení checkoutu |
| `Onhost-app.dc.html` (panel) | tikety zákazníka (sloučené s ukázkovými), počet otevřených; faktury zákazníka v záložce Fakturace (`storeInvoices()`, `storeOutstanding()`) | `createTicket` z formuláře; „Odpovědět“ předá ticket do provozního centra; `payInvoice` z tlačítka Zaplatit u živé faktury |
| `Onhost-admin.dc.html` | tabulka Objednávky a provisioning; sekce Faktury a doklady čte živé doklady ze store (nad ukázkovou historií) | `setOrderState` (řádkové akce i dávka „Provisionovat vše čekající“) |
| `Onhost-admin.dc.html` (`#/fronta`, `#/incidenty`, `#/doklady`, `#/maily`, `#/reporty`) | vše — přehled a fronta práce, incidenty s časovou osou, faktury a pohledávky, obrat po měsících, výstupní fronta mailů, MRR/inkaso/churn/MTTR/dostupnost, audit log | všechny přechody, odpovědi na tikety, `createIncident`, `payInvoice`, `remindInvoice`, `creditNote`, `slaCredit`, `dunningRun`, `queueMail` |
| `Onhost.dc.html` (`#/stav`) | živé incidenty a jejich časová osa, počet otevřených, medián opravy | — (jen čte) |
| `Onhost-mobil.dc.html` | otevřené incidenty, nezaplacené faktury a nepřečtené zákaznické notifikace jako živé výstrahy; dostupnost 30 d z incidentů | — (jen čte; akce vedou na panel a stavovou stránku) |
| `Onhost-partner.dc.html` | zaplacené a nezaplacené faktury pro výpočet provize a zadržené části | `queueMail('payout', …)` při žádosti o výplatu (+ handoff do Transakčních mailů) |
| Shell (přepínač) | `counts()` a nepřečtené notifikace | `markRead` |

### Plochy a přepínač

`window.OnhostSurfaces` je seznam všech ploch (`{g, h, t, d, k}`) — skupina, soubor, název, popis, klávesová zkratka. Přepínač vlevo dole je součástí shellu, načítá se v `<helmet>` každé plochy.

## Asistent, shluky ticketů a zátěž (store v aktuální verzi)

Nové API v `window.OnhostStore`:

- `topics()` — seznam témat (id, label). Rozpoznávání je na klíčových slovech bez diakritiky nad předmětem a všemi zprávami ticketu; výsledek se cachuje na (id, počet zpráv).
- `topicOf(ticket)` / `ticketsByTopic(topicId)` — téma jednoho ticketu, resp. tickety v tématu.
- `clusters()` — shluky: `{ id, label, count, open, waiting, solved, high, avgAge (h), share (%), topCustomer, topCustomerCount, ids, last }`, řazeno podle otevřených.
- `bot(text, email)` — asistent: `{ topic, label, confident, text, facts:[{k,v}], actions:[{kind:'link'|'pay'|'ticket', ...}] }`. Fakta se čtou ze store (neuhrazené faktury, stavy objednávek, otevřený incident), ne z textu dotazu. Backend nahradí odpovědní banku LLM voláním, kontrakt zůstává.
- `seedVolume(n)` — vygeneruje n objednávek, n×1,4 ticketů a n faktur s náhodným (LCG) rozložením témat, stavů, priorit a věku. Vrací `{ orders, tickets, invoices, ms, bytes, records, backend }`.
- `size()` — `{ orders, tickets, invoices, incidents, log, cacheBytes, backend }`; `cacheBytes` je velikost hot setu v localStorage, ne celého stavu.
- `ready(fn)` — promise (a volitelně callback) splněná po hydrataci z IndexedDB. Plochy s velkými tabulkami si na ni počkají, než nabidnou stránkování nad plným počtem.
- `flush()` — vynutí okamžitý zápis (IDB + cache).

Výkon: zápis je coalescovaný (idle callback, jeden `JSON.stringify` na dávku), emit pro UI jde v microtasku, takže dávková operace překreslí plochy jednou. Lookupy `order/ticket/incident/invoice` jdou přes indexy podle id (O(1)). Plný stav jde do IndexedDB structured clone (bez limitu 5 MB), do localStorage se ukládá jen hot set — při překročení kvóty se zužuje ten výřez, nikdy ne data v paměti.

Testovací matice rolí × ploch žije jako zadání pro Pest testy v `docs-laravel-backend.md` §12:
dosah rolí, veřejné plochy, destruktivní akce jen pro admina, rozsah dat u klienta a partnera.
Allowlisty rolí drží `onhost-shell.js` (`ROLES[].s`) — jsou zdrojem pravdy pro policy testy.

Asistent je sekce klientské sekce (`Onhost-app.dc.html?tab=asistent`) — chat nad živými daty ze store, fakta se čtou z objednávek, dokladů a incidentů, ne z textu. Shluky ticketů s dávkovými akcemi jsou v administraci. Generátor zátěžových dat s měřením.

## Integrační vrstva (pět cizích API)

Přidané k prototypu pro vývoj produkčního backendu:

| Soubor | Co v něm je |
| --- | --- |
| `onhost-integrations.js` | `window.OnhostIntegrations` — konektory (WEDOS WAPI, aaPanel, Proxmox VE, Pterodactyl, ISPConfig) s reálnými cestami, autentizací, rozpočty a jménem Laravel jobu u každé akce; workflow řetězy provisioningu; fronta úloh; mapování služeb na cizí objekty včetně driftu; log volání; kontrakt `.env`. |
| `onhost-domains.js` | `window.OnhostDomains` — domény, expirace, autorenew, NSSET, kredit registrátora a DNS zóny s **dvoufázovým zápisem** (dávka změn → `dns-domain-commit`). |
| `Onhost-admin.dc.html` (`#/sluzby`, `#/uzly`) | Integrace & provisioning: konektory, workflow, fronta úloh (opakovat/zrušit jen pro admina), mapování zdrojů, log volání, prostředí a tajemství. |
| `Onhost-app.dc.html` (`?tab=domains`) | Domény & DNS: seznam domén s filtry, detail, editor zóny s dávkou nepublikovaných změn a výslovnou publikací, mapování akcí na WAPI příkazy. |
| `docs-provider-apis.md` | Pět API do detailu: autentizace v kódu, endpointy, chování při chybách, asynchronnost, limity, reconciliace, testování konektorů. |
| `docs-laravel-backend.md` | Zadání produkčního backendu: stack, moduly, datový model, stavové automaty, HTTP API, fronty a plánovač, autorizace, bezpečnost, realtime, reporting, testy, nasazení, fáze a mapa ploch. |

Zdroj pravdy o cestách a jobech je `onhost-integrations.js` — dokumenty i plochy z něj vycházejí.
Nové plochy jsou v přepínači ve skupině Interní (klávesy `I` a `Y`); Integrace vidí admin a NOC,
Domény & DNS zatím jen admin.
