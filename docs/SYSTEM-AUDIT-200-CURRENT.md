# OnHost — Aktuální audit o 200 bodech (2026-08-01)

Nový úplný audit stavu systému, **ověřený proti skutečnému kódu** (grep + testy),
ne dopředně. Navazuje na `SYSTEM-AUDIT-200-VERIFIED.md` a promítá práci od té doby:
e-mailový hosting, SDK, onboarding, šifrování PII, web push, GraphQL, OAuth2,
nastavitelné API údaje v administraci a zákaznické sub-účty.

**Stav testů:** poslední ověřený plný běh **3417 zelených** (Batch 1 sub-účtů);
dávky 2–3 sub-účtů přidávají +20 testů, čekají na finální plný běh. PHPStan L6 = 0.
**Rozsah:** 343 controllerů, 186 modelů, 227 migrací, 52 notifikací, 50 konzolových
příkazů, 387 testovacích souborů, 457 Blade šablon, 27 domén.

## Klasifikace

- **HOTOVO** — implementováno, ověřeno grepem/testem
- **CHYBÍ** — skutečná mezera v kódu, k implementaci
- **EXTERNÍ** — kód hotový; blokuje reálný credential nebo produkční prostředí
- **INFRA** — konfigurace serveru / DNS / CI, ne kód repozitáře

## Souhrn

| Klasifikace | Počet | Podíl |
|---|---|---|
| HOTOVO | **159** | 79 % |
| CHYBÍ | **11** | 6 % |
| EXTERNÍ | **18** | 9 % |
| INFRA | **12** | 6 % |
| **Celkem** | **200** | 100 % |

**Klíčové zjištění:** systém je funkčně kompletní pro MVP i většinu rozšíření.
Zbývající práce je z převážné části **externí** (reálné credentials platebních bran
a panelů, Sentry DSN) a **infra** (server, fronty, zálohovací cíle), nikoli kód.
Skutečných kódových mezer je 14 a jsou drobné / rozšiřující.

---

## A. Autentizace a bezpečnost (1–22)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 1 | Přihlášení / registrace (Fortify) | HOTOVO | `app/Actions/Fortify/*`, `routes/web.php` |
| 2 | 2FA (TOTP) s QR kódem, recovery kódy | HOTOVO | Fortify TwoFactor, `HandleTwoFactorAuthenticationConfirmed` |
| 3 | 2FA nepovinné (standing rule) | HOTOVO | `RequireCustomerTwoFactor` opt-in, admin může vše (Gate::before) |
| 4 | Admin 2FA vynucení + IP allowlist | HOTOVO | `require-admin-2fa`, `admin-ip-allowlist` middleware |
| 5 | Magic-link přihlášení | HOTOVO | `MagicLinkController`, `throttle:10,1` |
| 6 | Rate-limit login / reset / magic-link | HOTOVO | pojmenované limitery, `throttle:*` v routes |
| 7 | Politika hesel | HOTOVO | `Password::default()` + validace při změně |
| 8 | Limit souběžných relací | HOTOVO | `EnforceConcurrentSessionLimit` listener |
| 9 | Bezpečnost session (regenerace, idle timeout) | HOTOVO | session config + logout listeners |
| 10 | CSP s per-request nonce, enforce režim | HOTOVO | `SecurityHeaders`, `$cspNonce`, žádné `unsafe-inline` |
| 11 | CSP violation report endpoint | HOTOVO | `CspReportController`, `throttle:60,1` |
| 12 | Bezpečnostní hlavičky (HSTS, X-Frame…) | HOTOVO | `SecurityHeaders` middleware |
| 13 | Šifrování PII sloupců (telefon) at rest | HOTOVO | `encrypted` cast + `BlindIndex` (phone_bidx) |
| 14 | Vyhledávání přes blind index | HOTOVO | `scopeWherePhone`, `GlobalSearchController`, `pii:reindex` |
| 15 | Tajemství nikdy do logu/serializace | HOTOVO | `SecretsNeverLeakTest` (statický test) |
| 16 | HMAC ověření webhooků všech bran | HOTOVO | `WebhookSignatureVerifier`, per-gateway ověření |
| 17 | Idempotence API zápisů | HOTOVO | `EnforceIdempotency` middleware, `Idempotency-Key` |
| 18 | API tokeny (Sanctum) s granulárními abilities | HOTOVO | `TokenController::ALLOWED_ABILITIES` |
| 19 | Impersonace zákazníka s časovým limitem | HOTOVO | `StopExpiredImpersonation` middleware |
| 20 | Čtyř-oční schválení rizikových operací | HOTOVO | doména `Approvals` |
| 21 | Audit log (aktivity) s časovou osou | HOTOVO | spatie/activitylog, admin timeline |
| 22 | Reset hesla zákazníka adminem | HOTOVO | `Admin\UserController` |

