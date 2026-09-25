# Onhost — produkční backend v Laravelu

> **Historický dokument — stav k 14. 9. 2026 (převzato do repozitáře, psáno pro prototyp); aktuální stav: `docs/context/CURRENT_STATE.md`.** Zadání psané před stavbou backendu. Aktuální platforma: Laravel 13 (13.30.1, modulární monolit `onhost-platform`), PHP 8.3, PostgreSQL 16 v produkci; živý aaPanel běží ve verzi 8.0.6 (ověřeno 2026-09-13, `docs/runbooks/preproduction-audit.md` §4). Tabulka „Stack“ v §1 neplatí: framework je Laravel 13, ne 11; Saloon, balíčky `spatie/*` ani Horizon se nepoužívají (HTTP klient Laravelu za kontrakty v `providers/Contracts`, stavové automaty jako data v `StateMachine`, role a oprávnění v `RoleCatalog`/`PermissionCatalog`, fronty `default`, `mails`, `provider-*` pod systemd). Rozhodnutí, která dokument ještě vede jako otevřená, jsou vzatá: **renderovací model** = prototypové plochy beze změny + datové švy (`docs/adr/0005-surfaces-preserved-with-data-seams.md`), administrace a e-maily v Blade, žádné Inertia/React/Livewire; **platby** = adaptéry Comgate, GoPay, Stripe a bankovní převod (`providers/Payments`), ke spuštění Comgate (`docs/runbooks/go-live-checklist.md` §3); **účetnictví a řady dokladů** v ONhostu (`docs/adr/0003-money-ledger-tax.md`); **druhý registrátor** = Subreg vedle WEDOS s výběrem nejlevnějšího (`docs/runbooks/domain-registrars.md`); **retence po zrušení** = lhůta na obnovu 30 dní a závěrečný archiv 60 dní (`config/onhost.php` `services.deletion`, `services.service_archive_days`); **LLM za asistentem** = pravidla napřed, pak OpenAI-kompatibilní nebo Anthropic poskytovatel jen se čtecími nástroji a návrhy, které zákazník potvrdí (`docs/provider-adapters/ai.md`).

Zadání pro vývoj backendu k prototypu. Prototyp je hotová klientská strana: 22 ploch,
provozní store se stavovými automaty, role a gate, reporty a testovací matice. Tento dokument
říká, co z toho se má stát serverem, jak to rozdělit a v jakém pořadí to postavit.

Doprovodné dokumenty: `docs-provider-apis.md` (pět cizích API do detailu),
`docs-backend-handoff.md` (routy, obsahová data, kontrakty UI), `docs-live-data.md`
(formát řádku konzole a logů). Strojově čitelný kontrakt integrací: `onhost-integrations.js`.

---

## 1. Stack

| Vrstva | Volba | Proč |
| --- | --- | --- |
| Runtime | PHP 8.3, Laravel 11 | LTS ekosystém, Horizon a Octane bez ohýbání |
| Databáze | PostgreSQL 16 | `jsonb` pro payloady jobů, partial indexy, `FOR UPDATE SKIP LOCKED` |
| Cache / fronty | Redis 7 + Horizon | fronty per konektor, limitery, `WithoutOverlapping` |
| HTTP klienti | Saloon v3 | konektor na API, request na akci, mock v testech |
| Stavy | `spatie/laravel-model-states` | stavové automaty 1:1 s prototypem |
| Oprávnění | `spatie/laravel-permission` + Policies | role z prototypu (admin, klient, partner, NOC, fakturace) |
| Autentizace | Sanctum (SPA cookie) + osobní tokeny pro veřejné API | panel a API mají jiné hrozby |
| DTO / validace | `spatie/laravel-data` + FormRequests | jeden tvar dat od requestu po job |
| Realtime | Laravel Reverb (nebo Pusher) | notifikace, dohledová zeď, stav úloh |
| Doklady | vlastní modul + `barryvdh/laravel-dompdf` | česká fakturace se do cizích balíků neschová |
| Testy | Pest 3, Saloon MockClient, Dusk na kritické toky | contract testy nad pěti API |
| Observabilita | Horizon, Telescope (jen staging), Prometheus exporter, Sentry | fronta a konektory musí být vidět |

