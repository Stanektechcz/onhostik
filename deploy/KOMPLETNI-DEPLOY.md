# OnHost.cz — Kompletní Deploy Průvodce

**Repozitář:** https://github.com/Stanektechcz/onhostik  
**Branch:** `development`  
**Server:** s2.onhost.cz  
**PHP:** 8.2, MySQL (OH_10_OHnew), Apache + ISPConfig  

---

## Reálné cesty na serveru

| Doména | ISPConfig složka | Laravel umístění | Nový Document Root |
|--------|-----------------|------------------|--------------------|
| `onhost.cz` | `/var/www/clients/client10/web19/web` | **SEM** naklonujeme Laravel | `/var/www/clients/client10/web19/web/public` |
| `admin.onhost.cz` | `/var/www/clients/client10/web62/web` | Nic neklonujeme | `/var/www/clients/client10/web19/web/public` (stejná!) |

> **Princip:** Laravel je jen na jednom místě — v `/var/www/clients/client10/web19/web/`.
> Obě domény v ISPConfig dostanou Custom Document Root nastavený na
> `/var/www/clients/client10/web19/web/public`.

```
/var/www/clients/client10/web19/web/   ← git clone SEM (celý Laravel)
├── public/          ← Document Root pro onhost.cz I admin.onhost.cz
│   ├── index.php
│   └── .htaccess
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/          ← po composer install
└── .env             ← produkční config (z .env.production)
```

---

## ČÁST 1: ISPConfig — Nastavte Custom Document Root

### 1.1 onhost.cz

ISPConfig → **Sites → Websites → onhost.cz → Edit**:

- Záložka **Options → Custom Document Root**:
  ```
  /var/www/clients/client10/web19/web/public
  ```

**Apache Directives** (záložka Options → Apache Directives):
```apache
<Directory /var/www/clients/client10/web19/web/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Klikněte **Save**.

---

### 1.2 admin.onhost.cz — STEJNÝ Document Root jako onhost.cz

ISPConfig → **Sites → Websites → admin.onhost.cz → Edit**:

- Záložka **Options → Custom Document Root**:
  ```
  /var/www/clients/client10/web19/web/public
  ```
  *(přesně stejná cesta jako u onhost.cz)*

**Apache Directives**:
```apache
<Directory /var/www/clients/client10/web19/web/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Přesměruj kořen domény (/) na /admin
RewriteEngine On
RewriteRule ^/?$ /admin [R=302,L]

Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000"
```

Klikněte **Save**.

> ℹ️ ISPConfig reloadne Apache automaticky. Stránky budou dostupné hned po clonování.

---

## ČÁST 2: SSH na server

```bash
ssh root@s2.onhost.cz

# Přejděte do cílové složky:
cd /var/www/clients/client10/web19/web

# Ověřte, že složka existuje a je prázdná (nebo obsahuje jen placeholder):
ls -la
```

---

## ČÁST 3: Přenos .env na server

Na **lokálním počítači** (ve složce projektu):

```bash
scp .env.production root@s2.onhost.cz:/tmp/onhost-env
```

Na **serveru**:

```bash
# Přesuňte .env do web složky:
mv /tmp/onhost-env /var/www/clients/client10/web19/web/.env

# Ověřte klíčové hodnoty:
grep APP_ENV   /var/www/clients/client10/web19/web/.env  # → production
grep APP_DEBUG /var/www/clients/client10/web19/web/.env  # → false
grep APP_URL   /var/www/clients/client10/web19/web/.env  # → https://onhost.cz
grep MAIL_PORT /var/www/clients/client10/web19/web/.env  # → 587

# Nastavte bezpečná oprávnění:
chmod 640 /var/www/clients/client10/web19/web/.env
```

---

## ČÁST 4: Clone z GitHubu

```bash
cd /var/www/clients/client10/web19/web

# Pokud je složka PRÁZDNÁ (nebo jen .env):
git init
git remote add origin https://github.com/Stanektechcz/onhostik.git
git fetch origin
git checkout development
# → .env zůstane (je v .gitignore)

# NEBO pokud chcete čistý clone a pak přesunout .env:
cd /var/www/clients/client10/web19
git clone https://github.com/Stanektechcz/onhostik.git web-tmp
# Zálohujte .env
cp web/web/.env /tmp/onhost-env-backup 2>/dev/null || true
# Přesuňte obsah
cp -a web-tmp/. web/
rm -rf web-tmp
# Obnovte .env
mv /tmp/onhost-env-backup web/.env 2>/dev/null || true