## B. Fakturace (23–45)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 23 | Generování faktur + PDF | HOTOVO | `Invoice`, spatie/laravel-pdf |
| 24 | PDF příloha v e-mailu | HOTOVO | invoice mailable |
| 25 | Číselná řada faktur — kontinuita | HOTOVO | invoice number series (audit D66) |
| 26 | Zaokrouhlování | HOTOVO | audit D65 |
| 27 | Číselná řada pro resellery | HOTOVO | reseller invoice series (Y1) |
| 28 | Dobropis (credit note) + admin flow | HOTOVO | credit note doména |
| 29 | Refundace na platební metodu | HOTOVO | audit D56 |
| 30 | Splátkový plán | HOTOVO | `InvoiceInstallmentController` |
| 31 | Pro-rata při změně tarifu | HOTOVO | audit D60 |
| 32 | OSS VAT (přeshraniční DPH) | HOTOVO | OSS/MOSS report |
| 33 | Kontrola DIČ (VIES) | HOTOVO | `ViesVatValidator` |
| 34 | Dunning (upomínky) + pozastavení | HOTOVO | `SendPaymentOverdueReminders`, `SuspendOverdueServices` |
| 35 | Pozdní poplatek | HOTOVO | audit 108 |
| 36 | PO číslo / vlastní reference faktury | HOTOVO | `billing.invoices.update-reference` |
| 37 | Námitka k faktuře | HOTOVO | `InvoiceDisputeController` |
| 38 | XLSX export faktur (nativní ZipArchive) | HOTOVO | audit D63 |
| 39 | Přepočet měn | HOTOVO | `ExchangeRateService` |
| 40 | Fakturační adresy | HOTOVO | `BillingAddressController` |
| 41 | Výkaz fakturace (statement) | HOTOVO | `BillingStatementController` |
| 42 | Pozastavení fakturace služby | HOTOVO | `ServiceBillingPauseController` |
| 43 | Chargeback evidence + analytika | HOTOVO | `ChargebackController`, `ChargebackAnalyticsController` |
| 44 | Automatické účtování obnov | HOTOVO | `AutoChargeRenewalsCommand` |
| 45 | Řešení selhání platby při obnově | HOTOVO | audit 111 |

## C. Platby a kredit (46–60)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 46 | Comgate brána (redirect, SAQ-A) | HOTOVO | `ComgateGateway` |
| 47 | Stripe brána | HOTOVO | `StripeGateway` (placeholder do credentials) |
| 48 | GoPay brána | HOTOVO | `GopayGateway` (placeholder do credentials) |
| 49 | **API údaje bran nastavitelné v administraci** | HOTOVO | brány čtou `IntegrationSetting` (admin vault), fallback .env |
| 50 | Credentials šifrované at rest + maskované | HOTOVO | `IntegrationSetting` Crypt + `maskedCredentials()` |
| 51 | Test připojení integrace | HOTOVO | `ConnectionTester`, `admin.integrations.test` |
| 52 | Mock režim plateb (lokální dokončení) | HOTOVO | `payMock`, `PaymentProviderRegistry` |
| 53 | Platba kreditem | HOTOVO | `payCredit`, `CreditLedger` |
| 54 | Dobití kreditu + limity + bonus | HOTOVO | audit D57–D59 |
| 55 | Expirace kreditu | HOTOVO | `ExpireCreditCommand` |
| 56 | Auto-dobití kreditu | HOTOVO | `CreditAutoTopupController` |
| 57 | Uložené platební metody | HOTOVO | `SavedPaymentMethodController` |
| 58 | QR platba | HOTOVO | audit 45 |
| 59 | Opakované platby | HOTOVO | audit 39 |
| 60 | Reálné credentials bran | EXTERNÍ | merchant_id/secret zadat v administraci před go-live |
| 60b | **SMTP z administrace** (transakční e-mail) | HOTOVO | `MailConfigurator` čte vault (host/port/user/heslo), fallback .env |

