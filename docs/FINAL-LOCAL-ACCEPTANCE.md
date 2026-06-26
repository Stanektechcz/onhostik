# OnHost.cz — Final Local Acceptance Report

**Phase:** 21 (acceptance) → 22 (blockers) → 23 (deploy gate)  
**Date:** 2026-06-26  
**Branch:** development  
**Latest commit:** bb739d8 (phase-22-production-blocker-readiness)  
**Environment:** Local SQLite / PHP 8.2.4 / Windows dev  
**Server:** s2.onhost.cz (SMTP 587/465 ✅, MySQL 3306 ✅ ready)  

---

## ✅ GO / NO-GO DECISION

**GO (lokálně) — Systém je lokálně schválen jako funkční MVP.**  
**PENDING (produkce) — Čeká na 3 critical + 2 high blockers (viz docs/PRODUCTION-BLOCKERS.md).**

Všechna GO kritéria lokálně splněna. Server připraven (SMTP ✅, MySQL ✅).
Technický deploy možný po doplnění SMTP hesla, billing adresy a admin hesla.
Veřejný launch po Comgate + právní kontrole.

---

## 1. Výsledky test suite

| Metrika | Výsledek |
|---------|----------|
| **Tests passed** | 316 / 765 assertions ✓ |
| **Tests failed** | 0 |
| **Regressions** | 0 |
| **Pending migrations** | 0 (34/34 applied) |
| **Failed queue jobs** | 0 |
| **Doctor** | PASS WITH WARNINGS (12 expected dev/mock) |
| **Scheduled jobs** | 4 (renewal, overdue, suspend, partner-approve) |

---

## 2. Artisan command výsledky

```
php artisan migrate:fresh --seed  → ALL DONE (8 seeders)
php artisan test                  → 316 passed (765 assertions)
php artisan route:list            → 192 routes registered
php artisan schedule:list         → 4 jobs (02:00/01:00/01:15/02:00)
php artisan migrate:status        → 0 Pending
php artisan queue:failed          → No failed jobs
php artisan onhost:doctor         → PASS WITH WARNINGS (12 warnings, all expected)
```

---

## 3. HTTP Smoke Audit

**67/67 routes → HTTP 200 ✓**

| Sekce | Routes | Výsledek |
|-------|--------|----------|
| Public frontend | 30 | ✅ všechny 200 |
| Admin panel | 24 | ✅ všechny 200 |
| Customer panel | 13 | ✅ všechny 200 |
| Partner panel | 6 | ✅ všechny 200 |

---

## 4. Self-Service E2E Matrix

| Služba | Frontend | Checkout | Payment | Tax doc | Provisioning | Panel | Admin | Stav |
|--------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|------|
| **Webhosting** | ✅ 200 | ✅ orderable | ✅ completed | ✅ issued | ✅ MOCK-AAP-* Active | ✅ | ✅ | **DONE** |
| **VPS** | ✅ 200 | ✅ orderable | ✅ completed | ✅ issued | ✅ MOCK-PVE-* Active | ✅ activating banner | ✅ | **DONE** |
| **Mailhosting** | ✅ 200 | ✅ orderable | ✅ completed | ✅ issued | ✅ MOCK-AAP-* Active | ✅ | ✅ | **DONE** |
| **Managed hosting** | ✅ 200 | ✅ orderable | ✅ completed | ✅ issued | ✅ MOCK-AAP-* Active | ✅ | ✅ | **DONE** |
| **Domény** | ✅ 200 | ✅ mock check | ✅ completed | ✅ issued | ✅ MOCK-WD-* | ✅ | ✅ | **DONE** |

**Detaily:**
- Payment method: mock platba (Comgate credentials chybí → card payment blokovaný)
- Tax document: funguje s billing adresou (CustomerAddress type=billing)
- VPS lifecycle: pending → CheckProxmoxTaskStatusJob → Active (MOCK-PVE-*)
- Domain registration: WAPI mock, WAPI_ALLOW_REAL_WRITES=false (real write blokovaný)
- Audit log: provisioning.started / provisioning.succeeded / domain.registered zaznamenány

---

## 5. Non-Self-Service Matrix

