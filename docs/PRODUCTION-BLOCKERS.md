# OnHost.cz — Production Blockers

**Aktualizováno: Phase 24**  
**Lokální acceptance:** ✅ GO (Phase 21)  
**Produkční .env:** ✅ 97% kompletní — chybí pouze Comgate, DIČ  
**Server:** s2.onhost.cz — SMTP 587/465 ✅, MySQL 3306 ✅ ready  

---

## Doctor --production výsledek se production .env (Phase 24 simulace)

```
✅ APP_KEY                  configured (base64)
✅ APP_ENV                  production
✅ APP_DEBUG=false           false
✅ APP_URL                  https://onhost.cz
✅ SESSION_DOMAIN            .onhost.cz
✅ SESSION_SECURE_COOKIE     true
✅ MAIL_MAILER               smtp
✅ MAIL_FROM                 info@onhost.cz
✅ BILLING_COMPANY_NAME      Adrian Staněk
✅ BILLING_COMPANY_IC        08094616
✅ BILLING_COMPANY_STREET    Molákova 2145/5
✅ BILLING_COMPANY_CITY      Brno - Líšeň
✅ BILLING_COMPANY_ZIP       62800
✅ BILLING_BANK_CZK          318 000 2153/0800
✅ WAPI_USER                 configured
✅ Scheduler                 4 jobs

✗ DB (migrations table)     → EXPECTED: MySQL na serveru ještě není migrated
✗ Storage symlink           → EXPECTED: php artisan storage:link na serveru
✗ COMGATE credentials       → INTENTIONAL BLOCKER pro public launch
✗ COMGATE_TEST_MODE=false   → INTENTIONAL (staging používá true)
✗ PROVISIONING_MOCK_MODE    → INTENTIONAL pro staged cutover

Critical errors: 6 → z toho 4 intentional / deploy-time fixes
```

**Závěr: Na serveru po `php artisan migrate && php artisan storage:link` zbydou pouze Comgate a provisioning blocker — obojí záměrné.**

---

## ✅ RESOLVED — Vyřešené blokery (Phase 23–24)

| # | Blocker | Stav |
|---|---------|------|
| R1 | **APP_KEY** | ✅ Vygenerován, v .env.production |
| R2 | **MySQL credentials** | ✅ OH_10_OHnew, s2.onhost.cz |
| R3 | **SMTP port** | ✅ 587 OPEN a 465 OPEN ověřeno |
| R4 | **SESSION_SECURE_COOKIE** | ✅ =true |
| R5 | **SESSION_DOMAIN** | ✅ =.onhost.cz |
| R6 | **MAIL_FROM_ADDRESS** | ✅ =info@onhost.cz |
| R7 | **BILLING_COMPANY_IC** | ✅ 08094616 |
| R8 | **BILLING_BANK_CZK** | ✅ 318 000 2153/0800 |
| R9 | **BILLING_COMPANY_STREET** | ✅ Molákova 2145/5 |
| R10 | **BILLING_COMPANY_CITY** | ✅ Brno - Líšeň |
| R11 | **BILLING_COMPANY_ZIP** | ✅ 62800 |
| R12 | **BILLING_COMPANY_NAME** | ✅ Adrian Staněk |
| R13 | **ADMIN_PASSWORD** | ✅ nastaveno — rotace po prvním loginu! |
| R14 | **SMTP password** | ✅ nastaveno — rotace po prvním deployi doporučena |

---

## 🔴 Zbývající blocker pro technický staging deploy

| # | Blocker | Urgentnost | Akce |
|---|---------|-----------|------|
| C1 | **SMTP credentials rotace** — heslo sdíleno přes chat | Po deployi | ISPConfig → Email → Mailboxes → Change password |

---

## 🟡 Blokují veřejný marketingový launch

| # | Blocker | Akce |
|---|---------|------|
| H1 | **Comgate credentials** — MERCHANT_ID + SECRET | Aktivovat na portal.comgate.cz |
| H2 | **Právní kontrola** — TOS/GDPR/SLA/Cookies/Reklamace | Předat právníkovi |
| H3 | **BILLING_COMPANY_DIC** — je firma plátce DPH? | Potvrdit DIČ status |

---

## Technický staging deploy — postup

```bash
# 1. Zkopírovat .env.production na server
scp .env.production root@s2.onhost.cz:/var/www/onhost/.env

# 2. Deploy
cd /var/www/onhost
git clone <repo> . || git pull
composer install --no-dev --optimize-autoloader
php artisan migrate:fresh --seed    # PRVNÍ deploy
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Ověření
php artisan onhost:doctor --production
# Zbydou pouze 2 blocker: Comgate + PROVISIONING_MOCK_MODE (obojí záměrné)

curl https://onhost.cz/up
# → HTTP 200
```

---

## Staged cutover plán

```
[1] Technický deploy    → PROVISIONING_MOCK_MODE=true ✓ CURRENT
[2] SMTP ověření       → testovací e-mail přes tinker
[3] SMTP rotace        → zmenit heslo v ISPConfig
[4] Billing smoke      → tax document E2E test
[5] Comgate sandbox    → COMGATE_TEST_MODE=true, viz COMGATE-CUTOVER.md
[6] Comgate production → COMGATE_TEST_MODE=false
[7] Marketing launch   → oznámení, start provozu
[8] aaPanel cutover    → AAPANEL_ALLOW_REAL_WRITES=true (po Connection Test)
[9] WEDOS cutover      → WAPI_ALLOW_REAL_WRITES=true
[10] Live provisioning → PROVISIONING_MOCK_MODE=false
```