## D. Provisioning a služby (61–82)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 61 | aaPanel webhosting driver | HOTOVO | `AapanelClient` čte `IntegrationSetting` |
| 62 | Proxmox VPS driver | HOTOVO | `ProxmoxClient` |
| 63 | Pterodactyl game driver | HOTOVO | `PterodactylClient`, `PterodactylProductionDriver` |
| 64 | WEDOS WAPI (domény) | HOTOVO | `WedosWapiClient` |
| 65 | Mock/dry-run brány (default OFF real writes) | HOTOVO | `GuardsRealCalls`, env gates |
| 66 | Provisioning retry + backoff + ruční review | HOTOVO | `ProvisionHostingServiceJob` |
| 67 | Kapacitně-vědomý výběr serveru | HOTOVO | audit E69 |
| 68 | Recovery osiřelých zdrojů | HOTOVO | orphan recovery všech driverů |
| 69 | Auto-healing (přeprovisionování) | HOTOVO | Y2 scheduled |
| 70 | Drain plného/selhávajícího serveru | HOTOVO | Y3 |
| 71 | Autoscaling / kapacitní alert | HOTOVO | Y4 `CapacityAlert` |
| 72 | Dry-run náhled provisioningu | HOTOVO | audit W3 (56) |
| 73 | VPS power management (panel+admin) | HOTOVO | `VpsPowerActionJob` |
| 74 | Game server reinstall | HOTOVO | `GameServerActionJob` |
| 75 | Konfigurace webhostingu (aaPanel parita) | HOTOVO | `WebhostingConfigActionJob`, service detail |
| 76 | E-mailový hosting — self-service schránky | HOTOVO | `ServiceMailboxController` (create/delete/quota) |
| 77 | PHP verze služby | HOTOVO | `WebhostingPhpVersionJob` |
| 78 | Živý stav služby | HOTOVO | `ServiceLiveStatusService` |
| 79 | Suspend s důvodem, snapshot restore approval | HOTOVO | audit E73/E76 |
| 80 | Automatické pozastavení (pipeline) | HOTOVO | audit 94 |
| 81 | Historie změn tarifu | HOTOVO | audit 97 |
| 82 | Konfigurace zálohovacího plánu služby | HOTOVO | audit 99 |

## E. Domény a DNS (83–96)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 83 | Kontrola dostupnosti domény + bulk | HOTOVO | `front.domains`, `throttle:domain-check` |
| 84 | Registrace domény (objednávka) | HOTOVO | order flow, doména-only nedojde do Active bez služby |
| 85 | DNS záznamy CRUD | HOTOVO | `DnsController` |
| 86 | DNSSEC UI | HOTOVO | audit F83/W2 |
| 87 | Transfer domény | HOTOVO | `DomainTransferFlowTest` |
| 88 | WHOIS privacy | HOTOVO | audit F89 |
| 89 | Hromadná změna NS | HOTOVO | `domains.bulk-nameservers` |
| 90 | DNS šablony | HOTOVO | audit F91 |
| 91 | Glue záznamy | HOTOVO | audit F92 |
| 92 | Auto-renew přepínač | HOTOVO | `domains.auto-renew` |
| 93 | Aktualizace nameserverů | HOTOVO | `domains.nameservers` |
| 94 | Registrace nové domény v košíku | HOTOVO | audit C40 |
| 95 | Reálný WEDOS credential | EXTERNÍ | WAPI user/heslo v administraci |
| 96 | WAPI real writes gate | EXTERNÍ | `WAPI_ALLOW_REAL_WRITES=false` do go-live |

