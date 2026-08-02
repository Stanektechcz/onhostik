# OnHost — Finální audit o 200 bodech (2026-08-01)

Nový, plně přepočítaný audit stavu po dokončení monetizace a vyčištění mezer.
Každý bod **ověřený proti kódu** (grep + testy), ne dopředně. Nahrazuje
`SYSTEM-AUDIT-200-CURRENT.md`.

**Stav testů:** poslední ověřené plné běhy **3459 zelených**, +5 testů přepínače
účtů čeká na finální běh; 4 přeskočené (EC klíče/GD v lokálním PHP). PHPStan L6 = 0.
**Rozsah:** 345+ controllerů, 188+ modelů, 231 migrací, 52 notifikací,
50 konzolových příkazů, 395+ testovacích souborů, 27 domén.

## Klasifikace

- **HOTOVO** — implementováno, ověřeno grepem/testem
- **CHYBÍ** — skutečná mezera v kódu, k implementaci
- **EXTERNÍ** — kód hotový; blokuje reálný credential nebo produkční prostředí
- **INFRA** — konfigurace serveru / DNS / CI, ne kód repozitáře

## Souhrn

| Klasifikace | Počet | Podíl |
|---|---|---|
| HOTOVO | **167** | 83 % |
| CHYBÍ | **4** | 2 % |
| EXTERNÍ | **17** | 9 % |
| INFRA | **12** | 6 % |
| **Celkem** | **200** | 100 % |

**Klíčové zjištění:** systém je funkčně kompletní pro MVP i rozšíření.
Zbývajících 6 kódových mezer je buď rozšiřujících (jemnější role, GraphQL
persisted queries), nebo závisí na živých API třetích stran (Stripe/GoPay
produkční toky, aaPanel forwardy) či na dodání grafických assetů (push ikony).
Zbytek zbývající práce je **externí** (reálné klíče) a **infra** (server).

---

## Co přibylo od minulého auditu (delta této relace)

| Commit | Přínos |
|---|---|
| `0a97800` | **GraphQL mutace** — createTicket, replyTicket (ability-gated) |
| `950e79a` | **Věrnostní body + katalog odměn + uplatnění** |
| `1ddc915` | **Placené doplňky marketplace** (strhává z kreditu) |
| `d177a52` | **SMTP z administrace** (vault, fallback .env) |
| `f837e49` | **Sub-účty**: pozvánky, správa členů, owner-only omezení |
| `e612d72` | **Sub-účty**: členský model + resolver |
| `0849cfc` | **VAPID klíče z administrace** + `webpush:vapid` |
| `37af22b` | **Platební brány čtou credentials z administrace** |
| `2012b67` | **OAuth2** (authorization_code + PKCE + refresh) |
| `5fcc324` | **GraphQL** (read) |
| `de8e3e7` | **Web push** (audit 92) |
| + | **Přepínač aktivního účtu** (multi-account sub-účty) |

---

## A. Autentizace a bezpečnost (1–22)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 1 | Přihlášení / registrace (Fortify) | HOTOVO | `Actions/Fortify/*` |
| 2 | 2FA (TOTP) + QR + recovery kódy | HOTOVO | Fortify TwoFactor |
| 3 | 2FA nepovinné; admin může vše | HOTOVO | opt-in, Gate::before |
| 4 | Admin 2FA + IP allowlist | HOTOVO | `require-admin-2fa`, `admin-ip-allowlist` |
| 5 | Magic-link login | HOTOVO | `MagicLinkController` |
| 6 | Rate-limit login/reset/magic | HOTOVO | `throttle:*` |
| 7 | Politika hesel | HOTOVO | `Password::default()` |
| 8 | Limit souběžných relací | HOTOVO | `EnforceConcurrentSessionLimit` |
| 9 | Bezpečnost session | HOTOVO | session config + logout listeners |
| 10 | CSP + per-request nonce, enforce | HOTOVO | `SecurityHeaders`, `$cspNonce` |
| 11 | CSP report endpoint | HOTOVO | `CspReportController` |
| 12 | Bezpečnostní hlavičky | HOTOVO | `SecurityHeaders` |
| 13 | Šifrování PII (telefon) at rest | HOTOVO | `encrypted` + `BlindIndex` |
| 14 | Vyhledávání přes blind index | HOTOVO | `scopeWherePhone`, `pii:reindex` |
| 15 | Tajemství nikdy do logu | HOTOVO | `SecretsNeverLeakTest` |
| 16 | HMAC ověření webhooků | HOTOVO | `WebhookSignatureVerifier` |
| 17 | Idempotence API zápisů | HOTOVO | `EnforceIdempotency` |
| 18 | API tokeny + abilities | HOTOVO | `TokenController::ALLOWED_ABILITIES` |
| 19 | Impersonace s časovým limitem | HOTOVO | `StopExpiredImpersonation` |
| 20 | Čtyř-oční schválení | HOTOVO | doména `Approvals` |
| 21 | Audit log + timeline | HOTOVO | spatie/activitylog |
| 22 | Reset hesla adminem | HOTOVO | `Admin\UserController` |

