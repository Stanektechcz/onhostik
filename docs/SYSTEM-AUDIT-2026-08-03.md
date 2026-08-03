# OnHost — Stavový audit a plán dokončení

**Datum:** 3. 8. 2026 · **Větev:** `development` @ `5256819` · **Předchozí audit:** [SYSTEM-AUDIT-500.md](SYSTEM-AUDIT-500.md)

Tenhle dokument je jiný než audity před ním. Ty byly převážně *backlogy nápadů* —
seznamy toho, co by šlo přidat. Tenhle vychází z **ověřeného chování běžícího
systému**: co bylo spuštěno, kliknuto, změřeno, a co se přitom ukázalo jinak,
než jak to vypadalo v kódu.

Hlavní zjištění tohoto kola je nepříjemné a stojí za to ho říct rovnou:

> **Systém obsahoval několik funkcí, které vypadaly hotově, byly otestované,
> měly UI — a nikdy neběžely nebo dělaly něco jiného, než tvrdily.**

Nešlo o chybějící funkce. Šlo o funkce, které se tvářily, že fungují. To je horší
kategorie problému, protože se sama nenahlásí — nikdo nepodá ticket na to, že
zálohovací e-mail nedorazil, když nikdo neví, že měl dorazit.

---

## 1. Rozsah systému (měřeno, ne odhadnuto)

| Metrika | Hodnota |
|---|---|
| Routy celkem | **1 092** (admin 657 · panel 257 · api 40) |
| Controllery | 330 (admin 206 · panel 106 · api 18) |
| Doménové třídy | 287 ve 26 doménách |
| Modely | 190 |
| Migrace | 235 |
| Blade šablony | 462 |
| Konzolové příkazy | 53 |
| Plánované úlohy | **48** (bylo 39) |
| Testy | **3 518 prošlo**, 1 selhal (prostředí) |
| PHPStan level 6 | **0 chyb** |
| Jazyky | cs, en |

Domény: `Ai, Api, Approvals, Audit, Automation, Backups, Bi, Billing,
Communication, Compliance, Customer, Developer, Dns, Integration, Integrations,
Loyalty, Marketplace, Monitoring, Partner, Products, Provisioning, Reporting,
Reseller, Security, Shared, Support`

> ⚠️ Duplicitní doména `Integration` vs `Integrations` — viz D‑2.

### 1.1 Funkční záběr zákaznického panelu

**67 samostatných oblastí**, největší: služby (38 rout), účet (32), fakturace (22),
domény (12), podpora (10), AI (8), košík (8), DNS manager (7).

Zákaznický panel dnes pokrývá: objednávku a košík se slevami, kredit a peněženku,
faktury a daňové doklady, správu služeb (webhosting, VPS, herní servery, domény),
git deploy, marketplace, DNS, WAF, firewall, zálohy a snapshoty, monitoring
a uptime, podporu s SLA, znalostní bázi, AI chat, věrnostní program, referral,
reseller program, GDPR a compliance, API tokeny a webhooky, PWA a push
notifikace, onboarding a průvodce.

Administrace má **657 rout** — provoz, fakturace, provisioning, CRM, reporting,
BI, marketing, schvalování, audit, bezpečnost.

> Rozsah tedy **není** problém tohoto systému. Problém je, že část z toho
> rozsahu nebyla propojená do konce — viz §2.

---

## 2. Co se v tomto kole opravilo (ověřeno v prohlížeči)

| # | Zjištění | Dopad | Commit |
|---|---|---|---|
| 1 | **9 příkazů nemělo záznam v scheduleru** — naplánované oznámení se nikdy nezveřejnilo, drip kampaně neposlaly nic, prahy monitoringu se nikdy nevyhodnotily, eskalace upomínek D+7/14/30 neproběhla, auto‑dobíjení kreditu se nikdy nespustilo | Tiché nefungování 9 funkcí | `5256819` |
| 2 | **One‑click instalace byla fikce** — zapsala úlohu `Success` s `mock: true` a řekla „úspěšně nainstalována", i když na server nikdy nic neodešlo | Zákazník věřil, že má WordPress | `22e42e7` |
| 3 | **Slevové kódy nevynucovaly svá pravidla** — limit „jednou na zákazníka" ani minimální hodnota objednávky se na cestě košíku nekontrolovaly; neplatný kód se tiše zahodil a zákazník zaplatil plnou cenu, kterou nepotvrdil | Přímá finanční a důvěryhodnostní chyba | `49a958d` |
| 4 | **Testová sada vůbec nešla spustit** (kolize `makeServer()`) — a proto nikdo neviděl, že 3 strážní testy jsou červené | Nulová regresní ochrana | `4003803` |
| 5 | **Inline handlery blokované vlastním CSP** (`offline.blade.php`) | Offline stránka by v produkci nefungovala | `4003803` |
| 6 | Kompletní konfigurace hostingu se **načítala a zahazovala** — view používal 1 klíč ze 6 | Zákazník otevíral ticket na to, co si mohl udělat sám | `2a3f5f9` |
| 7 | Git deploy jako v aaPanelu (chyběl úplně) | Nová funkce | `8494878` |
| 8 | Expirující kredit nebyl vidět na stránce, na kterou odkazoval upomínkový e‑mail | Kredit tiše propadal | `3946e1e` |