# Ověřte:
cd /var/www/clients/client10/web19/web
git branch          # → development
git log --oneline -3
# → 2712c09 docs: update deploy guide with real ISPConfig paths
# → fb2a506 docs: complete deploy guide for s2.onhost.cz

ls public/          # → index.php  .htaccess
cat .env | grep APP_ENV   # → production  (kontrola, že .env je stále tam)
```

---

## ČÁST 5: Composer install

```bash
cd /var/www/clients/client10/web19/web

# Zjistěte dostupné PHP:
which php8.2 && php8.2 --version

# Instalace závislostí (bez dev balíčků):
php8.2 /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
# nebo:
composer install --no-dev --optimize-autoloader --no-interaction

# Ověřte:
ls vendor/   # → složky balíčků (autoload.php, laravel/, ...)
```

---

## ČÁST 6: APP_KEY — Nikdy neregenerovat!

```bash
cd /var/www/clients/client10/web19/web

# Zkontrolujte APP_KEY:
grep APP_KEY .env
# → APP_KEY=base64:7y3PTP11B9mdAfstGzu0qpluYUzTcCda1O97PcRUB3c=

# Pokud je VYPLNĚNÝ → NIC NEDĚLEJTE
# Pokud je PRÁZDNÝ → php8.2 artisan key:generate --force
```

> ⚠️ APP_KEY šifruje sessions a payout details. Po prvním spuštění ho **nikdy nemeňte**.

---

## ČÁST 7: Migrace a seedery

```bash
cd /var/www/clients/client10/web19/web

# PRVNÍ deploy — --fresh smaže a znovu vytvoří všechny tabulky:
php8.2 artisan migrate:fresh --seed --force

# Ověřte:
php8.2 artisan migrate:status
# → Všechny migrace: Ran | 0 Pending
```

Seedery vytvoří:
- Role + oprávnění
- Admin uživatele `admin@onhost.cz`
- Produktový katalog (Webhosting, VPS, Mailhosting, Managed, Gamehosting = coming soon)
- Mock server, integrace, SiteContent, Blog, KB

> ⚠️ Každý příští deploy: `php8.2 artisan migrate --force` (BEZ --fresh!)

---

## ČÁST 8: Storage, cache a oprávnění

```bash
cd /var/www/clients/client10/web19/web

# Storage symlink:
php8.2 artisan storage:link
# → Vytvoří public/storage → ../storage/app/public

# Cache:
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

# Zjistěte vlastníka souborů (ISPConfig web user):
ls -la /var/www/clients/client10/web19/web/storage/
# → Vlastník je obvykle web19 nebo www-data

# Nastavte oprávnění:
chown -R web19:client10 /var/www/clients/client10/web19/web/storage
chown -R web19:client10 /var/www/clients/client10/web19/web/bootstrap/cache
chmod -R 775 /var/www/clients/client10/web19/web/storage
chmod -R 775 /var/www/clients/client10/web19/web/bootstrap/cache

# Pokud PHP-FPM běží jako www-data místo web19:
# chown -R www-data:www-data storage bootstrap/cache

# Ověřte symlink:
ls -la /var/www/clients/client10/web19/web/public/storage
# → lrwxrwxrwx ... storage -> ../storage/app/public
```

---

## ČÁST 9: Production Doctor

```bash
cd /var/www/clients/client10/web19/web
php8.2 artisan onhost:doctor --production
```

**Akceptovatelný výsledek pro staging:**

```
✅ APP_KEY           configured (base64)
✅ APP_ENV           production
✅ APP_DEBUG=false   false
✅ APP_URL           https://onhost.cz
✅ DB                OH_10_OHnew, migrations OK
✅ Storage           writable, symlink OK
✅ SESSION           database, .onhost.cz, secure=true
✅ MAIL              smtp, info@onhost.cz, port 587
✅ BILLING           Adrian Staněk, 08094616, Molákova 2145/5, Brno-Líšeň, 62800
✅ Scheduler         4 joby

⚠️  PROVISIONING_MOCK_MODE=true    → záměrné
⚠️  AAPANEL_ALLOW_REAL_WRITES=false → záměrné
⚠️  WAPI_ALLOW_REAL_WRITES=false   → záměrné
✗   COMGATE credentials            → záměrné (veřejný launch blocker)
```

Jakýkoli jiný critical error → opravte před pokračováním.

---

## ČÁST 10: Supervisor — Queue Worker

```bash
# Zkopírujte konfiguraci:
cp /var/www/clients/client10/web19/web/deploy/supervisor-queue.conf \
   /etc/supervisor/conf.d/onhost-queue.conf