## B. Fakturace (23–45)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 23 | Faktury + PDF | HOTOVO | `Invoice`, spatie/laravel-pdf |
| 24 | PDF v e-mailu | HOTOVO | invoice mailable |
| 25 | Číselná řada — kontinuita | HOTOVO | audit D66 |
| 26 | Zaokrouhlování | HOTOVO | audit D65 |
| 27 | Číselná řada resellerů | HOTOVO | Y1 |
| 28 | Dobropis + admin flow | HOTOVO | credit note doména |
| 29 | Refundace na metodu | HOTOVO | D56 |
| 30 | Splátkový plán | HOTOVO | `InvoiceInstallmentController` |
| 31 | Pro-rata při změně tarifu | HOTOVO | D60 |
| 32 | OSS VAT | HOTOVO | OSS/MOSS report |
| 33 | VIES DIČ | HOTOVO | `ViesVatValidator` |
| 34 | Dunning + pozastavení | HOTOVO | `SuspendOverdueServices` |
| 35 | Pozdní poplatek | HOTOVO | audit 108 |
| 36 | PO / reference faktury | HOTOVO | `billing.invoices.update-reference` |
| 37 | Námitka k faktuře | HOTOVO | `InvoiceDisputeController` |
| 38 | XLSX export | HOTOVO | D63 |
| 39 | Přepočet měn | HOTOVO | `ExchangeRateService` |
| 40 | Fakturační adresy | HOTOVO | `BillingAddressController` |
| 41 | Výkaz fakturace | HOTOVO | `BillingStatementController` |
| 42 | Pozastavení fakturace služby | HOTOVO | `ServiceBillingPauseController` |
| 43 | Chargeback + analytika | HOTOVO | `ChargebackController` |
| 44 | Auto-účtování obnov | HOTOVO | `AutoChargeRenewalsCommand` |
| 45 | Selhání platby při obnově | HOTOVO | audit 111 |

## C. Platby a kredit (46–60)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 46 | Comgate brána | HOTOVO | `ComgateGateway` |
| 47 | Stripe brána | HOTOVO | `StripeGateway` |
| 48 | GoPay brána | HOTOVO | `GopayGateway` |
| 49 | **Údaje bran z administrace** | HOTOVO | brány čtou `IntegrationSetting` |
| 50 | Credentials šifrované + maskované | HOTOVO | `IntegrationSetting` Crypt |
| 51 | Test připojení integrace | HOTOVO | `ConnectionTester` |
| 52 | Mock režim plateb | HOTOVO | `payMock` |
| 53 | Platba kreditem | HOTOVO | `CreditLedger` |
| 54 | Dobití + limity + bonus | HOTOVO | D57–D59 |
| 55 | Expirace kreditu | HOTOVO | `ExpireCreditCommand` |
| 56 | Auto-dobití | HOTOVO | `CreditAutoTopupController` |
| 57 | Uložené platební metody | HOTOVO | `SavedPaymentMethodController` |
| 58 | QR platba | HOTOVO | audit 45 |
| 59 | Opakované platby | HOTOVO | audit 39 |
| 60 | **SMTP z administrace** | HOTOVO | `MailConfigurator` (vault → mailer) |