Bez Octane začít; zapnout ho teprve po zátěžovém testu (stav konektorů se cachuje v procesu,
což s Octanem vyžaduje pozor na sdílený stav).

## 2. Struktura aplikace

Moduly jako jmenné prostory v `app/`, ne balíčky. Hranice se drží přes eventy, ne přes přímé
volání modelů cizího modulu.

```
app/
  Catalog/        produkty, tarify, konfigurátor, ceny, ROI koeficienty
  Ordering/       košík, objednávka, checkout, promo, kredit
  Billing/        faktury, platby, upomínky, opravné doklady, SLA kredity, výnosy
  Provisioning/   workflow řetězy, joby, resource mapping, reconciliace
  Integrations/   konektory (Wedos, AaPanel, Proxmox, Pterodactyl, IspConfig), credentials, call log
  Support/        tickety, zprávy, témata a shluky, asistent
  Incidents/      incidenty, časová osa, stavová stránka, postmortem
  Domains/        domény, zóny, dávky změn, commit, NSSET
  Notifications/  notifikace v panelu, outbox transakčních mailů, šablony
  Reporting/      MRR, inkaso, churn, MTTR, plnění SLA, kapacita
  Audit/          append-only log se zřetězeným hashem
  Platform/       role, uživatelé, session, API tokeny, feature flagy
```

## 3. Datový model

Klíčové tabulky. Sloupce jsou minimum, ne úplný výčet — `created_at`, `updated_at`,
`created_by` a soft delete tam, kde to má smysl.

**Zákazníci a lidé**
- `customers` — `id, type(person|company), name, ico, dic, address_*, country, lang, currency, credit_czk, partner_id, state`
- `users` — `id, customer_id, email, name, password, two_factor_*, last_login_at, state`
- `roles`, `permissions` — z `spatie/laravel-permission`; role odpovídají `ROLES` v `onhost-shell.js`
- `partners` — `id, customer_id, tier, commission_pct, hold_pct, whitelabel_domain`

**Katalog a objednávky**
- `products` — `id, key, category, name_cs, name_en, state`
- `plans` — `id, product_id, key, price_net_czk, price_net_eur, period(month|year), specs jsonb, badge, highlighted`
- `plan_options` — konfigurátor: `id, plan_id, kind(slider|addon), key, label, unit, min, max, step, price_net_per_unit`
- `orders` — `id, number, customer_id, state, commit_months, currency, net, vat, total, promo_code, source(web|admin|api), eta_at`
- `order_items` — `id, order_id, plan_id, sku, name, qty, unit_net, config jsonb`
- `services` — `id, order_item_id, customer_id, product_id, plan_id, state, hostname, node, params jsonb, activated_at, suspended_at, expires_at`

**Fakturace**
- `invoices` — `id, number(FV-YYYY-NNNN), customer_id, order_id, state, issued_at, due_at, paid_at, net, vat, total, currency, vat_rate`
- `invoice_lines` — `id, invoice_id, description, qty, unit_net, vat_rate, period_from, period_to`
- `payments` — `id, invoice_id, method, amount, external_id, state, paid_at, raw jsonb`
- `credit_notes` — `id, number(DK-YYYY-NNNN), invoice_id, reason, incident_id, amount_net`
- `dunning_runs`, `dunning_actions` — dávky upomínek a co která udělala

**Podpora a incidenty**
- `tickets` — `id, number, customer_id, service_id, subject, state, priority, topic, first_reply_at, resolved_at`
- `ticket_messages` — `id, ticket_id, author_type(customer|staff|bot), author_id, body, attachments jsonb`
- `incidents` — `id, number(INC-…), severity(p1..p4), state, title, started_at, resolved_at, affected jsonb, public`
- `incident_updates` — `id, incident_id, state, note, author_id, published_at`
- `sla_credits` — `id, incident_id, invoice_id, service_id, pct, amount_net, state`

