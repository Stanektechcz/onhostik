# Audit připravenosti ONhost (2026-10-03)

Fáze A programu „ONhost: kompletní zprovoznění“. Audit jen četl kód. Nespouštěl testy, `brain.ps1 gate` ani doctor
a nekontaktoval žádný panel.

- **Výchozí stav:** `development` @ e711b7c.
- **Ověřeno:** každé tvrzení s odkazem `soubor:řádek` je ověřené čtením kódu.
- **PŘEDPOKLAD:** chování za běhu, které je potřeba ověřit spuštěním.

## Souhrn

Jádro platformy je hotové a dobře otestované na úrovni služeb. Patří sem:
- objednávka, platba převodem a z kreditu;
- doklady v CZK;
- provisioning saga s kompenzací;
- obnovy, upomínky, pozastavení a obnovení;
- dobropisy, partneři a schvalování ve čtyřech očích.

Chybí hlavně tři věci:
1. **Spojení vrstev.** Testy nesledují celou cestu přes HTTP a frontend volá i routy, které neexistují.
2. **Pravdivost UI.** Admin rozhraní ukazuje každé roli totéž. Partnerský portál a mobilní rozhraní zobrazují vymyšlená
   data. Veřejná stránka API popisuje endpointy, které neexistují.
3. **Sliby katalogu.** Několik tarifů prodává funkce, které nic nedodá.

Oblasti A1–A6 a jejich dílčí nálezy jsou shrnuté v backlogu níže. Podrobné zprávy oblastí jsou v historii relace
programu a lze je doplnit jako samostatné soubory.

## Prioritizovaný backlog

### P0 – blokuje provoz nebo bezpečnost

| # | Nález | Důkaz | Oblast |
|---|---|---|---|
| P0-1 | `/surfaces/onhost-panel.js` posílá každému členovi organizace všechny služby a domény, kredit, poslední fakturu a bankovní údaje. Platí to i pro hosta, `support_contact` a `developer`. Kontroluje se jen členství. | `SurfaceDataController.php:310-328, 575-680` | A2 |
| P0-2 | Partnerský portál zobrazí vymyšlené partnerské a finanční údaje každému přihlášenému, který partner není, a také při chybě API. Portál nemá partnerskou bránu. | `SurfaceController.php:127-133`, `Onhost-partner.dc.html:516-521`, `SurfaceRenderer.php:128-151` | A3 |
| P0-3 | Terminál, Node projekty a cron běží na sdíleném aaPanel uzlu pod společným `www` (PA-02/PA-03). Čeká se na rozhodnutí vlastníka O1. | `AaPanelWebProvider.php:418, 427-428`, `.ai/PROJECT_STATE.md:169-170` | A1 |
| P0-4 | Placené zálohy VPS a managed DB se nedělají, dokud je `backups.compute` vypnuté. Web a mail se nezálohují tak, jak byly prodány. | `BackupOperationsCheck.php:50`, `PlanPromises.php:114` | A1 |
| P0-5 | Na stagingu chybí systemd drop-in `ProtectSystem=no`. aaPanel `libusranalyse.so` jinak shodí workery s exit kódem rc 7. Totéž hrozí produkci, pokud běží na aaPanelu (PŘEDPOKLAD). | `infra/systemd/onhost-queue@.service:21`, `install.sh:135-142` | A6 |
| P0-6 | `staging.sh setup` nasadí znovu aktuální VERSION místo nejnovějšího stavu `development`. Opakovaný `setup` po nedokončené instalaci navíc zaparkuje nový strom i `/etc/onhost` a ztratí se heslo k DB. | `staging.sh:207, 369, 375`, `install.sh:222-231` | A6 |

### P1 – zákazník platí za funkci, kterou nedostane, nebo je rozbitý tok

| # | Nález | Důkaz | Oblast |
|---|---|---|---|
| P1-1 | Admin sidebar ukazuje stejných 17 pohledů každé staff roli (`role()` vrací vždy `lead`). Odpověď 403 se zobrazí jako prázdný seznam. | `onhost-admin.api.js:14, 24-28, 47-48`, `onhost-store.api.js:59-61` | A2, A3 |
| P1-2 | Zavírání a znovuotevírání ticketu z konzole je rozbité: posílá `state`, ale controller čeká `to`. | `onhost-store.api.js:219`, `Staff/SupportController.php:110` | A2 |
| P1-3 | Zápisy ticketů obcházejí CommandBus. `assignee` se nevaliduje a změna priority ani fronty se neaudituje. UI vždy posílá `visibility: public`, takže interní poznámku nejde napsat. Makra, fronty a SLA nejdou spravovat a business hours se ignorují. | `Staff/SupportController.php:119-137`, `onhost-store.api.js:202`, `SupportSeeder.php:23-44` | A2 |
| P1-4 | Stránky `/sprava/nastaveni/*` kontrolují jen `is_staff`. | `SystemSettingsController.php:26-118` | A2 |
| P1-5 | Asistent má tlačítko restartu, které volá neexistující `POST /services/{id}/power`. Výsledek je 404. | `onhost-panel-chat.api.js:81` | A3 |
| P1-6 | Ceny mimo `ONHOST_DATA` jsou napevno v kódu: gpu, inference, vectordb, colo, git, ci, managed, storage, games. Kurzy měn a DPH ×1,21 jsou taky napevno. | `onhost-svc-compute.js:216-489`, `SurfaceRenderer.php:1005, 1037` | A3 |
| P1-7 | Náhodný Idempotency-Key při každém kliknutí zabezpečí hůř, než kdyby se žádný klíč neposílal. `put`/`patch`/`del` klíč neposílají vůbec. | `onhost-session-bridge.js:151-156`, `ApiController.php:39-57` | A3 |
| P1-8 | Seamy `SurfaceRenderer` při rozjetí prototypu tiše selžou a vrátí se mock data. | `SurfaceRenderer.php:157-160, 422-616` | A3 |
| P1-9 | Kontrakt OpenAPI je nevalidní: duplicitní operationId, chybně deklarovaný bearer a idempotence. Žádný test ani CI drift check ho nehlídá. Na stagingu padá, protože www nepřepíše `onhost-v1.yaml`, který vlastní root. | `GenerateOpenApi.php:49-55, 96-100, 126-138`, `deploy.sh:637` | A5 |
| P1-10 | Staff API nemá strukturální guard. Žádný sweep neověřuje, že zákazník je na `/v1/staff/*` odmítnut. `GET staff/.../panel-login` mění stav. | `routes/api.php:395, 543`, `TenantIsolationSweepTest.php:65` | A5 |
| P1-11 | Veřejné `/api` a `/dokumentace` popisují neexistující endpointy a limity. Odkazy `help` vedou na kotvy, které neexistují. | `onhost-public.js:126-156`, `DomainError.php:49` | A5 |
| P1-12 | Webhooky posílají surový payload z outboxu (ověřit), podepisují se jiným formátem, než popisuje dokumentace, a doručují se synchronně v relay. | `WebhookDispatcher.php:74-79, 123`, `docs/api/README.md:21` | A5 |
| P1-13 | Věrnostní body jde farmit drobnými platbami: 10 bodů za každou platbu, i za 1 Kč. Body se nikdy neodebírají. | `LoyaltyRouter.php:36-51`, `WalletController.php:74` | A4 |
| P1-14 | Comgate nemá testy nad nahranými odpověďmi a webhook route se přes HTTP netestuje. | `GatewayRecurringContractTest.php:41, 89` | A4 |
| P1-15 | Tarif slibuje, ale nic se neaplikuje: schránky v e-shop tarifech (aaPanel `mail=false`), `dedicated_ipv4` u webu, zálohy VPS/VDS, řízené aktualizace WP, CDN závislé na env, KNOWN_GAPS. | `CatalogSeeder.php:47-49, 60-62, 230-253`, `AaPanelWebProvider.php:423`, `PlanPromises.php:106-115` | A1 |
| P1-16 | Staging: `public/build` nevlastní www, runbook nemá kroky TOTP (první staff účet se nepřihlásí), v S7 chybí `PHP=`, expected-nonok se nikdy neobnovuje, `wedos-main` se zakládá jako aktivní bez přihlašovacích údajů. | `staging.sh:60-64, 221-227`, `staging-launch.md:759, 805`, `InfrastructureSeeder.php:41` | A6 |

### P2 a P3

**P2**
- Backend bez UI:
  - DNS: verze, rollback, export.
  - Domény: převod na ONhost, kontakty.
  - Služby: health check, náhled destruktivní akce, spec.
  - Konzole: noVNC, živá konzole herního serveru.
  - Pošta: `sending.set`.
  - Admin: SSO do panelu.
- Reinstalace VPS a vlastní ISO.
- `ONHOST_ADDON_RENEWALS=false`: doplňky se vyúčtují jen jednou.
- Změna tarifu na aaPanelu na uzlu nic nemění.
- Role a oprávnění:
  - spící oprávnění (iam_admin a auditor_read_only skoro nic neumí);
  - SS-7;
  - zákaznický sidebar ignoruje roli;
  - nekonzistence rolí (security_auditor, kdo smí otevřít ticket).
- Idempotence bez organizace a bez zámku.
- Tokeny bez expirace.
- Pole `provider` v chybách vidí zákazník.
- Limiter `probes` jde obejít.
- Paginace bez tiebreakeru.
- Outbox dead letters nemají alert.
- StaffReadAudit jen na 4 místech.
- Verifikační mail obchází MailOutbox.
- Partnerská provize bez ochranné lhůty.
- Úklid aaPanelu při ukončení služby není ověřený.
- Staging:
  - revize katalogu, capacity basis;
  - Turnstile, ClamAV;
  - openapi jen porovnávat.

**P3**
- Přístupnost: `lang`, `:focus-visible`, `window.prompt`.
- České plurály.
- Mrtvý modul `OnhostDomains`.
- DNS scopy.
- Politika verzí API.
- Executable bit `staging.sh`.

## Blokátory a rozhodnutí vlastníka (s doporučeným výchozím)

1. **TASK-0043/0044:** tabule je vede jako zamčené. Zámky pokrývají `RoleCatalog`, `PermissionCatalog`,
   `apps/surfaces/api`, `domains/Support`, `routes/*` a `config/onhost.php`. Podle `.ai/PROJECT_STATE.md:201` ale
   nikdy nevznikly a soubory úkolů neexistují. **Doporučení:** zámky uvolnit (`brain.ps1 task release`) před fází B.
2. **O1 (terminál, Node a cron na sdíleném aaPanelu):** vypnout na sdílených uzlech.
3. **Zálohy compute:** zapnout `backups.compute` po kontrole úložiště PBS.
4. **E-shop schránky a `dedicated_ipv4` u webu:** odebrat revizí katalogu, dokud nebudou dodatelné.
5. **Neověřený e-mail:** blokovat výplaty partnerům a objednávky nad limit.
6. **Věrnost:** minimální částka platby 100 Kč pro body; body odebírat při dobropisu nebo chargebacku.
7. **Partnerská provize:** ochranná lhůta 30 dní.
8. **SS-7:** odebrat `support.customer_impersonate` roli `support_manager`.
9. **Tokeny:** zapnout `ONHOST_TOKEN_ORGANIZATION_REQUIRED` po `operator:tokens:unbound`, výchozí expirace 365 dní.

## Pracovní balíky pro fáze B–F

Každý balík je samostatný úkol (`brain.ps1 task start`), má vlastní worktree a začíná testem, který nejdřív selže.

