# OnHost — Ověřený stav auditu (2026-07-19)

Varianta B: **každý bod 🟠 vysoké a 🟡 střední závažnosti z `SYSTEM-AUDIT-200-NEXT.md`
ověřen proti skutečnému kódu**, ne proti dojmu. Původní v2 audit byl psán dopředně
bez verifikace — a ukázalo se, že **velká část „chybějících" bodů už existuje**.

Stav: **3212 testů zelených, PHPStan L6 = 0.**

Klasifikace:
- **HOTOVO** — implementované, ověřeno grepem/testem (audit se mýlil)
- **DOPLNĚNO** — chybělo, implementováno v této dávce
- **CHYBÍ** — skutečná mezera v kódu, k implementaci
- **EXTERNÍ** — kód hotový/nepotřebný, blokuje credential nebo produkční prostředí
- **INFRA** — konfigurace serveru/DNS/CI, ne kód repozitáře

---

## Souhrn

| Klasifikace | Počet | Podíl |
|---|---|---|
| HOTOVO (audit se mýlil) | **31** | 29 % |
| DOPLNĚNO (V1–V5 + W1–W7 + 112) | **24** | 23 % |
| CHYBÍ (skutečná práce) | **18** | 17 % |
| EXTERNÍ (credentials/produkce) | **17** | 16 % |
| INFRA (server/DNS/CI) | **16** | 15 % |
| **Celkem high+medium** | **106** | 100 % |

**Klíčové zjištění:** z 106 bodů je **jen 34 skutečná práce v kódu**. 31 bodů už
bylo hotových — audit v2 je v těchto místech nespolehlivý a tento dokument ho
opravuje.

---

## HOTOVO — audit se mýlil (31)

Ověřeno, že funkce existuje. Žádná práce není potřeba.

| # | Bod | Důkaz v kódu |
|---|---|---|
| 9 | Horizon supervisor unit | `deploy/supervisor/onhost-horizon.conf` |
| 26 | composer audit v CI | `.github/workflows/ci.yml` job `security` |
| 27 | WAF pravidla | `Domains/Security/{WafRule,WafEvent,WafRuleType}` + `WafController` |
| 28 | 2FA per-role | `RequireAdminTwoFactor` + `RequireCustomerTwoFactor` (opt-in dle standing rule) |
| 32 | Rate limit reset/magic link | `throttle:10,1` a `throttle:5,1` v `routes/web.php` |
| 42 | Přepočet měn | `Domains/Billing/{ExchangeRate,ExchangeRateService}` |
| 43 | Slevové kupóny | `DiscountCode`: percent/fixed, `max_uses`, `max_uses_per_customer`, `min_order_haler` |
| 44 | Dunning + pozastavení | `SendPaymentOverdueReminders`, `SendServiceSuspensionWarnings`, `SuspendOverdueServices` |
| 46 | VIES kontrola DIČ | `app/Services/ViesVatValidator.php` |
| 51 | Provisioning retry + backoff | `ProvisionHostingServiceJob`: 30/60/120s, cap 900s, pak ManualReview |
| 54 | Snapshot restore | `SnapshotRestoreRequestController` (panel + admin approval) |
| 55 | VPS rescale | `changePackage()` na všech driverech |
| 61 | Domain transfer IN | `DomainTransferRequest` + admin approve volá `transferDomain()` |
| 62 | Notifikace expirace domén | `SendDomainExpiringRemindersCommand`, `ProcessDomainRenewalsCommand` |
| 63 | DNS šablony | `panel.domains.dns.template` → `DnsController::applyTemplate` |
| 72 | Admin provozní přehled | `QueueController`, `SystemHealthController`, `ScheduledTasksController` |
| 73 | Bulk e-mail se segmentací | `BulkCustomerEmailController`: `filter_segment` (vip/at_risk/healthy/churned) |
| 83 | Business metriky | `MetricsController`, `BiController`, `BiV2Controller` |
| 84 | Alerting na anomálie | `KpiAlert` + `CheckKpiAlertsCommand` |
| 85 | Horizon dashboard | `config/horizon.php` `path` — dostupný adminovi |
| 101 | Webhook UI pro zákazníka | `panel.webhooks.*` (Phase 117) |
| 110 | White-label doména | `DetectResellerDomain` matchuje `custom_domain` |
| 127 | Coverage práh v CI | `pest --coverage --min=60` |
| 156 | Marketplace | `Domains/Marketplace/{MarketplaceApp,AppInstallation}` + admin |
| 168 | Soft-delete politika | `SoftDeletes` na `Customer`, `Service`, `User` |
| 177 | GDPR export/mazání | `ProcessGdprErasureRequestsCommand` + `ComplianceController` |
| 179 | Cookie consent evidence | `ConsentRecord` + verzované dokumenty v `config/legal.php` |
| 185 | Win-back kampaně | `WinbackCampaign` + controller + notifikace |
| 187 | NPS | `NpsSurveyNotification` + survey flow |
| 188 | Referral tracking | `HandleReferralCookie` + `ReferralTracker` + `PartnerReferral` |
| 198 | Capacity planning data | `ServerSelector` + nově `FleetCapacityReport` |

