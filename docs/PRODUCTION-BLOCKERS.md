# OnHost.cz — Production Blockers

**Stav po Phase 23:** Deploy gate check — server připraven, vyčkáváme na 3 business blockers.  
**Lokální acceptance:** ✅ GO (Phase 21)  
**Technický deploy:** ✅ READY (server dostupný, MySQL připravena, SMTP porty ověřeny)

---

## Stav SMTP portů (ověřeno Phase 23)

```
PORT 587 (STARTTLS): OPEN  ✅
PORT 465 (SSL):      OPEN  ✅
```

→ Použijte `MAIL_PORT=587` s `MAIL_ENCRYPTION=tls`  
→ Stále potřeba ověřit SMTP credentials (heslo "tester" může být placeholder)

---

## ✅ RESOLVED — Vyřešené blokery

| # | Blocker | Stav | Výsledek |
|---|---------|------|----------|
| R1 | **APP_KEY** | ✅ DONE | Vygenerován, uložen v `.env.production` |
| R2 | **MySQL přepnutí** | ✅ DONE | DB_CONNECTION=mysql, s2.onhost.cz:3306, OH_10_OHnew |
| R3 | **SMTP port** | ✅ DONE | Port 587 a 465 oba OPEN na s2.onhost.cz |
| R4 | **SESSION_SECURE_COOKIE** | ✅ DONE | =true v `.env.production` |
| R5 | **SESSION_DOMAIN** | ✅ DONE | =.onhost.cz |
| R6 | **MAIL_FROM_ADDRESS** | ✅ DONE | =info@onhost.cz (ne stanektech.cz) |
| R7 | **BILLING_COMPANY_IC** | ✅ DONE | 08094616 vyplněno |
| R8 | **BILLING_BANK_CZK** | ✅ DONE | 318 000 2153/0800 vyplněno |

---

## 🔴 CRITICAL — Stále blokují technický deploy

| # | Blocker | Akce potřebná | Kdo |
|---|---------|---------------|-----|
| C1 | **SMTP credentials** — heslo "tester" pravděpodobně placeholder | Ověřit/nastavit správné SMTP heslo v ISPConfig → Email → Accounts → info@onhost.cz | DevOps |
| C2 | **Billing company address** — street/city/zip/DIČ chybí | Doplnit do `.env.production` (BILLING_COMPANY_STREET/CITY/ZIP) | Majitel |
| C3 | **ADMIN_PASSWORD** — v `.env.production` prázdný | Nastavit silné heslo před prvním přihlášením | Majitel |

---

## 🟡 HIGH — Blokují veřejný marketingový launch

| # | Blocker | Akce | Kdo |
|---|---------|------|-----|
| H1 | **Comgate credentials** — MERCHANT_ID + SECRET chybí | Aktivovat merchant účet na portal.comgate.cz | Business |
| H2 | **Právní kontrola** — TOS/GDPR/SLA/Cookies/Reklamace | Předat právníkovi k revizi (viz docs/LEGAL-REVIEW-CHECKLIST.md) | Právník |
| H3 | **BILLING_COMPANY_DIC** — neznámo, zda je firma plátce DPH | Potvrdit DIČ status | Majitel |

---

## ✅ Confirmed OK (Phase 23 smoke check)

| Položka | Stav |
|---------|------|
| s2.onhost.cz dostupný | ✅ |
| MySQL port 3306 dostupný | ✅ (viz DB credentials) |
| SMTP port 587 | ✅ OPEN |
| SMTP port 465 | ✅ OPEN |
| APP_KEY vygenerován | ✅ |
| .env.production připraven | ✅ (3 prázdné položky) |

---

## Postup pro technický deploy (jakmile C1-C3 jsou vyřešeny)

```bash
# Z lokálního stroje:
scp .env.production root@s2.onhost.cz:/var/www/onhost/.env

# Na serveru:
cd /var/www/onhost
git clone <repo> . || git pull
composer install --no-dev --optimize-autoloader
php artisan key:generate --only-if-empty  # NEgeneruje pokud APP_KEY existuje
php artisan migrate:fresh --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

# Ověření:
php artisan onhost:doctor --production
curl https://onhost.cz/up
```

---

## Staged cutover po technickém deployi

```
[1] Technický deploy    → PROVISIONING_MOCK_MODE=true, COMGATE_TEST_MODE=true
[2] SMTP ověření       → testovací e-mail přes tinker
[3] Billing ověření    → tax document smoke test
[4] Comgate sandbox    → viz docs/COMGATE-CUTOVER.md
[5] Comgate production → COMGATE_TEST_MODE=false
[6] aaPanel cutover    → AAPANEL_ALLOW_REAL_WRITES=true (po Connection Test)
[7] WEDOS cutover      → WAPI_ALLOW_REAL_WRITES=true (po availability check)
[8] Marketing launch   → announce, zatím mock provisioning
[9] Provisioning live  → PROVISIONING_MOCK_MODE=false
```