| Fáze | Balík | Obsah | Akceptace |
|---|---|---|---|
| B | B0 | Payload panelu podle oprávnění (P0-1) | Matice zákaznických rolí + host s `svc_view`: payload obsahuje přesně to, co vrátí API dané role |
| B | B1 | Transition přijme `to` i `state` (P1-2) | POST `{state: 'RESOLVED'}` vrátí 200 |
| B | B2 | Registr navigace řízený oprávněními (`NavItem{required_permissions any/all, section, order, icon, screen, api[]}`), doručení přes `ONHOST_BOOT.user.nav` | Snapshot pro každou roli; držitel oprávnění nedostane na `api` položky 403; architektonický test staff GET rout; prototyp zůstane byte-identický |
| B | B3 | Store načítá data podle registru; 403 zobrazí „nemáte přístup“ | Mockovaná 403 ukáže chybu, ne prázdný seznam |
| B | B4 | Chybějící položky pro každou roli; stránky nastavení s `authorize()` (P1-4) | Žádné oprávnění s endpointem nezůstane bez položky (výjimka: API-only) |
| B | B5 | Dokončení podpory: bus, validace, audit, `ticket.assigned`, UI pro přiřazení, interní poznámky a makra, správa front a SLA, business hours (P1-3) | L1 assign = 403, L2 = 200; assignee bez staff práv = 422; změna priority zapíše audit |
| B | B6 | Hygiena rolí: SS-7, security_auditor, spící oprávnění, chargebacky | Rozšířený PermissionMatrixTest |
| B | B7 | Zákaznický sidebar podle role | Matice `nav.links` pro každou roli |
| C | C0 | Partnerská brána a odstranění vymyšlených dat (P0-2) | Ne-partner dostane přihlášku; v HTML nejsou demo jména |
| C | C1 | Opravy volání neexistujících rout + kontraktní test frontend → routy (P1-5) | Žádná neznámá cesta, CI zčervená při driftu |
| C | C2 | Ceny jen z katalogu, nebo stránka „nedostupné“; kurzy z ČNB; DPH z tax rules (P1-6) | Test přes všechny slugy produktových stránek |
| C | C3 | Test kotev seamů (P1-8) | Každá kotva se trefí přesně jednou |
| C | C4 | Stabilní idempotence pro každý záměr uživatele (P1-7) | Dvojklik spustí jeden command |
| C | C5–C11 | Jedna rodina služeb = jeden balík (web, mail, domény, DNS, VPS, game, společné), včetně backendu bez UI a slibů katalogu (P1-15) | Feature testy pro každou akci a browser test hlavní cesty |
| D | D1 | Validní kontrakt, `--check`, CI drift, žádná regenerace při deployi (P1-9) | Spectral bez chyb; každá routa má právě jednu operaci |
| D | D2 | Staff middleware + sweep, odstranit GET panel-login (P1-10) | Zákazník dostane 403/404 na všech `/v1/staff/*` |
| D | D3 | Pravdivé `/api` a `/dokumentace/api`, index chybových slugů (P1-11) | Každý zobrazený endpoint existuje |
| D | D4–D7 | Webhooky (P1-12), idempotence s organizací, tokeny a service accounts, limitery a paginace | Viz backlog P2 |
| E | E0 | `staging.sh`: drop-in, SHA, parkování, public/build, chmod; detekce v `install.sh`; porovnání openapi (P0-5, P0-6) | Na serveru `ProtectSystem=no`, `setup` nasadí špičku `development` |
| E | E1–E10 | E2E přes HTTP s fake providery: zákazník, host, Comgate kontrakt (P1-14), billing lifecycle, zrušení + purge, dobropisy, partner + referral, podpora, plumbing, CZK/VAT | Jedna platba = jedno vyrovnání; ledger vyrovnaný; žádné dvojí vyrovnání |
| E | E11 | Hygiena doctoru a runbook (P1-16) | Doctor bez neočekávaných WARN |
| E | E12 | Věrnost: limit a odebírání bodů (P1-13) | Platba 1 Kč nedá body; dobropis body odebere |
| F | F1 | `docs/testing/manual/` podle promptu | Matice pokrytí funkcí |

## Co audit nespustil

Neběželo nic z tohoto: Pest, `brain.ps1 gate`, doctor na stagingu, browser a živé panely.

Za běhu je potřeba ověřit:
- 422 u transition ticketu;
- 403/404 u tlačítka Log ve staff konzoli;
- obsah outbox payloadu ve webhooku;
- schopnosti živých instancí (`mail.create`, `cron_api`);
- vlastníka souboru `onhost-v1.yaml` na stagingu;
- nesloučenou práci v TASK-0050.

## Stav po fázi C (2026-10-04)

Stav každého nálezu P0 a P1 po fázi C. „Hotovo“ znamená sloučeno do `development` s testy; „otevřeno“ znamená, že práce neproběhla, nebo čeká na rozhodnutí či zásah vlastníka. Čísla PR jsou z repozitáře `Stanektechcz/onhost-platform`.

### P0

| Nález | Stav | Task / PR | Poznámka |
|---|---|---|---|
| P0-1 | hotovo | TASK-0053 #42 | Payload panelu se skládá podle oprávnění role. |
| P0-2 | hotovo | TASK-0060 #51 | Partnerská brána; ne-partner dostane přihlášku a vymyšlená data zmizela. |
| P0-3 | hotovo | TASK-0068 #61 + TASK-0071 #63 | Terminál, Node projekty a cron na uzavřeném sdíleném aaPanel uzlu jsou vypnuté. Existující Node projekt jde stále zastavit nebo smazat (`node_projects_exit`); UI doplňuje TASK-0072. |
| P0-4 | otevřeno | – | `backups.compute` se zapne až po kontrole PBS a se souhlasem vlastníka (R3). |
| P0-5 | otevřeno | – | `staging.sh`, fáze E0. |
| P0-6 | otevřeno | – | `staging.sh`, fáze E0. |

### P1

| Nález | Stav | Task / PR | Poznámka |
|---|---|---|---|
| P1-1 | hotovo | TASK-0052 #44 | Sidebar admina podle role. |
| P1-2 | hotovo | TASK-0054 #43 | Přechody ticketu přijmou `to` i `state`. |
| P1-3 | hotovo | TASK-0054 #43 | Zápisy ticketů přes CommandBus. |
| P1-4 | hotovo | TASK-0052 #44 | Stránky nastavení s `authorize()`. |
| P1-5 | hotovo | TASK-0063 #59 | Volání neexistující routy je opravené a hlídá ho kontraktní test frontend → routy. |
| P1-6 | hotovo | TASK-0064 #60 (+ TASK-0070 #64, DPH) | Ceny z katalogu, kurzy z ČNB, DPH z pravidel. |
| P1-7 | hotovo | TASK-0063 #59 | Stabilní Idempotency-Key pro každý záměr uživatele. |
| P1-8 | hotovo | TASK-0064 #60 | Test kotev seamů (`SeamAnchorTest`). |
| P1-9 | otevřeno | – | Fáze D (kontrakt OpenAPI). |
| P1-10 | otevřeno | – | Fáze D (staff middleware a sweep). |
| P1-11 | otevřeno | – | Fáze D (pravdivé `/api` a `/dokumentace`). |
| P1-12 | otevřeno | – | Fáze D (webhooky). |
| P1-13 | otevřeno | – | Fáze E12 (věrnostní body). |
| P1-14 | otevřeno | – | Fáze E3 (Comgate nad nahranými odpověďmi). |
| P1-15 | částečně hotovo | TASK-0068 #61, TASK-0057 #47 | Revize katalogu je definovaná, ale operátor ji musí použít (`onhost:catalog:revise --apply`). Snapshoty VPS: TASK-0057 #47. |
| P1-16 | otevřeno | – | Fáze E0 a E11 (staging a runbook). |

### Další sloučená práce fáze C

| Task | PR |
|---|---|
| TASK-0043 / B6 | #55 |
| TASK-0044 | #53 |
| TASK-0067 | #58 |
| TASK-0056 | #46 |
| TASK-0058 | #48, #50 |
| TASK-0066 | #57 |
| TASK-0057 | #47 |
| TASK-0059 | #49 |
| TASK-0055 | #45 |
| TASK-0061 | #52 |
| TASK-0062 | #54 |
| TASK-0065 | #56 |
| TASK-0069 | #62 |
| TASK-0070 | #64 |
| TASK-0072 (uzavření fáze C: ukončení a automatická obnova předplatného na stránce Náklady a v detailu služby, Node projekty na uzavřeném sdíleném uzlu) | návrh, nesloučeno |

### Otevřené navazující body

* Operátor musí spustit `php artisan onhost:catalog:revise --apply`; bez toho se revize katalogu (TASK-0068) neprojeví.
* Přeinstalace VPS vyžaduje test na testovacím uzlu Proxmoxu a souhlas vlastníka.
* Texty `DestructivePreview` jsou jen česky.
* Časový limit načtení přes relay (relay fetch timeout).
* `InvoiceController` předává do busu klíč bez id faktury (LOW).
* Vedlejší efekt rotace remember tokenu.

## Stav po fázi D (2026-10-04)

Stav nálezů P1-9 až P1-12 a položek P2/P3 z oblasti A5 (veřejné API) po fázi D. „Hotovo“ znamená sloučeno do `development` s testy; „otevřeno“ znamená, že práce neproběhla, nebo čeká na rozhodnutí či zásah vlastníka. Čísla PR jsou z repozitáře `Stanektechcz/onhostik`.

### P1

| Nález | Stav | Task / PR | Poznámka |
|---|---|---|---|
| P1-9 | hotovo | TASK-0074 #67 (D1) | Platný OpenAPI: unikátní `operationId`, `x-token-scope`, `--check` v CI, deploy kontrakt jen porovnává. |
| P1-10 | hotovo | TASK-0073 #66 (D2) | `EnsureStaff` na staff API, `GET panel-login` odstraněno, sweep ověřuje odmítnutí zákazníka. |
| P1-11 | hotovo | TASK-0078 #71 (D3) | Pravdivé `/dokumentace/api` (Redoc hostovaný lokálně pod CSP), index slugů chyb, průvodce prvním voláním, changelog. Zbývá úklid prototypových tvrzení (viz otevřené body). |
| P1-12 | hotovo | TASK-0077 #70 (D4) | Veřejný allow-list payloadu (opravený únik 7 staff událostí), doručení přes frontu, jeden podpis, příkazy přes bus, oznámení o pozastavení, limity ping/redeliver, porty 443/8443, 6 pokusů (~14,6 h). |

### P2 a P3 (oblast A5)

| Položka | Stav | Task / PR | Poznámka |
|---|---|---|---|
| Idempotence bez organizace a bez zámku | hotovo | TASK-0075 #68 (D5) | Rozsah uživatel + token + organizace, atomická rezervace s tokenem vlastníka, `409 idempotency_in_progress`, `DELETE`, query string v hashi. |
| Pole `provider` v chybách vidí zákazník | hotovo | TASK-0075 #68 (D5) | Pole se skrývá; stav je i v odpovědích 4xx. |
| Limiter `probes` jde obejít | hotovo | TASK-0076 #69 (D7) | Probes podle IP; limiter callbacků Discordu; throttle neúspěšné autentizace jen pro bearer. |
| Paginace bez tiebreakeru | hotovo | TASK-0076 #69 (D7) | Tiebreaker podle `id`. |
| Politika verzí API (P3) | hotovo | TASK-0076 #69 (D7) | `X-API-Version`, `Deprecation`, `Sunset`; verze API 1.0.0 je oddělená od verze aplikace 4.0. |
| Tokeny bez expirace | hotovo jako dokumentace | TASK-0079 #72 (D6) | Rozhodnutí R9 se v této fázi řeší dokumentací; servisní účty jsou hotové. |
| DNS scopy (P3) | hotovo | TASK-0079 #72 (D6) | Scope `dns:read` a aliasy zón. |
| Servisní účty | hotovo | TASK-0079 #72 (D6) | API a oprávnění; portálové UI a `/v1/me` zbývají (viz otevřené body). |
| Staging: `openapi` jen porovnávat | hotovo | TASK-0074 #67 (D1) | `deploy.sh` kontrakt porovnává, nepřepisuje soubor vlastněný rootem. |