**Poučení do dalších fází:** *„je to v kódu" ≠ „funguje to".* Každá další fáze
níž má proto explicitní **ověřovací krok**, ne jen implementační.

---

## 3. Stav integrací a bran

Všechny brány jsou **zavřené** (`mock=1, dry=1`) — což je správný výchozí stav.

| Poskytovatel | Klient | Test připojení | Reálný zápis | Poznámka |
|---|---|---|---|---|
| aaPanel | ✅ 60 metod | ✅ | 🔒 brána | Endpointy psané dle API v4, neověřeny proti živé instanci |
| WEDOS WAPI | ✅ | ✅ | 🔒 brána | Registrátor: produkční driver existuje |
| Proxmox | ✅ | ✅ | 🔒 brána | |
| Pterodactyl | ✅ | ✅ | 🔒 brána | Chybí mock driver (jen produkční) |
| Cloudflare | ✅ | ✅ | 🔒 brána | |
| Comgate | ✅ | ⚠️ generický | 🔒 brána | Chybí credentials — záměrný blocker |
| Stripe | ✅ | ⚠️ generický | 🔒 brána | 3‑D Secure návratové toky nedotažené |
| GoPay | ✅ | ⚠️ generický | 🔒 brána | dtto |
| Uptime Kuma | ❌ | ⚠️ generický | — | Nemá REST API (socket.io) |
| n8n, SitePro, Softaculous, Backblaze, S3, SMTP, web push | ❌ klient | ⚠️ generický | — | Pokryté obecnými mechanismy |
| Claude / OpenAI | 🟡 placeholder | ⚠️ generický | 🔒 brána | `GatedRealAiProvider` odmítá čistě |

**Zjištění B‑7:** `ConnectionTester` má reálný test jen pro 5 z 20 poskytovatelů.
U zbytku v mock režimu vrací „self‑test OK", což je pravdivé, ale při otevření
brány řekne „not implemented yet" — tedy admin zjistí, že test neexistuje, až
v okamžiku, kdy ho nejvíc potřebuje.

### 3.1 Dvě paralelní implementace aaPanelu (B‑8)

Měřeno: `AapanelClient` má **59 metod, z toho 37 nemá jediného volajícího.**
Není to jen mrtvý kód — je to důsledek toho, že aaPanel je v systému napojený
**dvakrát, nezávisle**:

| | `AapanelProductionDriver` | `AapanelClient` |
|---|---|---|
| Použití | životní cyklus služby (create/suspend/terminate) | správa a konfigurace (60 metod) |
| HTTP | vlastní `PendingRequest` | vlastní vrstva + `dryRunOr()` |
| Autentizace | `md5($time . md5($apiKey))` — řádek 393 | `md5($time . md5($api_key))` — řádek 861 |
| Brána | `provisioning.aapanel.allow_real_writes` | `GuardsRealCalls` (5 podmínek) |

Podpis autentizace i logika brány existují dvakrát. Dokud jsou brány zavřené, je
to jen dluh; po otevření je to **dvě místa, kde se dá udělat jiná chyba** —
a driver má jednodušší podmínku brány než klient.

---

## 4. Co v systému chybí nebo je nedotažené

Seřazeno podle toho, **co může způsobit škodu**, ne podle velikosti práce.

### 4.1 Kategorie A — může způsobit finanční nebo důvěryhodnostní škodu