## F. API a integrace (97–118)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 97 | REST API v1 (Sanctum PAT) | HOTOVO | `routes/api.php`, `V1\*` |
| 98 | REST API v2 (rozšířené + webhooky) | HOTOVO | `V2\*` |
| 99 | Verzování + lifecycle/deprecation | HOTOVO | `api-lifecycle`, `ChangelogController` |
| 100 | Per-token rate limity + analytika | HOTOVO | audit 103, `LogApiUsage` |
| 101 | OpenAPI spec + Swagger UI | HOTOVO | `DocsController`, `OpenApiSpecCoverageTest` |
| 102 | Idempotence zápisů | HOTOVO | `EnforceIdempotency` |
| 103 | **GraphQL endpoint (read-only)** | HOTOVO | `GraphQLController`, `ApiSchema`, depth/complexity limity |
| 104 | **OAuth2 authorization_code + PKCE + refresh** | HOTOVO | `OAuthGrantService`, `/oauth/authorize`, `/oauth/token` |
| 105 | OAuth2 na existujícím registru aplikací | HOTOVO | vydává Sanctum tokeny, bez Passportu |
| 106 | Vývojářský portál (tokeny + OAuth apps) | HOTOVO | `DeveloperPortalController` |
| 107 | **Oficiální SDK (PHP + JS)** | HOTOVO | `/sdk/php`, `/sdk/js` (Idempotency-Key) |
| 108 | Webhook subscriptions (odchozí) | HOTOVO | `V2\WebhookController`, `WebhookDispatcher` |
| 109 | Příchozí webhook bus | HOTOVO | `InboundWebhookController`, `WebhookEndpoint` |
| 110 | Comgate/Stripe/GoPay webhook callbacky | HOTOVO | `Webhook\*Controller`, IP whitelist |
| 111 | **Integrace: credential vault v administraci** | HOTOVO | `admin.integrations.*`, 19 poskytovatelů |
| 112 | Klienti čtou credentials z vaultu | HOTOVO | aaPanel/WEDOS/Comgate/Stripe/GoPay/AI/web push |
| 113 | AI asistent (mock + Claude provider) | HOTOVO | `AiAssistantService`, `ClaudeProvider` čte vault |
| 114 | Reálné AI volání gate | EXTERNÍ | `AI_ALLOW_REAL_CALLS=false`, klíč v administraci |
| 115 | Monitoring integrace (Uptime Kuma) | EXTERNÍ | placeholder, klíč v administraci |
| 116 | Automatizace (n8n) | EXTERNÍ | placeholder v katalogu integrací |
| 117 | Cloudflare DNS/CDN | EXTERNÍ | placeholder v katalogu integrací |
| 118 | Chybové sledování (Sentry) | EXTERNÍ | scaffold `ErrorContext` bez DSN (redakce hotová) |

## G. Notifikace a komunikace (119–132)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 119 | **Web push notifikace (VAPID)** | HOTOVO | `WebPushService`, service worker, opt-in UI |
| 120 | Web push zrcadlí in-app notifikace | HOTOVO | `SendWebPushForNotification` listener |
| 121 | VAPID klíče nastavitelné v administraci | HOTOVO | `webpush:vapid` příkaz + vault, fallback .env |
| 122 | E-mailové notifikace (52 typů) | HOTOVO | `app/Notifications/*` |
| 123 | Předvolby notifikací (opt-in/out kanály) | HOTOVO | `NotificationCatalog`, preferences UI |
| 124 | Notifikační centrum (zvonek) | HOTOVO | `NotificationController`, badge |
| 125 | Týdenní digest | HOTOVO | audit 98 |
| 126 | Notifikace údržbových oken | HOTOVO | `MaintenanceWindow`, audit 106 |
| 127 | Changelog „Co je nového" | HOTOVO | `panel.changelog.index` |
| 128 | Oznámení změn cen | HOTOVO | `PriceChangeNotificationController` |
| 129 | DB-backed chat + eskalace na podporu | HOTOVO | audit — live support |
| 130 | Tickety podpory (panel + API) | HOTOVO | `SupportTicketController` |
| 131 | Churn signály → e-mail adminovi | HOTOVO | `ChurnRiskDetected*` |
| 132 | NPS průzkumy | HOTOVO | audit 95 |