### Další sloučená práce fáze D

| Task | PR |
|---|---|
| TASK-0073 / D2 | #66 |
| TASK-0074 / D1 | #67 |
| TASK-0075 / D5 | #68 |
| TASK-0076 / D7 | #69 |
| TASK-0077 / D4 | #70 |
| TASK-0078 / D3 | #71 |
| TASK-0079 / D6 | #72 |

### Otevřené navazující body

* S1-05: automatizační grants. Otázka pro vlastníka; operace HIGH/CRITICAL přes token zůstávají odmítnuté.
* `TRUSTED_PROXIES` v produkci: hodnotu musí nastavit operátor.
* `ONHOST_API_BASE_URL`: nastavit, pokud API poběží na vlastním hostu.
* RateLimiter potřebuje sdílený cache store, jinak limity neplatí napříč workery.
* Webhooky běží na výchozí frontě (bez vyhrazené).
* Rotace tajného klíče webhooku bez překryvného okna.
* Replay bez původních hlaviček (M3).
* Klíče busu `invoice.credit` a `invoice.markpaid` se předávají bez id faktury.
* `/v1/me` pro servisní účty.
* Portálové UI pro servisní účty.
* Tvrzení prototypu (`api.onhost.cz`, sliby 90 dní / 12 měsíců) v `onhost-svc-*.js`.
* `brain.ps1 task start -Paths` ukládá cesty jako jeden řetězec.
* Nestabilní `DiscordIntegrationTest` (deduplikace dávky).
* Dva testy používají neexistující roli `support_agent` (`AdminLoyaltyCapacityViewsTest`, `GameQuickCreateTest`).
* Webová routa `/sprava/konzole` není pod staff middlewarem; ověřit kontrolu v controlleru.
* Generované API klienty je nutné znovu vygenerovat (změnily se `operationId`).
* Runbook revize katalogu.
* `DestructivePreview` jen česky.
* UX rotace remember tokenu.
* Test přeinstalace VPS na testovacím uzlu se souhlasem vlastníka.

## Stav po fázi E (2026-10-05)

Stav nálezů P0-5, P0-6, P1-13, P1-14 a P1-16 a rozhodnutí R3, R5, R6 a R7 po fázi E. „Hotovo“ znamená sloučeno do `development` s testy; „otevřeno“ znamená, že práce neproběhla, nebo čeká na rozhodnutí či zásah vlastníka. Čísla PR jsou z repozitáře `Stanektechcz/onhostik`.

### P0

| Nález | Stav | Task / PR | Poznámka |
|---|---|---|---|
| P0-5 | hotovo v kódu, ověření na serveru otevřeno | TASK-0082 #77 (E0) | `staging.sh` dosadí drop-in `ProtectSystem=no` jen tam, kde detekuje `libusranalyse` (`install.sh` totéž), s kompenzačním zpevněním a seznamem výjimek pro každý host. Přítomnost direktivy na stagingu musí ověřit operátor podle `docs/runbooks/staging-aapanel.md`. |
| P0-6 | hotovo | TASK-0082 #77 (E0) | SHA se zapíše do `setup`, při selhání se strom zaparkuje a heslo k DB se neztratí, `public/build` vlastní www, `chmod` se týká jen souborů vlastněných rootem. |

### P1

| Nález | Stav | Task / PR | Poznámka |
|---|---|---|---|
| P1-13 | hotovo | TASK-0095 #87 (E12, R6) | Body jen od platby 100 Kč, dobropis body odebere (zpětné odebrání pod zámkem organizace). Uplatnění bodů R6 neřeší. |
| P1-14 | částečně hotovo | #78 (E1), #84 (E6) | Platební a dunning tok jde přes skutečné HTTP routy a příkazy cronu s fake bránou. Sada nad uloženými odpověďmi Comgate je od G9 v `tests/Contract/ComgateRecordedResponsesTest.php` (fixtures tvarované podle dokumentace; záznam z testovacího účtu zůstává na vlastníkovi). |
| P1-16 | hotovo, ověření na serveru otevřeno | TASK-0084 #76 (E11), TASK-0082 #77 | Doctor má nápravy u každého nálezu a go-live runbook pořadí kroků; `public/build` vlastní www; runbook `staging-aapanel.md`. Kroky TOTP a obnovu `expected-nonok` provádí operátor (viz níže). |

### Rozhodnutí R3, R5, R6, R7

| Rozhodnutí | Stav | Task / PR | Poznámka |
|---|---|---|---|
| R3 | otevřeno | – | Compute zálohy (P0-4): čeká na výsledek kontroly PBS vlastníkem. |
| R5 | hotovo (úzce) | TASK-0096 #88 | Neověřený e-mail blokuje objednávky nad 5 000 Kč a výplaty partnerům; přibyl endpoint pro opakované odeslání. Rozdělení objednávky na menší je známý limit. |
| R6 | hotovo | TASK-0095 #87 | Minimum 100 Kč a odebrání bodů při dobropisu. Událost `payment.refunded` viz otevřené body. |
| R7 | hotovo | TASK-0097 #89 | Splatnost 30 dní, platí od nasazení dál a i pro marketplace. Migrace se zastaví při skutečném dvojím naúčtování. |

### Sloučená práce fáze E

| Task | Obsah | PR |
|---|---|---|
| TASK-0083 | E-tool: seznam cest v `brain.ps1` | #75 |
| TASK-0084 | E11: nápravy v doctoru a pořadí kroků v go-live runbooku | #76 |
| TASK-0082 | E0: `staging.sh`, `install.sh`, runbook `staging-aapanel.md` | #77 |
| E1 | Registrace až po web | #78 |
| E2 | Doména, DNS, transfer (opraven únik `provider: powerdns`) | #79 |
| E3 | Mail (opraveno: DKIM se nikdy nepublikoval; únik chyby ISPConfigu) | #80 |
| E5 | Game (lístek konzole prozrazoval URL Wings a id panelu; cizí host v chybě) | #81 |
| E4 | VPS (úniky vmid/node/executor; dvojklik na konzoli přehrál lístek) | #82 |
| E7 | Podpora (osiřelý ticket; únik existence čísla ticketu) | #83 |
| E6 | Billing a dunning | #84 |
| E8 | Sdílení a role | #85 |
| E9 | Automatizace přes API (servisní účet: 401 změněno na 403 `staff_only`) | #86 |
| E12 | Věrnost, R6 | #87 |
| R5 | Neověřený e-mail | #88 |
| R7 | Odklad provize 30 dní | #89 |
| E10 | Zrušení a konec účtu (obnova automatického prodloužení; ISPConfig web s databází šel zrušit; chybějící e-mailová šablona; test seamy jen v testech) | #90 |
| – | Oracle existence vrací 404, zákaznické tickety přes bus, klíče podle požadavku pro staff | #91 |
| – | Vratný zůstatek nejvýše do nevyčerpaného zakoupeného kreditu; pravdivý stav po selhání závěrečné archivace; `service.termination.failed` | #92 |

### Otevřené navazující body

Vlastník (rozhodnutí):

* R3: výsledek kontroly PBS, teprve potom `backups.compute`.
* S1-05: automatizační grants.
* `TRUSTED_PROXIES`: přesné adresy.
* Dvojí doklad DPH: při zaplacení vystavené faktury vzniká i příjmový doklad.
* Uplatnění věrnostních bodů (R6 žádné nemá).
* `services.reinstate` zůstává ve výchozím stavu vypnuté.
* Pořadí čerpání kreditu: zvoleno nejdřív zakoupený.
* Vrácení v hotovosti u vratek zaplacených z kreditu.

Vlastník (kroky na serveru):

* Ověřit direktivu usranalyse na stagingu podle `docs/runbooks/staging-aapanel.md`.
* Revize katalogu (`onhost:catalog:revise --apply`).
* Zápis TOTP.

Technické navazující body:

* `X-Organization` s cizí organizací stále vrací 403.
* Zavření a hodnocení ticketu a předání asistenta nejdou přes bus.
* Událost `payment.refunded` pro odebrání bodů.
* Dvojí započtení základu fragmentu (partnerské tiery).
* Webhooky: výchozí fronta, překryv při rotaci tajného klíče, souběžný ping.
* `/v1/me` pro servisní účty a portálové UI.
* Tvrzení prototypu (`api.onhost.cz`, 90 dní / 12 měsíců).
* Znovu vygenerovat API klienty.
* `DestructivePreview` jen česky.
* UX rotace remember tokenu.
* Test přeinstalace VPS na testovacím uzlu se souhlasem vlastníka.
* Typ vazby „qemu“ je vidět zákazníkovi.
* Nestabilní testy: `DiscordIntegrationTest` (dávka), `LexiconCoverageTest`, selhání `getenv()` jen při paralelním běhu, `ArchiveRestoreScopeTest`.
* Neexistující role `support_agent` v `AdminLoyaltyCapacityViewsTest`, `StaffConsolePageTest`, `GameQuickCreateTest`.
* ~~`WalletService::refund` zatím nikdo nevolá.~~ Nahrazeno: odstraněno v G4 (TASK-0112, G-R4 — kredit se v hotovosti nevyplácí).
* R7: migrace se na nasazení může zastavit, pokud produkce obsahuje skutečné dvojí naúčtování; musí je vyřešit finance (viz go-live checklist §9).
* Panel nemá UI pro opakované odeslání a ověření e-mailu (R5).

## Stav po fázi F (závěrečný průchod, 2026-10-05)