**Integrace a provisioning**
- `provider_credentials` — `id, provider, node, label, secrets encrypted jsonb, egress_ip, state, rotated_at`
- `provider_calls` — `id, provider, action, method, path, http_code, body_code, duration_ms, job_id, actor, request jsonb(masked), response jsonb(masked), created_at` (partitioned per měsíc, retence 90 dní)
- `provisioning_jobs` — `id, workflow, step, steps_total, provider, order_id, service_id, state, attempts, idempotency_key(unique), external_handle, payload jsonb, error, queued_at, started_at, finished_at`
- `resource_mappings` — `id, service_id, provider, external_type, external_id, node, idempotency_key(unique), last_sync_at, checksum`
- `resource_drifts` — `id, service_id, provider, field, expected, actual, detected_at, resolved_at, resolution`
- `integration_health` — `provider, node, last_success_at, last_failure_at, error_rate_1h, p95_ms, budget_used`

**Domény a DNS**
- `domains` — `id, name, tld, customer_id, service_id, registrar(wedos), state, expires_at, auto_renew, nsset, dnssec, wapi_id, renewal_price_net`
- `dns_zones` — `id, domain_id, provider(wedos|ispconfig), serial, committed_at`
- `dns_records` — `id, zone_id, host, type, data, ttl, prio, managed_by(system|user)`
- `dns_changes` — `id, zone_id, op(add|update|delete), payload jsonb, reason, requested_by, state(pending|committed|discarded), committed_at`

**Komunikace, audit, reporting**
- `notifications` — `id, audience(internal|customer), customer_id, kind, title, body, read_at, ref`
- `outbox_mails` — `id, template, to, subject, vars jsonb, state(queued|sent|failed), ref, sent_at, provider_message_id`
- `mail_templates` — `id, key, subject_cs/en, body_cs/en, category, state`
- `audit_log` — `id, actor, actor_role, area, action, subject_type, subject_id, detail, ip, prev_hash, hash, created_at` — append-only, bez UPDATE/DELETE grantu pro aplikačního uživatele
- `capacity_snapshots` — `id, pool, node, cpu_used, mem_used, disk_used, capacity, taken_at`
- `webhook_events` — `id, source, signature_ok, payload jsonb, processed_at, result`
- `api_tokens` — Sanctum + `scopes jsonb, rate_limit, last_used_at`

## 4. Stavové automaty

Přesně to, co dnes drží `onhost-store.js`. Přechody smí dělat jen service třída, nikdy controller.

**Objednávka**: `nova → zaplaceno → provisioning → aktivni → pozastaveno | zruseno`
(z `pozastaveno` zpět na `aktivni` po zaplacení).
Vedlejší efekty přechodu: zápis do auditu, notifikace zákazníkovi i internímu týmu, mail do outboxu,
start/zastavení provisioning chainu.

**Faktura**: `vystavena → zaplacena` / `vystavena → po_splatnosti → zaplacena | stornovana`.
- `createOrder` vystaví fakturu ve stejné transakci (netto z položek, DPH `ONHOST_VAT_RATE`, splatnost 14 dní).
- Párování platby je **idempotentní v obou směrech**: platba překlopí objednávku, přechod objednávky
  na `zaplaceno` označí otevřenou fakturu. Dvojí zpracování webhooku nesmí vytvořit druhou platbu
  (unikátní index na `payments.external_id`).
- Sweep splatnosti je cron, ne akce při načtení stránky.
- `dunningRun` → upomínka ke každé faktuře po splatnosti; nad `ONHOST_DUNNING_SUSPEND_DAYS`
  spustí workflow `lifecycle.suspend`.