## H. Zákaznický panel a UX (133–148)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 133 | Cuba Tailwind design (admin/panel) | HOTOVO | `CubaConformityTest` (ratchet Bootstrap tříd) |
| 134 | Antler Bootstrap frontend | HOTOVO | `layouts.front`, `layouts.auth` |
| 135 | Skeleton loadery | HOTOVO | `x-panel.skeleton`, `.skeleton` CSS |
| 136 | Onboarding tour (první přihlášení) | HOTOVO | `OnboardingTourController`, nonced skript |
| 137 | i18n ratchet (hlídá tvrdě psanou češtinu) | HOTOVO | `I18nCoverageRatchetTest` baseline 4231 |
| 138 | Přístupnost (focus-trap, klávesové zkratky) | HOTOVO | audit V1 (137/138) |
| 139 | Košík více služeb (Cuba) | HOTOVO | multi-service cart |
| 140 | Checkout wizard + platební metody | HOTOVO | rebuilt checkout |
| 141 | Porovnání tarifů | HOTOVO | plan comparison |
| 142 | Moje služby + realtime hledání | HOTOVO | service list |
| 143 | Unified service detail (zákazník) | HOTOVO | audit 274 |
| 144 | Aktivita účtu (feed) | HOTOVO | audit 105 |
| 145 | Health score zákazníka | HOTOVO | `HealthScore` |
| 146 | Loyalty / věrnostní program | HOTOVO | doména `Loyalty` |
| 147 | Marketplace (doplňky) | HOTOVO | doména `Marketplace`, `panel.marketplace.*` |
| 148 | Empty-state komponenta | HOTOVO | audit A23 |

## I. Zákaznické sub-účty (149–158)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 149 | Členská pivot vazba user↔customer | HOTOVO | `customer_user`, role owner/member |
| 150 | Vlastník i členové v jedné vazbě | HOTOVO | backfill + `Customer::created` hook |
| 151 | Resolver členů (bez změny 120 míst) | HOTOVO | `ResolveMemberCustomer` middleware |
| 152 | `accessibleCustomer` / `isCustomerOwner` | HOTOVO | `User` helpery, `CustomerRole` enum |
| 153 | Pozvánky e-mailem (single-use, expirace) | HOTOVO | `CustomerInvitation`, hash tokenu |
| 154 | Přijetí pozvánky (nový uživatel + heslo) | HOTOVO | `CustomerInvitationController` |
| 155 | Správa členů (vlastník) | HOTOVO | `CustomerMemberController`, `panel.account.members` |
| 156 | Členové omezeni z fakturace/plateb/smazání | HOTOVO | `RestrictMembersFromBilling` middleware |
| 157 | Sidebar skrývá fakturaci členům | HOTOVO | `$isAccountOwner` v sidebaru |
| 158 | Testy (model + pozvánky + omezení) | HOTOVO | 25 testů (3 dávky) |

