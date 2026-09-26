# Audit pěti ploch a příprava na Laravel

> **Historický dokument — stav k 6. 9. 2026; aktuální stav: `docs/context/CURRENT_STATE.md`.** Audit prototypu před stavbou backendu. Aktuální platforma: Laravel 13 (13.30.1, modulární monolit `onhost-platform`), PHP 8.3, PostgreSQL 16 v produkci; živý aaPanel běží ve verzi 8.0.6 (ověřeno 2026-09-13, `docs/runbooks/preproduction-audit.md` §4). Rozhodnutí, která dokument ještě vede jako otevřená, jsou vzatá: **renderovací model** = prototypové plochy beze změny + datové švy (`docs/adr/0005-surfaces-preserved-with-data-seams.md`), administrace a e-maily v Blade, žádné Inertia/React/Livewire; **platby** = adaptéry Comgate, GoPay, Stripe a bankovní převod (`providers/Payments`), ke spuštění Comgate (`docs/runbooks/go-live-checklist.md` §3); **účetnictví a řady dokladů** v ONhostu (`docs/adr/0003-money-ledger-tax.md`); **druhý registrátor** = Subreg vedle WEDOS s výběrem nejlevnějšího (`docs/runbooks/domain-registrars.md`); **retence po zrušení** = lhůta na obnovu 30 dní a závěrečný archiv 60 dní (`config/onhost.php` `services.deletion`, `services.service_archive_days`); **LLM za asistentem** = pravidla napřed, pak OpenAI-kompatibilní nebo Anthropic poskytovatel jen se čtecími nástroji a návrhy, které zákazník potvrdí (`docs/provider-adapters/ai.md`).

Stav k 6. 9. 2026. Předmět auditu je celý prototyp — **pět ploch**:

| Plocha | Soubor | Role |
| --- | --- | --- |
| Prezentační web | `Onhost.dc.html` | veřejný web, katalog, obsah, stav služeb, API, dokumentace, košík |
| Klientská sekce | `Onhost-app.dc.html` | zákazník: služby, panely, domény, doklady, podpora |
| Administrátorská sekce | `Onhost-admin.dc.html` | interní role: fronta, incidenty, fakturace, reporty, maily |
| Partnerský portál | `Onhost-partner.dc.html` | provize, klienti, výplaty, whitelabel |
| Mobilní aplikace | `Onhost-mobil.dc.html` | výstrahy, metriky, konzole, doklady |

`Onhost-widgets.dc.html` je knihovna komponent pro vývojáře, ne produkt.
Kontrakty backendu jsou v `docs-laravel-backend.md`, cizí API v `docs-provider-apis.md`,
mapa obsahu a datové tvary v `docs-backend-handoff.md`.

## 1. Co audit našel a co je opravené

| # | Zjištění | Stav |
| --- | --- | --- |
| 1 | Klientská sekce neměla routování — do sekce se nedalo odkázat, takže mail ani administrace nemohly vést na konkrétní obrazovku. | **Opraveno.** `TAB_SLUG` (26 slugů), `parseHash()`, `syncHash()` přes `history.replaceState`, posluchač `hashchange`, úklid v `componentWillUnmount`. |
| 2 | Administrace neměla routování — osmnáct sekcí bez URL. | **Opraveno.** `VIEW_SLUG` (18 slugů) + vybraný záznam v cestě: `#/tiket/4821`, `#/zakaznici/c8`. |
| 3 | Partnerský portál neměl routování. | **Opraveno.** `TAB_SLUG` (6 slugů), `#/klienti/{id}` pro detail klienta. |
| 3b | Mobilní aplikace neměla routování — push notifikace neměla kam vést. | **Opraveno.** `SCREEN_SLUG` (5 obrazovek), `#/sluzba/{id}` pro detail služby. |
| 4 | Prezentační web neměl stav služeb, změnový log, lidi, API referenci ani dokumentaci — byly to samostatné plochy. | **Opraveno.** Pět nových sekcí: `#/stav`, `#/zmeny`, `#/lide`, `#/api`, `#/dokumentace`. Data z `OnhostStore`, `ONHOST_DATA`, `ONHOST_PUBLIC`, `onhost-docs.js`. |
| 5 | Veřejná stavová stránka vypisovala strojové slugy stavů incidentů. | **Opraveno.** Štítky bere `OnhostStore.flows.incident[state].label`; EN překládá tytéž klíče. |
| 6 | „Medián opravy" na stavu služeb počítal aritmetický průměr. | **Opraveno.** Skutečný medián — jeden dlouhý incident neutáhne číslo, které se prodává jako typická doba opravy. |
| 7 | Odkazy v tělové velikosti používaly plný akcent (kontrast 3,76:1). | **Opraveno.** `--color-accent-700` (6,4:1) pro text, plný akcent pro hover, výplně a poster. |
| 8 | Přepínač ploch a role odkazovaly na plochy, které v projektu nejsou. | **Opraveno.** `SURFACES` je pětice + UI knihovna, `ROLES[].s` allowlisty jen na existující soubory, metriky palety vedou na sekce administrace. |
| 9 | Odkazy napříč pěti plochami a čtyřmi datovými moduly. | **Bez mrtvých odkazů.** |
| 10 | Žádná plocha nikde nefetchuje (`fetch(` = 0). Data tečou přes čtyři globální seamy. | **Záměr** — viz kapitola 3. |

