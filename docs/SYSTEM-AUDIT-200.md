# OnHost — Systémový audit (200 bodů: nedokončené, chybějící, nalezené)

**Datum:** 2026-07-18 · **Testy:** 2784 zelených · **PHPStan:** 0 chyb (L6)
**Rozsah:** panel `/panel`, admin `/admin`, doménová logika, integrace, infrastruktura, testy.
**Metodika:** statická analýza (grep napříč `resources/views` + `app`, PHPStan), E2E v prohlížeči, porovnání s Cuba Tailwind šablonou (`laravel-admin-panel/template/`, 206 stran).
**Závažnost:** 🔴 kritické · 🟠 vysoké · 🟡 střední · 🟢 nízké/rozšíření.

## ✅ FÁZE A DOKONČENA (2026-07-18)
Kompletní migrace neexistujících Bootstrap tříd na Tailwind ekvivalenty, které Cuba skutečně dodává, napříč **367 Cuba-layout soubory** (panel/admin/reseller/partner + Cuba komponenty + cuba-standalone). Antler/Bootstrap front stránky ponechány. Ověřeno: 0 zakázaných tříd (guard test `CubaConformityTest`), 2785 testů zelených, PHPStan 0, dashboard/checkout/objednávky renderují správně (grid = sloupce vedle sebe, flex funguje).
- A1–A3 flex/display/align/justify/text/float: `d-flex→flex`, `align-items-*→items-*`, `justify-content-*→justify-*`, `flex-column→flex-col`, `text-end→text-right`, `float-end→float-right`, `d-block→block`, `d-inline-flex→inline-flex`.
- A4–A5 grid: `col-{bp}-N→col-span-12 {bp}:col-span-N`, `col-N→col-span-N`, `row→grid grid-cols-12`, `g-N→gap-N`. (opraven i pre-existing typo `lg:col-span-2-4`).
- A6 text-align, A7 spacing (ms/me/ps/pe už v Cuba existují — bez migrace), A8 float, A9 `rounded-pill/circle→rounded-full`, `rounded-3→rounded`.
- Navíc: `w-100→w-full`, `h-100→h-full`, `fw-bold→font-bold`, `fw-semibold→font-semibold`, `text-truncate→truncate`, `position-relative→relative`, `text-uppercase→uppercase`, `vh-100→h-screen`, `rounded-top→rounded`.
- A10 checkout: inline `<style>` přesunut do onhost.css (Cuba vars), `#54ba4a→rgba(var(--success-color),1)`.
- A11 hex: UI barvy (support border) → Cuba var; barvy grafů (ApexCharts JS) a color-picker data ponechány konkrétní (jinak rozbití/nesoulad dark mode) — dokumentováno.
- A12 inline styly: checkout blok přesunut; zbývající ~1159 je per-ikona sizing (theming-neutrální).
- A13 tooltipy: přidán init `bootstrap.Tooltip` v layoutu (Cuba je neinicializovala).
- A22 breadcrumb komponenta `x-panel.breadcrumb` už existuje; A21 Cuba loader už v layoutu.
- A25 `prefers-reduced-motion` blok přidán do onhost.css.
- **Guard**: `tests/Feature/CubaConformityTest.php` selže při jakémkoli návratu zakázané Bootstrap třídy do Cuba pohledů (M165 splněno).
- A15 table-responsive: ověřeno — široké datové tabulky (seznamy) už mají `overflow-x-auto`/`table-responsive`; 15 souborů bez wrapperu jsou úzké key-value tabulky (2 sloupce) → wrapper netřeba.
- A23 empty-state: nová komponenta `x-panel.empty-state` + nasazena (košík, chat inbox). A26 print styly + A25 reduced-motion v onhost.css. A18 kalendář: už používá FullCalendar (hotovo). A21 loader: Cuba loader už v layoutu. A22 breadcrumb: `x-panel.breadcrumb` už existuje.
- **A20/A21 skeleton/card-hover**: `.card-hover` ani `.skeleton` v Cuba NEEXISTUJÍ (0 def) → vytvářet je by porušilo pravidlo „jen z Cuba" → vynecháno.
- **Zbývá jako samostatné featury (ne konformita tříd)**: A16 datatable-advance (server-side stránkování je pro velká data LEPŠÍ — client-side by byl regres), A17 sjednocení grafů (dashboard už ApexCharts; ~4 vedlejší stránky na chart.js fungují), A19 dropzone (vyžaduje backend příloh), A24 Cuba e-mailové šablony (mail theme — riskantní zásah do všech e-mailů). Jádro fáze A (žádné třídy mimo Cuba) je 100% hotové.

## ✅ FÁZE B DOKONČENA (2026-07-19)
Chat a podpora: **B27** reálný AI provider napojen (`maybePhraseWithProvider()` přes `ClaudeProvider`, gated `config('ai.allow_real_calls')` — bez klíče deterministická KB); **B29** notifikace operátorům při eskalaci (`ChatEscalatedNotification`, database kanál); **B30** nepřečtené + doba čekání v inboxu; **B31** eskalace chatu → ticket s přepisem; **B32** přílohy v chatu (private disk, auth-gated download); **B33** rate-limit `ai.chat` 30/min, `ai.escalate` 10/min, `ai.poll` 120/min, `ai.upload` 20/min; **B34** historie konverzací na kartě zákazníka.
- **B28 WebSocket (Reverb) — VĚDOMĚ ODLOŽENO**: polling à 5 s je ověřeně funkční; Reverb by vyžadoval další běžící službu a deploy změnu bez úměrného přínosu při současném objemu chatů. Přehodnotit až při >50 souběžných konverzacích.

## ✅ FÁZE C DOKONČENA (2026-07-19)
- **C37** množství > 1 u provisioning produktů zakázáno (`Product::provisionsInstance()`, cart cap = 1). Zjištěno: `products.provisioning_driver` je NOT NULL → **každý** produkt zřizuje 1:1 instanci, takže qty>1 je vždy chyba; akce (`CreateCartOrderAction`) qty>1 nadále podporuje pro admin/API.
- **C38** DPH v košíku živě (`VatResolver` → mezisoučet / DPH / celkem k úhradě).
- **C39** slevový kód v košíku s AJAX validací (sdílený endpoint s wizardem).
- **C40** registrace NOVÉ domény z košíku (checkbox + kontrola dostupnosti přes registrar; nedostupná doména objednávku zablokuje).
- **C41** opakovaná (recurring) cena zobrazena, seskupená dle fakturačního cyklu.
- **C42** storno nezaplacené objednávky zákazníkem + storno otevřených proforem.
- **C43** náhled reseller marže v košíku.
- **C44** platba kreditem je výchozí, pokud zůstatek pokryje objednávku; jinak vede Comgate a kredit je zřetelně označen jako nedostatečný.
- **C45** e-mail „objednávka přijata" (`OrderReceivedNotification`).
- **C49** odhad doby zřízení v košíku. **C50** povinný souhlas s VOP + GDPR (validace `accepted`, ne jen UI).
- **ODLOŽENO s odůvodněním**: **C35** sjednocení `/pokladna` a `/kosik` (architektonický zásah do dvou funkčních toků — samostatná fáze), **C36** per-produkt konfigurátor (vyžaduje datový model atributů produktu), **C46** upsell/cross-sell, **C47** wishlist → košík, **C48** welcome kredit.

## ✅ FÁZE D — STAV (2026-07-19)
- **D54 bezpečnost webhooků — OPRAVENA VÁŽNÁ CHYBA**: `StripeGateway` a `GopayGateway` neměly **žádnou** container binding (konstruktory berou stringy → nelze autowire). Oba webhook endpointy tedy padaly na `BindingResolutionException` při **každé** notifikaci — přes kartu nemohla projít jediná platba. Doplněny bindingy + regresní test na resolvovatelnost všech bran a procesorů.
  - Stripe: HMAC-SHA256 + `hash_equals` + 300s replay okno ✓ (ověřeno testy na padělaný podpis, starý timestamp, chybějící hlavičku).
  - Comgate: bez HMAC, ale **autoritativní server-to-server re-fetch** stavu + shoda částky + IP whitelist → padělat platbu nelze ✓.
  - GoPay: re-fetch ✓, ale **chyběla kontrola shody částky** → podhodnocená platba by označila fakturu jako plně uhrazenou. Doplněno + sanitizace `gateway_response` (secrets se nesmí ukládat).