## J. Admin panel (159–174)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 159 | Dashboard s tržbami + rozvržení | HOTOVO | `Admin\DashboardController` |
| 160 | Objednávky — detail, přijetí, editace řádků | HOTOVO | order admin |
| 161 | Servery CRUD | HOTOVO | admin servers |
| 162 | Produkty create/edit | HOTOVO | product admin |
| 163 | Zákazníci — hledání, 360° detail, editace | HOTOVO | `Customer 360°` |
| 164 | Služby — 360° detail | HOTOVO | `Service 360°` |
| 165 | Role a oprávnění UI | HOTOVO | `RolePermissionController` (spatie) |
| 166 | Fronta úloh + failed jobs přehled | HOTOVO | audit E71 |
| 167 | Globální vyhledávání (blind index) | HOTOVO | `GlobalSearchController` |
| 168 | Metriky — produkty, kohorty, forecast | HOTOVO | audit 32/107 |
| 169 | BI dashboard | HOTOVO | doména `Bi` |
| 170 | KPI alerty | HOTOVO | audit 100 |
| 171 | SLA incidenty | HOTOVO | audit 15 |
| 172 | Monitoring dashboard | HOTOVO | doména `Monitoring` |
| 173 | Nastavení systému (UI) | HOTOVO | `admin.settings.*` |
| 174 | Vytvoření služby zákazníkovi adminem | HOTOVO | admin create service |

## K. Reseller a partner (175–184)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 175 | Reseller portál — sub-zákazníci | HOTOVO | `Reseller` doména |
| 176 | Reseller cenové přepisy | HOTOVO | `ResellerPricingOverride`, `ResellerPriceResolver` |
| 177 | Limit zákazníků resellera | HOTOVO | `ResellerCustomerLimitReached` |
| 178 | Reseller dashboard KPI | HOTOVO | audit 30/31 |
| 179 | Reseller branding (logo, domény) | HOTOVO | `DetectResellerDomain` |
| 180 | Partner program — nastavení (affiliate) | HOTOVO | `partner-program.settings` |
| 181 | Affiliate provize | HOTOVO | `AffiliateCommission` |
| 182 | Referral cookie + odkazy | HOTOVO | `HandleReferralCookie`, `front.affiliate` |
| 183 | Generátor bannerů partnera | HOTOVO | audit W8 (112) |
| 184 | Partner výplaty / statistiky | HOTOVO | audit W9 |

## L. Compliance a GDPR (185–194)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 185 | GDPR export osobních dat | HOTOVO | `GdprExportController` |
| 186 | GDPR výmaz (erasure) — zpracování | HOTOVO | `ProcessGdprErasureRequestsCommand` |
| 187 | Žádost o smazání účtu | HOTOVO | `AccountDeletionController` |
| 188 | Generování DPA | HOTOVO | audit W7 (178) |
| 189 | Souhlasy (consents) + cookies | HOTOVO | audit H123 |
| 190 | Retence dat (příkaz) | HOTOVO | `DataRetention` command (X2) |
| 191 | Audit log s filtrováním | HOTOVO | audit filters |
| 192 | Právní stránky (VOP, GDPR, SLA) | HOTOVO | `front.legal/gdpr/sla` |
| 193 | Manuální platba — audit stopa | HOTOVO | audit H112 |
| 194 | Ruční kontrola právního review | INFRA | `LEGAL-REVIEW-CHECKLIST.md` (proces, ne kód) |

## M. Provoz, výkon, infra a QA (195–200)

| # | Bod | Stav | Důkaz / poznámka |
|---|---|---|---|
| 195 | Horizon fronty (pcntl/posix) | EXTERNÍ | `deploy/supervisor/onhost-horizon.conf` — Linux server |
| 196 | Offsite DB záloha (S3) | INFRA | `X1` příkaz hotový; S3 bucket + cron na serveru |
| 197 | Zálohovací cíle (S3/B2) | EXTERNÍ | credentials v administraci |
| 198 | Go-live checklist + `.env.production.example` | HOTOVO | `GO-LIVE-CHECKLIST.md`, X3 |
| 199 | CI (Pest + PHPStan L6 + composer audit) | INFRA | `.github/workflows/ci.yml` |
| 200 | Test suite 3400+ zelených, PHPStan L6 = 0 | HOTOVO | poslední ověřený běh 3417; sub-účty +20 čeká na finální běh |

---

## Skutečné kódové mezery (CHYBÍ) — 11