## 2. Routy: prototyp → Laravel

Prototyp má hash routy, protože běží bez serveru. V Laravelu z nich jsou serverové cesty
1:1; slug se nemá vymýšlet podruhé.

### Prezentační web (`Onhost.dc.html`)

| Hash v prototypu | Laravel route | Controller |
| --- | --- | --- |
| `#/` | `GET /` | `HomeController@index` |
| `#/sluzby` | `GET /sluzby` | `CatalogController@index` |
| `#/sluzba/{id}` | `GET /sluzby/{service:slug}` | `CatalogController@show` |
| `#/ceny` | `GET /ceny` | `PricingController@index` |
| `#/ceny-a-sla` | `GET /sla` | `PricingController@sla` |
| `#/webhosting`, `#/gamehosting` | `GET /webhosting`, `/gamehosting` | `LandingController@show` |
| `#/technika` | `GET /technika` | `StockController@index` |
| `#/jak-fungujeme` | `GET /jak-fungujeme` | `PageController@show` |
| `#/blog`, `#/blog/{slug}` | `GET /blog`, `/blog/{post:slug}` | `PostController` |
| `#/znalostni-baze`, `#/znalostni-baze/{slug}` | `GET /napoveda[/{article:slug}]` | `KbController` |
| `#/dokumentace` | `GET /dokumentace[/{article:slug}]` | `DocsController` |
| `#/api` | `GET /api-reference` | `ApiDocsController@index` |
| `#/stav` | `GET /stav` | `StatusController@index` |
| `#/zmeny` | `GET /zmeny` | `ChangelogController@index` |
| `#/lide` | `GET /lide` | `PageController@team` |
| `#/reseller`, `#/verejne-zakazky` | `GET /reseller`, `/verejne-zakazky` | `PageController@show` |
| `#/kosik` | `GET /kosik` | `CartController@show` |
| `#/prihlaseni`, `#/registrace`, `#/obnova-hesla` | `GET /prihlaseni`, `/registrace`, `/obnova-hesla` | `Auth\*Controller` |

`#/stav` a `#/api` musí zůstat veřejné bez tokenu — stavový endpoint je součástí slibu
„dostupnost si můžete změřit sami" a je na něj `curl` přímo na stránce.

Doplnit při implementaci: canonical URL, `<title>`/meta/OG/JSON-LD per route, sitemap,
`hreflang` pro CS/EN (jazyk je dnes stav, ne segment), robots.

### Klientská sekce (`Onhost-app.dc.html`)

Prefix `/panel`, middleware `auth:sanctum` + `verified`. Slugy drží `TAB_SLUG`.

