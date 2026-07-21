# OnHost — Go-Live Checklist

Stav kódu: **hotovo** (3300+ testů zelených, PHPStan L6 = 0). Vše níže je
**konfigurace, credentials nebo infrastruktura** — mimo aplikační kód.

Ověření kdykoliv: `php artisan onhost:doctor --production`

---

## 0. Než začneš

- [ ] `git pull` na produkci + `composer install --no-dev --optimize-autoloader`
- [ ] `.env` z [`.env.production.example`](../.env.production.example) — vyplnit **všechny** hodnoty
- [ ] `php artisan key:generate` (pokud `APP_KEY` prázdný)
- [ ] `php artisan migrate --force`
- [ ] `php artisan config:cache route:cache view:cache event:cache`
- [ ] `chown -R web:web storage bootstrap/cache` (běží jako `sudo -u web`, ne root)

---

## 1. Config (bez credentials — hned) — pokrývá onhost:doctor „critical"

| # | Položka | Akce | Ověření |
|---|---|---|---|
| — | APP_DEBUG | `APP_DEBUG=false` | doctor ✓ |
| — | Session cookie | `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=.onhost.cz` | doctor ✓ |
| Billing | Firemní identita | `BILLING_COMPANY_IC/DIC/STREET/CITY/ZIP` (**povinné pro daňové doklady**) | vystavit testovací fakturu |
| Billing | Bankovní účet | `BILLING_BANK_CZK` (IBAN) | jinak nejde platba převodem |
| Mail | Reálný mailer | `MAIL_MAILER=smtp` + host/user/pass | doctor ✓ + odeslat test |

---

## 2. Credentials (EXTERNÍ audit 1–5, 40) — vyžadují účty

| # | Položka | Co dodat | Pak |
|---|---|---|---|
| 1 | Platební brána | `COMGATE_MERCHANT_ID` + `COMGATE_SECRET`, `COMGATE_TEST_MODE=false` (nebo Stripe/GoPay) | reálné E2E platby |
| 4 | WEDOS domény | `WAPI_USER` + `WAPI_PASSWORD` | reálná registrace domén |
| 2/3 | aaPanel / Proxmox / Pterodactyl | uzly + credentials do Server záznamů v adminu | connection test v adminu |
| 5 | Sentry | `composer require sentry/sentry-laravel` + `SENTRY_LARAVEL_DSN` | error tracking (kontext je připraven) |
| 40 | Refund přes bránu | refund API brány | jinak zůstává manuální (`RefundDestination`) |

**Standing rule:** real writes zůstávají OFF, dokud je vědomě nezapneš.

---

## 3. Staged cutover provisioningu (audit 16–22) — řízený první ostrý test

1. [ ] `PROVISIONING_MOCK_MODE=false` (jinak vše jen mock)
2. [ ] Connection test každého serveru v adminu (jen čtení, negatuje gate)
3. [ ] `AAPANEL_ALLOW_REAL_WRITES=true` na **jednom** uzlu → objednat 1 testovací hosting → ověřit v panelu
4. [ ] `WAPI_ALLOW_REAL_WRITES=true` → 1 testovací doména → ověřit availability + registraci
5. [ ] Ověřit mock→real u: aaPanel mailboxy, DNSSEC, Comgate re-fetch, GoPay OAuth, PHP verze, UPID, orphan recovery

> Náhled bez zápisu: tlačítko „Náhled zřízení" na detailu služby (audit 56).

---

## 4. Fronty / scheduler — **kritické, byla to reálná chyba v minulosti**

- [ ] Horizon běží a zpracovává **supervisor-provisioning** (`provisioning-high/…/-low`)
      — dřív šly joby do fronty, kterou žádný worker nezpracovával → služby se nikdy nezřídily
- [ ] Cron: `* * * * * php artisan schedule:run` (ověř `crontab -l`)
- [ ] Naplánované ověřit: `onhost:doctor` sekce Scheduler

---

## 5. INFRA (mimo repozitář) — DevOps

| # | Položka | Akce |
|---|---|---|
| 8 | Redis | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` |
| 7 | **Offsite DB zálohy** | `db:backup` je hotový — vyplnit `S3_BACKUP_*`; ověřit, že denní job nahrává + prořezává |
| 180 | Data retention | `retention:apply` hotový — přeběhne denně; případně dolaď `RETENTION_*` |
| 14 | Staging | samostatné prostředí pro test deploye |
| 11 | CDN + Cache-Control | statika přes CDN, cache hlavičky na reverzní proxy |
| 12 | WAF / rate limit | na reverzní proxy (app-level rate limit hotový) |
| 13 | SPF/DKIM/DMARC | DNS záznamy + bounce handling |
| 118 | Tailwind purge | `style.css` má 243k řádků — build pipeline (pozor: Cuba používá třídy dynamicky) |
| 10/86 | Uptime + synthetic monitoring | napojit `/api/up` na externí monitor |
| 196–197 | DR plán + restore test | **otestovat restore ze zálohy** (audit 7), on-call rotace |
| 24b | CSP enforce | projít nasbírané CSP reporty → `SECURITY_CSP_ENFORCE=true` |

---

## 6. Finální smoke test

- [ ] `php artisan onhost:doctor --production` → 0 critical
- [ ] Registrace → objednávka → platba (test brány) → provisioning → faktura (PDF s IČ/DIČ)
- [ ] Přihlášení do adminu, zpracuje fronta, dorazí e-mail
- [ ] Restore ze zálohy proběhne (ne jen že záloha existuje)

---

## Poznámka: produktová/architekturní rozhodnutí (ne blokery go-live)

Volitelné, každé vyžaduje tvé rozhodnutí — **nejsou nutná pro spuštění**:
web push (92, potřebuje `minishlink/web-push` + VAPID), OAuth2 flow (99, Passport),
GraphQL (102), SDK (100), e-mail hosting jako produkt (151), šifrování PII sloupců (31),
samostatná číselná řada faktur na resellera (111), upsell engine (186).
Detaily: [`docs/SYSTEM-AUDIT-200-VERIFIED.md`](SYSTEM-AUDIT-200-VERIFIED.md).
