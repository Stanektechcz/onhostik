# OnHost — Systémová analýza a Cuba audit (150 položek)

**Datum:** 2026-07-18
**Rozsah:** celý zákaznický panel (`/panel`), administrace (`/admin`), doménová logika, integrace, testy, infrastruktura.
**Metodika:** statická analýza kódu (PHPStan L6, grep napříč `resources/views` a `app`), E2E průchody v prohlížeči, porovnání s referenční Cuba Tailwind šablonou (`laravel-admin-panel/template/`, 206 stran) a s běžným standardem hostingové platformy.
**Legenda závažnosti:** 🔴 kritické · 🟠 vysoké · 🟡 střední · 🟢 nízké / rozšíření.

> Poznámka: Položky níže jsou **otevřené**. V této relaci již byly opraveny mj.: životní cyklus objednávky → `Active`, tlačítko „Zaplatit fakturu“ (`isOpen()`), admin akceptace/editace objednávek, doména v košíku, Cuba notifikační dropdown, porovnání tarifů, přihlašovací e-mail, a kompletní přestavba AI chatbota. Ty se zde neopakují.

---

## A. Konformita s Cuba šablonou (vizuál / CSS) — nejzávažnější systémový nález

Tato Cuba je **Tailwind port**, ne Bootstrap. Řada Bootstrap utility tříd v `style.css` **neexistuje**, přesto se používá napříč stovkami šablon. Ověřeno grepem proti `public/panel/css/style.css`.