| Modul | Hash | Laravel route | Data |
| --- | --- | --- | --- |
| Přehled | `#/prehled` | `/panel` | `GET /v1/me`, `/v1/services`, `/v1/invoices?state=unpaid`, `/v1/tickets?state=open` |
| Služby a servery | `#/sluzby` | `/panel/sluzby` | `GET /v1/services` |
| Konzole a logy | `#/sluzba/vps` | `/panel/sluzba/{kategorie}` | `GET /v1/services/{id}/console-token`, `/v1/services/{id}/logs` |
| Panely a technologie | `#/sluzba/panels` | `/panel/sluzba/panely` | `GET /v1/integrations/{provider}/objects`, akce per konektor |
| Zálohy a obnova | `#/zalohy` | `/panel/zalohy` | `GET /v1/services/{id}/backups`, `POST .../restore` |
| Domény a DNS | `#/domeny` | `/panel/domeny` | `GET /v1/domains`, `/v1/domains/{name}/zone` |
| Zóna s publikací (WAPI) | `#/domeny` → editor zóny | `/panel/domeny/{name}/zona` | `POST /v1/domains/{name}/zone/changes`, `.../commit` |
| Fakturace a platby | `#/fakturace` | `/panel/fakturace` | `GET /v1/invoices`, `POST /v1/invoices/{id}/pay`, `GET /v1/invoices/{id}/pdf` |
| Tickety | `#/tikety` | `/panel/tikety` | `GET/POST /v1/tickets`, `POST /v1/tickets/{id}/messages` |
| Asistent | `#/asistent` | `/panel/asistent` | `POST /v1/assistant/ask` (LLM za `bot()`) |
| Znalostní báze | `#/znalosti` | `/panel/znalosti` | `GET /v1/kb?q=` |
| Přístupy a API tokeny | `#/api` | `/panel/api` | `GET/POST /v1/api-tokens`, `GET /v1/webhooks/deliveries` |
| Nastavení účtu a firmy | `#/nastaveni` | `/panel/nastaveni` | `GET/PUT /v1/customer`, `/v1/team`, `/v1/security` |

Další sekce, které panel má a routy potřebují: `#/weby`, `#/certifikaty`, `#/hry`,
`#/posta`, `#/tym`, `#/affiliate`, `#/forum`, `#/deploye`, `#/monitoring`, `#/audit`,
`#/osobni-udaje`, `#/pristupnost`, `#/servisni-okna`, `#/hardware`, `#/housing`.

### Administrátorská sekce (`Onhost-admin.dc.html`)

Prefix `/sprava`, middleware `auth` + `role:*`. Slugy drží `VIEW_SLUG`.

| Sekce | Hash | Laravel route | Klíčové endpointy |
| --- | --- | --- | --- |
| Přehled | `#/prehled` | `/sprava` | `GET /v1/ops/summary` |
| Fronta práce | `#/fronta` | `/sprava/fronta` | `GET /v1/tickets?assigned=`, transition endpointy |
| Tiket | `#/tiket/{id}` | `/sprava/tiket/{ticket}` | `GET /v1/tickets/{id}`, `POST .../messages`, `.../transition` |
| Zákazníci | `#/zakaznici` | `/sprava/zakaznici` | `GET /v1/customers`, `/v1/customers/{id}` |
| Objednávky | `#/objednavky` | `/sprava/objednavky` | `GET /v1/orders`, `POST /v1/orders/{id}/transition` |
| Služby a integrace | `#/sluzby` | `/sprava/sluzby` | `GET /v1/integrations`, `/v1/provisioning/jobs`, `POST .../retry` |
| Uzly a kapacita | `#/uzly` | `/sprava/uzly` | `GET /v1/nodes`, `/v1/capacity` |
| Incidenty | `#/incidenty` | `/sprava/incidenty` | `GET/POST /v1/incidents`, `/v1/incidents/{id}/updates` |
| Fakturace | `#/fakturace` | `/sprava/fakturace` | `POST /v1/invoices/{id}/reminder`, `/v1/dunning/run` |
| Doklady | `#/doklady` | `/sprava/doklady` | `POST /v1/invoices/{id}/pay\|credit-note`, `/v1/sla-credits` |
| Tarify | `#/tarify` | `/sprava/tarify` | `GET/PUT /v1/plans` |
| Lidé a směny | `#/lide` | `/sprava/lide` | `GET /v1/staff`, `/v1/shifts` |
| Interní chat | `#/chat` | `/sprava/chat` | realtime kanál `internal.ops` |
| Znalosti a runbooky | `#/znalosti` | `/sprava/znalosti` | `GET/PUT /v1/kb`, `/v1/runbooks` |
| Šablony mailů | `#/maily` | `/sprava/maily` | `GET /v1/outbox`, `POST /v1/outbox/{id}/send`, `mail_templates` |
| Audit | `#/audit` | `/sprava/audit` | `GET /v1/audit` |
| Reporty | `#/reporty` | `/sprava/reporty` | `GET /v1/reports/*` |
| Nastavení | `#/nastaveni` | `/sprava/nastaveni` | `GET/PUT /v1/settings` |