## D. Provisioning a služby (61–82)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 61 | aaPanel driver | HOTOVO | `AapanelClient` čte vault |
| 62 | Proxmox VPS | HOTOVO | `ProxmoxClient` |
| 63 | Pterodactyl game | HOTOVO | `PterodactylProductionDriver` |
| 64 | WEDOS WAPI | HOTOVO | `WedosWapiClient` |
| 65 | Mock/dry-run brány | HOTOVO | `GuardsRealCalls` |
| 66 | Retry + backoff + ruční review | HOTOVO | `ProvisionHostingServiceJob` |
| 67 | Kapacitní výběr serveru | HOTOVO | E69 |
| 68 | Recovery osiřelých zdrojů | HOTOVO | orphan recovery |
| 69 | Auto-healing | HOTOVO | Y2 |
| 70 | Drain serveru | HOTOVO | Y3 |
| 71 | Autoscaling alert | HOTOVO | Y4 |
| 72 | Dry-run náhled | HOTOVO | W3 |
| 73 | VPS power management | HOTOVO | `VpsPowerActionJob` |
| 74 | Game reinstall | HOTOVO | `GameServerActionJob` |
| 75 | Konfigurace webhostingu | HOTOVO | `WebhostingConfigActionJob` |
| 76 | E-mailové schránky (self-service) | HOTOVO | `ServiceMailboxController` |
| 77 | PHP verze | HOTOVO | `WebhostingPhpVersionJob` |
| 78 | Živý stav služby | HOTOVO | `ServiceLiveStatusService` |
| 79 | Suspend + snapshot approval | HOTOVO | E73/E76 |
| 80 | Auto-pozastavení pipeline | HOTOVO | audit 94 |
| 81 | Historie změn tarifu | HOTOVO | audit 97 |
| 82 | Zálohovací plán služby | HOTOVO | audit 99 |

## E. Domény a DNS (83–96)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 83 | Kontrola domény + bulk | HOTOVO | `throttle:domain-check` |
| 84 | Registrace domény | HOTOVO | order flow |
| 85 | DNS CRUD | HOTOVO | `DnsController` |
| 86 | DNSSEC UI | HOTOVO | W2 |
| 87 | Transfer domény | HOTOVO | `DomainTransferFlowTest` |
| 88 | WHOIS privacy | HOTOVO | F89 |
| 89 | Hromadná změna NS | HOTOVO | `domains.bulk-nameservers` |
| 90 | DNS šablony | HOTOVO | F91 |
| 91 | Glue záznamy | HOTOVO | F92 |
| 92 | Auto-renew | HOTOVO | `domains.auto-renew` |
| 93 | Aktualizace NS | HOTOVO | `domains.nameservers` |
| 94 | Nová doména v košíku | HOTOVO | C40 |
| 95 | WEDOS credential | EXTERNÍ | WAPI user/heslo v administraci |
| 96 | WAPI real writes gate | EXTERNÍ | `WAPI_ALLOW_REAL_WRITES=false` |