| # | Zjištění | Proč to vadí |
|---|---|---|
| **A‑1** | Merchant‑initiated platby nemá napojené žádná brána | Auto‑dobíjení i auto‑obnova v ostrém režimu vždy odmítnou. Fakturace je „automatická" jen v mock režimu. |
| **A‑2** | 3‑D Secure návratové toky Stripe/GoPay jsou na placeholder úrovni | Platba může projít u brány a nedojít k nám, nebo naopak. |
| **A‑3** | Instalační recepty marketplace mají u 5 ze 7 aplikací **verzi napevno v URL** | Po vydání bezpečnostní opravy upstream instalujeme nezáplatovanou verzi, dokud recept někdo neaktualizuje. |
| **A‑4** | Slevové kódy: ověřeno v košíku, ale **API/GraphQL cesta objednávky** stejnou kontrolou neprošla auditem | Pokud existuje druhá cesta k `CreateCartOrderAction`, měla by ji dostat také. |
| **A‑5** | Refundace: `RefundPaymentAction` existuje, ale reálné vrácení přes brány není ověřené | Vrácení peněz je regulovaná povinnost, ne feature. |

### 4.2 Kategorie B — provozní rizika

| # | Zjištění | Proč to vadí |
|---|---|---|
| **B‑1** | aaPanel endpointy (60 metod) neověřené proti živé instanci | Otevření brány může selhat po částech — část operací projde, část ne. |
| **B‑2** | `Pterodactyl` má jen produkční driver, chybí mock | Nelze bezpečně testovat herní servery bez ostrého API. |
| **B‑3** | Git deploy: privátní repozitáře přes `git@` vyžadují deploy klíč, ale **UI ho neumí vygenerovat** (klient metodu má) | Zákazník s privátním repem se zasekne. |
| **B‑4** | Odinstalace marketplace aplikace v kořeni webu soubory nemaže (záměrně) | Správné rozhodnutí, ale znamená ruční úklid — chybí navigace do správce souborů. |
| **B‑5** | Fronta: 48 plánovaných úloh, ale **žádný alert na zpoždění konkrétní úlohy** (jen heartbeat scheduleru) | Scheduler běží, jednotlivá úloha může tiše padat. |
| **B‑6** | Zálohy: `db:backup` běží, ale **není ověřeno obnovení** | Záloha, kterou nikdo neobnovil, není záloha. |
| **B‑7** | Reálný test připojení jen pro 5 z 20 poskytovatelů | Admin zjistí, že test neexistuje, až při otevírání brány. |
| **B‑8** | aaPanel napojený **dvakrát** (driver vs. klient), autentizace i brána duplicitně | Dvě místa na stejnou chybu; driver má slabší podmínku brány. Viz §3.1. |

### 4.3 Kategorie C — funkční mezery vůči konkurenci (aaPanel/WHM/WHMCS)

| # | Chybí | Klient už metodu má? |
|---|---|---|
| **C‑1** | Správce souborů v panelu (upload, editace) | ❌ |
| **C‑2** | Přesměrování a rewrite pravidla v zákaznickém UI | ✅ `createRedirect`, `setRewriteRules` |
| **C‑3** | Subdomény / alias domény v UI | ✅ `setDomainBindings` |
| **C‑4** | Statistiky návštěvnosti webu | ✅ `getTrafficStats` |
| **C‑5** | Logy webu (error/access) v panelu | ✅ `getSiteLogs` |
| **C‑6** | Změna hesla FTP/DB z panelu | ✅ `setFtpPassword`, `setDatabasePassword` |
| **C‑7** | Zálohy webu/DB na vyžádání z panelu | ✅ `createSiteBackup`, `createDatabaseBackup`, `restoreDatabaseBackup` |
| **C‑8** | Let's Encrypt s vlastními doménami | ✅ `issueLetsEncrypt`, `setForceHttps` |
| **C‑9** | Mail forwardy a auto‑reply v UI | ✅ `createMailForward`, `setMailAutoReply` |

> **Tohle je nejlevnější balík hodnoty v celém dokumentu.** Devět funkcí, u osmi
> z nich už klient umí zavolat správný endpoint — chybí jen controller, routa a
> karta. Přesně stejný vzorec jako u konfigurace webhostingu, kterou jsme právě
> dodělali (data se načítala a zahazovala).
>
> Ověřeno: `getSiteLogs`, `getTrafficStats`, `createRedirect`, `setDomainBindings`,
> `setFtpPassword`, `createSiteBackup`, `issueLetsEncrypt`, `createMailForward`
> a `setRewriteRules` nemají **ani jednoho volajícího** v celé aplikaci.