# Upravte cesty v konfig souboru — aktualizujte artisan path:
nano /etc/supervisor/conf.d/onhost-queue.conf

# Změňte řádek command= na:
# command=php8.2 /var/www/clients/client10/web19/web/artisan queue:work database \
#   --sleep=3 --tries=3 --max-time=3600 \
#   --queue=provisioning-high,default,high,provisioning,low,provisioning-low

# Spusťte:
supervisorctl reread
supervisorctl update
supervisorctl start "onhost-queue:*"

# Ověřte:
supervisorctl status
# → onhost-queue:onhost-queue_00   RUNNING
# → onhost-queue:onhost-queue_01   RUNNING
```

---

## ČÁST 11: Cron — Scheduler

```bash
# Zkopírujte a upravte cron:
cp /var/www/clients/client10/web19/web/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost

# Upravte cron soubor na správné cesty:
nano /etc/cron.d/onhost
# Řádek musí být:
# * * * * * web19 cd /var/www/clients/client10/web19/web && php8.2 artisan schedule:run >> /dev/null 2>&1
# (nebo www-data místo web19 — záleží na PHP-FPM user)

# Ověřte joby:
cd /var/www/clients/client10/web19/web
php8.2 artisan schedule:list
# → billing:create-renewals   00:30 daily
# → billing:mark-overdue      01:00 daily
# → billing:suspend-overdue   01:15 daily
# → partner:approve-eligible  02:00 daily
```

---

## ČÁST 12: Smoke Test

```bash
# Health check:
curl -I https://onhost.cz/up
# → HTTP/2 200

# Veřejné stránky:
for url in / /webhosting /vps /mailhosting /managed-hosting /gamehosting /login /blog; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://onhost.cz$url" -L --max-time 10)
    echo "$CODE https://onhost.cz$url"
done

# Admin subdoména:
curl -I https://admin.onhost.cz/
# → HTTP/2 302   Location: .../admin

# V prohlížeči přihlaste se:
# https://onhost.cz/admin
# Email: admin@onhost.cz
# Heslo: (z .env ADMIN_PASSWORD)
```

---

## ČÁST 13: SMTP Live Test

```bash
cd /var/www/clients/client10/web19/web
php8.2 artisan tinker
```

```php
\Illuminate\Support\Facades\Mail::raw(
    'Onhost.cz SMTP test - ' . now(),
    fn ($m) => $m->to('info@onhost.cz')->subject('Onhost SMTP staging test')
);
exit;
```

Zkontrolujte:
- Žádná exception v konzoli
- `tail /var/www/clients/client10/web19/web/storage/logs/laravel.log` — bez SMTP chyby
- E-mail dorazil

---

## ČÁST 14: E2E Mock Billing Test

```bash
cd /var/www/clients/client10/web19/web
php8.2 artisan tinker
```

```php
$user     = App\Models\User::where('email', 'admin@onhost.cz')->first();
$customer = $user->customer;

if (!$customer->billingAddress()) {
    App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $customer->id, 'type' => 'billing',
        'street' => 'Molákova 2145/5', 'city' => 'Brno',
        'zip' => '62800', 'country_code' => 'CZ', 'is_primary' => true,
    ]);
    $customer->update([
        'type' => 'company',
        'company_name' => 'Adrian Staněk',
        'registration_number' => '08094616'
    ]);
}

$plan    = App\Domains\Products\Models\PricingPlan::whereHas('product',
               fn($q) => $q->where('slug','webhosting')
           )->where('is_active', true)->first();

$order   = app(App\Domains\Billing\Actions\CreateOrderAction::class)->execute($customer, $plan, []);
$invoice = app(App\Domains\Billing\Actions\IssueProformaInvoiceAction::class)->execute($order);
$payment = app(App\Domains\Billing\Actions\ProcessMockPaymentAction::class)->execute($invoice);
$invoice->refresh();

echo "Payment: "  . $payment->status->value . "\n";  // completed
echo "Invoice: "  . $invoice->status->value . " " . $invoice->number . "\n";  // paid