1. 🟠 **`d-flex` neexistuje** (0 definic v style.css), použito ve ~221 blade souborech. Kde se spoléhá na flex kontejner, layout spadne na `display:block`. Nahradit `flex` (Tailwind) nebo přidat kompat vrstvu do `onhost.css`.
2. 🔴 **`d-none` neexistuje** (0 definic), použito v 6 souborech. Prokázáno na AI chatu: prvek se **nikdy neskryje**. Projít všech 6 výskytů a nahradit `hidden`.
3. 🟠 **`align-items-center` / `justify-content-*` neexistují** (0 definic), použito ve ~197 / ~133 souborech. Flex zarovnání nefunguje → rozházené řádky. Nahradit `items-center` / `justify-between`.
4. 🟠 **Bootstrap grid `col-md-*` / `col-lg-*` neexistuje** (0 definic), použito ve ~142 / ~86 souborech. Mřížka mixuje Bootstrap (`row`>`col-md-6`) s Cuba (`grid grid-cols-12`>`col-span-*`). V mixovaných pohledech sloupce nestackují správně. Sjednotit na Cuba grid.
5. 🟡 **`text-end` / `text-center` jen částečně** (7 / 4 definice) — nekonzistentní; některé instance fungují, jiné ne. Sjednotit na Tailwind `text-right` / `text-center`.
6. 🟡 **`ms-auto` / `me-2` / `ps-0` / `pe-2` z většiny chybí** (0–4 definice), použito v desítkách souborů → chybné odsazení. Nahradit `ms-auto`→`ms-auto`(Cuba má), ostatní na `me-*`/`ps-*` Tailwind ekvivalenty nebo utility.
7. 🟡 **1159 inline `style="…"` v šablonách.** Většina je neškodné per-ikona sizing, ale brání jednotné údržbě a části obsahují barvy. Postupně přesunout do `onhost.css`.
8. 🟠 **29 blade souborů s natvrdo zadaným hex `#rrggbb`.** Rozbíjí dark mode (Cuba přebarvuje přes proměnné). Nahradit `rgba(var(--…),1)`.
9. 🟡 **Checkout wizard (`panel/checkout.blade.php`) má vlastní inline `<style>`** s `#54ba4a`, `d-flex`, `d-block`, `text-end`, `rounded-3` — nejméně konformní stránka. Přestavět na Cuba karty + kroky.
10. 🟡 **`rounded-3` / `rounded-pill` (Bootstrap radius) místo Cuba `rounded-*`.** Nekonzistentní zaoblení. Ověřit a sjednotit.
11. 🟢 **Nevyužité Cuba komponenty:** `datatable-advance` (řazení/stránkování/hledání client-side), `chart-apex` (hezčí grafy než aktuální), `calendar` (fullcalendar), `dropzone` (upload), `file-manager`. Zvážit nasazení tam, kde to dává smysl.
12. 🟡 **Cuba `.card` varianty** (`.card-hover`, `.card-absolute`, `.social-widget`) nejsou využity — dashboard vypadá plošší než Cuba předloha (`dashboard-08.html`).
13. 🟢 **Cuba loader / skeleton** (`.loader-wrapper`) není použit u AJAX sekcí (živý stav služeb, notifikace) — bliká prázdný stav.
14. 🟡 **Breadcrumb** se skládá ručně v každém pohledu přes `$breadcrumbItems` — Cuba má komponentu `breadcrumb.html`; extrahovat do jednoho `x-panel.breadcrumb`.
15. 🟢 **Cuba dark-mode přepínač** (`.mode`) v hlavičce je, ale stav se neukládá per-uživatel do DB (jen `data-theme`); po odhlášení se resetuje.
16. 🟡 **Tooltipy `data-bs-toggle="tooltip"`** (Bootstrap) — v Tailwind buildu se neinicializují, pokud není bootstrap tooltip JS aktivní. Ověřit, jinak nahradit title/Cuba tooltipem.
17. 🟢 **Ikony: mix `data-feather`, `fa-solid`, sprite `#icon`.** Tři ikonové systémy → nekonzistence a váha. Sjednotit primárně na feather + sprite.
18. 🟡 **Responsivita tabulek:** části používají `table-responsive` (existuje 1×) ale mnoho tabulek nemá `overflow-x-auto` wrapper → na mobilu přetékají (objednávky, faktury, služby).
19. 🟢 **Prázdné stavy** nejednotné — někde Cuba empty-state, jinde holý text. Zavést `x-panel.empty-state`.
20. 🟢 **Cuba `email-*` šablony** (`email-header.html`, `email-order-success.html`) nejsou využity pro transakční e-maily — ty jedou přes defaultní Laravel MailMessage. Sladit branding.
21. 🟡 **Header (front web) vs panel** — vizuální jazyk se liší (Antler vs Cuba, dle zadání OK), ale přechod z webu do panelu je skokový; sladit alespoň barvu accentu.
22. 🟢 **Cuba `apexcharts`** je v assetech, ale dashboard revenue graf používá jiné řešení; sjednotit knihovnu grafů.
23. 🟡 **Chybí `prefers-reduced-motion`** — swing animace zvonku a hover transformace běží i uživatelům s omezením pohybu.
24. 🟢 **Print styly** pro faktury jsou v PDF, ale běžné stránky nemají `@media print` — tisk z prohlížeče vypadá špatně.
25. 🟡 **CSP a inline `<style nonce>`** v layoutu — funguje, ale rozšiřuje se; přesunout zbylé inline styly do `onhost.css` (jazykový dropdown, atd.).

## B. AI asistent (po přestavbě — rozšíření)

26. 🟢 **Historie konverzace se neukládá** — po refreshi je chat prázdný. Uložit do `localStorage` nebo per-relaci do DB (`AiConversation`/`AiChatMessage`).
27. 🟢 **Reálný AI provider (Claude) není napojen na widget** — chatbot je zatím deterministický KB. Po zadání API klíče volitelně parafrázovat odpovědi přes `ClaudeProvider` a zachovat KB routing/odkazy.
28. 🟢 **Chybí předání kontextu služby** — když je zákazník na detailu konkrétní služby, chat by měl nabídnout dotazy k té službě.
29. 🟢 **Eskalace do ticketu z chatu** — tlačítko „Vytvořit ticket z této konverzace“ (předvyplní předmět + přepis).
30. 🟢 **Rate-limiting a anti-spam** na `/panel/ai/chat` (throttle middleware) — zatím bez limitu.
31. 🟢 **Vícejazyčnost chatu** — KB je jen v češtině; při `locale=en` odpovídat anglicky.
32. 🟢 **Admin varianta chatbota** — pro adminy nabídnout jiné kategorie (incidenty, provisioning, tickety) místo zákaznických.
33. 🟢 **Telemetrie chatu** — logovat kategorie a „bez shody“ dotazy pro doplnění KB (částečně: aktivita `ai.chatbot_message`, ale bez textu dotazu z důvodu soukromí — přidat agregovanou statistiku kategorií do admin metrik).

