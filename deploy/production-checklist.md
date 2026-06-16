# OnHost.cz — Produkční nasazení: kontrolní seznam

> Projděte každý bod **v pořadí**. Nepřeskakujte kroky.
> Zastavte se, pokud krok selže — pokračování s chybou může způsobit ztrátu dat nebo bezpečnostní incident.

---

## FÁZE A — Příprava serveru

- [ ] **Debian/Ubuntu 22.04 LTS** nainstalováno a aktualizováno
- [ ] **PHP 8.2** + rozšíření: `php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-intl php8.2-bcmath php8.2-gd`
- [ ] **Apache 2.4** + mod_rewrite, mod_headers, mod_proxy_fcgi, mod_ssl (`a2enmod ...`)
- [ ] **MySQL 8.0 / MariaDB 10.6+** nainstalováno
- [ ] **Composer** nainstalován globálně (`/usr/local/bin/composer`)
- [ ] **Supervisor** nainstalován (`apt install supervisor`)
- [ ] **Let's Encrypt certbot** připraven (`apt install certbot python3-certbot-apache`)
- [ ] **Git** nainstalován, SSH klíč nasazen nebo deploy token nastaven

---

## FÁZE B — Databáze

- [ ] Databáze vytvořena: `CREATE DATABASE onhost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
- [ ] Uživatel vytvořen s právy pouze na `onhost` databázi (ne `root`)
- [ ] Heslo k DB zaznamenáno do password manageru

---

## FÁZE C — Kód a závislosti

```bash
git clone <repo_url> /var/www/onhost
cd /var/www/onhost
composer install --no-dev --optimize-autoloader
```

- [ ] Repozitář naklonován do `/var/www/onhost`
- [ ] Composer install bez chyb (žádné dev závislosti)
- [ ] Oprávnění: `chown -R www-data:www-data /var/www/onhost/storage /var/www/onhost/bootstrap/cache`

---

## FÁZE D — Konfigurace .env

```bash
cp .env.production.example .env
nano .env   # nebo vim .env
php8.2 artisan key:generate
```

- [ ] `.env` vytvořen z `.env.production.example`
- [ ] `APP_KEY` vygenerován (`php artisan key:generate`)
- [ ] `APP_ENV=production` nastaven
- [ ] `APP_DEBUG=false` nastaven
- [ ] `APP_URL=https://onhost.cz` nastaven
- [ ] `DB_*` vyplněno a otestováno
- [ ] `MAIL_*` vyplněno (SMTP přihlašovací údaje)
- [ ] `COMGATE_MERCHANT_ID`, `COMGATE_SECRET`, `COMGATE_TEST_MODE=false` vyplněno
- [ ] `ADMIN_EMAIL`, `ADMIN_PASSWORD` nastaveno (dočasné — změňte po prvním přihlášení)
- [ ] Billing firemní údaje vyplněny (`BILLING_COMPANY_*`)

---

## FÁZE E — Migrace a seedery

```bash
php8.2 artisan migrate --force
php8.2 artisan db:seed --class=RoleSeeder --force
php8.2 artisan db:seed --class=ProductCatalogSeeder --force
php8.2 artisan db:seed --class=IntegrationSeeder --force
```

- [ ] Migrace proběhla bez chyb
- [ ] Seedery proběhly bez chyb
- [ ] `php8.2 artisan migrate:status` — všechny migrace označeny jako `Ran`

---

## FÁZE F — Optimalizace

```bash
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache
php8.2 artisan storage:link
```

- [ ] Config cache vytvořen
- [ ] Route cache vytvořen
- [ ] View cache vytvořen
- [ ] `public/storage` symlink existuje

---

## FÁZE G — Apache vhosty

```bash
cp deploy/apache-onhost.cz.conf /etc/apache2/sites-available/
cp deploy/apache-admin.onhost.cz.conf /etc/apache2/sites-available/
certbot --apache -d onhost.cz -d www.onhost.cz
a2ensite onhost.cz admin.onhost.cz
systemctl reload apache2
```