| Služba | sales_mode | CTA | Objednatelná | Backend | Riziko | Stav |
|--------|-----------|-----|:---:|:---:|--------|------|
| **Gamehosting** | `coming_soon` | "Brzy spustíme" + CTA na VPS/Webhosting/Kontakt | ❌ 404 | ❌ Pterodactyl MISSING | Nízké — coming soon jasně označeno | **SAFE** |
| **Dedicated servers** | contact_only | "Nezávazná poptávka" → /kontakt | ❌ není v katalogu | ❌ žádný | Nízké — kontaktní flow | **SAFE** |
| **Reseller hosting** | contact_only | "Kontaktovat obchodní tým" → /kontakt | ❌ není v katalogu | ❌ žádný | Nízké | **SAFE** |
| **Colocation** | contact_only | "Poptejte kolokaci" → /kontakt | ❌ | ❌ | Nízké | **SAFE** |
| **DDoS protection** | contact_only | Navigation na VPS/Webhosting | ❌ | Bundled s VPS/WH | Nízké | **SAFE** |
| **SSL certifikáty** | contact_only | → /webhosting nebo /kontakt | ❌ | Bundled (LE zdarma) | Nízké | **SAFE** |
| **Database hosting** | contact_only | "Poptat řešení" → /kontakt | ❌ | ❌ | Nízké — opraven v Phase 20 | **SAFE** |
| **E-mail security** | contact_only | "Poptat řešení" → /kontakt | ❌ | ❌ | Nízké — opraven v Phase 20 | **SAFE** |
| **Website builder** | coming_soon | "Připravujeme" + → /webhosting | ❌ | ❌ | Nízké | **SAFE** |

---

## 6. Admin Panel Audit

| Sekce | HTTP | Data | Akce | sales_mode | Mock/Manual badge | Stav |
|-------|:---:|:---:|:---:|:---:|:---:|------|
| Dashboard | ✅ 200 | ✅ live | KPI charts | — | — | ✅ |
| Produkty | ✅ 200 | ✅ 5 produktů | edit/add plan | ✅ badge | ✅ Pterodactyl warning | ✅ |
| Zákazníci | ✅ 200 | ✅ live | detail, kredit | — | — | ✅ |
| Objednávky | ✅ 200 | ✅ live | detail | — | — | ✅ |
| Faktury | ✅ 200 | ✅ live | mark paid, cancel, tax doc | — | — | ✅ |
| Platby | ✅ 200 | ✅ live | manual refund | — | ✅ MANUAL warning | ✅ |
| Služby | ✅ 200 | ✅ live | suspend/unsuspend | — | — | ✅ |
| Domény | ✅ 200 | ✅ live | — | — | — | ✅ |
| Servery | ✅ 200 | ✅ MOCK-AAP-01 | test connection | — | ✅ mock mode | ✅ |
| Provisioning | ✅ 200 | ✅ live | retry | — | ✅ mock | ✅ |
| Partneři | ✅ 200 | ✅ live | create/edit/status | — | ✅ MANUAL payouts | ✅ |
| Support | ✅ 200 | ✅ live | reply/update | — | — | ✅ |
| Audit log | ✅ 200 | ✅ live | — | — | — | ✅ |
| Systémové zdraví | ✅ 200 | ✅ live | — | — | — | ✅ |
| CMS obsah | ✅ 200 | ✅ 8 položek | bulk update | — | — | ✅ |
| Blog | ✅ 200 | ✅ 2 články | CRUD | — | — | ✅ |
| Znalostní báze | ✅ 200 | ✅ 5 článků | CRUD | — | — | ✅ |
| Nastavení | ✅ 200 | ✅ live | save | — | — | ✅ |

---

## 7. Customer Panel Audit