## C. Objednávky / pokladna / fakturace

34. 🟠 **Dvě paralelní cesty objednávky** (jednopoložková `/pokladna` wizard vs košík `/kosik`) s odlišným UX a odlišným sběrem konfigurace. Sjednotit na jeden tok (košík jako jediný zdroj).
35. 🟡 **Jednopoložkový wizard nesbírá per-produkt konfiguraci** kromě jedné domény — VPS hostname, OS, velikost disku, lokalita DC nikde. Přidat dynamický konfigurátor dle typu produktu.
36. 🟡 **Množství > 1 u položky vytvoří jen 1 službu** (`ensureService` klíčuje `order_item_id`). Buď zakázat qty>1 u provisioning produktů, nebo vytvářet N služeb.
37. 🟡 **DPH se v souhrnu košíku jen „dopočítá později“** — zákazník nevidí koncovou cenu s DPH před odesláním. Dopočítat živě dle fakturační adresy.
38. 🟡 **Slevový kód v košíku** se validuje až v `checkout()` — bez okamžité zpětné vazby jako u wizardu (`applyDiscount`). Sjednotit AJAX validaci.
39. 🟡 **Registrace domény při objednávce** (`register_domain`) je v akci podporovaná, ale UI ji nenabízí — zákazník nemůže objednat NOVOU doménu spolu s hostingem, jen zadat existující.
40. 🟢 **Reseller markup** se aplikuje jen když má uživatel `access-reseller` — chybí náhled marže pro resellera v košíku.
41. 🟡 **Recurring/renewal cena** není v objednávce vidět — zákazník nevidí, kolik zaplatí při obnově (jen první období).
42. 🟢 **Proforma splatnost** je z configu (10 dní) — chybí UI pro admina to změnit per-objednávka.
43. 🟡 **Storno objednávky zákazníkem** neexistuje (jen admin). Přidat „Zrušit nezaplacenou objednávku“ do panelu.
44. 🟢 **Částečná úhrada / splátky** (`InvoiceInstallment`) existují v kódu, ale nejsou napojené na objednávkový tok.
45. 🟡 **Comgate je defaultní metoda, ale bez reálné brány** dead-enduje. Dokud není sandbox napojen, defaultně vybírat „Kredit“ pokud zůstatek stačí, jinak jasně označit „přesměrování na bránu“.
46. 🟢 **E-mail potvrzení objednávky** — ověřit, že se odesílá `InvoicePaidNotification` i „objednávka přijata“ (před platbou); dnes chybí „přijali jsme objednávku“.
47. 🟢 **Faktury: hromadné akce** (zaplatit vše, stáhnout ZIP) — ZIP existuje, hromadná platba ne.
48. 🟡 **Kreditní dobití** nemá minimální/maximální limit v UI ani bonusová pásma (běžné u hostingů).
49. 🟢 **Historie stavů objednávky** (audit timeline) je v activity logu, ale není vizualizovaná na detailu objednávky pro zákazníka.
50. 🟢 **Kurzové/měnové přepínání** — zákazník má `preferred_currency`, ale nemůže ho změnit v profilu z UI.

## D. Provisioning / služby

