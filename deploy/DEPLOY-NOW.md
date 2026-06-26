# OnHost.cz — Přesný deploy na s2.onhost.cz

> Připraveno: 2026-06-26  
> Server: s2.onhost.cz  
> DB: OH_10_OHnew (MySQL)  
> PHP: 8.2  

---

## ⚠️ Před začátkem — opravte SMTP port

Zadali jste SMTP port **3306** (to je MySQL port!). Správný SMTP port je:
- **587** (STARTTLS — nejčastější)
- nebo **465** (SSL)

Ověřte: `telnet s2.onhost.cz 587` nebo se podívejte v ISPConfig → Email.  
Soubor `.env.production` má nastaveno 587 — pokud nefunguje, změňte na 465 + `MAIL_ENCRYPTION=ssl`.

---

## ⚠️ Doplňte před deployem (chybí)

V `.env.production` doplňte:

```
BILLING_COMPANY_DIC=       # DIČ (CZ08094616 pokud plátce DPH)
BILLING_COMPANY_STREET=    # Ulice a č.p.
BILLING_COMPANY_CITY=      # Město
BILLING_COMPANY_ZIP=       # PSČ
```

---

## Krok 1 — ISPConfig: Vytvořte weby

### onhost.cz
- Sites → Add Website
- Domain: `onhost.cz`
- Custom Document Root: `/var/www/onhost/public`
- PHP-FPM 8.2
- SSL: Let's Encrypt ✓, Force SSL ✓

### admin.onhost.cz
- Sites → Add Subdomain
- Domain: `admin.onhost.cz`
- Custom Document Root: `/var/www/onhost/public`
- PHP-FPM 8.2
- SSL: Let's Encrypt ✓

### Apache directives pro admin.onhost.cz (v ISPConfig → Options → Apache Directives):

```apache
<Directory /var/www/onhost/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

RewriteEngine On
RewriteRule ^/?$ /admin [R=302,L]

Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000"
```

---

## Krok 2 — SSH na server + clone repo

```bash
ssh root@s2.onhost.cz    # nebo příslušný user

# Naklonujte repo (nebo unzip)
git clone https://github.com/VAS-REPO/onhost.git /var/www/onhost
# případně:
# mkdir -p /var/www/onhost && cd /var/www/onhost && git clone . .

cd /var/www/onhost
```

---

## Krok 3 — Zkopírujte .env na server

Z lokálního počítače:

```bash
scp .env.production root@s2.onhost.cz:/var/www/onhost/.env
```

Nebo na serveru zkopírujte obsah `.env.production` ručně:

```bash
nano /var/www/onhost/.env
# Vložte obsah a uložte (Ctrl+X, Y, Enter)
```

---

## Krok 4 — Spusťte deploy

```bash
cd /var/www/onhost

# Composer
composer install --no-dev --optimize-autoloader

# Klíč (pokud APP_KEY je prázdný)
php8.2 artisan key:generate --force

# PRVNÍ DEPLOY — migrace + seed
php8.2 artisan migrate --force
php8.2 artisan db:seed --class=RoleSeeder --force
php8.2 artisan db:seed --class=ProductCatalogSeeder --force
php8.2 artisan db:seed --class=IntegrationSeeder --force
php8.2 artisan db:seed --class=AiPromptTemplateSeeder --force

# Storage a cache
php8.2 artisan storage:link
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

# Oprávnění
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## Krok 5 — Supervisor (queue worker)

```bash
cp /var/www/onhost/deploy/supervisor-queue.conf /etc/supervisor/conf.d/onhost-queue.conf

# Ověřte cestu k PHP:
which php8.2   # → /usr/bin/php8.2

supervisorctl reread
supervisorctl update
supervisorctl start "onhost-queue:*"
supervisorctl status
```

Pokud PHP není php8.2 ale php, upravte v `supervisor-queue.conf`:
```
command=php /var/www/onhost/artisan queue:work ...
```

---

## Krok 6 — Cron (scheduler)

```bash
# Zkontrolujte cestu k artisan:
which php8.2

# Přidejte do crontab:
crontab -u www-data -e

# Přidejte řádek:
* * * * * /usr/bin/php8.2 /var/www/onhost/artisan schedule:run >> /dev/null 2>&1
```

Nebo zkopírujte připravený soubor (upravte cestu k PHP):

```bash
cat /var/www/onhost/deploy/cron.txt   # ověřte obsah
cp /var/www/onhost/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost
```

---

## Krok 7 — Vytvořte admin uživatele

```bash
php8.2 artisan tinker