- [ ] SSL certifikát vydán Let's Encrypt pro `onhost.cz` a `www.onhost.cz`
- [ ] vhost `onhost.cz` aktivní, HTTPS 200 na `https://onhost.cz/`
- [ ] vhost `admin.onhost.cz` aktivní, HTTPS 200 na `https://admin.onhost.cz/`
- [ ] HTTP → HTTPS redirect funguje
- [ ] www → non-www redirect funguje

---

## FÁZE H — Queue Worker (Supervisor)

```bash
cp deploy/supervisor-queue.conf /etc/supervisor/conf.d/onhost-queue.conf
supervisorctl reread
supervisorctl update
supervisorctl status onhost-queue:*
```

- [ ] Supervisor konfigurace nahrána
- [ ] `onhost-queue_00` a `onhost-queue_01` mají stav `RUNNING`
- [ ] Log čitelný: `tail -f /var/log/onhost-queue.log`

---

## FÁZE I — Scheduler (Cron)

```bash
cp deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost
```

- [ ] Crontab nainstalován
- [ ] Otestujte ručně: `php8.2 artisan schedule:run` — proběhne bez chyby

---

## FÁZE J — Production Doctor

```bash
php8.2 artisan onhost:doctor --production
```

- [ ] **Všechny kritické kontroly zelené (✓)**
- [ ] Zkontrolujte varování (⚠) a rozhodněte, co opravit před spuštěním

---

## FÁZE K — Smoke test (předspuštění)

- [ ] `https://onhost.cz/` — veřejný web načten
- [ ] `https://onhost.cz/login` — přihlašovací stránka
- [ ] Přihlaste se jako admin a projděte `/admin`
- [ ] `/admin/system` — všechny system checks zelené
- [ ] `/admin/servery` → Test connection na aaPanel serveru
- [ ] `/admin/integrace` → Comgate status
- [ ] Vytvořte testovací zákazníka, objednávku, proformu
- [ ] Vyzkoušejte Comgate platbu (testovací kartou)
- [ ] Webhook přijat a zalogován v `/admin/platby`

---

## FÁZE L — Staged cutover (ostrý provoz)

> Každý krok proveďte postupně. Nevypínejte mock_mode u několika integrací najednou.

### L1 — aaPanel connection test
1. Přidejte aaPanel server v `/admin/servery` (URL + API klíč)
2. Klikněte "Test connection" — musí být zelené
3. Teprve pak nastavte v `.env`: `AAPANEL_ALLOW_REAL_WRITES=true`
4. `php8.2 artisan config:cache`
5. Vytvořte jednu testovací webhosting službu, ověřte v aaPanel panelu

### L2 — WEDOS domain check
1. Nastavte `WAPI_USER`, `WAPI_PASSWORD` v `.env`
2. `WAPI_TEST_MODE=false`, `WAPI_ALLOW_REAL_WRITES=false`
3. `php8.2 artisan config:cache`
4. Vyzkoušejte availability check pro neexistující doménu
5. Teprve po ověření nastavte `WAPI_ALLOW_REAL_WRITES=true`

### L3 — Comgate produkce
1. `COMGATE_TEST_MODE=false` v `.env`
2. `php8.2 artisan config:cache`
3. Proveďte první reálnou platbu minimální částkou
4. Ověřte webhook, fakturaci a provisioning

---

## FÁZE M — Bezpečnostní posílení po spuštění

- [ ] `ADMIN_PASSWORD` v `.env` změněn na silné heslo
- [ ] Admin účet s silným heslem vytvořen přes UI, seed heslo smazáno z `.env`
- [ ] Fail2ban nebo CSF nainstalován pro ochranu SSH a wp-login pokusů
- [ ] MySQL root heslo nastaveno a přístup z localhost only
- [ ] Logwatch nebo Sentry nakonfigurován pro monitoring chyb
- [ ] Certbot auto-renewal: `certbot renew --dry-run`
