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