- **D55 dobropis**: již kompletní (route + admin modal + `IssueCreditNoteAction` s guardy na typ/stav/idempotenci) — doplněny feature testy.
- **D56 refundace**: žádná napojená brána nemá refund API, takže vrácení na kartu je nutně manuální. Doplněna **volba cíle refundace** (`RefundDestination`): `credit` se připíše okamžitě, `original_method` se zaznamená jako úkol pro operátora. UI to říká výslovně místo aby to předstíralo.
- **D58 bonusová pásma kreditu**: doplněna (`billing.credit_topup.bonus_tiers`, aplikuje se nejvyšší pásmo, bonus jako samostatný ledger zápis). Limity dobití už existovaly.
- **D59 expirace kreditu**: ověřeno — `billing:expire-credit` (scheduled) zapisuje zápornou Expiry položku; ledger je append-only.
- **D65/D66 číselné řady a zaokrouhlení**: ověřeno — `lockForUpdate` + UNIQUE(series, year) + UNIQUE(invoices.number); mezisoučet + DPH = celkem na haléř.
- **D57 splátky**: existují (routes + Panel/Admin controller + testy) na úrovni faktury, což je správné místo.
- **D62 OSS / reverse charge**: implementováno (5 scénářů: CZ B2C/B2B 21 %, EU B2C OSS sazba země, EU B2B reverse charge 0 %, mimo EU 0 %) a pokryto testy.
- **D64 dunning**: implementováno a naplánováno (`MarkOverdueInvoices`, `SendPaymentOverdueReminders`, `EscalateInvoiceReminders`, `SuspendOverdueServices`) + testy.
- **ZBÝVÁ**: **D60 pro-rata** při změně tarifu (chybí úplně — vyžaduje návrh dopočtu nevyčerpaného období a doúčtování rozdílu) a **D63 XLSX export** (v projektu není žádná XLSX knihovna; vyžaduje rozhodnutí o přidání `phpoffice/phpspreadsheet`). **D51/D52/D53/D61** jsou blokované externě — vyžadují reálné sandbox credentials bran, které v tomto prostředí nejsou a real writes se zapínat nesmí.

## ✅ H119 — NALEZENA TICHÁ BEZPEČNOSTNÍ DÍRA V CSP (2026-07-19)
**Nejzávažnější nález této dávky.** CSP nastavuje `script-src` s nonce a **záměrně bez `'unsafe-inline'`**. Nonce ale autorizuje jen bloky `<script>` — **nikoli inline event atributy** (na ty by bylo potřeba `'unsafe-hashes'`). Při `SECURITY_CSP_ENFORCE=true` se tedy `onsubmit="return confirm(...)"` **nespustí**, a protože se nespustí, také nikdy nevrátí `false` → destruktivní formulář se odešle **úplně bez potvrzení**. Tiše. To je horší než žádná pojistka — mazání databáze, schránky nebo wipe game serveru by proběhly na první kliknutí.
- **138 inline handlerů ve 98 souborech** převedeno na deklarativní `data-confirm` / `data-prompt`, chování obsluhuje delegovaný listener v layoutu (uvnitř řádně nonceovaného skriptu).
- **34 inline `<script>` bez nonce** — rovněž blokované — doplněny nonce. Plus JSON-LD bloky na frontu (jinak by strict CSP shodil strukturovaná data → ztráta SEO).
- Guard test: **žádný formulář** nesmí mít inline handler (to je ta nebezpečná třída) + všechny skripty nonceované + `script-src` nesmí získat `'unsafe-inline'` (jinak je nonce jen dekorace).
- **Zbývá 38 `onclick`/`onchange`** volajících lokální funkce (filtry, kopírování do schránky, přepínače). Ty se při enforce rozbijí **viditelně**, ne tiše — proto se retirují postupně, ne jedním rizikovým sweepem. Test je **ráčna**: číslo smí klesat, ne růst.