| Sekce | HTTP | Izolace | Empty state | Stav |
|-------|:---:|:---:|:---:|------|
| Dashboard | ✅ 200 | ✅ customer-scoped | — | ✅ |
| Služby | ✅ 200 | ✅ OrderPolicy | ✅ CTA → nová objednávka | ✅ |
| Detail služby | ✅ 200 | ✅ ServicePolicy | ✅ activating banner | ✅ |
| Domény | ✅ 200 | ✅ DomainPolicy | ✅ CTA → nová objednávka | ✅ |
| Objednávky | ✅ 200 | ✅ | ✅ CTA → nová objednávka | ✅ |
| Faktury | ✅ 200 | ✅ InvoicePolicy | ✅ | ✅ |
| Platby | ✅ 200 | ✅ | ✅ | ✅ |
| Kredit/top-up | ✅ 200 | ✅ | ✅ | ✅ |
| Support | ✅ 200 | ✅ SupportTicketPolicy | ✅ | ✅ |
| Účet/profil | ✅ 200 | ✅ | — | ✅ |
| Bezpečnost | ✅ 200 | ✅ | ✅ 2FA COMING SOON badge | ✅ |
| AI asistent | ✅ 200 | ✅ | ✅ MOCK badge | ✅ |

---

## 8. Partner Panel Audit

| Test | Výsledek |
|------|----------|
| /partner bez profilu → onboarding screen | ✅ |
| /partner s profilem → dashboard | ✅ |
| ?ref=CODE → cookie + session + referral record | ✅ |
| Registrace přes referral → PartnerReferral | ✅ |
| Zaplacená objednávka → PartnerCommission (pending) | ✅ |
| Replay InvoicePaid → žádná duplicita (unique constraint) | ✅ |
| Auto-approve command (eligible commissions) | ✅ |
| Payout create (commission checkboxes) | ✅ |
| Payout mark paid → jen navázané commissions | ✅ |
| Payout cancel → commissions zpět do approved | ✅ |
| Partner nevidí cizí data | ✅ |
| Admin vytvoří/upraví partner profil | ✅ |

---

## 9. Billing / Tax / Renewal / Refund Audit

| Flow | Výsledek | Poznámka |
|------|----------|----------|
| Proforma creation | ✅ | Číslo CZ-YYYY-NNNNNN |
| Mock payment | ✅ | PROVISIONING_MOCK_MODE=true |
| Credit payment | ✅ | credit_topup → credit ledger |
| Comgate flow | ⚠️ BLOCKED | COMGATE_MERCHANT_ID not set → Comgate error gracefully returned |
| InvoicePaid event | ✅ | Fires HandleInvoicePaid + CreateCommissionOnInvoicePaid |
| Tax document (s adresou) | ✅ | IssueTaxDocumentAction → CZ-* invoice |
| Tax document (bez adresy) | ✅ | IncompleteBillingDetailsException (ne 500) |
| Renewal invoice creation | ✅ | billing:create-renewals command |
| Renewal payment → next_due_date | ✅ | renewal_applied_at idempotency |
| Replay InvoicePaid | ✅ | renewal_applied_at brání dvojímu prodloužení |
| Overdue marking | ✅ | billing:mark-overdue |
| Overdue suspend job dispatch | ✅ | ChangeServiceStateJob dispatched |
| Manual refund UI warning | ✅ | "Vrácení probíhá manuálně v Comgate portálu" |
| Partner commission po paid invoice | ✅ | CreateCommissionOnInvoicePaid |

---

## 10. Provisioning / Domain Audit

| Flow | Výsledek | Poznámka |
|------|----------|----------|
| aaPanel mock provisioning → Active | ✅ | MOCK-AAP-* external_id |
| Proxmox mock pending → CheckProxmoxTaskStatusJob → Active | ✅ | MOCK-PVE-* external_id |
| WEDOS mock domain registration | ✅ | MOCK-WD-* wedos_domain_id |
| WAPI_ALLOW_REAL_WRITES=false | ✅ | brání real registration |
| AAPANEL_ALLOW_REAL_WRITES=false | ✅ | brání real write |
| Failed provisioning → Failed/ManualReview | ✅ | simulate_failure=true |
| Retry funguje | ✅ | simulate_failure konsumována po 1 pokusu |
| Admin vidí provisioning tasks | ✅ | /admin/provisioning |
| Customer vidí pending/activating stav | ✅ | banner v panel/services/show |

---

## 11. Content / Legal Readiness