### 4.4 Kategorie D — kvalita a údržba

| # | Zjištění |
|---|---|
| **D‑1** | Duplicitní příkaz: `tickets:escalate-sla` vs `support:escalate-sla` — dva příkazy, stejná práce. Naplánovat oba by eskalovalo dvakrát. |
| **D‑2** | Duplicitní doména `app/Domains/Integration` vs `Integrations` |
| **D‑3** | GD není zapnuté v CLI (`;extension=gd` v `C:\php\php.ini`) → 1 test padá lokálně |
| **D‑4** | i18n dluh: **4 332** natvrdo psaných českých řetězců v Blade (ratchet drží, ale neklesá) |
| **D‑5** | Testy: 394 souborů, ale **žádné pro nové funkce tohoto kola** (na explicitní přání) — regresní ochrana git deploye, instalátoru a slev stojí jen na ratchetech |
| **D‑6** | Chybí mock driver pro Pterodactyl (viz B‑2) |

---

## 5. Fáze dalšího vývoje

Každá fáze má **výstup, ověření a definici hotovo**. Fáze jsou seřazené tak, aby
každá další stavěla na ověřené předchozí — ne podle atraktivity.

### Fáze 5 — „Dokončit, co už umíme zavolat" (nejvyšší poměr hodnota/práce)

Zpřístupnit devět funkcí z kategorie C, u kterých `AapanelClient` už má metodu.
Vzorec je ověřený: `WebhostingConfigService` rozšířit o čtení, akce přes
`WebhostingConfigActionJob`, karta z existujících Cuba komponent.

**Pořadí podle toho, co zákazník postrádá nejdřív:**

1. **Logy webu + statistiky návštěvnosti** — čtení, nulové riziko zápisu, okamžitá
   hodnota při ladění „proč mi web nejede".
2. **Zálohy webu a DB na vyžádání + obnovení** — zároveň splní **B‑6**
   (ověřené obnovení).
3. **Změna hesel FTP/DB** — dnes to znamená smazat a založit znovu.
4. **Subdomény a alias domény.**
5. **Přesměrování a rewrite pravidla.**
6. **Let's Encrypt s vlastními doménami + vynucení HTTPS.**
7. **Mail forwardy a auto‑reply.**

**Ověření:** každá karta projde v prohlížeči s otevřenou i zavřenou bránou;
při zavřené musí hlásit simulovaný režim, ne úspěch.
**Hotovo když:** zákazník zvládne běžnou správu webu bez ticketu.

---

### Fáze 6 — Platby v ostrém režimu

Tohle je jediná fáze, která blokuje **veřejné spuštění**.

1. Doplnit Comgate credentials (dosud záměrný blocker) a projít
   [COMGATE-CUTOVER.md](COMGATE-CUTOVER.md).
2. **Dotáhnout 3‑D Secure návratové toky** Stripe a GoPay (A‑2) — včetně
   případů „zákazník zavřel okno" a „brána potvrdila, callback nedorazil".
3. **Ověřit refundace** proti sandboxu každé brány (A‑5).
4. **Rozhodnout o merchant‑initiated platbách** (A‑1). Buď napojit
   (Stripe `off_session` + uložený mandát je nejschůdnější), nebo **v UI přestat
   slibovat automatické strhávání** a auto‑dobíjení popsat jako „vystavíme
   fakturu". Dnešní stav — kód odmítá čistě, ale UI mluví o automatice — je
   z těch dvou horší.
5. Ověřit párování plateb na proforma → daňový doklad na reálné transakci.

**Ověření:** jedna reálná platba v každé bráně, jedna refundace, jeden
nedokončený 3‑D Secure.
**Hotovo když:** peníze dojdou, vrátí se, a v obou případech sedí doklady.

---

### Fáze 7 — Otevření provisioningu (staged cutover)

Nespouštět naráz. Pořadí podle rizika:

1. **Čtecí operace** proti živému aaPanelu (`listSites`, `getUsage`,
   `getSiteInfo`) — ověří **B‑1** bez jediného zápisu.
2. **Reverzibilní zápisy** (PHP verze, kvóta) na testovacím webu.
3. **Vytváření** (site, DB, FTP) na testovacím webu.
4. **Destruktivní** (`deleteSite`, `deleteDatabase`) — až naposled, a s ověřeným
   obnovením ze zálohy (návaznost na fázi 5.2).
5. Až potom `AAPANEL_ALLOW_REAL_WRITES=true` pro zákazníky.