- Opravný doklad: originál `stornovana`, nový `DK-YYYY-NNNN` se zápornými řádky.
- SLA kredit: procento z měsíční ceny zasažených služeb, default p1 25 %, p2 10 %, p3 5 %,
  doklad s vazbou na incident.

**Ticket**: `otevreny ↔ ceka → vyreseny`; první odpověď se stopuje pro SLA report.
**Incident**: `vysetrovani → identifikovano → monitoring → vyreseno`; každý přechod je veřejný
záznam na stavové stránce.
**Provisioning job**: `queued → running → done | failed → retrying`; `failed` po vyčerpání pokusů
zakládá ticket a nechává objednávku v `provisioning`.

## 5. HTTP API

Verzované pod `/v1`, JSON, `Accept-Language` pro CS/EN. Chyby vždy po polích:
`{"message":"…","errors":{"email":["…"]}}` — prototyp to takhle vykresluje (`authRules()`, `coRules()`).
Seznamy: `?limit=40&offset=` + hlavička `X-Total-Count` (UI kreslí okno 40 řádků a pás „41–80 z 609").

| Oblast | Endpointy |
| --- | --- |
| Session | `POST /v1/auth/login`, `POST /v1/auth/register`, `POST /v1/auth/password/reset`, `GET /v1/me`, `POST /v1/auth/logout` |
| Katalog | `GET /v1/catalog`, `GET /v1/plans`, `GET /v1/plans/compare`, `GET /v1/services/{id}` (konfigurátor včetně koeficientů) |
| Košík | `GET/PUT /v1/cart`, `POST /v1/cart/promo`, `POST /v1/orders` |
| Objednávky | `GET /v1/orders`, `GET /v1/orders/{id}`, `POST /v1/orders/{id}/transition` |
| Fakturace | `GET /v1/invoices`, `GET /v1/invoices/{id}` (+`/pdf`), `POST /v1/invoices/{id}/pay`, `/reminder`, `/credit-note`, `POST /v1/sla-credits`, `GET /v1/revenue` |
| Platby | `POST /v1/payments/init`, `POST /v1/webhooks/payments` (podepsaný), návratové URL success/pending/failed |
| Služby | `GET /v1/services`, `POST /v1/services/{id}/power`, `/resize`, `/backup`, `GET /v1/services/{id}/console-token` |
| Tickety | `GET/POST /v1/tickets`, `POST /v1/tickets/{id}/messages`, `POST /v1/tickets/{id}/transition` |
| Incidenty | `GET /v1/incidents`, `POST /v1/incidents`, `POST /v1/incidents/{id}/updates`, `GET /v1/status` (veřejné) |
| Domény | `GET /v1/domains`, `POST /v1/domains/check`, `POST /v1/domains/{name}/renew`, `GET /v1/domains/{name}/zone`, `POST /v1/domains/{name}/zone/changes`, `POST /v1/domains/{name}/zone/commit` |
| Integrace | `GET /v1/integrations`, `GET /v1/integrations/{provider}/health`, `GET /v1/provisioning/jobs`, `POST /v1/provisioning/jobs/{id}/retry`, `/cancel`, `GET /v1/resource-mappings?drift=1` |
| Notifikace / maily | `GET /v1/notifications?audience=`, `POST /v1/notifications/read`, `GET /v1/outbox`, `POST /v1/outbox/{id}/send` |
| Reporty | `GET /v1/reports/mrr`, `/collections`, `/churn`, `/mttr`, `/sla`, `/capacity` |
| Obsah | `GET /v1/posts`, `/posts/{slug}`, `/kb?q=`, `/kb/{slug}`, `/changelog`, `/locations`, `/stock` |
| Leady | `POST /v1/leads`, `POST /v1/reseller/apply`, `POST /v1/tender/request` |

Veřejné API pro zákazníky (dokumentované na `Onhost.dc.html#/api`) je podmnožina se scope tokeny:
`services:read`, `services:power`, `invoices:read`, `tickets:write`, `dns:write`.
Limit 120 req/min na token, `429` s `Retry-After`.

## 6. Fronty a plánovač

**Fronty**: `default`, `mails`, `provisioning:wedos`, `provisioning:aapanel`, `provisioning:proxmox`,
`provisioning:pterodactyl`, `provisioning:ispconfig`, `sync`, `reports`.
Každá provisioning fronta má vlastní limiter (Redis funnel) podle rozpočtu konektoru
a `WithoutOverlapping` na `service_id` — dvě akce nad jednou VM se nikdy nepotkají.

**Workflow řetězy** (`Bus::chain()`), plné znění v `onhost-integrations.js`:
`provision.webhosting`, `provision.vps`, `provision.game`, `provision.mail`, `domain.register`,
`lifecycle.suspend`. Kroky typu „čekání" (`AwaitTask`, `AwaitDatalog`, `AwaitInstall`,
`AwaitRegistryResult`) se releasují zpět do fronty; chain se při selhání zastaví a nikdy
nepřeskočí dopředu.