# V tinker konzoli:
$user = \App\Models\User::create([
    'name' => 'Admin OnHost',
    'email' => 'admin@onhost.cz',
    'password' => bcrypt('VaseHesloZde'),
    'is_active' => true,
    'locale' => 'cs',
]);
$user->assignRole('admin');
$user->givePermissionTo('access-admin');
echo "Admin vytvořen: " . $user->email;
exit;
```

---

## Krok 8 — Ověření

```bash
# Doctor check
php8.2 artisan onhost:doctor --production

# Health endpoint
curl -s https://onhost.cz/up    # musí vrátit 200

# Scheduler
php8.2 artisan schedule:list

# Queue
php8.2 artisan queue:failed

# Migrace
php8.2 artisan migrate:status | tail -5

# Logy
tail -f /var/www/onhost/storage/logs/laravel.log
```

---

## Krok 9 — SMTP ověření

```bash
# Test odeslání e-mailu
php8.2 artisan tinker

\Illuminate\Support\Facades\Mail::raw('Test OnHost SMTP', function($m) {
    $m->to('vas@email.cz')->subject('OnHost SMTP test');
});
exit;
```

Pokud selže — zkontrolujte `storage/logs/laravel.log` a zkuste port 465:
```bash
php8.2 artisan config:clear
# Upravte .env: MAIL_PORT=465 MAIL_ENCRYPTION=ssl
php8.2 artisan config:cache
```

---

## Krok 10 — Staged cutover checklista

### Hned po spuštění (MOCK mode)
- [ ] https://onhost.cz načítá OK ✓
- [ ] https://onhost.cz/admin načítá OK ✓
- [ ] Registrace nového zákazníka OK ✓
- [ ] Objednávka plánu → mock platba → provisioning job ✓
- [ ] E-mail notification po mock platbě ✓
- [ ] WEDOS availability check (test mode) ✓

### Cutover WEDOS (WAPI_TEST_MODE=false, WAPI_ALLOW_REAL_WRITES=true)
- [ ] Ověřte WAPI dostupnost: `php8.2 artisan tinker` → `app(\App\Domains\Provisioning\Services\WedosWapiClient::class)->ping()`
- [ ] Zapněte real writes: `.env` → `WAPI_ALLOW_REAL_WRITES=true` → `php8.2 artisan config:cache`
- [ ] Otestujte availability check na testovací doméně

### Cutover aaPanel
- [ ] Přidejte server v admin: /admin/servery
- [ ] Connection Test → OK
- [ ] `AAPANEL_ALLOW_REAL_WRITES=true` + `PROVISIONING_MOCK_MODE=false`
- [ ] Otestujte provisioning jedné webhosting služby

### Cutover Comgate (až bude aktivní)
- [ ] Doplňte `COMGATE_MERCHANT_ID` a `COMGATE_SECRET`
- [ ] Nechte `COMGATE_TEST_MODE=true`
- [ ] Sandbox platba → ověřte webhook https://onhost.cz/api/webhooks/comgate
- [ ] Ověřte InvoicePaid flow + daňový doklad
- [ ] Pak `COMGATE_TEST_MODE=false` + první reálná platba

---

## ⚠️ Doplňte před prvním prodejem

1. **Adresa firmy** v `.env` (BILLING_COMPANY_STREET/CITY/ZIP)
2. **DIČ** pokud jste plátce DPH
3. **MAIL_FROM_ADDRESS** — zvažte info@onhost.cz místo info@stanektech.cz
4. **MAIL_PASSWORD** — heslo "tester" vypadá jako testovací, ověřte
5. **Produktový katalog** — zkontrolujte a nastavte ceny v `/admin/produkty`
6. **Gamehosting** — ponechte neaktivní (COMING SOON) dokud není implementováno

---

## Rychlý debug

```bash
# 500 error
tail -100 /var/www/onhost/storage/logs/laravel.log

# Permissions
ls -la /var/www/onhost/storage/
ls -la /var/www/onhost/bootstrap/cache/

# PHP syntax
php8.2 -l /var/www/onhost/artisan

# Cache clean (pokud divné chování)
php8.2 artisan config:clear
php8.2 artisan cache:clear
php8.2 artisan view:clear
php8.2 artisan route:clear
php8.2 artisan config:cache   # znovu
php8.2 artisan route:cache
```