Závěrečný průchod nad `origin/development` (stav k PR #101; po něm viz „Stav po fázi G“ na konci) vychází z čerstvého čtení tohoto souboru a kódu. „Hotovo“ znamená sloučeno s testy; „otevřeno“ znamená, že práce neproběhla, nebo čeká na rozhodnutí vlastníka či zásah operátora na serveru. Čísla PR jsou z repozitáře `Stanektechcz/onhostik`.

Opravy proti starším tabulkám:
- Seeder `wedos-main` je opravený v #99: zakládá se vypnutý a bez přihlašovacích údajů, stav poskytovatele se nastaví jen při vytvoření záznamu.
- Klíče busu `invoice.credit` a `invoice.markpaid` jsou opravené v #99.
- Expirace tokenů (P2-10) je v kódu hotová od #53 (365 dní, `TokenLifetime`). Starší tabulka fáze D ji vedla jen jako dokumentaci.
- Spectral běží v CI od G9 (TASK-0116, úloha `openapi-lint`, pravidla `.spectral.yaml`) vedle `onhost:openapi --check` a `OpenApiContractTest`.

### Všechny nálezy

#### P0

| Nález | Stav | PR | Vlastník / poznámka |
|---|---|---|---|
| P0-1 | hotovo | #42 | – |
| P0-2 | hotovo | #51 | – |
| P0-3 | hotovo | #61, #63 | Ověření úklidu aaPanelu při ukončení služby zůstává krokem operátora. |
| P0-4 | otevřeno | – | Vlastník: R3, výsledek kontroly PBS; `backups.compute` se zapne až po ní. |
| P0-5 | hotovo v kódu | #77 | Operátor ověří direktivu usranalyse na serveru. |
| P0-6 | hotovo | #77 | – |

#### P1

| Nález | Stav | PR | Vlastník / poznámka |
|---|---|---|---|
| P1-1 | hotovo | #44 | – |
| P1-2 | hotovo | #43 | – |
| P1-3 | hotovo | #43, #99 | Zavření, hodnocení a předání ticketu jde přes bus od #99. |
| P1-4 | hotovo | #44 | – |
| P1-5 | hotovo | #59 | – |
| P1-6 | hotovo | #60, #64 | – |
| P1-7 | hotovo | #59 | – |
| P1-8 | hotovo | #60 | – |
| P1-9 | hotovo | #67 | V CI běží `onhost:openapi --check` a `OpenApiContractTest`. Spectral je v CI od G9 (#109, TASK-0116). |
| P1-10 | hotovo | #66 | – |
| P1-11 | hotovo | #71 | Zbývá úklid tvrzení prototypu `onhost-svc-*.js`. |
| P1-12 | hotovo | #70 | Zbývá vyhrazená fronta webhooků a překryv při rotaci tajného klíče (inženýrství). |
| P1-13 | hotovo | #87, #99 | Minimum 100 Kč, odebrání bodů při dobropisu a stržení bodů při potvrzeném refundu (#99). Uplatnění bodů je rozhodnutí vlastníka. |
| P1-14 | částečně | #78, #84 | Nahrané odpovědi Comgate jsou zatím ručně psané fakes. Vlastník: testovací účet Comgate. |
| P1-15 | částečně | #61, #47 | `PlanPromises::KNOWN_GAPS` má 7 klíčů. Revizi katalogu musí operátor spustit na serveru (`onhost:catalog:revise --apply`). |
| P1-16 | hotovo | #76, #77, #99 | Seeder `wedos-main` opravený v #99. Kroky TOTP a obnovu `expected-nonok` provádí operátor. |

#### P2

Číslování P2 odpovídá odrážkám backlogu shora. Skupina „Backend bez UI“ je rozepsaná jako P2-1a až P2-1f.

| Nález | Stav | PR | Vlastník / poznámka |
|---|---|---|---|
| P2-1a DNS: verze, rollback, export | hotovo | #46 | Obrazovky domén a DNS (TASK-0056). |
| P2-1b Domény: převod na ONhost, kontakty | hotovo | #46 | Obrazovky domén a DNS (TASK-0056); cena převodu v #50. |
| P2-1c Služby: health check, náhled destruktivní akce, spec | hotovo | fáze C | `DestructivePreview` je jen česky. |
| P2-1d Konzole: noVNC, živá konzole herního serveru | hotovo v kódu | fáze C, #81 | Nasazení relay konzole je krok operátora. |
| P2-1e Pošta: `sending.set` | hotovo | #62 | Obrazovky webu a pošty (TASK-0069). |
| P2-1f Admin: SSO do panelu v UI | otevřeno | – | Inženýrství. |
| P2-2 Reinstalace VPS a vlastní ISO | částečně | – | Reinstalace VPS je hotová v kódu, test na testovacím uzlu zbývá. Vlastní ISO otevřeno (vlastník). |
| P2-3 `ONHOST_ADDON_RENEWALS=false` | hotovo | #61 (R12) | – |
| P2-4 Změna tarifu na aaPanelu | otevřeno (známý limit) | – | Resize na uzlu nic nemění. |
| P2-5 Role: spící oprávnění | otevřeno | – | Vlastník rozhodne. |
| P2-6 Role: SS-7, zákaznický sidebar, nekonzistence rolí | hotovo | #55, #44, #91 | – |
| P2-7 Idempotence bez organizace a zámku | hotovo | #68 | – |
| P2-8 Pole `provider` v chybách | hotovo | #68 | – |
| P2-9 Limiter `probes` | hotovo | #69 | – |
| P2-10 Tokeny bez expirace | hotovo | #53, #72 | Expirace v kódu od #53. Zapnutí `ONHOST_TOKEN_ORGANIZATION_REQUIRED` po `operator:tokens:unbound` je krok operátora. |
| P2-11 Paginace bez tiebreakeru | hotovo | #69 | – |
| P2-12 Partnerská provize bez ochranné lhůty | hotovo | #89 | Rozhodnutí financí R7 o dvojím naúčtování. |
| P2-13 Úklid aaPanelu při ukončení | hotovo v kódu | #90 | Ověření na serveru zbývá (operátor). |
| P2-14 Outbox dead letters bez alertu | otevřeno | – | Chybí vyhrazený alert. |
| P2-15 StaffReadAudit | otevřeno | – | Je jen ve 4 controllerech. |
| P2-16 Verifikační mail obchází MailOutbox | otevřeno (záměr) | – | Obchází záměrně. |
| P2-17 Staging: openapi jen porovnávat | hotovo | #67 | – |
| P2-19 Staging: revize katalogu, capacity basis | otevřeno | – | Krok na serveru. |
| P2-20 Staging: Turnstile, ClamAV | otevřeno | – | Krok na serveru (klíče Turnstile, clamd). |


#### P3

| Nález | Stav | PR | Vlastník / poznámka |
|---|---|---|---|
| P3-1 Přístupnost: `lang`, `:focus-visible` | hotovo | #64 | `window.prompt` je samostatně v P3-2. |
| P3-2 `window.prompt` | otevřeno | – | Asi 98 výskytů. |
| P3-3 České plurály | otevřeno | – | Neověřeno. |
| P3-4 Mrtvý modul `OnhostDomains` | otevřeno | – | – |
| P3-5 DNS scopy | hotovo | #72 | – |
| P3-6 Politika verzí API | hotovo | #69 | – |
| P3-7 Executable bit `staging.sh` | otevřeno | – | – |

### Sloučená práce fáze F

| PR | Obsah |
|---|---|
| #94 | F9: matice rolí se generuje (`onhost:docs:roles --check` v CI). |
| #95 | F1–F4: ruční testy a `ManualTestsTest`. |
| #97 | F10: manuál API s 59 přehranými příklady curl. |
| #98 | F5–F8: ruční testy; do katalogu přibyly 4 události. |
| #99 | F12b: seeding `wedos-main`, klíče busu pro faktury, zavření/hodnocení/předání ticketu přes bus, `payment.refunded` se zamčeným, měnově kontrolovaným a idempotentním refundem, stržení věrnostních bodů při potvrzeném refundu, go-live §6. |
| #100 | F12a: skutečné role v testech a guard, cizí `X-Organization` dává 404, `/v1/me` pro service accounty, atomický cooldown pingu webhooku. |
| #101 | `development` je zelený po doplnění manuálních dokumentů. |

### Otevřené body

#### Rozhodnutí vlastníka

* R3: výsledek kontroly PBS, teprve potom `backups.compute`.
* S1-05: automatizační grants.
* `TRUSTED_PROXIES`.
* Dvojí doklad DPH.
* Uplatnění věrnostních bodů.
* `services.reinstate`.
* Pořadí čerpání kreditu.
* Vrácení v hotovosti u vratek zaplacených z kreditu.
* Vlastní ISO.
* Spící oprávnění.
* Testovací účet Comgate.
* R7: rozhodnutí financí o dvojím naúčtování.

#### Kroky na serveru (operátor)

* Ověřit direktivu usranalyse.
* `onhost:catalog:revise --apply`.
* TOTP a obnova `expected-nonok`.
* Capacity basis, klíče Turnstile, clamd.
* Sdílený cache store.
* `ONHOST_TOKEN_ORGANIZATION_REQUIRED` po `operator:tokens:unbound`.
* Nasazení relay konzole.
* Test reinstalace VPS na testovacím uzlu.
* Ověření úklidu aaPanelu.

#### Inženýrské navazující body

* ~~Spectral v CI.~~ Hotovo v G9 (TASK-0116): úloha `openapi-lint`, pravidla `.spectral.yaml`; vzdané jsou jen `operation-description` a `oas3-unused-component` (důvod je v souboru).
* Sada nahraných odpovědí Comgate: přehrávání uložených odpovědí adaptérem je hotové v G9 (`tests/Contract/ComgateRecordedResponsesTest.php`); fixtures jsou tvarované podle dokumentace API v2.0, skutečný záznam z testovacího účtu Comgate zůstává na vlastníkovi.
* Webhooky: vyhrazená fronta a překryv při rotaci tajného klíče.
* Portálové UI pro service accounty.
* Tvrzení prototypu v `onhost-svc-*.js`.
* Znovu vygenerovat API klienty.
* `DestructivePreview` jen česky.
* UX rotace remember tokenu.
* Typ vazby „qemu“ je vidět zákazníkovi.
* UI pro opakované odeslání ověřovacího e-mailu (R5).
* `WalletService::refund` a `PaymentService::refund` nemají v aplikaci volajícího. Nahrazeno: `WalletService::refund` je odstraněný (G4, TASK-0112); `PaymentService::refund` zůstává pro zákonnou vratku platby při odstoupení (G6) se strážcem `purpose = topup` (G1).
* Šablona e-mailu `payment.refunded`.
* Cesta potvrzení bankovního refundu.
* Token s oprávněním chatu může spustit předání (bus ignoruje oprávnění tokenu).
* Dvojí započtení základu fragmentu.
* SSO do panelu v admin UI.
* Resize aaPanelu.
* Alert pro dead letters.
* StaffReadAudit všude.
* `window.prompt`, plurály, `OnhostDomains`. Exec bit `staging.sh` je v indexu již `100755` (ověřeno v G9).
* Rozšířit `ExistenceOracleTest`.
* Nestabilní testy: #74 (Discord, PR otevřen) a #96 (`LexiconCoverageTest`, PR otevřen) jsou věc jiných relací. `getenv()` při paralelním běhu (číselný název proměnné prostředí, int klíč pod `strict_types`) a náhodné hodnoty v `ArchiveRestoreScopeTest` opraveny v G9.
* ~~Stavové řádky v `.ai/tasks`.~~ Sloučené úkoly jsou sjednocené na `INTEGRATED` (G9, TASK-0116).
* ~~README neobsahuje #65, #73 a #74.~~ Doplněno: #65 = uzavření fáze C (TASK-0072), #73 = uzavření fáze D (TASK-0080), #74 = oprava okna dávky action hooků (otevřený PR).

## Stav po fázi G (2026-10-06)

Stav nad `origin/development` po sloučení PR #103 až #114 (úkoly TASK-0110 až TASK-0119, všechny `INTEGRATED`). „Hotovo“ znamená sloučeno s testy; co závisí na rozhodnutí vlastníka nebo na serveru, je v otevřených bodech níže. Rozhodnutí G-R1 až G-R5 jsou v `ROZHODNUTI.md`.

### Sloučená práce fáze G

| PR | Úkol | Obsah |
|---|---|---|
| #103 | G1, TASK-0111 | Jediným daňovým dokladem je faktura (G-R1): platba nevytváří druhý doklad, doklad prodejce podle režimu DPH. |
| #104 | G4, TASK-0112 | Kredit se v hotovosti nikdy nevyplácí (G-R4); `WalletService::refund` odstraněn, pořadí čerpání kreditu G-R3. |
| #105 | G5, TASK-0110 | Vlastní ISO jen v tarifu (G-R5): ClamAV, kvóta organizace, připojení/odpojení/smazání, 403 mimo tarif. |
| #106 | G2, TASK-0113 | Režim plátce DPH, zálohová faktura, doklad k přijaté platbě, konečná faktura, podklady KH/SH. |
| #107 | G3, TASK-0114 | Uplatnění věrnostních bodů (strop 20 %, dluh bodů, doplňky a domény se nezlevňují). |
| #108 | G7, TASK-0115 | Rozsah tokenu platí na sběrnici příkazů, audit čtení personálem, alert dead letters, fronta webhooků a překryv při rotaci tajného klíče (`X-ONhost-Signature-Previous`). |
| #109 | G9, TASK-0116 | Spectral v CI, přehrávání odpovědí Comgate, EN lexikon, opravené nestabilní testy, sjednocené stavy úkolů. |
| #111 | G6, TASK-0117 | Vrácení platby objednávky na kartu při odstoupení (step-up, schválení, důkazy, potvrzení bankou druhou osobou, storno), e-mail `payment.refunded`, oprava partnerského tarifu, doctor `services.reinstate`, executor v nabídce. |
| #112 | G10, TASK-0119 | Řádky doctoru, go-live checklist §10, runbook prvního dne. |
| #113 | oprava | Uvozování v generovaném OpenAPI. |
| #114 | G8, TASK-0118 | UI servisních účtů, opakované odeslání ověření e-mailu, tlačítko SSO personálu, EN náhled, `qemu` na `vm`, `OnhostDialog`, plurály, seam nároků na kredit. |

### Nálezy a navazující body, jejichž stav se ve fázi G změnil

| Bod | Stav po G | PR | Poznámka |
|---|---|---|---|
| P1-9 Spectral v CI | hotovo | #109 | Úloha `openapi-lint`, pravidla `.spectral.yaml`. |
| P1-12 Webhooky: fronta, rotace | hotovo | #108 | Vyhrazená fronta `webhooks` (jednotka `onhost-queue@webhooks`) a překryv rotace; zapnutí fronty na serveru je krok operátora. |
| P1-13 Věrnostní body | hotovo | #107, #111 | Uplatnění G-R2; stržení bodů při potvrzeném refundu v #111. |
| P1-14 Nahrané odpovědi Comgate | částečně | #109 | Přehrávání hotové; skutečný záznam z testovacího účtu Comgate zůstává na vlastníkovi. |
| P2-1f SSO do panelu v admin UI | hotovo | #114 | Tlačítko SSO personálu. |
| P2-2 Vlastní ISO | částečně | #105 | Kód hotový; tarify s `custom_iso` je jen návrh (`docs/proposals/custom-iso-plans.md`), katalog zatím nikdo neprodává. Reinstalace VPS: test na testovacím uzlu zbývá. |
| P2-14 Dead letters bez alertu | hotovo | #108 | Alert a řádek doctoru. |
| P2-15 StaffReadAudit | hotovo | #108 | Průchod napříč čtecími cestami personálu (`StaffReadAuditSweepTest`); seznam vrácení platby doplněn v #111. |
| P3-2 `window.prompt` | částečně | #114 | Moduly klientského API používají `OnhostDialog`; zbývá 8 výskytů v admin Blade (`approvals`, `integrations`, `lifecycle`, `operations`, `plans`) a `service-console`. |
| P3-3 České plurály | hotovo | #114 | – |
| Typ vazby „qemu“ viditelný zákazníkovi | hotovo | #114 | Zobrazuje se `vm`. |
| UI pro service accounty | hotovo | #114 | – |
| UI opakovaného odeslání ověřovacího e-mailu (R5) | hotovo | #114 | – |
| `DestructivePreview` jen česky | hotovo | #114 | EN náhled. |
| `WalletService::refund` bez volajícího | hotovo | #104 | Odstraněno (G-R4). `PaymentService::refund` volá vrácení při odstoupení (#111). |
| Šablona `payment.refunded`, cesta potvrzení bankovního refundu | hotovo | #111 | – |
| Token s oprávněním chatu a předání | hotovo | #108 | Bus uplatňuje rozsah tokenu. |
| Dvojí doklad DPH, pořadí čerpání kreditu, vratky z kreditu v hotovosti, uplatnění bodů, vlastní ISO | rozhodnuto | #103 až #107 | G-R1 až G-R5. |

### Otevřené body po fázi G

#### Rozhodnutí vlastníka

* R3: výsledek kontroly PBS, teprve potom `backups.compute`.
* S1-05: automatizační grants.
* `TRUSTED_PROXIES`.
* R7: rozhodnutí financí o dvojím naúčtování (migrace může na produkci s reálným duplicitním naúčtováním zastavit).
* Spící oprávnění.
* Testovací účet Comgate (skutečný záznam odpovědí).
* `services.reinstate` („Zaplatit a obnovit“, řádek doctoru z G6; ruční test F7-14).
* Tarify s vlastním ISO: schválit návrh `docs/proposals/custom-iso-plans.md` a spustit revizi `2026-10-custom-iso`.
* G-R4, otevřené otázky: (1) dobití kreditu a odstoupení (vlastník + právník), (2) zůstatek kreditu při zrušení účtu, (3) nová verze VOP, (4) texty prototypových obrazovek, které stále slibují vrácení kreditu.
* Potvrzení účetní: sleva za věrnostní body je sleva při prodeji (snižuje základ daně), ne platba; potvrzení právníkem výkladu § 1831 OZ (`resources/legal/LEGAL_REVIEW_withdrawal.md`).
* Kdy a jak přepnout platformu na plátce DPH (`ONHOST_VAT_PAYER`, finance se step-upem a druhou osobou).

#### Kroky na serveru (operátor)

* Ověřit direktivu usranalyse.
* `onhost:catalog:revise --apply` (a později revize `2026-10-custom-iso`).
* TOTP a obnova `expected-nonok`.
* Capacity basis, klíče Turnstile, clamd (clamd je i podmínka vlastního ISO; viz `docs/runbooks/custom-iso.md`: disk, limity PHP/nginx, úložiště Proxmoxu `custom_iso_storage`).
* Sdílený cache store.
* `ONHOST_TOKEN_ORGANIZATION_REQUIRED` po `operator:tokens:unbound`.
* Nasazení relay konzole.
* Test reinstalace VPS na testovacím uzlu.
* Ověření úklidu aaPanelu.
* Zapnout frontu webhooků: `systemctl enable --now onhost-queue@webhooks`.
* Překontrolovat §10 go-live checklistu a runbook prvního dne (G10).

#### Inženýrské navazující body

* Tvrzení prototypu v `onhost-svc-*.js` a texty prototypu o vrácení kreditu (`onhost-content.js`, `Onhost-admin.dc.html`; prototypy jsou byte-identické a mění se jen přes seam).
* Znovu vygenerovat API klienty.
* UX rotace remember tokenu.
* Dvojí započtení základu fragmentu.
* Resize aaPanelu (známý limit).
* `window.prompt` v admin Blade a `service-console` (8 výskytů) převést na `OnhostDialog`.
* Mrtvý modul `OnhostDomains`.
* Rozšířit `ExistenceOracleTest`.
* Nestabilní testy: #74 (Discord) a #96 (`LexiconCoverageTest`) jsou věc jiných relací (PR otevřené).
* Doba běhu sady: `pest-postgres` trvá 17 až 25 minut a opakovaně se rušil; limit úlohy zvýšen na 40 minut (tato změna). Úvaha o rozdělení sady zůstává.
* Vlastní ISO: zákaznická oznámení k událostem `service.iso.*` (Lexicon), záznamy v `docs/architecture/events-catalog.md`, úklid osiřelých souborů `incoming/`, případný doplněk, který `custom_iso` přidá (nic z toho není postavené).

## Stav po fázi H (2026-10-06)

Stav nad `origin/development` po sloučení PR #116 až #121 (úkoly TASK-0121 až TASK-0126, všechny `INTEGRATED`; úkol TASK-0127 je tento závěrečný zápis). Rozhodnutí vlastníka H-R0 až H-R6 (a varianty H-R0a, H-R1a, H-R3a, H-R5a, H-R5b, H-R6a) jsou v `ROZHODNUTI.md`. Žádný server nebyl změněn; krok rehearsalu (H6) je připravený, ne spuštěný.

### Sloučená práce fáze H

| PR | Úkol | Obsah |
|---|---|---|
| #116 | H1 + H3, TASK-0121 | Oznámení zákazníkovi k vlastním ISO obrazům (`service.iso.*`), doplněk „Vlastní ISO“ pro jedno VPS, úklid osiřelých souborů `incoming/` (karanténa, retence, `--dry-run`). Peníze: report vratek, které nikdo znovu nevyplatil, odhad vratky podle skutečně zaplaceného, povolený seznam klíčů v nabídce košíku, návratové adresy brány jen na portál (`PAYMENT_RETURN_HOSTS` pro Comgate, GoPay i Stripe). |
| #117 | H0, TASK-0122 | Rozhodnutí vlastníka z 2026-10-06: výchozí neplátce DPH, riskantní akce tokenu čekají na schválení vlastníka nebo správce organizace (S1-05), `TRUSTED_PROXIES` jen loopback, `services.reinstate` zapnuto, tarify s vlastním ISO schválené (dokumentace), od dobití kreditu nelze odstoupit a kredit při výmazu propadá, prototyp `onhost-domains.js` archivován. |
| #118 | H2 + H4, TASK-0124 | UI: `window.prompt` pryč z admin Blade a `service-console` (`OnhostDialog`), opravy seamů. CI: rozdělená sada `pest-postgres` na shardy, průchod oraclů. |
| #119 | H8, TASK-0123 | Penpot pro webhosting: vlastní Docker uzel přes SSH, zpevněný; jen s fakes, katalogová revize připravená a neaplikovaná (`docs/runbooks/penpot.md`, ruční test `11-penpot.md`). |
| #120 | H5, TASK-0125 | Výkon: indexy, benchmark v doctoru, přepis závislosti `shell-quote`. |
| #121 | H6, TASK-0126 | Skript zkoušky go-live na stagingu (`docs/runbooks/staging-rehearsal-2026-10.md`), připravený, nespuštěný. |

### Nálezy a navazující body, jejichž stav se ve fázi H změnil

| Bod | Stav | PR | Poznámka |
|---|---|---|---|
| S1-05 schvalování riskantních akcí tokenu | hotovo | #117 | `403 approval_required`, rozhoduje vlastník/správce organizace se step-upem; personál ne. |
| `TRUSTED_PROXIES` | rozhodnuto, hotovo | #117 | Loopback; doctor hlídá. |
| `services.reinstate` | rozhodnuto, zapnuto | #117 | Audit `onhost:billing:reinstatement-audit` před nasazením. |
| Tarify s vlastním ISO | rozhodnuto | #117 | Revizi `2026-10-custom-iso` provede operátor po krocích G-4 až G-7. |
| G-R4 otázky 1 a 2 (dobití a odstoupení, kredit při zrušení účtu) | rozhodnuto | #117 | H-R5; zbývá potvrzení právníkem a znění VOP (otázka 3). |
| VAT: kdy přejít na plátce | rozhodnuto (zatím neplátce) | #117 | H-R0; přechod je akce financí. |
| Mrtvý modul `OnhostDomains` | hotovo | #117 | Archivován, ne smazán. |
| `window.prompt` v admin Blade a `service-console` | hotovo | #118 | – |
| Doba běhu sady `pest-postgres` | hotovo | #118 | Shardy. |
| Zákaznická oznámení `service.iso.*`, doplněk `custom_iso`, úklid `incoming/` | hotovo | #116 | – |
| Vratky bez výplaty, odhad vratky, návratové URL brány | hotovo | #116 | – |
| Penpot pro webhosting | kód hotový, produkt neprodáván | #119 | Čeká na uzel a revizi katalogu. |
| Výkon (indexy, benchmark) | hotovo | #120 | – |
| Zkouška go-live na stagingu | připraveno | #121 | Nespuštěno. |

### Otevřené body po fázi H

#### Rozhodnutí vlastníka

* R3: výsledek kontroly PBS, teprve potom `backups.compute`.
* Spící oprávnění.
* Testovací účet Comgate (skutečný záznam odpovědí).
* Nová verze VOP (G-R4/3) a ověření právníkem: odstoupení od dobití, propadnutí kreditu (`resources/legal/LEGAL_REVIEW_withdrawal.md`, otázky 8 a 9); účetní posoudí DPH propadlé zálohy, až bude ONhost plátcem.
* Texty prototypových obrazovek, které stále slibují vrácení kreditu.
* Potvrzení účetní: sleva za věrnostní body je sleva při prodeji; potvrzení právníkem výkladu § 1831 OZ.
* R7: rozhodnutí financí o dvojím naúčtování (migrace může na produkci s reálným duplicitním naúčtováním zastavit).
* Kdy a jak přepnout na plátce DPH (`ONHOST_VAT_PAYER`, finance se step-upem a druhou osobou).
* Zda a kdy prodávat Penpot (uzel, cena, revize katalogu) a kdy spustit revizi `2026-10-custom-iso`.

#### Kroky na serveru (operátor)

* Ověřit direktivu usranalyse.
* `onhost:billing:reinstatement-audit` před nasazením H0, pak `onhost:catalog:revise --apply` (a po krocích G-4 až G-7 revize `2026-10-custom-iso`; revize Penpotu).
* Zkontrolovat řádky doctoru *legal entity carries its VAT mode* (`onhost:production:prepare --legal`) a *trusted proxies*.
* TOTP a obnova `expected-nonok`.
* Capacity basis, klíče Turnstile, clamd (podmínka vlastního ISO, `docs/runbooks/custom-iso.md`).
* Sdílený cache store.
* `ONHOST_TOKEN_ORGANIZATION_REQUIRED` po `operator:tokens:unbound`.
* Nasazení relay konzole.
* Test reinstalace VPS na testovacím uzlu.
* Ověření úklidu aaPanelu.
* `systemctl enable --now onhost-queue@webhooks`.
* Dedikovaný Penpot uzel (SSH, Docker) podle `docs/runbooks/penpot.md`.
* Spustit zkoušku `docs/runbooks/staging-rehearsal-2026-10.md` na stagingu; překontrolovat §10 go-live checklistu a runbook prvního dne (G10).

#### Inženýrské navazující body

* Tvrzení prototypu v `onhost-svc-*.js` a texty o vrácení kreditu (prototypy mění jen seam).
* Znovu vygenerovat API klienty.
* UX rotace remember tokenu.
* Dvojí započtení základu fragmentu.
* Resize aaPanelu (známý limit).
* Rozšířit `ExistenceOracleTest`.
* Nestabilní testy: #74 (Discord) a #96 (`LexiconCoverageTest`), věc jiných relací.

## Stav po fázi J a K (2026-10-12)

Stav nad `origin/development` po sloučení PR #146 až #158 (fáze J: TASK-0151 až TASK-0156; fáze K: TASK-0157 až TASK-0163). Fáze I
(PR #126–#145) má svůj zápis v `ROZHODNUTI.md` (I-R1 … I-R19). Rozhodnutí vlastníka J-R1, J-R2, J-R3 jsou v `ROZHODNUTI.md`. **Na
stagingu ani v produkci se ve fázích J a K nic nespustilo** (staging stále `e711b7c7`); kroky čekají na „ano“ vlastníka podle
`GENERALKA-K-A-C-deploy-prikazy-2026-10-12.md` (zmrazený cíl `fa2ea75d`, nahrazuje dokument J) a `GENERALKA-Penpot-D-G-prikazy.md`.

### Sloučená práce fází J a K

| PR | Úkol | Obsah |
|---|---|---|
| #146 | J0, TASK-0151 | Zelené CI, generátory bez driftu, nový job `php85` (Pest SQLite + deployer na PHP 8.5). |
| #147 | J8, TASK-0152 | J-R1: příběh „první rok“, eko čísla a sklad EPYC staženy z `#/technika` a `#/ceny-a-sla`. |
| #148 | J9, TASK-0153 | Plán produkčního spuštění `docs/runbooks/production-plan-2026-10.md`, kostra `PRODUKCE-prikazy` (nespouštět). |
| #150 | J2, TASK-0154 | Uzel Penpot bez skupiny docker, `local_certs`, idempotence skriptu (chyba porovnání módů), papírový rodič `onhost:penpot:paper-parent`. |
| #151 | J4, TASK-0155 | Patička s identitou a ČOI, karta jen s funkční bránou, `payment_card_unavailable` 409. |
| #152 | J1, TASK-0156 | Preflight cíle deploye a protokol `staging-rehearsal-2026-10-11.md`. |
| #153 | K0, TASK-0157 | `CatalogCompatibilityTest` vybíral „cizí“ produkt neseřazeně (PostgreSQL vybral Penpot). |
| #154 | K0b, TASK-0159 | Odhad vratky (chargeback) k danému okamžiku počítal den v UTC, ne účetní den — CI červené 22–24 UTC a o den vyšší odhad. |
| #155 | K5, TASK-0158 | Storno jedné provize (I-R6, J-R3): migrace 000949 odloží novější řádek před 000950, příkaz `partner.commission.cancel_duplicate` (HIGH, step-up, čtyři oči). |
| #156 | K8b, TASK-0160 | J-R2: eko čísla a sklad/sestavy EPYC staženy ze stránek služeb; GPU bez „7 / 24 volných karet“ (I-R16). |
| #157 | K7, TASK-0161 | Kontrola v prohlížeči (lokální sandbox): zjištění K7-1 … K7-7. |
| #158 | K7-1, TASK-0162 | Karty ceníku (`#/ceny`) končily v košíku jako nespárovaná „VPS Standard“. |
| — | K12, TASK-0163 | Tento zápis, J-R2/J-R3, ruční testy (karta bez brány, papírový rodič, dvojí provize), řádky 6 a 12 Comgate, cíl generálky. |

### Co zůstává (podrobně v `PROMPT-faze-L-2026-10-12.md` na ploše vlastníka)

* Generálka na stagingu (K3): A–C, `--legal`, D–G Penpot, R3–R24, R25, G2 — každý krok s „ano“ vlastníka.
* Kontrola v prohlížeči na stagingu (po C) a vady K7-2 … K7-7 (ceník, stránka „Podmínky, GDPR a cookies“, texty pokladny, drobnosti).
* Rozhodnutí vlastníka 2–10 (`ROZHODNUTI-podklady-J-2026-10-11.md`): prostředí pro Comgate, vlastní ISO, zálohy compute/PBS, výpisy ČS
  (K6), advokát (K10), provozní vstupy, Penpot, produkce, úklid stagingu.
* Nestabilní testy: #96 (`LexiconCoverageTest`, jiná relace); `DeployGateTest` pod paralelní zátěží lokálně (samostatně projde).

## Stav po fázi L a M (2026-10-12)

Stav nad `origin/development` po sloučení PR #161 až #168 (fáze L: TASK-0164 až TASK-0167; fáze M: TASK-0168 až TASK-0173).
Rozhodnutí vlastníka J-R4 … J-R12 jsou v `ROZHODNUTI.md`, provedení J-R7 a J-R9 je tam doplněné. **Na stagingu ani v produkci se ve
fázích L a M z této práce nic nespustilo**; staging vede jiná relace (`staging-rehearsal-2026-10-11.md`). Návod
`GENERALKA-Penpot-D-G-prikazy.md` na ploše vlastníka nově toleruje instanci registrátora bez přihlašovacích údajů (`wedos-main`) v D0/F0
(pokyn vlastníka 2026-10-12; plánovač Penpot umisťuje jen na uzel role `penpot` instance `penpot`).

### Sloučená práce fází L a M

| PR | Úkol | Obsah |
|---|---|---|
| #161 | TASK-0165 | Protokol generálky na stagingu 2026-10-12 (Path A). |
| #162 | L2, TASK-0164 | Ceník `#/ceny` z katalogu (srovnávací tabulka, roční cena, FAQ podle VOP/SLA), košík „Webhosting Standard“. |
| #163 | L5, TASK-0166 | Staff konzole čte jen to, co navigace dovolí (`OnhostApi.reads()`). |
| #164 | L12a, TASK-0167 | Rozhodnutí J-R4 … J-R12. |
| #165 | M1, TASK-0168 | Stránka „Podmínky, GDPR a cookies“ ukazuje dokumenty v platnosti (`PublicLegalLinks::documents`, `ONHOST_DATA.legalDocs`), řádek otevře dokument; pás SLA 99,9 % / 4 h. |
| #166 | M3, TASK-0170 | J-R7: import výpisu České spořitelny (camt.053, ABO/GPC), idempotence, ochrana před XXE, oprávnění `billing.reconcile`. |
| #167 | M2, TASK-0171 | J-R9: texty pokladny (karta jen s bránou, DPH podle režimu, bez bonusu k dobití, tlačítko podle košíku, „49,00 CZK“), obrázek hry bez dotazu na `{{ gcx.gameArt }}`. |
| #168 | M2b, TASK-0172 | Doplněk „CDN a WAF“ jen s nastaveným účtem CDN (R4), jinak „Připravujeme“ a `cdn_unavailable` 409. |
| — | M8, TASK-0173 | Tento zápis, ROZHODNUTI J-R7/J-R9, ruční testy F1.7 a F7-19, řádky 1/3/12 Comgate. |

### Co zůstává

* M4 kontrola v prohlížeči na stagingu (po C) a M5 zbytek generálky — jen s „ano“ vlastníka ke každému kroku, a jen když staging
  nevede jiná relace.
* M6 právní texty po advokátovi (J-R8), M7 Comgate po zřízení účtu (J-R4/J-R9) — čekají na vstupy.
* Vstup ke J-R7: vzorový výpis ČS (ověřit, kde ČS v camt.053 nese VS, a pozice GPC); CSV z George se nepodporuje.
* Účet CDN (Cloudflare), pokud se má doplněk „CDN a WAF“ prodávat.
* Stejná nedoložená čísla na dalších stránkách služeb: 99,99 % a 15 min v `onhost-svc-compute.js` a `onhost-svc-dev.js`, obecné
  „technik odpovídá do deseti minut“ a „Zkuste to 14 dní. Bez karty“ stránek služeb, „Servery v Praze, podpora 24/7“ a „/ měsíc s DPH“
  v konfigurátoru her.
* Lokální stroj: běhy testů padají na „stránkovací soubor příliš malý“, když běží jiné relace — důkazem je matice CI.

## Stav po fázi N (2026-10-12)

Stav nad `origin/development` po sloučení PR #170 (N1, TASK-0174) a tohoto zápisu (N7, TASK-0175). **Na stagingu ani v produkci se ve
fázi N nic nespustilo**: N2 (kontrola v prohlížeči na stagingu, = M4) a N3 (zbytek generálky, = M5) nemají „ano“ vlastníka a staging
vede jiná relace. N0: CI na `development` po sloučení #165 až #169 bylo zelené (merge #169 `cc207eb8`: tests včetně pest-postgres a
php 8.5, e2e smoke, security).

### Sloučená práce fáze N

| PR | Úkol | Obsah |
|---|---|---|
| #170 | N1, TASK-0174 | Zbytek nedoložených čísel (J-R2, L-03): pás SLA stránek compute 99,99 % / „< 15 min“ → SLA 2026-09 Standard 99,9 % / 4 h; VPS bez „root do 55 s“, „0 % přeprodej CPU“, „99,99 % dostupnost 2025“; správa serverů bez 15/60 min a „SLA 99,99 % s kreditem“ (reakce podle třídy SLA, kompenzace kreditem); obecné texty stránek služeb bez „technik odpovídá do deseti minut“ a „Zkuste to 14 dní. Bez karty“ (výpověď ke konci období, 14 dní na odstoupení podle VOP); konfigurátor her bez „Servery v Praze, podpora 24/7“ a neplátci „/ měsíc“ bez „bez DPH“. Prototypy beze změny, demo režim beze změny. |
| — | N7, TASK-0175 | Tento zápis, J-R2 doplněné o N1, ruční test F1.8. |

### Co zůstává

* N2 a N3 (staging) — jen s „ano“ vlastníka ke každému kroku a jen když staging nevede jiná relace.
* N4 výpis ČS podle vzorku (J-R7), N5 právní texty po advokátovi (J-R8), N6 Comgate po zřízení účtu (J-R4/J-R9) — čekají na vstupy.
* Další nedoložené texty, které seznam N1 nejmenoval (kandidáti pro další fázi, vzor `ServicePageClaimsSeam`): v pásu SLA stránek
  compute „2× N+1 napájení a chlazení“ a „10 Gbit/s v základu“; titulek VPS „ne s přeprodaným výkonem“ a „Steal time pod 0,5 %, měříme
  to veřejně“; u správy serverů „zásahy 24/7“ (podporní hodiny jsou otevřený vstup J-R9); perk her „Anti-DDoS ochrana v ceně“; „SLA
  99,99 %“ v tarifech inference, vektorové databáze a úložiště a v tabulkách VPS/dedikovaných serverů (dokud je nepřekryje katalog);
  na hlavní stránce `ctaTitle` „Prvních 14 dní zdarma“, `authRegBtn` „… začít 14 dní zdarma“ a `authProofTitle` „14 dní zdarma, bez
  rizika“ (zkušební doba neexistuje, VOP čl. 2 bod 2).

## Stav po fázi O (2026-10-12)

Stav nad `origin/development` po sloučení PR #173 (O1, TASK-0177), #175 (oprava košíku z kontroly na stagingu, TASK-0178) a tohoto
zápisu (O8, TASK-0180). **Na stagingu ani v produkci se ve fázi O nic nespustilo**: O3 (kontrola v prohlížeči na stagingu) a O4
(zbytek generálky) nemají „ano“ vlastníka. O0: CI na `development` po #170, #171 a #172 (`62e76c44`, protokol stagingu TASK-0176
jiné relace) bylo zelené (tests včetně pest-postgres a php 8.5, e2e smoke, security).