$taxDoc = app(App\Domains\Billing\Actions\IssueTaxDocumentAction::class)->execute($invoice);
echo "Tax doc: "  . $taxDoc->number . "\n";  // CZ-2026-000002

exit;
```

Zpracujte provisioning queue:
```bash
php8.2 artisan queue:work --stop-when-empty --max-jobs=10
```

Ověřte na https://onhost.cz/admin/objednavky, /admin/faktury, /admin/sluzby.

---

## ČÁST 15: Post-Deploy Security (POVINNÉ!)

```bash
# 1. Změňte admin heslo ihned po přihlášení na https://onhost.cz/admin

# 2. Rotujte SMTP heslo v ISPConfig:
#    ISPConfig → Email → Mailboxes → info@onhost.cz → Change password
#    Pak aktualizujte .env:
nano /var/www/clients/client10/web19/web/.env
# → změňte MAIL_PASSWORD="nové-heslo"

# 3. Smažte ADMIN_PASSWORD ze .env:
nano /var/www/clients/client10/web19/web/.env
# → smažte řádek ADMIN_PASSWORD=...

# 4. Obnovte config cache po změně .env:
cd /var/www/clients/client10/web19/web
php8.2 artisan config:cache
```

---

## ČÁST 16: Budoucí deploye (aktualizace kódu)

```bash
cd /var/www/clients/client10/web19/web

git pull origin development

composer install --no-dev --optimize-autoloader --no-interaction

php8.2 artisan migrate --force        # BEZ --fresh!

php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

chown -R web19:client10 storage bootstrap/cache

supervisorctl restart "onhost-queue:*"

php8.2 artisan onhost:doctor --production
```

---

## Kompletní checklist

```
ISPConfig:
[ ] onhost.cz Custom Doc Root = /var/www/clients/client10/web19/web/public
[ ] admin.onhost.cz Custom Doc Root = /var/www/clients/client10/web19/web/public (stejná!)
[ ] Apache Directives s AllowOverride All na obou doménách
[ ] admin.onhost.cz má RewriteRule ^/?$ /admin [R=302,L]
[ ] SSL certifikáty aktivní

Server — první deploy:
[ ] .env přenesen na /var/www/clients/client10/web19/web/.env
[ ] git clone branch development do /web19/web/
[ ] composer install --no-dev
[ ] APP_KEY zachován (NEprázdný, NEregenerován)
[ ] php8.2 artisan migrate:fresh --seed --force
[ ] php8.2 artisan storage:link
[ ] config/route/view/event cache
[ ] Oprávnění storage + bootstrap/cache (web19:client10)
[ ] onhost:doctor --production → bez neočekávaných critical errors

Services:
[ ] Supervisor: onhost-queue RUNNING (2 procesy), cesta v conf aktualizována
[ ] Cron: schedule:run každou minutu, cesta aktualizována

Testy:
[ ] curl https://onhost.cz/up → 200
[ ] Smoke test stránek → 200
[ ] Admin přihlášení funguje
[ ] SMTP test → e-mail dorazil

Security (IHNED po deployi):
[ ] Admin heslo ZMĚNĚNO
[ ] SMTP heslo ROTOVÁNO v ISPConfig
[ ] ADMIN_PASSWORD odstraněno ze .env
[ ] php8.2 artisan config:cache po úpravě .env
```

---

## Přehled cest

| | Cesta |
|---|---|
| **Laravel projekt** | `/var/www/clients/client10/web19/web/` |
| **Document Root** (obě domény) | `/var/www/clients/client10/web19/web/public` |
| **.env** | `/var/www/clients/client10/web19/web/.env` |
| **Logy** | `/var/www/clients/client10/web19/web/storage/logs/laravel.log` |
| **Artisan** | `php8.2 /var/www/clients/client10/web19/web/artisan` |
| **Supervisor conf** | `/etc/supervisor/conf.d/onhost-queue.conf` |
| **Cron** | `/etc/cron.d/onhost` |

---

## Rychlé příkazy (vše najednou)

```bash
APP=/var/www/clients/client10/web19/web
cd $APP

composer install --no-dev --optimize-autoloader
php8.2 artisan migrate:fresh --seed --force
php8.2 artisan storage:link
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache
chown -R web19:client10 $APP/storage $APP/bootstrap/cache
chmod -R 775 $APP/storage $APP/bootstrap/cache
supervisorctl reread && supervisorctl update && supervisorctl start "onhost-queue:*"
php8.2 artisan onhost:doctor --production
```