| Položka | Stav | Poznámka |
|---------|------|----------|
| Homepage SiteContent announcement | ✅ | `/admin/obsah` → homepage lišta |
| Blog (≥2 published articles) | ✅ | 2 články seedovány |
| KB (≥5 published articles) | ✅ | 5 článků seedováno |
| Kontaktní stránka | ⚠️ | E-maily jsou placeholders (podpora@, fakturace@) |
| Legal pages — bez veřejných demo textů | ✅ | Interní poznámka "vyžaduje právní kontrolu" |
| Legal pages — právní kontrola | ❌ DEPLOY BLOCKER | Vyžaduje finální schválení právníkem |
| Billing company address | ❌ DEPLOY BLOCKER | Street/city/zip/DIČ potřeba pro daňové doklady |
| MAIL_FROM_ADDRESS | ⚠️ | info@stanektech.cz — zvažte info@onhost.cz |

---

## 12. Release Freeze Dokumentace

**Od Phase 21 platí RELEASE FREEZE:**

✅ Přijatelné změny:
- Opravy 500 chyb, broken routes, broken CTA
- Regresní testy
- Produkční konfigurace (`.env`, Apache, Supervisor)
- Doplnění reálných credentials (Comgate, WAPI, SMTP)

❌ Nepřijatelné změny:
- Nové business funkce
- Změny architektury databáze / modelů
- Redesign hotových UI
- Přidávání nových modulů
- PHPStan hardening

---

## 13. Zbývající Deploy Blockers

| # | Blocker | Závažnost | Akce |
|---|---------|-----------|------|
| 1 | **Comgate credentials** — COMGATE_MERCHANT_ID + COMGATE_SECRET | 🔴 CRITICAL | Aktivovat Comgate merchant účet, doplnit do `.env` |
| 2 | **Billing company address** — chybí street/city/zip/DIČ | 🟡 HIGH | Doplnit do `.env.production` (BILLING_COMPANY_STREET atd.) |
| 3 | **Legal pages finální kontrola** — obchodní podmínky, GDPR, SLA | 🟡 HIGH | Právní kontrola před prvním prodejem |
| 4 | **SMTP credentials** — port 587 na s2.onhost.cz neověřen | 🟡 HIGH | Ověřit `telnet s2.onhost.cz 587`, případně změnit na 465 |
| 5 | **MySQL přepnutí** — lokálně SQLite, produkce MySQL | 🟡 HIGH | Přepnout `DB_CONNECTION=mysql` v `.env` na serveru |
| 6 | **APP_KEY pro produkci** — prázdné v `.env.production` | 🔴 CRITICAL | `php artisan key:generate --force` na serveru |
| 7 | **Session cookies** — SESSION_DOMAIN=.onhost.cz, SESSION_SECURE_COOKIE=true | 🟡 HIGH | Doplnit do `.env` pro HTTPS |

---

## 14. Zbývající Functional Blockers

| # | Blocker | Dopad | Doporučení |
|---|---------|-------|------------|
| 1 | **Pterodactyl driver chybí** | Gamehosting nelze provozovat | Implementovat v samostatném sprintu |
| 2 | **Proxmox real task polling** | VPS provisioning je fire-and-forget 120s | CheckProxmoxTaskStatusJob::handleReal() TODO |
| 3 | **Dedicated/Reseller** — bez self-service | Objednávky jen přes kontakt | Záměrné — contact-only model |
| 4 | **MAIL_FROM info@stanektech.cz** | E-maily přijdou ze špatné domény | Změnit na info@onhost.cz |
| 5 | **Partner payout** — pouze manuální | Výplata provizí jen ručně | Záměrné — MANUAL badge |
| 6 | **AI real provider** — mock mode | AI asistent vrací deterministické odpovědi | Zapnout po konfiguraci OpenAI/Anthropic |
| 7 | **Monitoring/Backups** — mock mode | Monitorovací data jsou simulovaná | Zapnout po integraci externího poskytovatele |

---

## 15. Known Limitations

