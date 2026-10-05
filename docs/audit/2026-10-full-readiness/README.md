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