51. 🔴 **Preview fronta nezpracuje joby přes `queue:work --env=preview`** (spadne na výchozí MySQL). Reálné provisioning se v preview spustí jen `dispatchSync`. Opravit env resol0uci nebo zdokumentovat + přidat `queue:work` do preview runneru.
52. 🟠 **Doménové-only a driver-less produkty se nikdy nedostanou do `Active`** — `SyncOrderCompletionAction` čeká na `ServiceStatus::Active`, ale RegisterDomainJob nenastaví službu Active. Doplnit dokončení i pro doménové/manuální položky.
53. 🟡 **Retry provisioningu** má exponenciální back-off, ale chybí admin „mrtvá fronta“ přehled (failed jobs UI).
54. 🟡 **Orphan recovery** (aaPanel `findSiteIdByName`) je jen u AddSite; u Proxmox/Pterodactyl chybí obdobná idempotence.
55. 🟡 **Suspend/unsuspend pipeline** existuje, ale UI pro admina „pozastavit službu ručně“ s důvodem chybí na detailu.
56. 🟢 **Migrace služby mezi servery** (`ServiceMigrationBatch`) je v modelech, bez UI toku.
57. 🟡 **Kapacita serveru** (`current_services` vs limit) se inkrementuje, ale výběr serveru (`orderByDesc('is_default')`) nezohledňuje volnou kapacitu → přeplnění defaultního serveru.
58. 🟢 **Health/monitoring** vytváří monitor při aktivaci (mock), ale reálný uptime provider (Uptime Kuma/StatusCake) není napojen.
59. 🟡 **Zálohy**: plán se ukládá, ale „obnovit ze zálohy“ (`SnapshotRestoreRequest`) nemá dokončený admin schvalovací tok.
60. 🟢 **SSL stav** se nikde nezobrazuje na detailu služby (vydáno/platnost/obnova) — jen text v chatbotu.
61. 🟡 **PHP verze** — `WebhostingPhpVersionJob` má pevný seznam 7.4–8.3; chybí dynamické načtení dostupných verzí z panelu (jako u `resolvePhpVersion`).
62. 🟢 **Cron/plánované úlohy zákazníka** (webhosting cron) — žádné UI.
63. 🟢 **Databáze / e-mailové schránky** — webhosting nenabízí správu DB uživatelů ani mailboxů z panelu.
64. 🟡 **Živý stav služby** (`liveStatus`) whitelistuje pole, ale při chybě integrace vrací prázdno bez chybové hlášky uživateli.
65. 🟢 **Reinstalace game serveru** má destruktivní potvrzení, ale chybí volba čistá/zachovat data.

## E. Domény / DNS / e-mail

66. 🟠 **DNS editor** (`panel.domains.dns`) — ověřit plnou CRUD (přidat/upravit/smazat A/AAAA/CNAME/MX/TXT/SRV); dnes jen `store`, chybí update/delete jednotlivých záznamů.
67. 🟡 **DNSSEC** — žádná podpora (zapnutí/DS záznamy).
68. 🟡 **Registrace nové domény** — WEDOS WAPI je napojený jen na test/kontrolu dostupnosti; objednávkový tok registrace nové domény pro zákazníka chybí (viz C39).
69. 🟡 **Transfer domény** (`DomainTransferRequest`) je v modelech, bez plného UI toku (auth code, schválení).
70. 🟢 **WHOIS privacy / kontakty domény** — bez UI.
71. 🟢 **Hromadná změna nameserverů** pro více domén najednou chybí.
72. 🟡 **Expirační upozornění** na doménu — ověřit, že jde e-mail X dní před expirací (renewal pipeline řeší služby, domény samostatně).
73. 🟢 **E-mailový hosting jako produkt** (mailboxy, aliasy, autoresponder) — chybí celá kategorie, běžná u hostingů.
74. 🟢 **Glue records / vlastní nameservery** pro reseller domény — chybí.
75. 🟢 **DNS šablony** (rychlé nastavení Google Workspace, M365) — chybí.