**Plánovač**

| Kdy | Úloha |
| --- | --- |
| každou minutu | `provisioning:tick` (dohled nad zaseknutými joby), `outbox:send` |
| každých 5 min | `integrations:health`, `capacity:snapshot` |
| každou hodinu | `domains:sync` (WEDOS), `integrations:reconcile`, `reports:refresh` |
| každý den 06:00 | `invoices:due-sweep`, `billing:dunning`, `domains:renewals` (30/14/3 dny), `domains:credit` |
| každý den 03:00 | `provider_calls:prune` (90 dní), `backups:verify` |
| každý týden | `sla:report`, `audit:verify-chain` |

## 7. Autorizace

Role z prototypu jsou zdroj pravdy (`ROLES` v `onhost-shell.js`): `admin` (vše),
`klient` (jen vlastní data), `partner` (klienti pod partnerem, provize, whitelabel),
`noc` (dohled, incidenty, konzole), `fakturace` (doklady, upomínky, výnosy).

- Akce z prototypu (`pay`, `dunning`, `mail`, `incident`, `order`, `ticket`, `seed`) se stávají
  Gate abilities; `order` je destruktivní skupina (provisioning, power akce, mazání) a má ji jen admin.
- Rozsah dat: `own: true` role dostávají globální scope na `customer_id` (partner na `partner_id`).
  Nikdy nefiltrovat v UI — filtruje se v dotazu.
- Zásah na uzlu nebo v clusteru (`P_NODE_MAY` v prototypu) je vyhrazený adminovi a NOC.
- Allowlisty rolí z `onhost-shell.js` (`ROLES[].s`, `ROLES[].acts` — dosah rolí, destruktivní akce, rozsah dat)
  se překlápějí na Pest testy — je to hotová testovací matice, stačí ji přepsat do assertů.

## 8. Bezpečnost

- Tajemství konektorů v `provider_credentials` s `encrypted` castem; `.env` nese jen `APP_KEY`
  a adresy. Rotace klíčů bez nasazení (záznam v DB, verze u konektoru).
- Volání do cizích API jen z workerů s pevnou výstupní IP (whitelisty WEDOSu, aaPanelu, ISPConfigu).
- Do prohlížeče jde výhradně krátkodobý token pro konzoli (`ONHOST_CONSOLE_TOKEN_TTL`).
  Nikdy API klíč účtu, nikdy heslo do panelu.
- Audit má zřetězený hash (`prev_hash` → `hash`), aplikační uživatel nemá UPDATE ani DELETE.
  Každý zásah člověka nese jméno a důvod — stejné pravidlo, jaké dnes vynucuje `panelAudit()`
  a `fromStaffAction()`.
- Webhook platební brány: ověření podpisu, idempotence podle `external_id`, uložení surového
  payloadu do `webhook_events` i při neplatném podpisu (pro forenzní analýzu).