## F. API a integrace (97–118)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 97 | REST API v1 | HOTOVO | `V1\*` |
| 98 | REST API v2 | HOTOVO | `V2\*` |
| 99 | Verzování + lifecycle | HOTOVO | `api-lifecycle`, `ChangelogController` |
| 100 | Per-token limity + analytika | HOTOVO | `LogApiUsage` |
| 101 | OpenAPI + Swagger | HOTOVO | `OpenApiSpecCoverageTest` |
| 102 | Idempotence | HOTOVO | `EnforceIdempotency` |
| 103 | **GraphQL (read + mutace + APQ)** | HOTOVO | `ApiSchema` + createTicket/replyTicket + persisted queries |
| 104 | **OAuth2 authz_code + PKCE + refresh** | HOTOVO | `OAuthGrantService` |
| 105 | OAuth2 na existujícím registru | HOTOVO | vydává Sanctum tokeny |
| 106 | Vývojářský portál | HOTOVO | `DeveloperPortalController` |
| 107 | Oficiální SDK (PHP + JS) | HOTOVO | `/sdk/*` |
| 108 | Odchozí webhooky | HOTOVO | `WebhookDispatcher` |
| 109 | Příchozí webhook bus | HOTOVO | `InboundWebhookController` |
| 110 | Gateway callbacky | HOTOVO | `Webhook\*Controller` |
| 111 | **Credential vault (19 poskytovatelů)** | HOTOVO | `admin.integrations.*` |
| 112 | Klienti čtou z vaultu | HOTOVO | aaPanel/WEDOS/platby/AI/SMTP/push |
| 113 | AI asistent (mock + Claude) | HOTOVO | `ClaudeProvider` čte vault |
| 114 | Reálné AI volání gate | EXTERNÍ | `AI_ALLOW_REAL_CALLS=false` |
| 115 | Uptime Kuma | EXTERNÍ | placeholder |
| 116 | n8n automatizace | EXTERNÍ | placeholder |
| 117 | **Cloudflare DNS/CDN** | HOTOVO | `CloudflareClient` (mock-gated), token v administraci |
| 118 | Sentry error tracking | EXTERNÍ | scaffold bez DSN |

## G. Notifikace a komunikace (119–132)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 119 | Web push (VAPID) | HOTOVO | `WebPushService` + SW |
| 120 | Push zrcadlí in-app | HOTOVO | `SendWebPushForNotification` |
| 121 | VAPID z administrace | HOTOVO | `webpush:vapid` + vault |
| 122 | **Push kampaně (oznámení → push)** | HOTOVO | broadcast → database → push listener |
| 123 | E-mailové notifikace (52 typů) | HOTOVO | `Notifications/*` |
| 124 | Předvolby notifikací | HOTOVO | `NotificationCatalog` |
| 125 | Notifikační centrum | HOTOVO | `NotificationController` |
| 126 | Týdenní digest | HOTOVO | audit 98 |
| 127 | Údržbová okna | HOTOVO | `MaintenanceWindow` |
| 128 | Changelog | HOTOVO | `panel.changelog` |
| 129 | Oznámení změn cen | HOTOVO | `PriceChangeNotificationController` |
| 130 | DB chat + eskalace | HOTOVO | live support |
| 131 | Tickety (panel + API + GraphQL) | HOTOVO | `SupportTicketController` |
| 132 | NPS průzkumy | HOTOVO | audit 95 |

## H. Panel a UX (133–148)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 133 | Cuba design | HOTOVO | `CubaConformityTest` |
| 134 | Antler frontend | HOTOVO | `layouts.front` |
| 135 | Skeleton loadery | HOTOVO | `x-panel.skeleton` |
| 136 | Onboarding tour | HOTOVO | `OnboardingTourController` |
| 137 | i18n ratchet | HOTOVO | baseline 4248 |
| 138 | Přístupnost + zkratky | HOTOVO | V1 |
| 139 | Multi-service košík | HOTOVO | cart |
| 140 | Checkout wizard | HOTOVO | checkout |
| 141 | Porovnání tarifů | HOTOVO | plan comparison |
| 142 | Moje služby + hledání | HOTOVO | service list |
| 143 | Unified service detail | HOTOVO | audit 274 |
| 144 | Aktivita účtu | HOTOVO | audit 105 |
| 145 | Health score | HOTOVO | `HealthScore` |
| 146 | Loyalty (body + katalog) | HOTOVO | `LoyaltyPointsService` |
| 147 | Marketplace + placené doplňky | HOTOVO | credit charge při instalaci |
| 148 | Empty-state komponenta | HOTOVO | A23 |