---

## DOPLNĚNO v této dávce (8)

| # | Bod | Co přibylo |
|---|---|---|
| 24 | CSP violation reporting | `report-uri`/`report-to` + `CspReportController` (6 testů) |
| 25 | SRI / CDN pinning | Swagger připnutý na `@5.17.14` + `crossorigin` |
| 52 | Kapacitní alert | `FleetCapacityReport` + `provisioning:check-capacity` (7 testů) |
| 71 | Impersonation audit | `impersonated_by_admin_id` na každé aktivitě během session |
| 81 | Slow-query logging | `DB::whenQueryingForLongerThan` + `db.slow_query` |
| 195 | Runbooky | *(v této dávce jen částečně — viz CHYBÍ)* |
| — | M166 log kontext | `LogContext` (z předchozí dávky) |
| — | O189 health strip | `ServiceHealthSummary` (z předchozí dávky) |

### Dávka W (medium/lower, 2026-07-21) — 7 bodů

| # | Bod | Co přibylo | Testy |
|---|---|---|---|
| 29 | Limit souběžných relací | `EnforceConcurrentSessionLimit` (opt-in, nejstarší se odhlásí, cyklí remember token) | 6 |
| 64 | DNSSEC UI | route + `DnsController::dnssecStore/Destroy` + karta (validace algoritmu/digestu) | 6 |
| 56 | Provisioning dry-run náhled | `ProvisioningPreviewService` (driver/server/režim/test spojení, jen čtení) | 7 |
| 103 | API deprecation + changelog | `AnnounceApiLifecycle` (Deprecation/Sunset) + `/api/changelog` | 5 |
| 178 | Generování DPA | `DpaPdfService` + PDF šablona (GDPR čl. 28) | 5 |
| 75 | Jednotné poznámky | `HasEntityNotes` + `EntityNote` + komponenta (objednávka, faktura) | 9 |
| 74 | Čtyři oči | `ApprovalService` + executor kontrakt + review UI (napojeno na výplatu reselleru) | 14 |

---

## CHYBÍ — skutečná práce v kódu (19)

Seřazeno dle hodnoty. **Toto je reálný backlog** (po dávkách V + W).

### Vysoká hodnota (10) — **8 z 10 HOTOVO, 2 vyžadují rozhodnutí**

> Implementováno 2026-07-19/20: **137** focus-trap, **138** klávesové zkratky,
> **109** limit sub-zákazníků, **41** net-30 platební podmínky,
> **45** QR Platba (SPAYD), **39** automatické opakované platby,
> **53** migrace služby mezi servery, **120** fronta pro těžké exporty.

| # | Bod | Stav |
|---|---|---|
| 39 | Automatické opakované platby | ✅ `AutoChargeSavedMethodAction` |
| 109 | Limit sub-zákazníků resellera | ✅ vynuceno v modelu (`Customer::booted`) |
| 41 | Kreditní limity / net-30 | ✅ `payment_terms_days`, `credit_limit` |
| 137 | Focus-trap v modálech | ✅ CSP-safe, v `layouts/panel` |
| 138 | Klávesové zkratky | ✅ „/" hledání, „?" nápověda |
| 53 | Migrace služby mezi servery | ✅ `MigrateServiceToServerAction` (rebind, ne přesun dat) |
| 120 | Fronta pro těžké exporty | ✅ `GenerateInvoiceBatchExportJob`, strop 50 → 500 |
| 45 | QR platba na faktuře | ✅ `QrPaymentGenerator` (SPAYD) |
| **141** | **cs řetězce v blade** | ⚠️ **není „zbytek" — viz níže** |
| **92** | **Web push** | ⛔ **blokováno závislostí + tajemstvím — viz níže** |

#### 141 — audit podhodnotil rozsah o dva řády

Audit to popsal jako „zbytky". Měření: **4 161 výskytů ve ~398 šablonách**,
proti 423 klíčům v `lang/`. Aplikace je psaná česky-first; `lang/` pokrývá
zlomek. Jednorázové vygenerování čtyř tisíc překladových klíčů bez vizuální
kontroly by rozbilo layouty a zabetonovalo překlepy rychleji, než by to kdokoli
zvládl zrevidovat — to není refaktoring, to je samostatný projekt.