- 2FA povinná pro role `admin`, `noc`, `fakturace`. Zákaznické účty volitelně.
- GDPR: export a výmaz zákazníka jako job; audit a doklady se nemažou (zákonná retence),
  anonymizují se osobní údaje.

## 9. Realtime a konzole

- Notifikace, stav úloh a dohledová zeď přes Reverb; kanály `customer.{id}`, `internal.ops`,
  `service.{id}.console`.
- Konzole neproxujeme přes vlastní socket, kde to nemusí být: pro Pterodactyl jde prohlížeč
  přímo na Wings s krátkodobým tokenem, pro Proxmox na `vncwebsocket` s ticketem.
  Backend je jen vydavatel tokenu a zapisovatel auditu.
- Formát řádku je jeden pro všechno (`{t, src, m, k, l}` z `docs-live-data.md`); server
  nepřepisuje `t` a řádek s `k: 'us'` bez jména a důvodu odmítne postavit.
- Buffery: 40 řádků konzole, 60 shell; delší historie je stahování logu, ne WebSocket.

## 10. Reporting

Metriky, které dnes prototyp počítá v UI, se počítají na serveru z ledgeru:
MRR/ARR z aktivních služeb, inkaso a pohledávky po dnech splatnosti, churn po měsících,
MTTR z incidentů, první odpověď z tiketů, plnění SLA, kapacita z `capacity_snapshots`
(pooly Webhosting, VPS, Game, Mail, Storage) včetně predikce stropu N+1.
Materializované pohledy obnovované hodinově; UI dostává hotová čísla, ne surová data k dopočtu.

## 11. Testy

- **Unit**: stavové automaty a jejich vedlejší efekty, výpočet DPH a cen, dunning, SLA kredity.
- **Feature**: každý endpoint pro každou roli (matice z `onhost-shell.js`), stránkování,
  chyby po polích.
- **Contract**: pět konektorů proti fixtures (úspěch, chyba v těle při HTTP 200, 429, timeout).
- **Workflow**: každý chain celý, plus scénář „krok N selže" pro každý krok.
- **E2E**: objednávka → platba → provisioning → aktivace → faktura → incident → SLA kredit
  (to je scénář, který prototyp přehrává v `Onhost-scenar.dc.html`).
- **Zátěž**: seed 5 000 objednávek / 7 000 ticketů (prototyp to umí přes `seedVolume()`),
  cíl p95 < 300 ms na seznamech se stránkováním.

## 12. Nasazení

Dvě prostředí (staging, produkce), infrastruktura jako kód. Role strojů: web (Nginx + PHP-FPM),
worker (Horizon, pevná výstupní IP), db (PostgreSQL + PITR), redis, storage (doklady, zálohy logů).
Nasazení bez odstávky (`php artisan down` jen pro migrace, které to vyžadují), migrace vždy
zpětně kompatibilní o jedno vydání. Záloha DB každých 15 minut WAL + denní full, obnovu
zkoušet měsíčně — SLA na stavové stránce je slib, ne přání.

Monitoring: Horizon (fronty), Prometheus (latence konektorů, hloubka front, chybovost),
alerty na: konektor bez úspěchu 10 min, fronta provisioning > 50, faktura po splatnosti > 30 dní
bez akce, kredit u registrátora pod hranicí, zaseknutý job > 6 h.

## 13. Fáze

1. **Skelet a katalog** — auth, role, katalog, ceny, veřejné obsahové endpointy. Web běží na API.
2. **Objednávka a fakturace** — košík, objednávka, faktura, platební brána, upomínky, doklady.
3. **Provisioning I** — konektory Proxmox a Pterodactyl, workflow VPS a hra, fronta, audit.
4. **Provisioning II** — aaPanel, ISPConfig, WEDOS, workflow webhosting, mail, doména, commit zón.
5. **Podpora a incidenty** — tickety, notifikace, outbox, stavová stránka, SLA kredity.
6. **Reporty a reconciliace** — metriky, kapacita, drift, plánovač, veřejné API s tokeny.
7. **Zpevnění** — 2FA, rotace klíčů, chaos testy, zátěž, obnova ze zálohy, penetrační test.