### Sloučená práce fáze O

| PR | Úkol | Obsah |
|---|---|---|
| #173 | O1, TASK-0177 | Další nedoložené texty (J-R2, L-03): pás SLA compute bez „2× N+1“ a „10 Gbit/s“; VPS bez „nepřeprodáváme“ a „steal time pod 0,5 %“ (katalog: sdílená jádra, vyhrazená má VDS); „SLA 99,99 %“ v tarifech a tabulkách bez katalogu (inference, vektorová DB, object storage, stránky řešení) → „podle individuální smlouvy“; úvodní stránka a registrace bez „14 dní zdarma“; konfigurátor her bez „Anti-DDoS ochrana v ceně“ (nic ji nedokládá). Prototypy i demo režim beze změny. |
| #175 | TASK-0178 | Kontrola na stagingu 2026-10-13, B2: řádek „VPS Standard“ v košíku opravilo už L2 (TASK-0164, po vydání `fa2ea75d`); doplněk `backup-plus`, který webhosting prodává záměrně, se jmenoval „Zálohy VPS (PBS)“ — teď „Rozšířené zálohy“ (seeder + migrace `001160`, změněný název týmem zůstává). |
| — | O8, TASK-0180 | Tento zápis, J-R2 doplněné o O1, ruční test F1.9 (a F1.8 bez perku Anti-DDoS). |

