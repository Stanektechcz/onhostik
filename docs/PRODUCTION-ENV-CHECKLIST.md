# Production .env Checklist

**Aktualizováno Phase 24** — `.env.production` je 97% kompletní.

Projděte tento seznam před spuštěním produkčního serveru.
Šablona je v `.env.production.example`.

---

## Status Phase 24

| Sekce | Stav |
|-------|------|
| APP_KEY | ✅ vygenerován |
| MySQL | ✅ s2.onhost.cz credentials |
| SMTP | ✅ port 587 ověřen, heslo nastaveno |
| Billing address | ✅ Molákova 2145/5, Brno-Líšeň, 62800 |
| ADMIN_PASSWORD | ✅ nastaveno — rotovat po prvním loginu! |
| Comgate | ❌ credentials chybí |
| Legal review | ❌ zatím ne |

---

## ✅ Kritické hodnoty (bez nich systém nefunguje)

```bash
# Aplikace
APP_ENV=production           ✓ nastaveno
APP_DEBUG=false              ✓ nastaveno
APP_URL=https://onhost.cz    ✓ nastaveno
APP_KEY=                     ← VYPLNIT: php artisan key:generate --show

# Databáze
DB_CONNECTION=mysql          ✓ nastaveno
DB_HOST=s2.onhost.cz         ✓ nastaveno
DB_PORT=3306                 ✓ nastaveno
DB_DATABASE=OH_10_OHnew      ✓ nastaveno
DB_USERNAME=OH_10_OHnew      ✓ nastaveno
DB_PASSWORD=***              ✓ nastaveno

# Session (HTTPS-only)
SESSION_DOMAIN=.onhost.cz    ✓ nastaveno
SESSION_SECURE_COOKIE=true   ← přidat

# Comgate
COMGATE_MERCHANT_ID=         ← VYPLNIT po aktivaci merchant účtu
COMGATE_SECRET=              ← VYPLNIT (NIKDY do gitu!)
COMGATE_TEST_MODE=true       ← sandbox, pak false
```

---

## ⚠️ Důležité hodnoty (systém funguje, ale s omezeními)

```bash
# E-mail
MAIL_MAILER=smtp             ✓ nastaveno
MAIL_HOST=s2.onhost.cz       ✓ nastaveno
MAIL_PORT=587                ✓ nastaveno (NIKDY 3306!)
MAIL_USERNAME=info@onhost.cz ← VYPLNIT správný SMTP login
MAIL_PASSWORD=               ← VYPLNIT SMTP heslo (ověřit s ISP)
MAIL_ENCRYPTION=tls          ✓ nastaveno
MAIL_FROM_ADDRESS=info@onhost.cz  ← OVĚŘIT (ne stanektech.cz)
MAIL_FROM_NAME="OnHost.cz"   ✓ nastaveno

# Fakturační údaje firmy (pro daňové doklady)
BILLING_COMPANY_NAME=        ← VYPLNIT název (Adrian Staněk nebo s.r.o.)
BILLING_COMPANY_IC=08094616  ← VYPLNIT IČO
BILLING_COMPANY_DIC=         ← VYPLNIT DIČ (pokud plátce DPH)
BILLING_COMPANY_STREET=      ← VYPLNIT ulici
BILLING_COMPANY_CITY=        ← VYPLNIT město
BILLING_COMPANY_ZIP=         ← VYPLNIT PSČ
BILLING_COMPANY_COUNTRY=CZ   ✓ nastaveno
BILLING_BANK_CZK=318 000 2153/0800  ← OVĚŘIT formát
```

---

## ℹ️ Provisioning (záměrně mock pro první deploy)

```bash
PROVISIONING_MOCK_MODE=true          # → false po ověření aaPanel
AAPANEL_ALLOW_REAL_WRITES=false      # → true po Connection Test
WAPI_ALLOW_REAL_WRITES=false         # → true po WEDOS ověření
WAPI_USER=info@stanektech.cz         ✓ nastaveno
WAPI_PASSWORD=***                    ✓ nastaveno
WAPI_TEST_MODE=true                  # → false po ověření
```

---

## Ověřovací příkazy po deploy

```bash
# Spusťte na serveru:
php artisan onhost:doctor --production
# Musí vrátit 0 critical errors

# Test health endpoint:
curl -s https://onhost.cz/up
# Musí vrátit HTTP 200

# Test e-mail:
php artisan tinker
\Illuminate\Support\Facades\Mail::raw('Test SMTP', fn($m) => $m->to('test@example.com')->subject('OnHost SMTP Test'));
exit
# Zkontrolujte storage/logs/laravel.log nebo inbox

# Test migrace:
php artisan migrate:status | grep Pending
# Musí být 0 pending
```

---

## SMTP port ověření

```bash
# Ověřte port před nasazením:
telnet s2.onhost.cz 587    # STARTTLS (preferovaný)
telnet s2.onhost.cz 465    # SSL/TLS alternativa
telnet s2.onhost.cz 25     # Plain SMTP (nedoporučeno)

# Pokud 587 nefunguje, zkuste v .env:
# MAIL_PORT=465
# MAIL_ENCRYPTION=ssl
```

---

## APP_KEY bezpečnost

```
⚠️  APP_KEY NIKDY neměnit po prvním spuštění!
    Změna klíče by zneplatnila:
    - Všechny aktivní sessions (zákazníci budou odhlášeni)
    - Encrypted session data
    - Encrypted payout_details u partner profilů (šifrováno Crypt AES-256)
    
    Uložte APP_KEY do bezpečného password manageru (1Password, Bitwarden, atd.)
```