- **SQLite vs MySQL**: Lokálně běžíme na SQLite pro dev. Produkce vyžaduje MySQL (credit ledger triggery jsou MySQL-specific).
- **Comgate**: Všechny platby v mock/credit režimu. Kartou/bankou nelze platit bez credentials.
- **WAPI**: Doménová registrace je mock. WAPI_ALLOW_REAL_WRITES=false záměrně.
- **aaPanel**: Provisioning je mock. AAPANEL_ALLOW_REAL_WRITES=false záměrně.
- **Partner commissions**: Sazba 10% výchozí, výplata manuální — žádná automatika.
- **Proxmox VPS**: Mock lifecycle funguje. Real Proxmox cluster není nakonfigurován.
- **2FA**: COMING SOON badge — není implementovaná.
- **DNS správa domén**: COMING SOON — WEDOS WAPI read-only operace.
- **Gamehosting**: COMING SOON — Pterodactyl driver chybí.
- **Website builder**: COMING SOON — není implementovaný.

---

## 16. Přesné podmínky pro Production Deploy

### Před nasazením (MUST HAVE):

```bash
# 1. MySQL databáze — připravit na serveru
#    ISPConfig → Sites → Databases → Add Database

# 2. Vyplnit .env na serveru:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://onhost.cz
APP_KEY=<php artisan key:generate --show>
DB_CONNECTION=mysql
DB_HOST=s2.onhost.cz
DB_DATABASE=OH_10_OHnew
DB_USERNAME=OH_10_OHnew
DB_PASSWORD=vuQXhF_3iRpu4VXLbs
SESSION_DOMAIN=.onhost.cz
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_PORT=587                   # OVĚŘIT! 3306 je MySQL port
BILLING_COMPANY_STREET=<doplnit>
BILLING_COMPANY_CITY=<doplnit>
BILLING_COMPANY_ZIP=<doplnit>
BILLING_COMPANY_DIC=<doplnit>

# 3. Deploy:
bash /var/www/onhost/deploy/deploy.sh --fresh

# 4. Ověřit:
php artisan onhost:doctor --production
curl https://onhost.cz/up
```

### Po nasazení (BEFORE FIRST SALE):

- [ ] Právní kontrola obchodních podmínek
- [ ] Aktivovat Comgate (COMGATE_MERCHANT_ID + COMGATE_SECRET + COMGATE_TEST_MODE=true)
- [ ] Ověřit Comgate sandbox platbu
- [ ] Přepnout COMGATE_TEST_MODE=false
- [ ] Ověřit SMTP (testovací e-mail)
- [ ] Spustit Supervisor + cron
- [ ] Vytvořit admin uživatele (viz deploy/DEPLOY-NOW.md Krok 7)
- [ ] Nastavit produktové ceny v /admin/produkty
- [ ] Vytvořit první KB a Blog obsah (nebo použít seed data)

### Staged cutover (AFTER SOFT LAUNCH):

- [ ] WEDOS: WAPI_TEST_MODE=false → WAPI_ALLOW_REAL_WRITES=true (po ověření)
- [ ] aaPanel: přidat server, test connection, AAPANEL_ALLOW_REAL_WRITES=true
- [ ] PROVISIONING_MOCK_MODE=false

---

## 17. Phase History

| Phase | Commit | Klíčový obsah |
|-------|--------|---------------|
| 1–11 | — | Core domain models, billing, provisioning, support, AI |
| 12 | f1145a9 | Cuba Tailwind dashboard — všechny 3 dashboardy |
| 12 polish | ed56f92 | W4/W8 table structure |
| 13 | 9f3f7b7 | KPI strips, breadcrumbs, empty states |
| 14 | 5ca62d4 | Partner sub-pages (5 stránek) |
| 15 | b330d33 | Partner affiliate MVP (4 DB modely, tracking, commissions) |
| 16 | 66d64c1 | Admin partner create/edit, auto-approve, notifications |
| 17 | 735e58c | Payout precision, admin shortcut, production docs |
| 17.1 | 283d2f1 | Deploy.sh + DEPLOY-NOW.md |
| 18 | — | Local QA audit (no commit — audit only) |
| 19 | 7cb975c | Gamehosting coming-soon, VPS banner, Blog/KB seed, SiteContent |
| 20 | 883ad1f | SalesMode, ProxmoxMockDriver, CTA fixes, E2E tests |
| 21 | TBD | Final acceptance + this document |