**Zvoleno místo toho:** `tests/Feature/I18nCoverageRatchetTest.php` — ráčna
(stejný nástroj, kterým se umořil dluh inline handlerů): počet smí klesat,
nikdy růst. Nová práce musí používat `__()`; existující dluh se splácí po
obrazovkách a baseline se snižuje. Sdílené komponenty mají vlastní, přísnější
strop (60), protože je renderuje každá stránka.

#### 92 — vyžaduje rozhodnutí majitele, ne kód

Web push potřebuje tři věci, které nelze dodat zevnitř tohoto úkolu:

1. **Runtime závislost** `minishlink/web-push`. Šifrování payloadu (aes128gcm /
   ECDH) se neimplementuje ručně.
2. **Instalace je zde blokovaná:** `composer require` padá na chybějícím
   `ext-pcntl` a `ext-posix` (Horizon). Není to nová vada — Horizon byl
   nainstalován s `--ignore-platform-req`, protože Windows tyto extenze nemá.
   Přidat závislost by znamenalo přepsat `composer.lock` ze stroje, kde se
   kontroly platformy obcházejí, a ten lock pak jede na produkčním Linuxu.
3. **VAPID klíče** = tajemství. Platí zadání „nikdy necommituj hesla".

Doporučení: přidat balíček na Linuxu (CI nebo prod image), vygenerovat VAPID
klíče do `.env`, teprve pak implementovat. Odhad po odblokování: cca půl dne
(model `PushSubscription`, service worker, kanál, preference v katalogu).

### Střední hodnota (14)
| # | Bod |
|---|---|
| 29 | Concurrent session limit per uživatel |
| 30 | Detekce anomálií přihlášení dle země/ASN (dnes jen IP) |
| 29 | ✅ **HOTOVO** — limit souběžných relací (`EnforceConcurrentSessionLimit`, opt-in, 0 = vypnuto) |
| 56 | ✅ **HOTOVO** — provisioning dry-run náhled (`ProvisioningPreviewService`, jen čtení + test spojení) |
| 64 | ✅ **HOTOVO** — DNSSEC UI (route + `DnsController::dnssecStore/Destroy` + karta na detailu domény) |
| 74 | ✅ **HOTOVO** — čtyři oči (`ApprovalService` + executor kontrakt; napojeno na výplatu reselleru, opt-in) |
| 75 | ✅ **HOTOVO** — jednotné poznámky (`HasEntityNotes` trait + `EntityNote` + komponenta; na objednávce a faktuře) |
| 31 | Šifrování dalších PII sloupců — **rozhodnutí, ne kód**: šifrování IČ/DIČ/telefonu rozbije admin hledání, VIES a vykreslení faktur (neindexovatelné), vyžaduje datovou migraci |
| 100 | API SDK (PHP/JS/Python) — samostatné repozitáře, mimo tuto codebase |
| 111 | Reseller jako fakturující subjekt |
| 119 | Read replica pro reporting — **INFRA** |
| 121 | Cache warming po deploy — **INFRA** (deploy skript) |
| 140 | Skeleton/loading stavy — kosmetika |
| 142 | WCAG AA kontrast audit — audit, ne feature |
| 186 | Upsell/cross-sell engine — produktová sázka |