### Partnerský portál (`Onhost-partner.dc.html`)

Prefix `/partner`, middleware `auth` + `role:partner`, globální scope na `partner_id`.

| Sekce | Hash | Laravel route | Endpointy |
| --- | --- | --- | --- |
| Přehled | `#/prehled` | `/partner` | `GET /v1/partner/summary` |
| Klienti | `#/klienti[/{id}]` | `/partner/klienti[/{customer}]` | `GET /v1/partner/customers` |
| Provize | `#/provize` | `/partner/provize` | `GET /v1/partner/commissions` |
| Výplaty | `#/vyplaty` | `/partner/vyplaty` | `GET/POST /v1/partner/payouts` (samofakturace) |
| Whitelabel | `#/whitelabel` | `/partner/whitelabel` | `GET/PUT /v1/partner/branding` |
| Materiály | `#/materialy` | `/partner/materialy` | `GET /v1/partner/assets` |

### Mobilní aplikace (`Onhost-mobil.dc.html`)

Prefix `/app`, middleware `auth:sanctum`. Slugy drží `SCREEN_SLUG`.

| Obrazovka | Hash | Laravel route | Endpointy |
| --- | --- | --- | --- |
| Služby | `#/sluzby` | `/app` | `GET /v1/services` |
| Výstrahy | `#/vystrahy` | `/app/vystrahy` | `GET /v1/status`, `/v1/invoices?state=unpaid`, `/v1/notifications` |
| Detail služby | `#/sluzba/{id}` | `/app/sluzba/{service}` | `GET /v1/services/{id}`, `POST .../power` |
| Konzole | `#/konzole` | `/app/konzole` | `GET /v1/services/{id}/console-token` |
| Účet | `#/ucet` | `/app/ucet` | `GET /v1/me`, `/v1/invoices` |

Push notifikace cíluje přímo na `#/sluzba/{id}` nebo `#/vystrahy` — obrazovka je v URL.
Potvrzování výstrah je klientské; server dostane jen `POST /v1/notifications/{id}/ack`.

## 3. Datové seamy — kde přesně nahradit lokální data

Žádná plocha nefetchuje. Vše jde přes globální objekty; napojení znamená vyměnit
jejich těla, ne obrazovky.

| Seam | Kdo ho čte | Náhrada |
| --- | --- | --- |
| `window.ONHOST_DATA` (`onhost-data.js`) | prezentační web — katalog, tarify, srovnání, stav, lokality, changelog | předplnit ze serveru (Blade/Inertia props) nebo `GET /api/*` — tvary jsou v `docs-backend-handoff.md` §2 |
| `window.ONHOST_PUBLIC` (`onhost-public.js`) | prezentační web — API reference a SLA matice | `GET /v1/api-reference`, `GET /v1/sla` nebo statická data v repozitáři backendu; jeden zdroj pro web i pro skutečné limity |
| `window.OnhostStore` (`onhost-store.js`) | klientská sekce, administrace, partner, mobil | REST `/v1/*` + stavový automat na serveru; UI čte stejné tvary |
| `window.OnhostSession` (`onhost-shell.js`) | všech pět ploch | `GET /v1/me`, `GET/PUT /v1/cart`; `onhost.handoff` v produkci nepotřeba |
| `window.OnhostIntegrations` / `OnhostDomains` | administrace, klientská sekce | `/v1/integrations*`, `/v1/domains*` — konektory podle `docs-provider-apis.md` |
| `onhost-content.js`, `onhost-docs.js`, `onhost-svc-*.js` (ES moduly) | prezentační web — blog, knowledgebase, dokumentace, 49 katalogových listů | CMS tabulky nebo Markdown v repozitáři; tvar `{ id, sec, title, lead, blocks[] }` zůstává |