## F. Administrace

76. 🟡 **Admin editace objednávky** (nově přidána) neumožňuje měnit položky/ceny — jen stav, poznámku a doménu. Doplnit přidání/odebrání položky a slevu.
77. 🟡 **Admin „přehled fronty jobů“** (failed_jobs, retry, čekající) chybí jako stránka.
78. 🟡 **Hromadné akce nad zákazníky** (suspend, e-mail, tag) — jen po jednom.
79. 🟢 **Impersonace zákazníka** — banner existuje; ověřit audit + časový limit + zákaz citlivých akcí během impersonace.
80. 🟡 **Admin nastavení produktů** — ceny per-měna a per-billing-cycle: ověřit, že jde editovat všechny cykly (měsíc/rok) z jednoho formuláře.
81. 🟢 **Refundace / dobropis** (`CreditNote`) — model je, admin tok pro vystavení dobropisu k faktuře chybí v UI.
82. 🟡 **Admin globální hledání** — ověřit pokrytí (zákazník/objednávka/faktura/služba/doména); dnes omezené.
83. 🟢 **Reporty/exporty** jsou CSV; chybí XLSX a plánované e-mailové reporty (`ReportSchedule` je v modelech, bez UI).
84. 🟢 **Audit log filtr** — dle uživatele/typu/období; ověřit, že admin UI to umí.
85. 🟡 **Nastavení systému** (SMTP, brány, DPH sazby) — ověřit, že vše je editovatelné z `admin.settings` a ne jen v `.env`.
86. 🟢 **Role & oprávnění UI** — Spatie role jsou v DB; admin editor rolí/oprávnění z UI chybí (jen seeder).
87. 🟢 **Šablony e-mailů editovatelné adminem** (`InvoiceTemplate` existuje) — rozšířit na všechny transakční e-maily.

## G. Bezpečnost / autentizace

88. 🟠 **Reálný režim akceptace platby** — `RecordManualPaymentAction` funguje ve všech režimech; ověřit oprávnění (jen admin s `access-admin`) a audit u ostrého nasazení proti zneužití.
89. 🟡 **2FA recovery kódy** — 2FA je, ale UI pro zobrazení/regeneraci záložních kódů ověřit; bez nich hrozí lockout.
90. 🟡 **Vynucení 2FA pro adminy** je opt-in přes `settings` — zvážit povinné pro `access-admin` v ostrém provozu.
91. 🟡 **Rate-limiting loginu** — ověřit throttle na `login` a `two-factor-challenge` (brute force).
92. 🟢 **Session security** — `session.secure`, `same_site`, rotace při přihlášení; ověřit v `config/session.php` pro produkci.
93. 🟡 **API tokeny** — abilities existují; chybí UI pro rotaci a „last used“ přehled per token pro zákazníka.
94. 🟢 **Password policy** (délka, kompromitované heslo přes HIBP) — ověřit `Password::defaults()`.
95. 🟢 **IP allowlist adminu** existuje; chybí UI pro správu seznamu (jen DB/config).
96. 🟡 **CSP enforce** — ověřit, že žádná stránka neporušuje CSP (inline handlery `onsubmit="confirm(...)"` v nových šablonách mohou být blokovány v enforce módu). Nahradit data-atributy + delegovaným JS.
97. 🟢 **Odhlášení ze všech zařízení** — chybí.
98. 🟢 **GDPR export/mazání** — toky existují; ověřit kompletnost a lhůty.
99. 🟢 **Webhook podpisy** (Comgate/GoPay) — ověřit verifikaci HMAC u příchozích webhooků.
100. 🟡 **Comgate secret / api_key** — nikdy nelogovat (constraint aktivní); přidat statický test, který selže, pokud se objeví v logu/serializaci.

## H. Notifikace / komunikace