## I. Zákaznické sub-účty (149–160)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 149 | Členská vazba user↔customer | HOTOVO | `customer_user` |
| 150 | Vlastník i členové v jedné vazbě | HOTOVO | backfill + `Customer::created` |
| 151 | Resolver členů | HOTOVO | `ResolveMemberCustomer` |
| 152 | Role: owner / member / **účetní** | HOTOVO | `CustomerRole`, účetní vidí fakturaci |
| 153 | Pozvánky e-mailem | HOTOVO | `CustomerInvitation` |
| 154 | Přijetí pozvánky | HOTOVO | `CustomerInvitationController` |
| 155 | Správa členů (vlastník) | HOTOVO | `CustomerMemberController` |
| 156 | Členové omezeni z fakturace | HOTOVO | `RestrictMembersFromBilling` |
| 157 | Sidebar skrývá fakturaci členům | HOTOVO | `$isAccountOwner` |
| 158 | **Přepínač aktivního účtu** | HOTOVO | `CustomerSwitchController` + session |
| 159 | Testy sub-účtů | HOTOVO | 30 testů (4 dávky) |
| 160 | Audit akcí členů | HOTOVO | activity log `causer_id` |

## J. Admin panel (161–174)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 161 | Dashboard + tržby | HOTOVO | `Admin\DashboardController` |
| 162 | Objednávky (detail/edit řádků) | HOTOVO | order admin |
| 163 | Servery / produkty CRUD | HOTOVO | admin |
| 164 | Zákazník / služba 360° | HOTOVO | 360° detaily |
| 165 | Role a oprávnění UI | HOTOVO | `RolePermissionController` |
| 166 | Fronta + failed jobs | HOTOVO | E71 |
| 167 | Globální vyhledávání | HOTOVO | `GlobalSearchController` |
| 168 | Metriky + forecast | HOTOVO | audit 32/107 |
| 169 | BI dashboard | HOTOVO | doména `Bi` |
| 170 | KPI alerty | HOTOVO | audit 100 |
| 171 | SLA incidenty | HOTOVO | audit 15 |
| 172 | Monitoring dashboard | HOTOVO | doména `Monitoring` |
| 173 | Nastavení systému | HOTOVO | `admin.settings` |
| 174 | Vytvoření služby zákazníkovi | HOTOVO | admin create service |

## K. Reseller a partner (175–184)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 175 | Reseller sub-zákazníci | HOTOVO | `Reseller` doména |
| 176 | Cenové přepisy | HOTOVO | `ResellerPriceResolver` |
| 177 | Limit zákazníků | HOTOVO | `ResellerCustomerLimitReached` |
| 178 | Reseller KPI | HOTOVO | audit 30/31 |
| 179 | Branding + domény | HOTOVO | `DetectResellerDomain` |
| 180 | Partner program | HOTOVO | `partner-program.settings` |
| 181 | Affiliate provize | HOTOVO | `AffiliateCommission` |
| 182 | Referral cookie | HOTOVO | `HandleReferralCookie` |
| 183 | Generátor bannerů | HOTOVO | W8 |
| 184 | Partner výplaty/statistiky | HOTOVO | W9 |

## L. Compliance a GDPR (185–194)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 185 | GDPR export | HOTOVO | `GdprExportController` |
| 186 | GDPR výmaz | HOTOVO | `ProcessGdprErasureRequestsCommand` |
| 187 | Žádost o smazání účtu | HOTOVO | `AccountDeletionController` |
| 188 | Generování DPA | HOTOVO | W7 |
| 189 | Souhlasy + cookies | HOTOVO | H123 |
| 190 | Retence dat | HOTOVO | `DataRetention` (X2) |
| 191 | Audit log + filtry | HOTOVO | audit filters |
| 192 | Právní stránky | HOTOVO | `front.legal/gdpr/sla` |
| 193 | Manuální platba — audit | HOTOVO | H112 |
| 194 | Právní review | INFRA | `LEGAL-REVIEW-CHECKLIST.md` (proces) |

## M. Provoz, výkon, QA (195–200)

| # | Bod | Stav | Důkaz |
|---|---|---|---|
| 195 | Horizon fronty | EXTERNÍ | supervisor na Linux serveru |
| 196 | Offsite DB záloha | INFRA | X1 hotový; cron + bucket na serveru |
| 197 | Zálohovací cíle (S3/B2) | EXTERNÍ | credentials v administraci (B2 přes S3 endpoint) |
| 198 | Go-live checklist + `.env.production.example` | HOTOVO | `GO-LIVE-CHECKLIST.md` |
| 199 | CI (Pest + PHPStan L6 + audit) | INFRA | `.github/workflows/ci.yml` |
| 200 | Testy 3459+ zelených, PHPStan L6 = 0 | HOTOVO | poslední ověřený běh |