Persistence prototypu, která v produkci zmizí: IndexedDB snapshot +
`localStorage['onhost.ops']` (hot cache), `onhost.session`, `onhost.cart`, `onhost.role`.
UI paměť ploch (`onhost.web`, `onhost.appka`) smí zůstat — je to preference zobrazení,
ne data.

## 4. Co musí backend dodat, aby se UI nemuselo měnit

1. **Chyby po polích**: `{"message":"…","errors":{"email":["…"]}}` — web i panel to takhle kreslí (`authRules()`, `coRules()`).
2. **Stránkování**: `?limit=40&offset=` + `X-Total-Count`; UI kreslí okno 40 řádků a pás „41–80 z 609".
3. **Ceny**: server vrací netto za jednotku a období; DPH (`ONHOST_VAT_RATE`) se počítá na jednom místě. Konfigurátor musí dostat koeficienty (`cfgBase`, `cfgCpu`, `cfgRam`, `cfgDisk`, `capexMult`, `roiModel`) s katalogem, jinak se cena v konfigurátoru rozejde s objednávkou.
4. **Slovník stavů**: názvy stavů posílá server (`flows.incident[state].label` v prototypu). Veřejná stránka nesmí mít vlastní mapu — rozejde se a vypíše strojový slug.
5. **Idempotence**: platba, provisioning i dávkové akce v administraci (`Provisionovat vše čekající`) musí snést dvojí odeslání.
6. **Krátkodobé tokeny pro konzoli**: `GET /v1/services/{id}/console-token` (Wings WS token, noVNC ticket), TTL v desítkách sekund. Klíče konektorů do prohlížeče nikdy.
7. **Realtime**: kanály `customer.{id}`, `internal.ops`, `service.{id}.console`; formát řádku podle `docs-live-data.md` (`{t, src, m, k, l}`), server nepřepisuje `t`.
8. **Role a rozsah dat**: role z `onhost-shell.js` (`admin`, `klient`, `partner`, `noc`, `fakturace`) → policies; `own: true` role dostávají globální scope na `customer_id` (partner na `partner_id`). Filtrovat v dotazu, ne v UI.

## 5. Co zbývá rozhodnout (blokuje implementaci, ne prototyp)

1. **Renderovací model**: Inertia + React (sekce se stanou komponentami jedné SPA) nebo Blade + Livewire (sekce jako samostatné stránky). Prototyp je připravený na obojí.
2. **Platební brána** (Comgate / GoPay / Stripe) — návratové URL a webhook.
3. **Účetní systém** a číselné řady dokladů (dnes `FV-YYYY-NNNN`, `DK-YYYY-NNNN`).
4. **Registrátor mimo WEDOS** pro TLD, které WAPI nepokrývá.
5. **Retence dat po zrušení služby** — SLA k tomu dnes mlčí.
6. **LLM za asistentem** — model, limity, co smí opustit naši infrastrukturu.

## 6. Kontrolní seznam před prvním sprintem

- [ ] Vybraný renderovací model a platební brána.
- [ ] Migrace podle datového modelu (`docs-laravel-backend.md` §3) a seed z prototypových dat.
- [ ] `/v1` endpointy z kapitoly 2 tohoto dokumentu, chyby po polích, stránkování s hlavičkou.
- [ ] Stavové automaty s vedlejšími efekty (audit, notifikace, mail do outboxu).
- [ ] Pět konektorů s frontou per konektor, idempotenčními klíči a `provider_calls`.
- [ ] Policy testy z allowlistů rolí (`onhost-shell.js` → `ROLES[].s`, `ROLES[].acts`).
- [ ] Kanonické URL, meta a sitemap pro prezentační web; `/panel`, `/sprava` a `/partner` za autentizací; `/stav` a `/api-reference` veřejně.
- [ ] Odstranit lešení: přepínač ploch (`onhost-shell.js`), příkazová paleta, `data-oh-chrome`.