101. 🟡 **Notifikace jen `database` u nového-IP loginu** (záměr) — chybí ale uživatelská volba znovu zapnout e-mail v předvolbách.
102. 🟢 **Real-time push** (Reverb/Echo) je napojen na zvonek; ověřit fallback polling a reconnect.
103. 🟢 **Souhrnný digest** (`WeeklyDigest`) — ověřit odesílání a opt-out.
104. 🟢 **SMS/Slack kanál** pro kritické incidenty — chybí.
105. 🟢 **Notifikační centrum stránka** (`panel.notifications.index`) — ověřit stránkování, filtr přečtené/nepřečtené.
106. 🟢 **Maintenance banner** — funguje; přidat plánované okno do kalendáře a e-mail dotčeným zákazníkům (částečně `MaintenanceWindow`).
107. 🟢 **Per-událost preference** (které typy chci e-mailem) — UI je; rozšířit granularitu.
108. 🟢 **In-app „co je nového“** changelog pro zákazníky — chybí.

## I. API / integrace

109. 🟡 **OpenAPI/Swagger** endpoint existuje; ověřit, že pokrývá všechny write endpointy (tickety, kredit, objednávky) a je aktuální.
110. 🟢 **Webhooky pro zákazníka** (`panel.webhooks`) — ověřit retry policy a podpisové tajemství.
111. 🟡 **Rate limiting API** per-token — ověřit a zobrazit limity zákazníkovi.
112. 🟢 **Verzování API** (`/api/v1`) — ověřit prefix a deprecation politiku.
113. 🟢 **WEDOS produkční cutover** — blokováno IP whitelistem (přidat IP serveru do WAPI).
114. 🟢 **Stripe** — test mode + CLI webhook replay nedokončeno.
115. 🟢 **Comgate sandbox** E2E nedokončeno (viz PRODUCTION-BLOCKERS.md).
116. 🟢 **Pterodactyl/Proxmox** — reálné E2E jen mock; potvrdit s reálnými credentials mimo produkci.
117. 🟢 **Idempotency-Key** hlavička u API write operací — chybí.
118. 🟢 **Sandbox/API klíče pro vývojáře zákazníka** (`DeveloperPortal`) — ověřit plný tok vytvoření OAuth app.

## J. Reseller / partner / billing ops

119. 🟢 **Reseller white-label** (`ResellerProfile`, doména, logo) — ověřit plnou konfiguraci z UI.
120. 🟢 **Sub-zákazníci resellera** — ověřit limity a fakturaci mezi resellerem a koncovým zákazníkem.
121. 🟢 **Partner výplaty** (affiliate commission) — tok schválení a výplaty ověřit end-to-end.
122. 🟢 **Cenové skupiny / individuální ceny** pro konkrétního zákazníka — chybí.
123. 🟢 **Dunning** (upomínky) — konfigurace je; ověřit reálné odesílání a suspend po X upomínkách.
124. 🟡 **Pro-rata při změně tarifu** (`PlanChangeProrate`) — ověřit výpočet a fakturaci rozdílu.
125. 🟢 **Kredit expirace** — `expires_at` na credit transaction; ověřit, že se expirovaný kredit odečítá.

## K. Výkon / infrastruktura / deploy

126. 🟠 **Route/view cache po `git pull`** — nové routy/šablony se bez `route:cache`+`view:cache` neprojeví (opakovaný zdroj „nefunguje na produkci“). `deploy.sh` to řeší; přidat kontrolu do CI a upozornění.
127. 🟡 **N+1 dotazy** — projít seznamy (objednávky, služby, faktury) na eager loading; některé pohledy počítají per-řádek (`CreditLedger::getBalance` v hlavičce na každé stránce).
128. 🟡 **Hlavička volá `CreditLedger::getBalance` a cart composer na KAŽDÉ stránce** — cache balance per-request.
129. 🟢 **Fronty**: oddělit provisioning frontu od e-mailů/notifikací (priority queues) + supervisor konfigurace pro každou.
130. 🟢 **Horizon** pro monitoring front — není nasazen.
131. 🟢 **Asset build**: `style.css` je obří (200k+ řádků) — purge/tree-shake Tailwind na reálně použité třídy zmenší payload.
132. 🟡 **DB indexy** — ověřit indexy na `services.customer_id`, `invoices.customer_id/status`, `orders.status`, `order_items.order_item_id` (dotazy v SyncOrderCompletion/HandleInvoicePaid).
133. 🟢 **Cache driver** v produkci (redis) vs database — ověřit `config/cache.php`.
134. 🟢 **Zdravotní endpoint** `/up` (Laravel health) + monitoring — ověřit.
135. 🟢 **Zálohy DB** (spatie/laravel-backup) — ověřit plán a offsite úložiště.