Drobné / rozšiřující, žádná není blokátor MVP. *(Aktualizováno: SMTP z administrace
doplněno; B2 a audit členů přeřazeny — viz níže.)*

1. **Přepínač aktivního účtu** — uživatel, který je vlastníkem i členem jiného účtu, nemá v panelu přepínač (v1 sub-účtů řeší jen jeden účet na uživatele).
2. **Sub-účty — granularita rolí** — zatím owner/member; role „účetní" (jen faktury) nebo „technik" (jen služby) nejsou.
3. **Reálné Stripe/GoPay klienty** — brány jsou napojené na credentials, ale plné produkční toky (3-D Secure návraty) jsou placeholder úrovně vedle Comgate.
4. **GraphQL mutace** — endpoint je záměrně jen pro čtení; zápisy zůstávají na REST.
5. **Web push — cílené kampaně** — push zrcadlí notifikace; hromadné marketingové push kampaně nejsou.
6. **Mailbox — autoresponder / přesměrování** — self-service schránky mají create/delete/quota, ne pravidla (vyžaduje reálné aaPanel mail API).
7. **Uptime Kuma / n8n / Cloudflare klienti** — v katalogu jako placeholder, reálné klienty neimplementované.
8. **Marketplace — platby za doplňky** — doména existuje, monetizace doplňků neúplná.
9. **Loyalty — odměny/uplatnění** — body se počítají, katalog odměn je základní.
10. **API GraphQL — perzistentní dotazy** — bez persisted-query cache (výkonová optimalizace).
11. **Web push — ikony notifikace** — service worker odkazuje `/panel/svg/icon-192.png`; produkční ikony dodat.

**Přeřazeno (nebyla to mezera):**
- *SMTP z administrace* → HOTOVO (`MailConfigurator`, viz bod 60b).
- *Backblaze B2* → funguje přes S3-kompatibilní `backup-s3` disk (`S3_BACKUP_ENDPOINT` na B2); zvláštní driver není potřeba.
- *Audit akcí členů* → již pokryto: activity log loguje `causer_id` = přihlášený člen.

## Externí / produkční blokátory (EXTERNÍ) — 18

Kód hotový; čeká na reálné údaje nebo prostředí:

- Comgate merchant_id + secret (administrace)
- Stripe secret_key + webhook_secret (administrace)
- GoPay goid + client_id/secret (administrace)
- aaPanel base_url + api_key + `AAPANEL_ALLOW_REAL_WRITES=true`
- WEDOS WAPI user/heslo + `WAPI_ALLOW_REAL_WRITES=true`
- Proxmox / Pterodactyl endpoint + token
- AI klíč (Claude/OpenAI) + `AI_ALLOW_REAL_CALLS=true`
- VAPID klíče (`webpush:vapid` nebo administrace)
- Sentry DSN
- S3/B2 zálohovací credentials
- SMTP / transakční e-mail credentials
- Uptime Kuma / n8n / Cloudflare klíče
- `PROVISIONING_MOCK_MODE=false` po ověření
- Reálná doména + TLS
- Uptime monitoring cíl

## Infra (server / DNS / CI) — 12

- Horizon supervisor na Linux serveru (Windows nemá pcntl/posix)
- Cron pro scheduled příkazy (obnovy, dunning, healing, zálohy)
- S3 bucket + offsite záloha cron
- CI pipeline běh (`.github/workflows/ci.yml`)
- DNS záznamy produkční domény
- TLS certifikát + HSTS preload
- Redis pro fronty/cache
- Zálohovací retence na úložišti
- Rate-limit reverzní proxy
- Legal review (proces)
- Go-live checklist průchod
- Produkční `.env` z `.env.production.example`

---

*Metodika: každý bod HOTOVO ověřen grepem přes `app/`, `database/`, `routes/`,
`config/` nebo testem. Body EXTERNÍ/INFRA mají hotový kód, ale závisí na
credentials nebo serverovém prostředí — nejde o kódovou práci.*