### Co zůstává

* O2 podporní hodiny (vstup 4): „zásahy 24/7“ u správy serverů, „30 min reakce podpory 24/7“ v pásu stránek řešení, `tbHours`.
* O3 a O4 (staging) — jen s „ano“ vlastníka ke každému kroku a jen když staging nevede jiná relace; O5 výpis ČS (J-R7), O6 právní
  texty po advokátovi (J-R8), O7 Comgate po zřízení účtu (J-R4/J-R9).
* Ostatní texty o DDoS (vstup 8): KPI „1,2 Tbps anti-DDoS“, profily „pro každou hru“, „detekce do tří sekund“
  (`onhost-svc-other.js`, stránka herních komunit v `onhost-svc-more.js`), `gmBadge` „Anti-DDoS 1,2 Tbps · 24/7“, řádky menu a
  vyhledávání „anti-DDoS v ceně“, karta „Ochrana proti DDoS na hraně“, odrážka katalogu „Anti-DDoS upstream L3/L4“ u herních tarifů.
* Benchmarky VPS (Geekbench „1 842 pts“, „VPS s přeprodanými jádry“) a „2N napájení, chlazení N+1“ u colocation.

## Stav po fázi P, část 1 (2026-10-13)

Pokyn vlastníka 2026-10-13 „Rozhodni a zapracuj všechny vstupy ze seznamu“: každá položka `SEZNAM-vstupu-vlastnika-2026-10-13.md`
je rozhodnuta na doporučeném výchozím jako **P-R1 … P-R75** v `ROZHODNUTI.md` (PR #179, TASK-0183). Údaje, které dodá jen vlastník
(soubory, data, tajemství, odpovědi advokáta, účetní a Comgate, kroky v cizích systémech), zůstávají čekající se svým kódem.

| PR | Úkol | Co se změnilo |
|---|---|---|
| #179 | TASK-0183 | P-R1 … P-R75 v `ROZHODNUTI.md`. |
| #180 | TASK-0184 (P1, P2, P3) | Podpora po–pá 8:00–17:00 přes panel a e-mail (P-R37) na celém veřejném webu včetně stránky podpory a pásů SLA; DDoS u her bez nedoložených čísel (P-R69) včetně odrážky katalogu (migrace `0001_01_01_001170`, jen nedržené verze); VPS benchmark a colocation 2N/N+1 staženy (P-R70). |
| #181 | TASK-0185 | Přirážka EU 0 % (P-R29), stažení závěrečného archivu zdarma (P-R30); bezplatné stažení dál jen vlastník nebo fakturační správce. |

**Staging a produkce:** koordinátorská relace fáze P na stagingu ani v produkci nic nespustila. Jiná relace vlastníka provedla
2026-10-13 s „ano ke stagingu“ jen čtecí běh (režim S) a kontrolu v prohlížeči jako host (protokol
[`staging-rehearsal-2026-10-15.md`](../../runbooks/staging-rehearsal-2026-10-15.md), PR #183, TASK-0187): na hostitel se nic
nezapsalo (jediný stav je anonymní košík hosta), nic se nenasadilo a staging je dál na `199071b8` (doctor 0 FAIL / 28 WARN).
Nálezy protokolu vedly k rozhodnutím P-R76 … P-R78 (balíky Q2–Q4) a ke stavu „hotovo“ u P-R62 (G2) a P-R63 (ST-4).
V produkci se nic nespustilo.

**Zbývá (fáze Q, `PROMPT-faze-Q-2026-10-13.md` na ploše):** P11 HSTS z konfigurace (P-R74), P20 `onhost_ref` varianta C (P-R75),
P10, P12–P19, zbytky textů (Q1), a balíky se vstupem P4–P8 podle SEZNAMu.

## Stav po fázi Q (2026-10-13)

Fáze Q dokončuje balíky fáze P bez vstupu vlastníka (`PROMPT-faze-Q-2026-10-13.md`). Rozhodnutí P-R76 … P-R78 (zjištění kontroly
stagingu) jsou v `ROZHODNUTI.md`. Souhlas vlastníka 2026-10-13 „pokračuj fází Q, ano ke stagingu“ pokryl nový cyklus A–C (Path B)
a ST-6; vše ostatní na stagingu a celá produkce dál čeká na samostatné „ano“.

| PR | Úkol | Co se změnilo |
|---|---|---|
| #184 | TASK-0188 (Q0) | P-R76 … P-R78; P-R62 (G2) a P-R63 (ST-4) hotovo; věta o stagingu ve fázi P podle protokolu 2026-10-15. |
| #187 | TASK-0192 | CI na `development` znovu zelené: pět testů s pevným datem seedovalo ceny před cestou v čase (od 2026-10-13 12:00 UTC 409 „No month price“). |
| #185 | TASK-0189 (Q2, P-R76) | Doplňky `priority_support` („reakce do 10 minut, 24/7“) a `waf_cdn` („anycast cache“) se neprodávají (seeder + migrace `001180`, jen seedovaná slova; držitelé beze změny). |
| #186 | TASK-0190 (Q4, P-R78) | Spec deployeru připouští `ONHOST_TOKEN_ORGANIZATION_REQUIRED` true/false; skutečný běh `write_expected_env` + `env-assert` v CI. |
| #188 | TASK-0191 (Q1, Q3, P-R77) | Stránky O nás, Enterprise a Datacentra nahrazeny celé podle dokumentů v platnosti; bez NOC 24/7, TIER III, ISO 27001, NIS2, CDN 1,2 Tbps, Anti-DDoS Pro, vymyšleného incidentu 480 Gbps, tlačítka „Vyzkoušet zdarma“ a čísel vektorové DB. |
| #189 | TASK-0193 (P11, P-R74) | HSTS z konfigurace (bez preload a includeSubDomains), řádek doctoru *HSTS is sent once and as configured*, runbook `hsts.md`. |
| #190 | TASK-0195 (P17) | `day-2-operations.md`, první den srovnán, `RunbookCommandsTest` (našel neexistující `onhost:provisioning:jobs`). |
| #191 | TASK-0196 (P10, P-R73) | Návrh `aapanel-waf-log-privacy.md` (log WAF 0666, od července, `log_save` nefunguje); provedení je krok vlastníka. |
| #192 | TASK-0197 | Protokol stagingu 2026-10-16: A–C `36009513` (VERDICT pass, 85 migrací) a ST-6. |
| #193 | TASK-0198 (P20, P-R75) | `onhost_ref` vypnutá výchozím přepínačem; kód z `?ref=` jde v požadavku na registraci. |
| #194 | TASK-0194 (P14) | Každá plánovaná úloha zapisuje poslední úspěch a má rozpočet stáří; řádek doctoru a metriky pro alerty. |
| #195 | TASK-0199 (P12) | Alerty pro úlohy po rozpočtu, certifikáty, import výpisu a disk; `promtool test rules` na každé pravidlo v CI; runbook monitorovací VM. |

**Staging (VERIFIED, protokol 2026-10-16):** `36009513ca39f980f670af9da021030923e31e9a` přes bránu (VERDICT pass), migrace 83 → 85,
doctor 0 FAIL; ST-6 hotovo (řádek R9 OK, `env-assert` rc 0, doctor 0 FAIL / 121 OK / 27 WARN). P11–P14, P20 a další PR po #188
na stagingu **nejsou** (přijdou dalším cyklem A–C; řádek HSTS bude WARN `2×`, dokud vlastník nevypne HSTS na vhostu).
**V produkci se nic nespustilo.**

**Zbývá (fáze R, `PROMPT-faze-R-2026-10-13.md` na ploše):** P13 (cvičná obnova skriptem, CI na PostgreSQL), P15a/b (E2E cesty),
P16 (staff stránka „Provoz“), P18 (cutover a `PRODUKCE-prikazy`), P19 (měření po spuštění), zbytky textů nalezené v Q1 (stavová
stránka a postmortemy, DNS anycast, kariéra), a balíky se vstupem P4–P8 podle SEZNAMu.

## Stav po fázi R (2026-10-13)

Fáze R dokončuje balíky fází P/Q bez vstupu vlastníka (`PROMPT-faze-R-2026-10-13.md`). Nové rozhodnutí nepřibylo; odkazy jsou
v `ROZHODNUTI.md`, oddíl *Fáze R*. Nové „ano“ ke stagingu nepřišlo, takže cyklus A–C (R3) **neproběhl**; na stagingu se jen četlo
(režim S, 2026-10-13: `36009513`, doctor 0 FAIL / 27 WARN, vhost stále posílá HSTS).

| PR | Úkol | Co se změnilo |
|---|---|---|
| #198 | TASK-0201 (R0) | #195/#196 sloučené, CI zelené; zametení časovaných bomb celou sadou s hodinami posunutými na 2027-04-01 (`ONHOST_TEST_CLOCK`): žádný test s pevným datem nepadá; 16 falešných poplachů zapsaných v `.ai/TESTING.md`. |
| #197 | TASK-0202 (P13) | Cvičná obnova: `export-db`, `restore-drill.sh` (role aplikace, superuser odmítnut), `drill-record`, řádek doctoru, metriky, alerty; CI job `restore-drill` na skutečném PostgreSQL. |
| #199 | TASK-0204 (R1) | Stavová stránka, postmortemy (46 200 Kč / 214 zákazníků), kroky kreditu, kariéra a DNS anycast podle SLA 2026-09. |
| #200 | TASK-0203 (P16) | Staff stránka *Provoz* (`/sprava/nastaveni/provozni-prehled`), jen čtení, sekce podle oprávnění. |
| #201 | TASK-0205 (P19) | Návrh `metering-after-launch.md`: 7 mezer, balíky M1–M5, harmonogram P-R24 s náhledy a rollbackem. |
| #202 | TASK-0206 (P15) | E2E cesta webhostingu placeného převodem (camt.053 i GPC) od objednávky po obnovení; návrh pro staging. |
| #203 | TASK-0207 (R2) | CSV dokladů a plateb za měsíc pro účetní (finance, ochrana proti vzorcům, záznam čtení). |
| #204 | TASK-0208 (P18) | Plán produkce na cíl `fda0cedf`, `PRODUKCE-prikazy-2026-10-13.md` na ploše (NESPOUŠTĚT); test skriptů a přepínačů plánu. |

**Zmrazený cíl fáze R:** `fda0cedf443a3c603ef2aeae1a6184fbaf7dfc6a` (merge #203). Dokument pro další cyklus stagingu:
`GENERALKA-R-A-C-deploy-prikazy-2026-10-13.md` (krok B přidá 2 WARN řádky — HSTS a cvičná obnova; volitelný krok D = první cvičná
obnova, heslo role zadává vlastník). **V produkci se nic nespustilo.**

**Zbývá:** R3 (A–C `fda0cedf`, nové „ano“), kroky vlastníka z `GENERALKA-Q-V-HSTS-WAF-prikazy-2026-10-13.md`, balíky se vstupem
P4–P8, metering M1 (aliasy, jedno čtení na stagingu), zbytky textů nalezené v R1 (seznam v úkolu TASK-0204).

## Stav po fázi S (2026-10-14)

Fáze S (`PROMPT-faze-S-2026-10-13.md`): vlastník dal „ano R3“; cyklus A–C stagingu proběhl přes SSH a staging běží na
**`fda0cedf`** (protokol `docs/runbooks/staging-rehearsal-2026-10-17.md`; doctor 0 FAIL / 122 OK / 29 WARN, migrace 86). Krok D
(cvičná obnova, heslo role zadává vlastník) souhlas nemá a neproběhl. Odkazy na rozhodnutí: `ROZHODNUTI.md`, oddíl *Fáze S*.

| PR | Úkol | Co se změnilo |
|---|---|---|
| #206 | TASK-0212 (S3) | `/metrics` pod PHP-FPM neznal žádnou plánovanou úlohu (`command="none"`, rozvrh je v `routes/console.php`); plánovač nechává rozpočty v cache, web je čte — alert `OnhostScheduledJobOverdue` může vystřelit. |
| #207 | TASK-0210 (S1) | Aliasy pošty jsou limit tarifu (`alias.create`), počítají se jen řádky `type = alias` (ISPConfig vrací přesměrování i catch-all z téže tabulky); `alias.delete` s ID přesměrování odmítnut; `KNOWN_GAPS` 7 → 6. |
| #208 | TASK-0211 (S2) | Zbytek textů R1 podle dokumentů v platnosti; stavová mřížka za 30 dní (dřív 90 dní ve 30 sloupcích). |
| #209 | TASK-0213 (S9) | Protokol stagingu, tento oddíl, odkazy v `ROZHODNUTI.md`. |

S0: CI na `development` zelené (`1c4853f5`), #204/#205 sloučené, zámky TASK-0201 … TASK-0209 uvolněné; zametení s hodinami
2027-04-01 (`ONHOST_TEST_CLOCK`): 3 192 testů, 16 pádů — všechny ze seznamu falešných poplachů v `.ai/TESTING.md`, žádná nová
časovaná bomba. **V produkci se nic nespustilo.** PR fáze S čekají na sloučení (sloučení bez review automatický režim odmítl;
slučuje vlastník nebo koordinátor).

**Zbývá:** D (DR-1), HS-2, balíky se vstupem P4–P8, měření M2–M5, zbytky textů nalezené v S2 (seznam v úkolu TASK-0211).

## Stav po fázi T (2026-10-14)

Fáze T (`PROMPT-faze-T-2026-10-14.md`): T0 — PR #206–#209 sloučené (MRG-1, koordinátor s pověřením vlastníka), CI na `development`
zelené (`2848db64`, včetně pest-postgres 1–4, php 8.5, monitoring, restore-drill), zámky TASK-0210 … TASK-0213 uvolněné. Na stagingu
se jen četlo (`fda0cedf`, doctor 0 FAIL / 121 OK / 30 WARN, migrace 86; herní verze s `pids` nikdo nedrží). **V produkci se nic
nespustilo.** Odkazy na rozhodnutí: `ROZHODNUTI.md`, oddíl *Fáze T*.

| PR | Úkol | Co se změnilo |
|---|---|---|
| #210 | TASK-0214 (T2) | Herní tarify neprodávají strop procesů, který nic nedrží: seeder bez `pids`, migrace `001200`, revize `2026-10-game-plans-without-pids`; `KNOWN_GAPS` 6 → 5. |
| #211 | TASK-0215 (T4) | Zbytky textů ze S2 přes `PhaseTCopySeam` (prototypy byte-identické); veřejné swapy fáze T běží až po `PublicContentSeam`. |
| #212 | TASK-0216 (T3) | Měsíční přenos VPS/VDS z Proxmoxu proti `traffic_tb` (měkký limit, nová metrika rodiny `cloud`); oznámení plného měkkého limitu neříká, že se nic nedá přidat. |
| #213 | TASK-0217 (T9) | Tento oddíl a odkazy v `ROZHODNUTI.md`. |

**Nový zmrazený cíl:** `a5b827cb192177cd36949003746dec26fd0d07fc` (merge #212) — dokument `GENERALKA-T-A-C-deploy-prikazy-2026-10-14.md`
(A–C beze změny skriptů, migrace 87, žádný nový řádek `expected-nonok`, C4 navíc `/metrics` se skutečnými příkazy a mřížka 30 dní).
Čeká na nové „ano“ (T-ano). `PRODUKCE-prikazy-2026-10-13.md` míří dál na `fda0cedf` — po cyklu T na stagingu se přesune na nový cíl.

**Zbývá:** T-ano, D (DR-1), HS-2, balíky se vstupem P4–P8, M4 (`spam_filter`), M5 (`php_workers`), CD-3 (doplněk CDN z katalogu → zavře
`traffic_tb` i `bot_management`), zbytky textů nalezené v T4 (seznam v úkolu TASK-0215), ověření přenosu na Proxmoxu (PX-1).

## Stav po fázi U (2026-10-14)

Fáze U (`PROMPT-faze-U-2026-10-14.md`): U0 — CI na `development` po #213 zelené (`cffda3a2`), zámky TASK-0214 … TASK-0217 uvolněné.
Vlastník nedal „T-ano“, proto **U1 neproběhl**: na stagingu se jen četlo (2026-10-14: `VERSION` = `current` = `fda0cedf`, migrace 86,
produkt `cdn` aktivní se seedovanými texty, 0 předplatných a 0 služeb `cdn`). **V produkci se nic nespustilo.** Sloučil koordinátor
s pověřením vlastníka (MRG-1) po zelené celé matici CI. Odkazy na rozhodnutí: `ROZHODNUTI.md`, oddíl *Fáze U*.

| PR | Úkol | Co se změnilo |
|---|---|---|
| #214 | TASK-0218 (U2 = CD-3) | Doplněk „CDN a WAF“ (`cdn`) z prodeje: seeder `draft`, datová migrace `001210` (jen dokud stojí seedovaný stav a texty; plány, verze, ceny a držená předplatná beze změny; `down()`); `KNOWN_GAPS` 5 → 3 (`traffic_tb`, `bot_management`); `MetricRegistry` `traffic_tb` bez `gap_families`, `bot_management` zůstává GAP pro držené. |
| #215 | TASK-0219 (U3) | Zbytky textů z T4 přes `PhaseUCopySeam`: databáze bez PITR (lead, štítky, karty, tabulka, výhoda), WordPress bez „4 200 instalací“ a „0 rozbitých updatů“, VPS „přenos podle tarifu“ místo „neměřený“, bez „vlastní páteře Praha–Brno“, changelog API jen z `docs/api/CHANGELOG.md`; řádek tendru „Enterprise podle individuální smlouvy“ (oprava v `PhaseRCopySeam`, P-R37). `slaData()` se nevykresluje (ověřeno). |
| #216 | TASK-0220 (U4 = M4) | `spam_filter` poštovních tarifů jako fair use s důvodem; ceník „Antispam rspamd s pravidly serveru (fair use)“ místo úrovně „policies“; `KNOWN_GAPS` 3 → 2 (`php_workers`, `backup_days`). |
| — | TASK-0221 (U9) | Tento oddíl a odkazy v `ROZHODNUTI.md`. |

**Nový zmrazený cíl:** `259cbea6f3ab7e673436778dccaf251c66de21ce` (merge #216) — dokument `GENERALKA-T-A-C-deploy-prikazy-2026-10-14.md`
obnovený na tento cíl (skripty beze změny, migrace 86 → **88**: `001200` a `001210`, žádný nový řádek `expected-nonok`; C4 navíc `cdn` =
`draft` a 2 známé mezery). Čeká dál na „T-ano“. `PRODUKCE-prikazy-2026-10-13.md` míří dál na `fda0cedf` — po cyklu na stagingu se přesune.

**Zbývá:** T-ano (U1), D (DR-1), HS-2, balíky se vstupem P4–P8, M5 (`php_workers`), `backup_days` (pravidla vlastníka vypnutá),
ověření přenosu VM na Proxmoxu (PX-1); per-tarifní politika rspamd jen na volbu vlastníka (balík se stagingem).