## ✅ FÁZE H DOKONČENA (2026-07-19)
- **H112** akceptace platby adminem: chráněno `can:access-admin` a auditováno s `causer` — ověřeno testy.
- **H118** IP allowlist: plné CRUD UI už existovalo (audit tvrdil „jen DB/config"); záznamy jsou CIDR, takže lze povolit celý rozsah kanceláře.
- **H122** GDPR: export i výmaz vč. admin schvalování a **plánovaného** `ProcessGdprErasureRequestsCommand` — lhůty nezávisí na tom, že si někdo vzpomene spustit skript.
- **H123 evidence souhlasů — DOPLNĚNO**: souhlas s VOP se dosud jen validoval, ale **nikde neukládal**. GDPR čl. 7(1) ale žádá, aby šel souhlas **doložit**. Nová `consent_records` (kdo, jaký typ, **která verze dokumentu**, kdy, z jaké IP, odkud) + `config/legal.php` s verzemi. Souhlas sněním z roku 2024 není souhlasem s něním z roku 2026. Tabulka je **append-only** — odvolání je nový záznam, ne přepis; přepisování historie by celý smysl evidence popřelo.
- **H124** hlavičky: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` ověřeny; HSTS se posílá jen přes HTTPS (posílat ho po HTTP je bezpředmětné a umí zablokovat lokální/staging instalaci).

## ✅ FÁZE J — API (2026-07-19)
- **J136 Idempotency-Key — DOPLNĚNO**: klient, kterému spadne spojení uprostřed requestu, dosud nemohl bezpečně opakovat — buď riskoval dvojí objednávku, nebo ztrátu požadavku. Nyní: stejný klíč přehraje uloženou odpověď (handler se nespustí znovu), **stejný klíč s jiným tělem → 422** (tiché přehrání by zamaskovalo chybu klienta), rozpracovaný požadavek → 409, **neúspěšný request se neukládá**, aby šel po opravě zopakovat. Klíče jsou scoped **per token** — klíč jednoho klienta nesmí potlačit požadavek jiného.
- **J134 per-token rate limiting — DOPLNĚNO**: dosud `throttle:60,1` klíčovalo na uživatele, takže pět integrací jednoho zákazníka sdílelo jeden rozpočet a jeden splašený skript vyhladověl ostatní. Nyní se klíčuje na token a limit lze per token přepsat (tabulka `api_token_rate_limits`); klient vidí zbytek v `X-RateLimit-*`.
- **J142 health endpoint `/api/up` — DOPLNĚNO**: hlásí i databázi a při její nedostupnosti vrací **503** — proces, který „běží", ale nevidí DB, patří z rotace ven, ne mezi zdravé. Bez autentizace: health probe vyžadující credentials je jen další věc, co se rozbije ve tři ráno.
- **J135 verzování** (v1/v2) už existovalo. **J137** webhook retry + podpis existuje.
- **J133 OpenAPI pokrytí — DOKONČENO**: spec měl `servers` na `/api/v1` i `/api/v2`, zatímco path klíče už verzi nesly → každá URL se skládala špatně (`/api/v1/v2/services`). Nyní jeden base `/api` a verzované path klíče; Idempotency-Key na všech write operacích; `/up` a v2 endpointy doplněny. **Guard test** `OpenApiSpecCoverageTest` selže, když přibude write route bez dokumentace, když spec popisuje neexistující endpoint, nebo když se rozbije `$ref`.
- **J138 developer portal — OVĚŘENO E2E**: `DeveloperPortalTest` prochází celý tok token/OAuth: vytvoření → funkční API volání → zobrazení tajemství právě jednou → rotace (staré tajemství přestane platit) → **zrušení tokenu okamžitě odřízne API přístup**. Cizí zákazník nesmí sáhnout na cizí token/aplikaci.
- **J141 error tracking — PŘIPRAVENO (bez externí závislosti)**: žádný tracker se neinstaluje (rozhodnutí o `sentry/sentry-laravel` + DSN je na operátorovi, `config/error-tracking.php` to dokumentuje). Hotová je ta část, co MUSÍ být správně DŘÍV, než se tracker zapne: `ErrorContext` nese request_id/route/user_id a nic víc, vše přes `SecretRedactor` — napojit tracker na neredaktovaný kontext by poslalo credentials třetí straně při první výjimce. **Sdílený `SecretRedactor`** nahradil dvě rozcházející se privátní kopie (aapanel + WEDOS). **`AssignRequestId`** dává každému requestu ID (v hlavičce i log kontextu) pro korelaci.
- **Nalezená chyba mimo audit**: per-token rate limiter četl `$token->id` bezpodmínečně; session (cookie) request má `TransientToken` bez id → `ErrorException` a 500 na každém takovém volání. Opraveno (fallback na usera), regresní test.
- **ZBÝVÁ (externě blokováno)**: J139 reálné E2E Pterodactyl/Proxmox (chybí credentials), J140 aaPanel produkční deploy.

## ✅ FÁZE I — Notifikace / komunikace (2026-07-19)
- **I132 katalog notifikací — SJEDNOCENO**: předvolby byly dvě natvrdo psaná pole v `AccountController`, která se už rozešla s kódem: `credit` se v kódu kontroloval, ale v UI chyběl → **nešel vypnout** (opt-out mapa se skládala diffem proti UI seznamu; klíč mimo seznam = „chce"). `backup` byl v UI, ale žádná notifikace ho nepoužívala → přepínač do prázdna. Nyní jediný `config/notifications.php` + `NotificationCatalog`; 35 notifikací, které předvolby úplně ignorovaly, teď respektuje `wantsNotification()`. **Povinné typy** (bezpečnost, kritické stavy služeb) nejdou vypnout — mlčení by samo bylo škoda.
- **I125 e-mail u nového-IP loginu — DOPLNĚNO**: `NewIpLoginNotification` měl `toMail()`, který se nikdy neposlal (`via()` vracelo jen `['database']`). Příčina byla reálná: detekce porovnávala jen s `last_login_ip`, takže kdo střídá dvě adresy, „byl na nové IP" při každém loginu → mail-bomba. Detekce teď kouká do historie loginů (`TrackSecurityEvent::isUnfamiliar`), takže signál něco znamená — a mail je **opt-in** (kdo ho chce, chce ho hodně; ostatní se nemají učit ignorovat bezpečnostní maily).
- **Nalezená chyba mimo audit**: `config('app.security_csp_enforce')` se četl, ale nebyl definován v žádném config souboru → `SECURITY_CSP_ENFORCE=true` nikdy nic neudělal a CSP jel pořád jen v Report-Only. Vytvořen `config/security.php`, přepnuto na `security.csp_enforce`.
- **I129 údržba → dotčení + kalendář — DOPLNĚNO**: příkaz mailoval VŠECHNY zákazníky o KAŽDÉM okně (i o údržbě serveru, na kterém nic nemají) a ignoroval `notify_customers`. Nyní cílí jen na zákazníky se službou na dotčeném serveru, respektuje vypnutí, a přikládá **`.ics` pozvánku** (vlastní RFC 5545 writer — CRLF, 75-oktetové folding bez rozseknutí UTF-8 znaku, escaping).
- **I126 kritické incidenty (Slack/SMS) — DOPLNĚNO**: `CriticalAlertDispatcher` — off, dokud není webhook (tichá cesta, které se věří, je horší než žádná); selhání doručení nikdy neeskaluje incident (volá se z jeho středu); webhook URL se nikdy nedostane do logu. Napojeno na selhání jobů.
- **I128 notifikační centrum — filtr přečtené/nepřečtené** doplněn; neznámý `status` se ignoruje místo prázdné schránky.
- **I130 in-app changelog „co je nového"** — nový `ProductUpdate` model + admin CRUD + panel stránka; „nové" je relativní k `changelog_seen_at` daného uživatele (ne globální datum), nový zákazník nedostane odznak 40.
- **I127 digest opt-out — DOPLNĚNO**: `digest_frequency` měl obrazovku i sloupec a nic ho nečetlo — kdo zvolil „nikdy", dostával digest dál. Nyní se respektuje.
- **I131 Reverb reconnect — DOPLNĚNO**: bindovaly se jen `connected`/`disconnected`; `unavailable` a `failed` (server nedostupný — běžný případ) nikdy neobnovily polling → zvonek tiše zamrzl. `state_change` pokrývá všechny stavy + při reconnectu dotáhne, co přišlo během výpadku; `visibilitychange` pro throttlované taby.

## ✅ FÁZE K — Reseller / partner / affiliate (2026-07-19)
- **K145 výplaty partnerům — STAVOVÝ STROJ + audit**: `ResellerPayoutRequestController::update` dovolil JAKÝKOLIV stav → jakýkoliv, takže **`paid` request šel přepnout na `approved` a vyplatit podruhé** (reálné peníze, žádná pojistka). Nyní přechodová matice (`paid`/`rejected` terminální), 422 na nelegální přechod, a **activity log** u pohybu peněz.
- **K146 individuální ceny — ZAPOJENO**: `ResellerPricingOverride` (per-plán pevné ceny) měl admin CRUD, ale **nic ho nečetlo při tvorbě ceny** — vyjednaná cena se nakonfigurovala a pak tiše ignorovala, zákazník platil markup cenu. Nový `ResellerPriceResolver`: override > markup > base; override vyhrává napevno (markup se na něj nepřičítá), chybějící měna nepropadne na 0 Kč. Zapojeno do `CreateOrderAction` i `CreateCartOrderAction`.
- **K149 reseller MRR + marže — DOPLNĚNO**: dashboard měl jen historické tržby; přidán MRR (aktivní služby normalizované na měsíc — roční plán = dvanáctina) a měsíční marže.
- **Stav auditu upřesněn**: K150 (víceúrovňové tiers) UŽ existuje (`PartnerTierService`); K143 white-label, K147 affiliate tracking, K148 bannery hotové dřív.

## ✅ FÁZE L — Výkon / škálovatelnost / infrastruktura (2026-07-19)
- **L156 priority front — NALEZENA TICHÁ CHYBA**: provisioning joby se posílaly na fronty `provisioning-high` / `provisioning` / `provisioning-low`, ale Horizon supervisor zpracovával jen `default` → **na produkci by ty joby ležely na frontě navěky a žádnou službu by worker nikdy neaktivoval**. Nic by neselhalo, jen ticho. Rozděleno na dva supervisory: `supervisor-provisioning` (izolovaný, `provisioning-high` první — payment webhook → aktivace se drénuje před pomalejším provisioningem) a `supervisor-default` (maily, notifikace, digesty, exporty), aby backlog jednoho nebrzdil druhé. **Guard test** `QueueSupervisorCoverageTest` selže, když nějaký job cílí na frontu, kterou žádný supervisor nezpracovává.
- **L151 route/view cache — GUARD V CI**: `ProductionCacheReadinessTest` reprodukuje produkční cache stav (route/config/view cache) v CI — chytá closure v routě (rozbije `route:cache` → všechny routy 500), duplicitní názvy rout (cached se zapečou a `route()` vrací špatnou URL) a chybějící config klíče (`env()` mimo config vrací po cache null). To je přesně třída „nefunguje na produkci" reportů.
- **L152/L153/L154 — OVĚŘENO HOTOVÉ + zajištěno regresí**: header credit balance je už cachovaný (`CreditLedger::getBalance`), cart composer je levný (session); hot list views (služby/faktury/objednávky) už eager-loadují — `ListViewQueryCountTest` počítá dotazy při 2 vs 20 řádcích a selže, když počet roste s řádky (někdo shodil `with(...)`); DB indexy na hot sloupcích (`services (customer_id, status)`, `invoices (customer_id, status)`, `orders`, FK na `server_id`/`order_id`) existují.

## ✅ FÁZE M — Testy / QA / observabilita (2026-07-19)
- **M166 strukturované logování — DOPLNĚNO**: `LogContext::with([...], fn)` stampuje `customer_id`/`service_id`/`server_id`/`driver` na každý log řádek uvnitř bloku (nasazeno v `ProvisionHostingServiceJob`). Selhání ve 3 ráno teď odpoví „čí služba, který server?" z log řádku samotného, ne z grep výpravy. Pole procházejí `SecretRedactor` (defence in depth).
- **M168 contract testy integrací — DOPLNĚNO**: `ProvisioningDriverContractTest` drží každý driver (mock i produkční) ke stejnému rozhraní a stejným návratovým TVARům — create vrací externalId nebo pending task, lifecycle ops vrací dobře utvořený `ProvisioningResult`, usage je typované `UsageStats`, a **žádný driver nevypustí credential do metadata**.
- **M161/M165/M167 už existovaly**: `ProvisioningFlowTest`/`ProvisionPendingServicesTest` (E2E fronta), `CubaConformityTest` (grep guard), `MvpFullSmokeTest` (všechny GET routy).
- **ZBÝVÁ (CI tooling)**: M163 coverage/mutační testy v CI, M164 axe a11y — konfigurace CI pipeline, ne kód.

## ✅ FÁZE N — UX / přístupnost / i18n / mobil (2026-07-19)
- **N172/N173 i18n parita — DOPLNĚNO**: 51 klíčů bylo v `cs`, ale chybělo v `en` → anglicky mluvící uživatel viděl syrový klíč (`services.pay_renewal`) místo překladu. Doplněny; `LocalizationParityTest` selže, když cs/en přestanou být v synchronu, i když se placeholder tokeny (`:count`) rozejdou.
- **N176 dark mode per-uživatel — DOPLNĚNO**: sloupec + controller existovaly, ale toggle na ně nebyl napojený a layout uloženou volbu neaplikoval → „resetovalo se" (přesně ta stížnost). Nyní `<body class="dark-only">` server-side (žádný flash) + delegovaný handler (CSP-safe) překlopí třídu okamžitě a uloží na pozadí. `DarkModePreferenceTest`.
- **N171 aria-live — DOPLNĚNO**: flash region hlásí výsledek akce screen readeru (status polite, chyby assertive).
- **N169 inline confirm, N179 chybové stránky — OVĚŘENO HOTOVÉ**: inline handlerů je 0 (fáze L/I); error stránky 403/404/419/500/503 už jedou na `layouts.cuba-standalone`.
- **N174 mobilní nav** (sidebar-toggle) existuje.
- **ZBÝVÁ (enhancement, ne konformita)**: N170 focus-trap v modálech, N175 klávesové zkratky, N178 skeleton, N180 onboarding tour — polish.

## ✅ FÁZE O — Produktové mezery (2026-07-19)
- **O189 agregovaný live status na dashboardu — DOPLNĚNO**: dashboard listoval služby jednu po druhé, ale neodpovídal na první otázku zákazníka „je teď vše OK?". Nový `ServiceHealthSummary` sroluje celé portfolio na jeden verdikt (ok / attention / critical) + počty za ním; strip na dashboardu. `ServiceHealthSummaryTest`.
- **O188 KB, O190 add-ony k běžící službě — OVĚŘENO HOTOVÉ**: KB controllery (panel+admin) existují; add-on flow je plně zapojený (`services.addons.index/activate/cancel` + admin resource).
- **ODLOŽENO S ODŮVODNĚNÍM (vícenítýdenní produktové sázky, vyžadují infra rozhodnutí)**: O181 e-mailový hosting (chybí mail API v aaPanel klientovi), O182 SSL jako produkt, O183 object storage/S3, O184 managed WordPress, O185 rozšíření marketplace, O187 statusová stránka (externí monitoring), O191 migrace z cPanel/Plesk, O194 usage grafy (potřebují reálný sběr metrik). Tyto nejsou „chybějící kód", ale nové produktové kategorie — patří do produktového roadmapu, ne do audit-fix dávky.

## ✅ FÁZE P — Datový model / tech-dluh (2026-07-19)
- **P195 stavové enumy — UPŘESNĚNO + reálná díra opravena**: `InvoiceStatus::unpaid` se NEPŘIDÁVAL (ten fantomový stav byl vyřešen přes `isOpen()`); `refunded` žije na `Chargeback`, ne na objednávce. ALE reálná díra: **nezaplacené proforma objednávky seděly v `Pending` navěky**. Přidán terminální `OrderStatus::Expired` (přidán protože se SKUTEČNĚ nastavuje) + `billing:expire-unpaid-orders` command (nikdy neexpiruje objednávku s paid_at nebo paid fakturou). `ExpireUnpaidOrdersTest`.
- **P196 @php collision — GUARD + reálná oprava**: `TechDebtGuardTest` replikuje Laravelí raw-block regex a najde jen SKUTEČNĚ nebezpečné pořadí (paren-před-blokem). Našel 1 reálný soubor (`bulk-customer-email-show`), kde by regex spolknul řádky 3–65 jako raw PHP — opraveno.
- **P195 guard**: test, že žádný kód nereferencuje neexistující enum case (třída chyby `=== 'unpaid'`).
- **P199 migrace verifikátor v CI — DOPLNĚNO**: `TechDebtGuardTest` spouští `tools/verify-migration-columns.php` (chytá `->after()` na sloupec, který v tu chvíli neexistuje — neviditelné pro SQLite testy, projeví se až na produkčním MySQL).

## ✅ DŘÍVE ODLOŽENÉ BODY DOKONČENY (2026-07-19)
- **D60 pro-rata — OPRAVEN CHYBNÝ VÝPOČET**: dosavadní `PlanChangeProrateController` počítal kredit z ceny **NOVÉHO** tarifu za dny, které už **uplynuly** — což není proporce ničeho; upgrade mohl vyjít levněji než downgrade. Nový `CalculatePlanChangeProrationAction`: `kredit = staráCena × zbývajícíDny / dnyVObdobí`, `poplatek = docháCena × zbývajícíDny / dnyVObdobí`, rozdíl se vyrovná. Délka období se bere z **fakturačního cyklu tarifu**, takže roční tarif se neproratuje jako měsíční. `applyChangePlan` navíc dosud **vůbec neúčtoval** — jen zalogoval záměr a poslal zákazníka objednat znovu; nyní upgrade vystaví doplatkovou fakturu a downgrade vrátí přeplatek na kredit.
- **D63 XLSX export — bez nové závislosti**: `XlsxWriter` postavený na vestavěném `ZipArchive` (XLSX = ZIP s XML). Kvůli jedné funkci nemá smysl brát `phpoffice/phpspreadsheet` a navěky ji udržovat a hlídat bezpečnostně. Rozsah je vědomě malý (jeden list, tučná hlavička, čísla + text) — až bude potřeba víc, teprve tehdy má smysl sáhnout po knihovně. Testy soubor **rozbalují a kontrolují jednotlivé party**, protože export, který se stáhne, ale Excel ho odmítne otevřít, je horší než žádný. Částky jdou ven jako čísla (dají se sečíst), identifikátory jako text (nepřijdou o vedoucí nuly).
- **F87 DNSSEC, F89 WHOIS kontakty a privacy, F92 glue records**: doplněny do `WedosWapiClient` podle zavedeného vzoru (mock payload v dry-runu, reálné volání až po otevření brány).
- **E80/F88 e-mailové schránky**: doplněny do `AapanelClient` (`listMailboxes`, `createMailbox`, `deleteMailbox`, `setMailboxQuota`) přes mail_sys plugin + sekce v detailu služby. Když plugin v panelu není, sekce to **řekne** místo aby ukázala prázdno.
- ⚠️ **Poznámka k reálnému provozu**: tvary odpovědí těchto nových endpointů jsou postavené podle dokumentace, ne ověřené proti živému API — v mocku jsou plně funkční a otestované, ale při produkčním zapojení je potřeba je ověřit proti reálné odpovědi.
- **Bezpečnostní opravy nalezené při tom**: `dryRunOr()` v obou klientech **logoval a vracel celý payload** — s novým heslem schránky nebo transfer auth kódem by je zapsal do logu i do `ProvisioningTask`. Doplněna redakce (`***redacted***`) na obou stranách.

## ✅ FÁZE H — STAV (2026-07-19), 2FA ZŮSTÁVÁ NEPOVINNÉ
- **H114 povinné 2FA pro adminy — VĚDOMĚ NEIMPLEMENTOVÁNO** dle zadání. 2FA se nabízí, nikdy nevynucuje. Bezpečnostní váhu nesou místo toho ostatní kontroly níže; kryto testem, který selže, kdyby někdo vynucení zapnul.
- **H121 úniky tajemství — nový guard test** `SecretsNeverLeakTest`: canary hodnoty se nesmí objevit v logu, v serializaci modelu, v uložených credentials ani v echu klientů; navíc **statická kontrola zdrojáků**, že se nikde nelogují credentialové hodnoty (odlišuje hodnotu od pouhé zmínky slova ve zprávě — první verze regexu měla false positive na `'credentials undecryptable'`). Ověřeno i to, že `AAPANEL_ALLOW_REAL_WRITES` je defaultně vypnuté.
- **H116 password policy**: komplexita (délka, velké písmeno, číslice, speciální znak) existovala; doplněna **kontrola prolomených hesel** přes HIBP (`AUTH_PASSWORD_BREACH_CHECK`, k-anonymita — odchází jen prvních 5 znaků SHA-1, nikdy heslo). Defaultně vypnuto, aby offline instalace ani testy nepadaly na nedostupném API. Důvod: „Password1!" projde všemi pravidly komplexity a přitom je v každém úniku.
- **H117 ukončení ostatních relací — OPRAVENA REÁLNÁ DÍRA**: `destroyOthers` mazal jen řádky v `sessions`, jenže zařízení s „remember me" cookie se přihlásí zpátky — ta se ověřuje proti `remember_token`, ne proti session řádku. Token se teď cykluje explicitně. (`Auth::logoutOtherDevices()` by tu nepomohl: spoléhá na middleware `AuthenticateSession`, který aplikace neregistruje.) Nově se navíc **vyžaduje heslo**, aby to držitel ukradené relace nemohl použít proti majiteli účtu.
- **H111 throttling**: login (5/min dle e-mail+IP) i 2FA challenge (5/min) byly už nastavené — doplněny testy. To je důležité právě proto, že 2FA je nepovinné.
- **H115 session security**: `http_only` zapnuto, `same_site=lax`, rotace session ID při loginu ověřena testem.
- **H113 recovery kódy**: UI pro zobrazení i regeneraci už existuje (kritické, když je 2FA dobrovolné — jinak lockout).
- **ZBÝVÁ**: H112 (audit oprávnění u `RecordManualPaymentAction`), H118 (UI správy IP allowlistu), H119 (náhrada inline `onsubmit="confirm()"` za delegovaný JS kvůli CSP enforce), H122–H124 (GDPR lhůty, evidence souhlasů, produkční hlavičky).

## ✅ 2FA NENÍ POVINNÉ + ADMIN SMÍ VŠE (2026-07-19)
- **2FA je striktně opt-in** a bylo tak i dosud: `security.require_admin_2fa` má default OFF a nikde se neseeduje na ON. Admin bez 2FA se dostane do celého admin panelu; vynucení se zapne až explicitně v Nastavení zabezpečení. Pokryto testy (default OFF, admin bez 2FA projde, zapnutí → redirect).
- **Admin smí jakoukoli akci a editaci**: doplněn globální `Gate::before` — dosud měla admin bypass jen `before()` v jednotlivých policies, takže model **bez** policy (nebo nově přidaný) admina tiše zablokoval. Nyní platí plošně a nezapomenutelně. Pro ne-adminy vrací `null`, takže normální řetěz policies rozhoduje dál.
- **Rozlišeno autorizační vs. datové pravidlo**: bypass se týká jen oprávnění. Stavová pravidla (nelze editovat odeslanou kampaň, refundovat nedokončenou platbu, měnit položky zaplacené objednávky) zůstávají v platnosti — chrání účetnictví, ne přístup. Kryto testem.

## ✅ FÁZE F — STAV (2026-07-19)
- **F83 DNS editor CRUD**: editor uměl záznamy jen **přidávat** — chybělo update i delete, takže překlep se bez podpory opravit nedal. Doplněno inline editování a mazání podle `row_id` registrátora; čtení převedeno z `getDomainInfo` na `getDnsRecords`, protože jen ten vrací row ID. Mock záznamy mají row ID také, aby UI fungovalo i v mocku.
- **F86 přenos domény — DVĚ CHYBY**: (1) **`auth_code` (EPP kód) se ukládal v plaintextu**, přestože na `DomainRegistration` je šifrovaný — je to credential, kterým lze doménu odvést jinam. Nyní `encrypted` + `$hidden`. (2) Schválení přenosu (`processing`) **nikdy nevolalo registrátora** — jen přepsalo štítek v DB. Nyní spustí `transferDomain()`; odmítnutí registrátorem označí `failed` s důvodem, ale auth kód se do poznámky ani logu nikdy nedostane. Když registrátor není nakonfigurován, **stav admina se nepřepisuje** (může přenos řešit ručně) — jen se přidá poznámka.
- **Navíc nalezeno**: admin přehled přenosů renderoval `$transferRequest->domain`, ale sloupec je `domain_name` → **sloupec s doménou byl prázdný**. Opraveno.
- **F90 hromadná změna nameserverů** napříč doménami (scoped na vlastní domény — ID z formuláře se nedůvěřuje; kryto testem, že cizí doména zůstane nedotčená).
- **F91 DNS šablony** jedním klikem (Google Workspace, Microsoft 365, OnHost mail).
- **ODLOŽENO**: **F87 DNSSEC** (WEDOS klient nemá DNSSEC/DS endpointy — vyžaduje rozšíření klienta), **F88 e-mailový hosting jako produkt** a **F89/F92 WHOIS kontakty a glue records** (rovněž chybějící endpointy). **F85 WEDOS produkční cutover** blokován externě IP whitelistem.

## ✅ FÁZE G — STAV (2026-07-19)
- **G95 editace položek objednávky**: admin mohl měnit jen stav/poznámku/doménu. Nyní přidání, úprava (množství i cena) a odebrání položky, vždy s přepočtem hlavičky objednávky (`RecalculateOrderTotalsAction`) — bez toho by fakturovaná částka utekla od řádků. Zábrany: zaplacenou objednávku nelze měnit (od toho je dobropis), poslední položku nelze smazat, položku se zřízenou službou nelze smazat.
- **G96 role a oprávnění z UI**: stránka byla jen read-only výpis. Doplněno vytváření/mazání rolí, správa oprávnění role, přidělení/odebrání role uživateli a vytváření oprávnění. Pojistky: roli `admin` nelze smazat ani jí odebrat všechna oprávnění a admin si nemůže odebrat vlastní admin roli — jinak by se ze systému uzamkli všichni.
- **G97 obnova účtu zákazníka adminem**: odeslání odkazu pro reset hesla, zrušení ztraceného 2FA (**s povinným důvodem**, silně auditováno) a odhlášení všech relací. **Záměrně NE „nastav zákazníkovi heslo"** — admin by heslo znal, psal a přeposílal; reset odkaz drží credential jen mezi zákazníkem a systémem.
- **G98 impersonace časově omezena** na 30 minut (`StopExpiredImpersonation` middleware) — dosud běžela, dokud si admin nevzpomněl kliknout na stop. Po vypršení se admin vrátí na vlastní účet, neodhlásí se. Audit a zákaz impersonace jiného admina existovaly už dřív.
- **G99 hromadné akce nad zákazníky** (aktivace, deaktivace, přidání/odebrání štítku). Admina hromadná deaktivace nikdy nevypne. Při té příležitosti doplněna **chybějící relace `Customer::segmentTags()`** — pivot tabulka existovala od zavedení štítků, ale ani jedna strana relaci nedeklarovala, takže štítky šlo přiřadit jen raw dotazem.
- **Ověřeno jako už hotové (audit byl neaktuální)**: **G104** filtry audit logu (log, fulltext, od/do, causer, subject_type) a **G101** editovatelné nastavení systému.
- **ODLOŽENO**: G100 rozšíření globálního hledání, G102 editovatelné e-mailové šablony, G103 sjednocení KPI grafů, G105 feature flags, G106–G110 drobná rozšíření.

## ✅ FÁZE E — STAV (2026-07-19)
- **E68 doménové položky nikdy nedosáhly Active — OPRAVENY DVĚ CHYBY**: (1) WEDOS produkt nebyl v seznamu `$provisionable` a `RegisterDomainJob` se pouštěl jen při `register_domain === true`, takže samostatná objednávka domény nedispatchovala **vůbec nic**; (2) `RegisterDomainJob` po úspěchu nikdy nenastavil službu na Active, takže `SyncOrderCompletionAction` čekal navěky. Každá z nich sama o sobě nechala objednávku trvale v Processing. Nyní: u WEDOS driveru je registrace *samotnou službou* (běží bez ohledu na add-on flag), po úspěchu se služba aktivuje a spustí se sdílené `ServiceActivationHooks` (monitor, záloha, notifikace, dokončení objednávky). Bez domény zůstává Pending — nesmí „zplanět" do Active.
- **E69 výběr serveru dle kapacity**: nový `ServerSelector` — místo `orderByDesc('is_default')` (což plnilo default server až do pádu) vybírá server s nejvíce volnými sloty. Zátěž se počítá **živě z tabulky services**, ne z `Server::current_services` — ten se při provisioningu inkrementuje, ale při ukončení nikdy nedekrementuje, takže by časem vypadal každý server plný. Ukončené a failed služby se do kapacity nepočítají. Když je plno všude, vrátí nejméně vytížený a zaloguje varování — odmítnout zaplacenou objednávku by bylo horší.
- **E70 orphan recovery** doplněna pro Pterodactyl (`findServerIdByName`): pokud předchozí pokus server vytvořil a spadl před uložením `external_id`, adoptuje se místo vytvoření duplicitního. aaPanel to uměl, ostatní drivery duplikáty leakovaly.
- **E71 přehled fronty v adminu**: nová stránka „Fronta úloh" — čekající/neúspěšné/zaseknuté (rezervované >15 min = spadlý worker), rozpad podle front, retry i mazání jednotlivě nebo hromadně, vše auditované. Čte přímo DB frontu, takže funguje i bez Redisu/Horizonu.
- **E73 suspend s důvodem**: už existoval (povinné pole, min. 3 znaky) — doplněny testy.
- **E76 schvalování obnovy ze zálohy**: doplněn celý admin tok (fronta požadavků s filtrem, schválit/zamítnout, zamítnutí **vyžaduje důvod**, který zákazník uvidí, dvojí rozhodnutí odmítnuto). Dřív zákazník požádat mohl, ale admin neměl jak odpovědět.
- **E81 reinstalace game serveru**: Pterodactyl API umí jen reset na egg — „zachovat data" tedy znamená **nejdřív záloha, pak reinstalace**. Dvě jasně oddělená tlačítka; při volbě se zálohou a chybějící zálohovací politice akce **odmítne** místo tichého smazání dat.
- **E82 živý stav**: admin chybu hlásil, ale **zákaznický panel při chybě vyprázdnil obsah** — červený badge bez vysvětlení, k nerozeznání od „nic tu není". Nyní se vypíše důvod. Stejný princip drží nový `WebhostingConfigService`: každá sekce selhává nezávisle a řekne proč.
- **E72 Horizon**: audit tvrdil „nenasazen", ve skutečnosti **je nainstalovaný** (`laravel/horizon` ve vendoru). Vyžaduje ale Redis; nová stránka „Fronta úloh" čte DB frontu a funguje vždy, takže obě se doplňují.
- **E67 preview fronta**: systémové riziko už zmírněno — `services:provision-pending` běží **synchronně každou minutu**, takže zřízení nezávisí na workeru vůbec; nová stránka fronty navíc spadlý worker zviditelní. Env-resoluce SQLite v preview zůstává jako známý quirk (v preview používat `dispatchSync`).
- **ODLOŽENO**: **E74** UI migrace služby mezi servery, **E75** reálné monitoring napojení (Uptime Kuma/StatusCake — vyžaduje externí službu a credentials), **E80 e-mailové schránky** — aaPanel klient v tomto projektu nemá mail API (databáze, FTP, cron, PHP, SSL doplněny; schránky by vyžadovaly rozšíření klienta o mail endpointy).

## ✅ DETAIL SLUŽBY — KOMPLETNÍ KONFIGURACE JAKO V AAPANELU (2026-07-19)
Nový `WebhostingConfigService` (čtení) + `WebhostingConfigActionJob` (zápisy) + karta „Konfigurace webhostingu (aaPanel)" v detailu služby:
- **Databáze** (výpis, vytvoření, smazání), **FTP účty** (výpis, vytvoření, smazání), **cron úlohy** (výpis, přidání, smazání), **PHP verze** (výběr **jen z verzí, které panel skutečně má** — `GetPHPVersion`, pseudo-build `00` = Static se odfiltruje, audit E78), **SSL** (stav + vydání/obnova), disková kvóta.
- **Čtení nikdy nepotřebuje zápisová práva**: jde přes `getSiteOverview()`/`assertReadyForReadCall`, tj. mimo bránu `AAPANEL_ALLOW_REAL_WRITES`. (`getSiteInfo`/`listSites` tou bránou naopak chráněné jsou — pro zobrazení konfigurace se nesmí používat.)
- **Zápisy zůstávají za bránou**: každá akce jde přes `dryRunOr()`, je sledovaná jako `ProvisioningTask` a při zavřené bráně se jen simuluje (`dry_run: true`). Nic tu bránu neotevírá.
- **Hesla se nikdy neukládají** — ani do payloadu úlohy, ani do audit logu (kryto testem).
- Názvy databází/uživatelů validované regexem (žádné shell-nebezpečné znaky).

## ✅ AAPANEL — KONTROLA ZŘÍZENÍ A SYNCHRONIZACE (2026-07-19)
Nově `ServiceRemoteSyncService` + `ServiceSyncState` + `services.last_synced_at/sync_state/sync_message`:
- Odpovídá na otázku, kterou fakturace neumí: *„zákazník zaplatil a služba je Active — existuje ale web opravdu v aaPanelu?"* Drift se ukládá jako `MissingRemote` místo aby služba vypadala zdravě.
- **Striktně read-only**: aaPanel čte přes `getSiteOverview()`, což **není** za bránou `AAPANEL_ALLOW_REAL_WRITES` — ověřování nikdy nepotřebuje zápisová práva. Jediný zápis je adopce nalezeného `external_id`, a jen pokud bylo prázdné.
- **V mock/dry-run režimu vrací `Unsupported`, nikdy `InSync`** — falešné ujištění je horší než žádná odpověď.
- Verdikty: `MissingRemote` (Active/Suspended, ale v panelu nic), `Adopted` (nalezeno a spárováno), `StatusMismatch` (lokálně Active, v panelu zastaveno), `InSync`, `Unsupported`, `Error`.
- `services:sync-remote` (hodinově, `withoutOverlapping`) + notifikace adminům **jen při nově vzniklém driftu** (ne každou hodinu dokola).
- V detailu služby: karta „Synchronizace s panelem" se stavem, časem ověření, tlačítkem „Ověřit v panelu" a — u `MissingRemote` — tlačítkem „Zřídit službu nyní" (idempotentní; při zavřené zápisové bráně driver odmítne a nahlásí to).

## Hotovo v této relaci (nezapočítáno do 200)
Databázový chat (`support_chat_*`) s AI botem **i eskalací na živého operátora** (widget ↔ DB ↔ admin inbox, polling ověřen E2E); admin **vytváření služby zákazníkovi** bez objednávky; admin **editace zákazníka/uživatele** + zapnutí/vypnutí účtu; zvýrazněné akce **dobití kreditu** a **akceptace platby**; oprava rozbíjejících `d-none` v Cuba pohledech (nahrazeno Tailwind `hidden`). +12 nových testů.

---

## A. Konformita s Cuba Tailwind šablonou (1:1) — největší dluh

Ověřeno proti `public/panel/css/style.css`: tyto Bootstrap třídy **v CSS neexistují** (0 definic), přesto se používají napříč stovkami pohledů. Nutná migrace na Tailwind ekvivalenty, které Cuba skutečně dodává (`flex`, `items-center`, `justify-between`, `grid grid-cols-12`, `col-span-*`, `hidden`).

1. 🟠 **`d-flex` (0 def / ~221 souborů)** → migrovat na `flex`. Většina jsou zarovnávací kontejnery.
2. 🟠 **`align-items-center` (0 / ~197)** → `items-center`.
3. 🟠 **`justify-content-between|center|end|start` (0 / ~133)** → `justify-between|center|end|start`.
4. 🟠 **`col-md-*` (0 / ~142)** a **`col-lg-*` (0 / ~86)** — Bootstrap grid; v mixu s `row` sloupce nestackují. Migrovat na `grid grid-cols-12` + `col-span-*` + `sm:/md:/lg:` breakpointy.
5. 🟡 **`row` (Bootstrap, 2 částečné def / ~246)** → `grid grid-cols-12`.
6. 🟡 **`text-end` (7 / ~94)** a **`text-center` (4 / ~245)** — jen částečné; sjednotit na Tailwind `text-right`/`text-center`.
7. 🟡 **`ms-auto`/`me-*`/`ps-*`/`pe-*` (0–4 def)** → Tailwind `ms-auto`/`me-*`/`ps-*`/`pe-*` (Cuba subset) nebo `ml-auto` atd.
8. 🟡 **`float-end` (2 / 5)** → `float-right` / flex.
9. 🟡 **`rounded-3`/`rounded-pill` (Bootstrap radius)** → Cuba `rounded-*`.
10. 🟠 **Checkout wizard `panel/checkout.blade.php`** má vlastní inline `<style>` s `#54ba4a`, `d-flex`, `d-block`, `text-end` — přestavět 1:1 na Cuba karty + kroky (nejméně konformní stránka).
11. 🟡 **29 blade souborů s natvrdo `#rrggbb`** — rozbíjí dark mode; nahradit `rgba(var(--…),1)`.
12. 🟡 **1159 inline `style="…"`** — přesunout do `onhost.css` (většina per-ikona sizing).
13. 🟡 **`data-bs-toggle="tooltip"` (Bootstrap tooltipy)** se v Tailwind buildu neinicializují — nahradit Cuba tooltipem nebo nativním `title`.
14. 🟡 **`data-bs-toggle="modal"` / `bootstrap.Modal`** — křehké (fade race, viz porovnání tarifů). Sjednotit self-managed Cuba modal jako komponentu `x-panel.modal`.
15. 🟡 **`table-responsive` (1 def)** — mnoho tabulek nemá `overflow-x-auto` wrapper → mobil přetéká.
16. 🟢 **Nevyužitá Cuba `datatable-advance`** (client-side řazení/hledání/stránkování) na velkých seznamech.
17. 🟢 **Nevyužitý Cuba `chart-apex`** — sjednotit grafy (dashboard míchá knihovny).
18. 🟢 **Nevyužitý Cuba `calendar`** (fullcalendar) pro plánovaná okna/údržbu.
19. 🟢 **Nevyužitý Cuba `dropzone`/`file-manager`** pro přílohy ticketů a nahrávání.
20. 🟢 **Cuba `small-widget`/`social-widget`/`card-hover`** varianty — dashboard je plošší než předloha `dashboard-08`.
21. 🟢 **Cuba `loader-wrapper`/skeleton** u AJAX sekcí (živý stav, notifikace, chat) místo prázdného bliknutí.
22. 🟡 **Breadcrumb** se skládá ručně v každém pohledu → extrahovat `x-panel.breadcrumb`.
23. 🟢 **Empty-state** nejednotný → `x-panel.empty-state`.
24. 🟢 **Cuba `email-*` šablony** nevyužity pro transakční e-maily (branding).
25. 🟡 **`prefers-reduced-motion`** není respektováno (swing zvonku, hover transformace).
26. 🟢 **`@media print`** chybí u běžných stránek (tisk z prohlížeče).

## B. Chat & podpora (rozšíření nad rámec hotového)

27. 🟢 **Reálný AI provider (Claude)** není napojen na chat widget — zatím deterministický KB; po zadání klíče parafrázovat přes `ClaudeProvider`, zachovat routing/odkazy.
28. 🟢 **WebSocket push do chatu** (Reverb) místo pollingu à 5 s (zátěž, latence).
29. 🟢 **Notifikace operátorům** při eskalaci (zvonek/e-mail/Slack) — teď jen v inboxu.
30. 🟢 **Nepřečtené a SLA v chat inboxu** — počítadlo nepřečtených, doba čekání, přiřazení operátora.
31. 🟢 **Eskalace chatu → ticket** (jedním klikem převést přepis do `SupportTicket`).
32. 🟢 **Přílohy v chatu** (obrázky, logy).
33. 🟢 **Rate-limit `/panel/ai/*`** (throttle) proti spamu/DoS.
34. 🟢 **Historie uzavřených konverzací** na kartě zákazníka (kontext pro operátora).

## C. Objednávky / pokladna / košík

35. 🟠 **Dvě paralelní cesty objednávky** (`/pokladna` wizard vs `/kosik`) s odlišným UX a sběrem konfigurace — sjednotit na jednu.
36. 🟡 **Konfigurátor per-produkt** — VPS hostname/OS/disk/DC, game slots/RAM, e-mail počty schránek: nikde. Dynamické pole dle typu produktu.
37. 🟡 **Množství > 1 vytvoří 1 službu** (`ensureService` klíčuje `order_item_id`) — buď zakázat qty>1 u provisioning produktů, nebo N služeb.
38. 🟡 **DPH v košíku „dopočítá později“** — zobrazit koncovou cenu s DPH živě dle adresy.
39. 🟡 **Slevový kód v košíku** bez okamžité AJAX validace (na rozdíl od wizardu).
40. 🟡 **Registrace NOVÉ domény při objednávce** — UI chybí (jen zadání existující).
41. 🟡 **Recurring cena** není v objednávce vidět (jen první období).
42. 🟢 **Storno nezaplacené objednávky zákazníkem** — chybí (jen admin).
43. 🟢 **Náhled marže resellera** v košíku.
44. 🟡 **Comgate default bez brány** dead-enduje — defaultně kredit, když stačí; jinak jasné „přesměrování“.
45. 🟢 **E-mail „objednávka přijata“** (před platbou) — chybí; jen `InvoicePaid`.
46. 🟢 **Upsell/cross-sell** v košíku (doména k hostingu, zálohy, SSL).
47. 🟢 **Uložený košík / wishlist → košík** propojení.
48. 🟢 **Kupon na první objednávku / welcome kredit** — chybí.
49. 🟢 **Odhad doby zřízení** u produktu v pokladně.
50. 🟢 **Souhlas s VOP** checkbox v pokladně (právní) — ověřit přítomnost a audit.

## D. Fakturace / platby / účetnictví

51. 🟠 **Comgate sandbox E2E** nedokončeno (viz PRODUCTION-BLOCKERS.md).
52. 🟢 **Stripe** test mode + CLI webhook replay nedokončeno.
53. 🟢 **GoPay** — ověřit webhook a stav.
54. 🟡 **Webhook podpisy (HMAC)** u příchozích plateb — ověřit verifikaci u všech bran.
55. 🟡 **Dobropis (CreditNote)** — model je, admin tok vystavení k faktuře v UI chybí.
56. 🟡 **Refundace na platební metodu** (ne jen kredit) — chybí.
57. 🟡 **Částečná úhrada / splátky** (`InvoiceInstallment`) nejsou napojené na objednávkový tok.
58. 🟢 **Kredit: limity dobití + bonusová pásma** — chybí.
59. 🟢 **Expirace kreditu** (`expires_at`) — ověřit odečet expirovaného.
60. 🟡 **Pro-rata při změně tarifu** — ověřit výpočet a fakturaci rozdílu.
61. 🟢 **Opakované platby kartou (tokenizace)** pro auto-obnovu — ověřit.
62. 🟢 **Reverse-charge / OSS DPH** pro EU B2C — ověřit sazby a výkazy (DAC7 je).
63. 🟢 **XLSX export** faktur (teď jen CSV) + plánované reporty (`ReportSchedule` bez UI).
64. 🟢 **Automatické upomínky (dunning)** — ověřit reálné odesílání a suspend po X krocích.
65. 🟢 **Zaokrouhlení a haléřové vyrovnání** na faktuře — ověřit dle legislativy.
66. 🟡 **Číselné řady faktur** per rok/typ — ověřit kontinuitu a zámek proti duplicitě.

## E. Provisioning / služby

67. 🔴 **Preview fronta `queue:work --env=preview` nezpracuje SQLite joby** (padá na výchozí MySQL) — v preview nutné `dispatchSync`. Opravit env resoluci nebo zdokumentovat runner.
68. 🟠 **Doménové-only / driver-less položky se nikdy nedostanou do `Active`** — `SyncOrderCompletionAction` čeká na `ServiceStatus::Active`, který RegisterDomainJob nenastaví. Doplnit dokončení i pro tyto položky.
69. 🟡 **Výběr serveru nezohledňuje kapacitu** (`orderByDesc('is_default')`) → přeplnění defaultního serveru. Vážit dle volné kapacity.
70. 🟡 **Orphan recovery** jen u aaPanel AddSite; Proxmox/Pterodactyl bez obdobné idempotence.
71. 🟡 **Přehled fronty / failed jobs** v adminu — chybí stránka (retry, čekající, mrtvé).
72. 🟢 **Horizon** pro monitoring front — nenasazen.
73. 🟡 **Ruční suspend/unsuspend s důvodem** z detailu služby — ověřit UI (batch je).
74. 🟢 **Migrace služby mezi servery** (`ServiceMigrationBatch`) bez UI toku.
75. 🟢 **Reálné monitoring napojení** (Uptime Kuma/StatusCake) — teď mock.
76. 🟡 **Obnova ze zálohy** (`SnapshotRestoreRequest`) — admin schvalovací tok nedokončen.
77. 🟢 **SSL stav na detailu služby** (vydáno/platnost/obnova) — chybí UI.
78. 🟢 **Dynamické PHP verze z panelu** (jako `resolvePhpVersion`) místo pevného seznamu.
79. 🟢 **Cron úlohy zákazníka** (webhosting) — žádné UI.
80. 🟢 **Správa DB uživatelů a e-mailových schránek** z panelu — chybí.
81. 🟢 **Reinstalace game serveru** — volba čistá/zachovat data.
82. 🟢 **Živý stav služby** — při chybě integrace tichý prázdný stav bez hlášky.

## F. Domény / DNS / e-mail hosting

83. 🟠 **DNS editor CRUD** — ověřit update/delete jednotlivých záznamů (dnes hlavně `store`).
84. 🟡 **Registrace nové domény pro zákazníka** (WEDOS WAPI je jen test/kontrola) — objednávkový tok chybí.
85. 🟡 **WEDOS produkční cutover** — blokováno IP whitelistem (přidat IP serveru do WAPI).
86. 🟡 **Transfer domény** (`DomainTransferRequest`) — plný UI tok (auth code, schválení) chybí.
87. 🟡 **DNSSEC** — žádná podpora (DS záznamy).
88. 🟢 **E-mailový hosting jako produkt** (mailboxy, aliasy, autoresponder) — celá kategorie chybí.
89. 🟢 **WHOIS privacy / kontakty domény** — bez UI.
90. 🟢 **Hromadná změna nameserverů** pro více domén.
91. 🟢 **DNS šablony** (Google Workspace, M365, jednoklik).
92. 🟢 **Glue records / vanity NS** pro resellery.
93. 🟢 **Expirační upozornění na doménu** X dní předem — ověřit.
94. 🟢 **Auto-renew domény** — ověřit fakturaci a prodloužení end-to-end.

## G. Administrace

95. 🟡 **Admin editace položek objednávky** (přidat/odebrat položku, cena, sleva) — dnes jen stav/poznámka/doména.
96. 🟡 **Admin editace uživatelských rolí/oprávnění z UI** (Spatie) — jen seeder.
97. 🟡 **Admin reset hesla / vynucení 2FA** zákazníkovi — chybí.
98. 🟡 **Impersonace** — ověřit audit, časový limit a zákaz citlivých akcí během ní.
99. 🟡 **Hromadné akce nad zákazníky** (suspend, e-mail, tag) — po jednom.
100. 🟢 **Globální hledání** — rozšířit pokrytí (zákazník/objednávka/faktura/služba/doména/chat).
101. 🟡 **Nastavení systému z UI** (SMTP, brány, DPH) — ověřit editovatelnost místo `.env`.
102. 🟢 **Šablony e-mailů editovatelné adminem** — rozšířit na všechny transakční.
103. 🟢 **Admin dashboard KPI** — sjednotit grafy a přidat live metriky (MRR, churn, fronta).
104. 🟢 **Admin audit log** — filtr dle uživatele/typu/období v UI.
105. 🟢 **Feature flags** (zapínání funkcí bez deploye) — chybí.
106. 🟢 **Admin poznámky u služby/faktury** — sjednotit „interní zápisky“ napříč entitami.
107. 🟢 **Správa produktů: ceny per-měna a per-cyklus** z jednoho formuláře — ověřit.
108. 🟢 **Správa serverů: kapacita, health, přiřazené služby** — rozšířit detail.
109. 🟢 **Onboarding checklist** pro nové zákazníky (admin pohled) — ověřit.
110. 🟢 **Ad-hoc faktura** (`invoices.adhoc`) — ověřit plný tok a daňový doklad.

## H. Bezpečnost / autentizace / compliance

111. 🟠 **Rate-limiting loginu / 2FA challenge** — ověřit throttle (brute force).
112. 🟠 **Akceptace platby adminem** (`RecordManualPaymentAction`) — ověřit oprávnění + audit proti zneužití v ostrém provozu.
113. 🟡 **2FA recovery kódy** — UI zobrazení/regenerace ověřit (jinak lockout).
114. 🟡 **Povinné 2FA pro adminy** — dnes opt-in přes `settings`; zvážit vynucení.
115. 🟡 **Session security** — `secure`, `same_site`, rotace při loginu (config/session.php).
116. 🟡 **Password policy** — délka + kontrola kompromitovaného hesla (HIBP) přes `Password::defaults()`.
117. 🟢 **Odhlášení ze všech zařízení** — chybí.
118. 🟢 **IP allowlist adminu** — UI správy (jen DB/config).
119. 🟡 **CSP enforce audit** — nové inline `onsubmit="confirm()"` mohou být blokovány; nahradit delegovaným JS.
120. 🟢 **Audit citlivých akcí** (kredit, akceptace, mazání) — ověřit úplnost.
121. 🟠 **Comgate secret / api_key nikdy nelogovat** — přidat statický test, který selže při výskytu v logu/serializaci.
122. 🟢 **GDPR export/mazání** — ověřit úplnost a lhůty.
123. 🟢 **Souhlasy (cookies, VOP, marketing)** — evidence a verze.
124. 🟢 **Bezpečnostní hlavičky** (HSTS, X-Frame-Options, Referrer-Policy) — ověřit v produkci.

## I. Notifikace / komunikace

125. 🟡 **Předvolby oznámení** — vrátit uživateli možnost zapnout e-mail u nového-IP loginu (dnes jen in-app).
126. 🟢 **SMS/Slack kanál** pro kritické incidenty — chybí.
127. 🟢 **Souhrnný digest** — ověřit odesílání a opt-out.
128. 🟢 **Notifikační centrum** (`panel.notifications.index`) — stránkování, filtr přečtené.
129. 🟢 **Plánovaná údržba → kalendář + e-mail dotčeným** — částečně `MaintenanceWindow`.
130. 🟢 **In-app changelog „co je nového“** — chybí.
131. 🟢 **Reverb reconnect/fallback** — ověřit robustnost realtime zvonku i chatu.
132. 🟢 **Granularita per-událost** v předvolbách oznámení — rozšířit.

## J. API / integrace

133. 🟡 **OpenAPI/Swagger aktuálnost** — ověřit pokrytí všech write endpointů (vč. chatu, admin ops).
134. 🟡 **Rate limiting API per-token** + zobrazení limitů zákazníkovi.
135. 🟢 **Verzování API** (`/api/v1`) + deprecation politika.
136. 🟢 **Idempotency-Key** hlavička u write operací — chybí.
137. 🟢 **Webhooky pro zákazníka** — retry policy + podpisové tajemství ověřit.
138. 🟢 **Developer portal** (OAuth app) — ověřit plný tok vytvoření/rotace.
139. 🟢 **Pterodactyl/Proxmox reálné E2E** — jen mock; potvrdit s credentials mimo produkci.
140. 🟢 **aaPanel produkční deploy** — dokončit (viz aapanel.md).
141. 🟢 **Sentry/error tracking** — ověřit napojení `report()`.
142. 🟢 **Health endpoint `/up`** + externí monitoring.

## K. Reseller / partner / affiliate

143. 🟢 **White-label** (doména, logo, barvy) — ověřit plnou konfiguraci z UI.
144. 🟢 **Sub-zákazníci resellera** — limity a fakturace mezi resellerem a koncovým zákazníkem.
145. 🟢 **Partner výplaty** — tok schválení a výplaty end-to-end.
146. 🟢 **Cenové skupiny / individuální ceny** pro zákazníka — chybí.
147. 🟢 **Affiliate tracking** (cookie, atribuce, konverze) — ověřit.
148. 🟢 **Bannery/odkazy partnera** — ověřit generování a statistiky.
149. 🟢 **Reseller dashboard KPI** — rozšířit (MRR sub-zákazníků, marže).
150. 🟢 **Vícestupňový partner program** (tiers) — chybí.

## L. Výkon / škálovatelnost / infrastruktura

151. 🟠 **Route/view cache po `git pull`** — bez `route:cache`+`view:cache` se nové routy/šablony neprojeví (opakovaný zdroj „nefunguje na produkci“). Přidat kontrolu do CI.
152. 🟡 **Hlavička volá `CreditLedger::getBalance` + cart composer na KAŽDÉ stránce** — cache per-request.
153. 🟡 **N+1 dotazy** v seznamech (objednávky/služby/faktury) — projít eager loading.
154. 🟡 **DB indexy** — ověřit na `services.customer_id`, `invoices.customer_id/status`, `orders.status`, `order_items.order_id`, `support_chat_*`.
155. 🟢 **Purge/tree-shake Tailwind** — `style.css` má 200k+ řádků; zmenšit na použité třídy.
156. 🟢 **Priority fronty** — oddělit provisioning od e-mailů/notifikací + supervisor per fronta.
157. 🟢 **Cache/Session driver v produkci** (redis) — ověřit config.
158. 🟢 **Zálohy DB** (spatie/laravel-backup) — plán + offsite úložiště.
159. 🟢 **CDN + cache statiky** — ověřit hlavičky a verzování assetů.
160. 🟢 **Load test** kritických endpointů (login, objednávka, webhook, chat poll).

## M. Testy / QA / observabilita

161. 🟡 **E2E test provisioning fronty v preview** (kvůli E67) — řetězec objednávka→platba→Active přes `dispatchSync`.
162. 🟢 **Browser/Dusk testy** JS-kritických toků (košík, porovnání, chat, wizard).
163. 🟢 **Coverage report + mutační testování** v CI.
164. 🟢 **Accessibility testy (axe)** — chybí.
165. 🟢 **Statická kontrola „neexistujících Cuba tříd“** (grep guard v CI) — zabránit regresi konformity.
166. 🟢 **Strukturované logování** (customer_id, service_id kontext).
167. 🟢 **Smoke test všech GET routes** — rozšířit o nové (chat, create-service).
168. 🟢 **Contract testy integrací** (aaPanel/WEDOS/Comgate) proti fixture.

## N. UX / přístupnost / i18n / mobil

169. 🟡 **`confirm()` inline** v nových formulářích — CSP riziko + nepřístupné; nahradit Cuba modálem.
170. 🟡 **Fokus management v modálech** — po otevření přesunout fokus, trap, Esc.
171. 🟢 **ARIA/aria-live** u AJAX sekcí — dokončit (chat má, ostatní ne).
172. 🟡 **Kompletní i18n** — mnoho řetězců natvrdo česky místo `__()`; extrahovat do `lang/`.
173. 🟢 **Anglická lokalizace** — ověřit pokrytí `en`.
174. 🟢 **Mobilní navigace panelu** (< 768px) — sidebar toggle, tabulky→karty.
175. 🟢 **Klávesové zkratky** („/“ hledání, „?“ nápověda).
176. 🟢 **Dark mode per-uživatel** do DB (dnes jen `data-theme`, resetuje se).
177. 🟢 **Kontrast a WCAG AA** — projít barvy badge/light variant.
178. 🟢 **Skeleton/loading stavy** u pomalých sekcí.
179. 🟢 **Chybové stránky (403/404/419/500)** — sladit s Cuba `error-*`.
180. 🟢 **Onboarding tour** pro nové zákazníky.

## O. Produktové mezery a rozšíření

181. 🟢 **E-mailový hosting** (viz F88) — velká chybějící kategorie.
182. 🟢 **SSL jako samostatný produkt** (placené certifikáty, wildcard).
183. 🟢 **Object storage / S3** produkt.
184. 🟢 **Managed WordPress** jako samostatný tarif s jednoklik migrací.
185. 🟢 **Marketplace aplikací** (`Marketplace*` existuje) — rozšířit katalog a instalace.
186. 🟢 **Affiliate/věrnostní kredit** za doporučení.
187. 🟢 **Statusová stránka** (status.onhost.cz) napojená na monitoring.
188. 🟢 **Znalostní báze (KB)** — ověřit vyhledávání, hlasování, kategorie.
189. 🟢 **Live status služeb na dashboardu** — agregovaný přehled zdraví.
190. 🟢 **Objednávka add-onů k běžící službě** (`ServiceAddon`) — ověřit plný tok.
191. 🟢 **Migrace z konkurence** (import cPanel/Plesk) — nástroj chybí.
192. 🟢 **Automatické doporučení tarifu** (AI recommendPlan) — napojit na objednávku.
193. 🟢 **Vícejazyčné faktury a e-maily** dle `preferred_locale`.
194. 🟢 **Zákaznický „usage“ dashboard** (CPU/RAM/disk/traffic grafy) — rozšířit.

## P. Datový model / architektura / tech-dluh

195. 🟡 **Sjednotit stavové enumy** — `OrderStatus` nemá `expired`/`refunded`; `InvoiceStatus` nemá `unpaid` (zdroj chyby dříve). Zrevidovat a doplnit.
196. 🟡 **`@php(...)` paren forma + `@php…@endphp` blok** v jednom pohledu se navzájem ruší (Blade regex) — projít a sjednotit na blokovou formu.
197. 🟢 **Konzistence UUID vs id v URL** — Order/Service/Invoice bindují uuid, Domain id; sjednotit + zdokumentovat.
198. 🟢 **Soft-delete politika** — ověřit u zákazníků/služeb (GDPR vs audit).
199. 🟢 **Migrace: verifikátor sloupců** (`tools/verify-migration-columns.php`) spustit v CI před nasazením.
200. 🟢 **Dokumentace API + doménových služeb** (`docs/`) — doplnit chat, admin ops, order lifecycle.

---

## Priority (doporučené pořadí)

| # | Priorita | Body |
|---|---|---|
| 1 | 🔴 kritické | E67 (preview fronta), E68 (doménové položky→Active) |
| 2 | 🟠 vysoké | A1–A4 konformita, A10 checkout, C35 dvě cesty objednávky, F83 DNS CRUD, H111/H121 bezpečnost, L151 cache |
| 3 | 🟡 střední | konfigurátor produktů, DPH v košíku, dobropisy, indexy, i18n, CSP modály |
| 4 | 🟢 rozšíření | e-mail hosting, DNSSEC, Horizon, status stránka, marketplace, Dusk |

**Klíčové doporučení:** systémovou konformitu (A) řešit migrací na Tailwind třídy, které Cuba dodává (ne kompat vrstvou), s CI grepem proti neexistujícím Bootstrap třídám (M165), aby se dluh dále nekumuloval.