Souběžně: **mock driver pro Pterodactyl** (B‑2), aby herní servery šly testovat.

**Ověření:** každý stupeň má vlastní kontrolní seznam; postup dál až po zeleném.
**Hotovo když:** provisioning nové služby proběhne end‑to‑end bez zásahu.

---

### Fáze 8 — Provozní jistota

Cílem je, aby se **systém sám ozval dřív než zákazník**.

1. **Alert na zpožděnou úlohu**, ne jen na mrtvý scheduler (B‑5) — každá
   plánovaná úloha zapíše poslední úspěch, admin vidí prošlé.
2. **Ověřené obnovení zálohy** — pravidelný automatický restore do dočasné DB
   s kontrolou počtu řádků (B‑6). Záloha bez restore testu je jen soubor.
3. **Alert na frontu**: hloubka, stáří nejstarší úlohy, poměr `failed_jobs`.
4. **Deploy‑time kontrola**, že každý příkaz má buď schedule, nebo je označený
   jako manuální. Přesně tenhle audit by pak nebyl potřeba dělat ručně.
5. Doplnit reálný `ConnectionTester` pro Comgate/Stripe/GoPay (B‑7) — test
   připojení, který existuje až po otevření brány, přichází pozdě.

**Hotovo když:** výpadek integrace se objeví v adminu dřív, než přijde ticket.

---

### Fáze 9 — Dokončení funkčního záběru

1. **Správce souborů** (C‑1) — jediná položka kategorie C bez hotového klienta;
   promyslet, jestli stačí odkaz do aaPanelu s SSO místo vlastní implementace.
2. **Generování deploy klíče v UI** (B‑3) — klient metodu má.
3. **Git deploy: rollback** na předchozí commit, historie nasazení.
4. **Marketplace: aktualizace aplikace** (dnes jen instalace a odinstalace).
5. **Automatická kontrola verzí receptů** (A‑3) — porovnat s upstream a upozornit
   admina, místo spoléhání na to, že si někdo vzpomene.

---

### Fáze 10 — Konsolidace a dluh

1. Odstranit duplicitní `tickets:escalate-sla` (D‑1).
2. Sloučit `Integration` do `Integrations` (D‑2).
2b. **Sjednotit aaPanel na jednu implementaci** (B‑8) — driver ať volá klienta,
    ať existuje jedna autentizace a jedna brána. Udělat **před** fází 7, ne po ní:
    sjednocovat přístup k živému panelu, když už jím tečou zákaznická data, je
    zbytečné riziko.
3. Systematicky snižovat i18n dluh (D‑4) — dnes ratchet jen brání růstu.
4. Doplnit regresní testy pro funkce tohoto kola (D‑5): git deploy, instalátor,
   pravidla slev. Ne kvůli metrice — kvůli tomu, že jde o kód, který sahá na
   shell a na peníze.
5. Zapnout `Model::preventLazyLoading()` mimo produkci (bod A‑1 z auditu 500).

---

## 6. Doporučené pořadí

```
Fáze 5 ──────────► Fáze 7 ──► Fáze 9
   │                  ▲
   │      10.2b ──────┤  (sjednotit aaPanel PŘED otevřením bran)
   ▼                  │
Fáze 8 ───────────────┘  (zálohy z 5.2 před destruktivními zápisy)

Fáze 6 ── nezávislá, ale blokuje veřejné spuštění → začít paralelně
Fáze 10 ─ průběžně, kromě 2b, které má pevné místo výše
```

**Kdyby byl čas jen na jedno:** fáze 5. Devět funkcí, osm z nich je propojení
existujících dílů, a všechny řeší ticket, který dnes chodí na podporu.

**Kdyby bylo cílem spustit veřejně:** fáze 6, a to hned — je to jediná věc,
která dnes brání přijímat peníze.

---

## 7. Co tento audit nezkoumal

Aby bylo jasné, kde má tento dokument hranice:

- **Zátěžové chování** — 1 092 rout nikdo neprofiloval pod zátěží.
- **Bezpečnostní penetrační test** — kontrolovaly se konkrétní věci (shell
  injection, path traversal, CSP, tokeny), ne systém jako celek.
- **Přístupnost (a11y)** — neměřeno.
- **Reálné chování integrací** — všechno běželo v mock/dry‑run režimu. Až
  otevření bran ukáže, jestli endpointy sedí.
- **Datová migrace ze stávajícího systému**, pokud nějaká proběhne.