## L. Testy / kvalita / observabilita

136. 🟡 **Chybí E2E test provisioning fronty v preview** (kvůli K51) — přidat test s `dispatchSync` řetězcem objednávka→platba→Active.
137. 🟢 **Browser/Dusk testy** — sada je Pest feature; přidat pár smoke Dusk testů na JS-kritické toky (košík, porovnání, chat, wizard).
138. 🟢 **Mutační testování / coverage report** — není v CI.
139. 🟢 **Accessibility testy** (axe) — chybí.
140. 🟢 **Sentry/error tracking** — ověřit napojení `report()` na externí službu.
141. 🟢 **Strukturované logování** (kontext: customer_id, service_id) — sjednotit.
142. 🟢 **Feature flags** (`config`/DB) pro postupné zapínání funkcí — chybí.
143. 🟢 **Load test** kritických endpointů (login, objednávka, provisioning webhook) — neproveden.

## M. UX / přístupnost / i18n

144. 🟡 **Fokus management v modálech** (porovnání, potvrzení) — po otevření nepřesune fokus, Esc funguje jen u porovnání. Sjednotit přístupný modal.
145. 🟡 **`confirm()` inline** v nových formulářích (`onsubmit="return confirm(...)"`) — může kolidovat s CSP a není přístupné. Nahradit Cuba modálem.
146. 🟢 **ARIA/labely** — projít formuláře (aria-label, aria-live u AJAX), částečně doplněno u chatu.
147. 🟢 **Kompletní i18n** — mnoho řetězců je natvrdo česky v šablonách místo `__()`. Extrahovat do `lang/`.
148. 🟢 **Anglická lokalizace** — ověřit pokrytí `en` (přepínač jazyka je).
149. 🟢 **Mobilní navigace** panelu — ověřit sidebar toggle a header na < 768px (tabulky, karty).
150. 🟢 **Klávesové zkratky** (Cuba má bookmark/search) — nasadit „/“ pro hledání, „?“ pro nápovědu.

---

## Shrnutí priorit

| Priorita | Počet | Nejdůležitější |
|---|---|---|
| 🔴 Kritické | 3 | d-none skrývání (A2), preview fronta (D51/K51), životní cyklus doménových položek (D52) |
| 🟠 Vysoké | ~12 | Bootstrap↔Tailwind třídy (A1/A3/A4), dvě cesty objednávky (C34), DNS CRUD (E66), akceptace plateb bezpečnost (G88) |
| 🟡 Střední | ~55 | konfigurátor produktů, DPH v košíku, N+1, indexy, fokus/CSP modály |
| 🟢 Nízké / rozšíření | ~80 | e-mail hosting, DNSSEC, Horizon, i18n, Dusk, feature flags |

**Doporučený postup:** (1) dořešit 🔴 a 🟠 z sekcí A/C/D/E, (2) systémově nahradit neexistující Bootstrap třídy kompat vrstvou v `onhost.css` nebo migrací na Tailwind, (3) sjednotit objednávkový tok a konfigurátor produktů, (4) doplnit chybějící produktové kategorie (e-mail hosting, registrace domén), (5) zbytek jako průběžná rozšíření.