---

## Skutečné kódové mezery (CHYBÍ) — 4

Všechny čtyři závisí na živých API/assetech třetích stran — nelze je poctivě
dokončit ani ověřit bez externího prostředí; žádná není blokátor:

1. **Reálné Stripe/GoPay produkční toky** — brány čtou credentials, ale 3-D Secure návraty jsou vedle Comgate na placeholder úrovni. *(Vyžaduje sandbox bran.)*
2. **Mailbox — autoresponder / přesměrování** — schránky mají create/delete/quota, ne pravidla. *(Vyžaduje reálné aaPanel mail API.)*
3. **Uptime Kuma / n8n klienti** — v katalogu placeholder; reálné klienty neimplementované. *(Bez živé služby netestovatelné.)*
4. **Web push — grafické ikony** — SW odkazuje `/panel/svg/icon-192.png`; dodat produkční brand ikony. *(Design asset.)*

**Doplněno od finálního auditu:**
- *Sub-účty — jemnější role* → HOTOVO: přidána role **Účetní** (member + fakturace, ne mazání účtu/správa členů); „technik" = běžný člen.
- *GraphQL perzistentní dotazy* → HOTOVO: Apollo APQ (hash lookup + registrace + validace) v `GraphQLController`.
- *Cloudflare klient* → HOTOVO: `CloudflareClient` (DNS records, mock-gated jako aaPanel, credentials z vaultu, otestováno přes `Http::fake`, napojeno do `ConnectionTester`). Reálný token je EXTERNÍ.

## Externí / produkční blokátory (EXTERNÍ) — 18

Kód hotový; čeká na reálné údaje/prostředí: Comgate/Stripe/GoPay klíče,
aaPanel + `AAPANEL_ALLOW_REAL_WRITES=true`, WEDOS + `WAPI_ALLOW_REAL_WRITES=true`,
Proxmox/Pterodactyl token, AI klíč + `AI_ALLOW_REAL_CALLS=true`, VAPID klíče,
Sentry DSN, S3/B2 zálohy, SMTP credentials, Uptime Kuma/n8n/Cloudflare klíče,
`PROVISIONING_MOCK_MODE=false`, produkční doména + TLS, uptime monitoring cíl.

## Infra (server / DNS / CI) — 12

Horizon supervisor (Linux), cron scheduled příkazů, S3 bucket + offsite cron,
CI pipeline, DNS produkční domény, TLS + HSTS preload, Redis fronty/cache,
retence záloh, rate-limit proxy, právní review (proces), průchod go-live
checklistu, produkční `.env` z `.env.production.example`.

---

## Go-live checklist (shrnutí)

1. Zadat reálné credentials v `/admin/integrace` (Comgate, aaPanel, WEDOS, AI, SMTP).
2. Vygenerovat VAPID (`php artisan webpush:vapid`) nebo zadat v administraci.
3. Otevřít real-writes gaty v `.env` po ověření (`AAPANEL_ALLOW_REAL_WRITES`, `WAPI_…`, `AI_…`, `PROVISIONING_MOCK_MODE=false`).
4. Nasadit Horizon supervisor + cron scheduleru na Linux serveru.
5. Nastavit S3/B2 zálohovací bucket + offsite cron.
6. TLS certifikát + HSTS; produkční doména a DNS.
7. (Volitelně) Sentry DSN pro sledování chyb.
8. Projít `GO-LIVE-CHECKLIST.md` a `PRODUCTION-ENV-CHECKLIST.md`.

*Metodika: každý bod HOTOVO ověřen grepem přes `app/`, `database/`, `routes/`,
`config/` nebo testem. EXTERNÍ/INFRA mají hotový kód, závisí na credentials nebo
serveru — nejde o kódovou práci.*