Fáze 3 a 4 nejdou zaměnit: Proxmox a Pterodactyl jsou nejjednodušší z pěti (token v hlavičce,
jasná odpověď) a naučí tým pracovat s asynchronností. WEDOS a ISPConfig přidávají dvoufázovost,
kterou má smysl řešit až nad hotovou frontou.

## 14. Mapa: plocha prototypu → backend

| Plocha | Moduly | Klíčové endpointy |
| --- | --- | --- |
| `Onhost.dc.html` (web) | Catalog, Ordering | `/v1/catalog`, `/v1/plans`, `/v1/cart`, `/v1/orders` |
| `Onhost-app.dc.html` (panel) | Ordering, Billing, Support, Provisioning, Domains | `/v1/me`, `/v1/services`, `/v1/invoices`, `/v1/tickets`, `/v1/domains` |
| `Onhost-mobil.dc.html` | Incidents, Billing, Notifications | `/v1/status`, `/v1/invoices`, `/v1/notifications` |
| `Onhost-admin.dc.html` | Ordering, Provisioning, Incidents, Billing, Reporting, Notifications, Support | `/v1/orders`, `/v1/provisioning/jobs`, transition endpointy, `/v1/incidents*`, `/v1/invoices/*`, `/v1/sla-credits`, `/v1/revenue`, `/v1/outbox`, `mail_templates`, `/v1/reports/*`, `/v1/integrations*`, `/v1/resource-mappings`, `/v1/tickets` |
| `Onhost-app.dc.html` | Services, Provisioning, Domains, Billing, Support, Platform | `/v1/services*`, `/v1/backups`, `/v1/domains*`, `/zone`, `/zone/changes`, `/zone/commit`, `/v1/invoices*`, `/v1/tickets*`, `/v1/tokens`, clustering a LLM za `bot()` |
| `Onhost.dc.html` | Catalog, Content, Public status, Ordering, Platform | `/v1/catalog`, `/v1/plans`, `/v1/content/*`, `/v1/status`, `/v1/incidents`, `/v1/changelog`, `/v1/cart`, `/v1/orders`, scope tokeny a limity (dokumentované na `#/api`) |
| `Onhost-partner.dc.html` | Billing, Platform | provize, whitelabel, výplaty se samofakturací |

Testovací matice rolí (dosah rolí, veřejné plochy, destruktivní akce, rozsah dat) vychází
z allowlistů v `onhost-shell.js` (`ROLES[].s`, `ROLES[].acts`) — viz §12.

## 15. Co ještě chybí a je to rozhodnutí, ne kód

1. **Platební brána** — Comgate, GoPay nebo Stripe. Vybrat před fází 2; návratové URL a webhook
   se liší, zbytek modelu ne.
2. **Účetní systém** — export dokladů (Pohoda, Money, ABRA) nebo účtování jen v Onhostu.
   Ovlivňuje číselné řady a formát exportu.
3. **Registrátor pro TLD mimo WEDOS** — pokud se bude prodávat víc než `.cz/.eu/gTLD`,
   je potřeba druhý registrátor a `domains.registrar` už s tím počítá.
4. **Retence dat po zrušení** — jak dlouho držet zálohy zrušené služby (dnes SLA mlčí).
5. **Whitelabel pro partnery** — vlastní domény panelu znamenají certifikáty, mailové odesílatele
   a oddělené šablony; rozsah patří rozhodnout před fází 5.
6. **LLM za asistentem** — `bot()` v prototypu je odpovědní banka nad živými daty; kontrakt
   zůstává, ale je potřeba vybrat model, limity a co se smí posílat mimo naši infrastrukturu.