### Nižší priorita (10)
| # | Bod |
|---|---|
| 103 | ✅ **HOTOVO** — API deprecation (Deprecation/Sunset hlavičky) + veřejný changelog (`/api/changelog`) |
| 178 | ✅ **HOTOVO** — generování DPA (`DpaPdfService` + PDF šablona, GDPR čl. 28) |
| 112 | ✅ **HOTOVO** — generátor partnerských bannerů (`PartnerBannerService`, SVG v 5 IAB rozměrech s referral odkazem + embed kód + download; nahradil „PŘIPRAVUJEME" placeholder) |
| 139 | ✅ **JIŽ HOTOVO** — onboarding checklist je na dashboardu (`onboarding-checklist` partial, kroky + %); „tour" overlay je redundantní |
| 111 | ✅ **z větší části hotovo** — reseller je fakturující subjekt na PDF (`InvoicePdfService::resolveBrand` vkládá IČ/DIČ/branding); zbývá jen samostatná číselná řada (rizikové, marginální) |
| 99 | OAuth2 authorization flow — potřebuje Passport (composer blokovaný zde) |
| 102 | GraphQL endpoint — velký, potřebuje závislost |
| 128 | Mutační testování (Infection) — **INFRA/CI** |
| 129 | Browser/Dusk E2E testy — **INFRA/CI** |
| 130 | Accessibility testy (axe) v CI — **INFRA/CI** |
| 131 | Visual regression testy — **INFRA/CI** |
| 139 | Onboarding tour — checklist už existuje, chybí jen průvodce UI |
| 30 | Detekce anomálií dle země/ASN — potřebuje GeoIP závislost (blokovaná) nebo externí volání při každém přihlášení |

### Produktové kategorie (chybí, ale jde o víceníté sázky)
151 e-mail hosting · 152 SSL jako produkt · 153 object storage · 154 managed WP · 155 migrace z cPanel/Plesk

---

## EXTERNÍ — blokuje credential nebo produkce (17)

Kód je hotový nebo není potřeba; chybí přístup. **Standing rule zakazuje zapínat real writes.**

| # | Bod | Co konkrétně chybí |
|---|---|---|
| 1 | Reálné platební brány E2E | Sandbox credentials Comgate/Stripe/GoPay |
| 2 | aaPanel produkční zápisy | Rozhodnutí operátora + `AAPANEL_ALLOW_REAL_WRITES` |
| 3 | Proxmox/Pterodactyl E2E | Reálné uzly mimo produkci |
| 4 | WEDOS WAPI reálné volání | Produkční WAPI credentials |
| 5 | Sentry | `composer require sentry/sentry-laravel` + DSN |
| 16–22 | Mock→real ověření (7 bodů) | aaPanel mailboxy, DNSSEC, Comgate re-fetch, GoPay OAuth, PHP verze, UPID, orphan recovery |
| 24b | CSP enforce zapnout | Projít nasbírané violations (sběr už funguje) |
| 40 | Refund přes bránu | Refund API bran |
| 81b | APM | Externí nástroj (slow-query logging hotový) |
| 82 | Centralizované logy | Loki/ELK (`LogContext` připravený) |
| 117 | Load test | Běhové prostředí |

---

## INFRA — konfigurace mimo repozitář (16)

| # | Bod |
|---|---|
| 6 | Ověřit route/config/view cache na produkčním PHP |
| 7 | **Offsite DB zálohy** — pozor: `docs/backups.md` popisuje zálohy ZÁKAZNICKÝCH služeb (mock), DB dump offsite skutečně chybí |
| 8 | Redis jako cache/session/queue (`.env.example` má `database`) |
| 10 | Napojení `/api/up` na externí uptime službu |
| 11 | CDN + Cache-Control hlavičky |
| 12 | WAF/rate limit na reverzní proxy |
| 13 | SPF/DKIM/DMARC + bounce handling |
| 14 | Staging prostředí |
| 86 | Synthetic monitoring |
| 118 | Tailwind purge (`style.css` má 243 087 řádků) — build pipeline |
| 122–126 | Connection pooling, archivace, HTTP/2 preload… |
| 180–181 | Retenční politika, přenositelnost dat (proces + kód) |
| 196–197 | On-call rotace, DR plán + restore test |

---

## Doporučený postup

1. ~~**CHYBÍ – vysoká hodnota (10)**~~ — **hotovo (8/10)**; 141 a 92 vyžadují rozhodnutí, ne kód
2. ~~**CHYBÍ – medium/lower buildable (7)**~~ — **hotovo (dávka W): 29, 56, 64, 74, 75, 103, 178**
3. **Rozhodnout o 92** (web push) — přidat `minishlink/web-push` na Linuxu + VAPID klíče
4. **Rozhodnout o 141** (i18n) — kolik obrazovek překládat a v jakém pořadí; ráčna mezitím drží dluh
5. **Rozhodnout o 31** (šifrování PII) — rozbíjí hledání/VIES/faktury; potřebuje datovou migraci + produktové rozhodnutí
6. **Zbylé CHYBÍ (19)** — z toho většina je INFRA/CI (119, 121, 128–131), dependency-blokované (99, 100, 102, 30) nebo produktové sázky (186, 111, 112, 139, 140, 142)
7. **EXTERNÍ / INFRA / Produktové kategorie** — checklist operátorovi / DevOps / roadmap

**Nejdůležitější oprava oproti v2:** audit tvrdil ~106 bodů k implementaci.
Reálně to bylo **34** skutečné práce; z toho **23 hotovo** (dávky V+W), zbytek
je z 80 % INFRA/CI, dependency-blokované, nebo produktová rozhodnutí — ne
aplikační kód. A i uvnitř toho byl rozsah u 141 podhodnocený o dva řády
(4 161 řetězců, ne „zbytky"). **Audit odhaduje rozsah, neměří ho.**
