# OnHost.cz — Kompletní Deploy Průvodce

**Repozitář:** https://github.com/Stanektechcz/onhostik  
**Branch:** `development`  
**Server:** s2.onhost.cz  
**PHP:** 8.2, MySQL (OH_10_OHnew), Apache + ISPConfig  

---

## Reálné cesty na serveru

| Doména | ISPConfig cesta | Nový Document Root |
|--------|----------------|--------------------|
| `onhost.cz` | `/var/www/clients/client10/web19/web` | `/var/www/clients/client10/web19/public` |
| `admin.onhost.cz` | `/var/www/clients/client10/web62/web` | `/var/www/clients/client10/web19/public` |

> **Klíčová informace:** Laravel se nainstaluje do `/var/www/clients/client10/web19/`
> (rodičovská složka stránky onhost.cz). Obě domény potom budou mít Custom Document Root
> nastaven na `/var/www/clients/client10/web19/public`.

---

## ČÁST 1: ISPConfig — Změna Document Root

### 1.1 onhost.cz — změňte Document Root

ISPConfig → **Sites → Websites → onhost.cz → Edit**:

- Záložka **Domain**:
  - ✅ SSL / Let's Encrypt by mělo být aktivní
- Záložka **Options → Custom Document Root**:
  - Změňte z: `/var/www/clients/client10/web19/web`
  - Na: `/var/www/clients/client10/web19/public`

**Apache Directives** (záložka Options → Apache Directives — přidejte):
```apache
<Directory /var/www/clients/client10/web19/public>
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

### 1.2 admin.onhost.cz — změňte Document Root na STEJNOU složku

ISPConfig → **Sites → Websites → admin.onhost.cz → Edit**:

- Záložka **Options → Custom Document Root**:
  - Změňte z: `/var/www/clients/client10/web62/web`
  - Na: `/var/www/clients/client10/web19/public` ← **stejná jako onhost.cz!**

**Apache Directives** (záložka Options → Apache Directives):
```apache
<Directory /var/www/clients/client10/web19/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Přesměruj / na /admin
RewriteEngine On
RewriteRule ^/?$ /admin [R=302,L]

Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000"
```

Klikněte **Save**. ISPConfig automaticky reloadne Apache.

---

## ČÁST 2: SSH na server a příprava složky

```bash
# Přihlaste se na server:
ssh root@s2.onhost.cz

# Ověřte, že složka web19 existuje:
ls /var/www/clients/client10/web19/
# Měla by existovat podsložka web/, log/, ... — to jsou ISPConfig složky

# Složka web/ nás už nezajímá (Laravel půjde do parent /web19/)
# Můžeme ji nechat prázdnou nebo ji smazat:
# rm -rf /var/www/clients/client10/web19/web/*   # volitelné
```

---

## ČÁST 3: Přenos .env na server

Na **lokálním počítači** (spusťte v příkazové řádce ve složce projektu):

```bash
scp .env.production root@s2.onhost.cz:/tmp/onhost-env
```

Na **serveru**:
```bash
# Přesuňte .env do cílové složky:
mv /tmp/onhost-env /var/www/clients/client10/web19/.env

# Ověřte klíčové hodnoty:
grep APP_ENV   /var/www/clients/client10/web19/.env  # → production
grep APP_DEBUG /var/www/clients/client10/web19/.env  # → false
grep APP_URL   /var/www/clients/client10/web19/.env  # → https://onhost.cz
grep MAIL_PORT /var/www/clients/client10/web19/.env  # → 587 (NE 3306!)

# .env nesmí být čitelný pro veřejnost:
chmod 640 /var/www/clients/client10/web19/.env
chown web19:client10 /var/www/clients/client10/web19/.env
# (uživatel a skupina závisí na ISPConfig konfiguraci — ověřte: ls -la /var/www/clients/client10/web19/)
```

---

## ČÁST 4: Clone z GitHubu

```bash
# Klonujte do rodičovské složky (NE do /web19/web/ ale do /web19/):
cd /var/www/clients/client10

# MOŽNOST A — klonovat do nové složky a pak přesunout:
git clone https://github.com/Stanektechcz/onhostik.git onhost-tmp
# Přesuňte obsah do web19/:
cp -a onhost-tmp/. web19/
rm -rf onhost-tmp

# MOŽNOST B — inicializovat git přímo ve web19/:
cd /var/www/clients/client10/web19
git init
git remote add origin https://github.com/Stanektechcz/onhostik.git
git fetch origin
git checkout development
# .env zůstane (je v .gitignore)

# Ověřte:
cd /var/www/clients/client10/web19
git branch          # → development
git log --oneline -3
# → fb2a506 docs: complete deploy guide for s2.onhost.cz
# → ba25bfc docs: staging-deploy-docs-update
# → f26b155 docs: phase-24-production-env-completion-docs

ls                  # → app/ bootstrap/ config/ database/ public/ resources/ routes/ vendor(nebude) .env ...
ls public/          # → index.php .htaccess ...
```

---

## ČÁST 5: Instalace Composeru

```bash
cd /var/www/clients/client10/web19

# Zjistěte PHP binary:
which php8.2 || which php
php8.2 --version    # musí být 8.2.x

# Instalace závislostí (production mode, bez dev):
php8.2 /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction
# nebo pokud composer je dostupný přímo:
composer install --no-dev --optimize-autoloader --no-interaction

# Ověřte:
ls vendor/   # → složky balíčků
```

---

## ČÁST 6: APP_KEY — Důležité!

```bash
cd /var/www/clients/client10/web19

# Zkontrolujte APP_KEY:
grep APP_KEY .env

# → Musí vypadat: APP_KEY=base64:7y3PTP11...
# → Pokud je VYPLNĚNÝ: NIC NEDĚLEJTE! APP_KEY se nesmí měnit.
# → Pokud je PRÁZDNÝ: php artisan key:generate --force
```

> ⚠️ **Kritické:** APP_KEY šifruje sessions a partner payout details.
> Po prvním spuštění ho NIKDY nemeňte.

---

## ČÁST 7: Migrace a seedery (PRVNÍ deploy)

```bash
cd /var/www/clients/client10/web19

# PRVNÍ deploy — --fresh smaže vše a vytvoří nové (ztratí se data!):
php8.2 artisan migrate:fresh --seed --force

# Ověřte:
php8.2 artisan migrate:status
# → Všechny migrace: Ran | 0 Pending

# Co se seedovalo:
# ✅ Role: admin, customer, support, partner
# ✅ Admin uživatel: admin@onhost.cz
# ✅ Produkty: Webhosting, VPS, Mailhosting, Managed, Gamehosting (coming soon)
# ✅ Mock server pro provisioning
# ✅ Integrace (Comgate, aaPanel, WEDOS placeholders)
# ✅ SiteContent (homepage announcement bar)
# ✅ Blog (2 články) + KB (5 článků)
```

> Pro každý příští deploy: `php8.2 artisan migrate --force` (BEZ --fresh!)

---

## ČÁST 8: Storage, cache a oprávnění

```bash
cd /var/www/clients/client10/web19

# Storage symlink (public/storage → storage/app/public):
php8.2 artisan storage:link

# Artisan cache:
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

# Oprávnění — ISPConfig web user musí moci zapisovat:
# (zjistěte správného vlastníka: ls -la /var/www/clients/client10/web19/)
chown -R web19:client10 /var/www/clients/client10/web19/storage
chown -R web19:client10 /var/www/clients/client10/web19/bootstrap/cache
chmod -R 775 /var/www/clients/client10/web19/storage
chmod -R 775 /var/www/clients/client10/web19/bootstrap/cache

# Pokud PHP-FPM běží jako www-data:
# chown -R www-data:www-data storage bootstrap/cache

# Ověřte symlink:
ls -la /var/www/clients/client10/web19/public/storage
# → lrwxrwxrwx ... storage -> ../storage/app/public
```

---

## ČÁST 9: Production Doctor

```bash
cd /var/www/clients/client10/web19
php8.2 artisan onhost:doctor --production
```

**Akceptovatelný výsledek pro staging deploy:**
```
✅ APP_KEY           configured (base64)
✅ APP_ENV           production
✅ APP_DEBUG=false   false
✅ APP_URL           https://onhost.cz
✅ DB connection     OH_10_OHnew
✅ migrations        all applied
✅ Storage           writable, symlink OK
✅ SESSION           database, .onhost.cz, secure=true
✅ MAIL              smtp, info@onhost.cz, port 587
✅ BILLING           Adrian Staněk, 08094616, Molákova 2145/5, Brno, 62800
✅ Scheduler         4 joby

⚠️  PROVISIONING_MOCK_MODE=true   → záměrné
⚠️  AAPANEL_ALLOW_REAL_WRITES=false → záměrné
⚠️  WAPI_ALLOW_REAL_WRITES=false  → záměrné
✗   COMGATE credentials           → záměrné (veřejný launch blocker)
```

Pokud vidíte jiné critical errors → opravte před pokračováním.

---

## ČÁST 10: Supervisor — Queue Worker

```bash
# Zkopírujte konfiguraci:
cp /var/www/clients/client10/web19/deploy/supervisor-queue.conf \
   /etc/supervisor/conf.d/onhost-queue.conf

# Ověřte PHP binary v konfig souboru:
grep command /etc/supervisor/conf.d/onhost-queue.conf
# Musí odpovídat: php8.2 /var/www/clients/client10/web19/artisan queue:work ...
# Pokud cesta nesedí, upravte:
nano /etc/supervisor/conf.d/onhost-queue.conf
# Změňte řádek command=php8.2 /var/www/clients/client10/web19/artisan queue:work ...

# Spusťte:
supervisorctl reread
supervisorctl update
supervisorctl start "onhost-queue:*"

# Ověřte:
supervisorctl status
# → onhost-queue:onhost-queue_00   RUNNING   pid XXXXX, uptime 0:00:XX
# → onhost-queue:onhost-queue_01   RUNNING   pid XXXXX, uptime 0:00:XX
```

---

## ČÁST 11: Cron — Scheduler

```bash
# Metoda A — /etc/cron.d/:
cp /var/www/clients/client10/web19/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost

# Upravte cron.txt na správnou cestu (pokud se liší):
nano /etc/cron.d/onhost
# Řádek musí být:
# * * * * * www-data cd /var/www/clients/client10/web19 && php8.2 artisan schedule:run >> /dev/null 2>&1

# NEBO Metoda B — přidejte ručně do crontabu:
crontab -u www-data -e
# Přidejte:
* * * * * cd /var/www/clients/client10/web19 && php8.2 artisan schedule:run >> /dev/null 2>&1

# Ověřte registrované joby:
cd /var/www/clients/client10/web19
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
# → Všechno 200

# Admin subdoména redirect:
curl -I https://admin.onhost.cz/
# → HTTP/2 302   Location: https://admin.onhost.cz/admin
curl -I https://admin.onhost.cz/admin
# → HTTP/2 200 nebo 302 (redirect na login)

# V prohlížeči:
# https://onhost.cz/admin → login stránka
# https://admin.onhost.cz → redirect na /admin → login stránka
```

---

## ČÁST 13: SMTP Live Test

```bash
cd /var/www/clients/client10/web19
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
- `tail /var/www/clients/client10/web19/storage/logs/laravel.log` — bez SMTP erroru
- E-mail dorazil na info@onhost.cz

---

## ČÁST 14: E2E Mock Billing Test

```bash
cd /var/www/clients/client10/web19
php8.2 artisan tinker
```

```php
$user = App\Models\User::where('email', 'admin@onhost.cz')->first();
$customer = $user->customer;

// Přidej billing adresu (pokud ještě není — seeder ji přidal, ale pro jistotu):
if (!$customer->billingAddress()) {
    App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $customer->id, 'type' => 'billing',
        'street' => 'Molákova 2145/5', 'city' => 'Brno',
        'zip' => '62800', 'country_code' => 'CZ', 'is_primary' => true,
    ]);
    $customer->update(['type' => 'company', 'company_name' => 'Adrian Staněk', 'registration_number' => '08094616']);
}

$plan    = App\Domains\Products\Models\PricingPlan::whereHas('product', fn($q) => $q->where('slug','webhosting'))->where('is_active',true)->first();
$order   = app(App\Domains\Billing\Actions\CreateOrderAction::class)->execute($customer, $plan, []);
$invoice = app(App\Domains\Billing\Actions\IssueProformaInvoiceAction::class)->execute($order);
$payment = app(App\Domains\Billing\Actions\ProcessMockPaymentAction::class)->execute($invoice);
$invoice->refresh();

echo "Payment: "  . $payment->status->value . "\n";  // → completed
echo "Invoice: "  . $invoice->status->value . " " . $invoice->number . "\n";  // → paid CZ-2026-000001

$taxDoc = app(App\Domains\Billing\Actions\IssueTaxDocumentAction::class)->execute($invoice);
echo "Tax doc: "  . $taxDoc->number . "\n";  // → CZ-2026-000002

exit;
```

Zpracujte provisioning job:
```bash
php8.2 artisan queue:work --stop-when-empty --max-jobs=10
```

Ověřte v adminu:
- https://onhost.cz/admin/objednavky → objednávka existuje
- https://onhost.cz/admin/faktury → faktura + daňový doklad
- https://onhost.cz/admin/sluzby → služba Active (MOCK-AAP-...)

---

## ČÁST 15: Post-Deploy Security (POVINNÉ!)

```bash
# 1. Změňte admin heslo — přihlaste se na https://onhost.cz/admin
#    → "Zapomenuté heslo" nebo Nastavení → Změna hesla

# 2. Rotujte SMTP heslo (bylo sdíleno přes chat):
#    ISPConfig → Email → Mailboxes → info@onhost.cz → Change password
#    Pak aktualizujte .env na serveru:
nano /var/www/clients/client10/web19/.env
# Změňte řádek: MAIL_PASSWORD="nové-heslo"

# 3. Smažte ADMIN_PASSWORD ze .env (bezpečnost):
#    Smažte řádek ADMIN_PASSWORD=... z .env

# 4. Po úpravě .env znovu cachujte:
cd /var/www/clients/client10/web19
php8.2 artisan config:cache
```

---

## ČÁST 16: Budoucí deploye (aktualizace)

Při každé další aktualizaci kódu — **BEZ `--fresh`!**:

```bash
cd /var/www/clients/client10/web19

git pull origin development

composer install --no-dev --optimize-autoloader --no-interaction

php8.2 artisan migrate --force        # BEZ --fresh! Data se zachovají.

php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache

chown -R web19:client10 storage bootstrap/cache

supervisorctl restart "onhost-queue:*"

php8.2 artisan onhost:doctor --production
```

---

## ČÁST 17: Staged Cutover (po staging testování)

```
[ ] SMTP heslo rotace          → okamžitě po deployi
[ ] Admin heslo změna          → okamžitě po prvním loginu
[ ] ADMIN_PASSWORD ze .env     → smazat po vytvoření admina
[ ] Comgate sandbox test       → po aktivaci merchant účtu na portal.comgate.cz
[ ] COMGATE_TEST_MODE=false    → po úspěšném sandbox testu
[ ] aaPanel server přidání     → adminu → /admin/servery → Test connection
[ ] AAPANEL_ALLOW_REAL_WRITES  → true, až po Connection Test
[ ] PROVISIONING_MOCK_MODE     → false, až po ověření aaPanel
[ ] WEDOS WAPI ověření         → test availability check na reálnou doménu
[ ] WAPI_ALLOW_REAL_WRITES     → true, až po ověření
[ ] Právní kontrola            → docs/LEGAL-REVIEW-CHECKLIST.md
[ ] Marketing launch           → announce, start provozu
```

---

## Kompletní checklist

```
ISPConfig:
[ ] onhost.cz Custom Doc Root = /var/www/clients/client10/web19/public
[ ] admin.onhost.cz Custom Doc Root = /var/www/clients/client10/web19/public
[ ] SSL certifikáty aktivní na obou doménách
[ ] Apache Directives vloženy pro obě domény

Server:
[ ] .env přenesen na /var/www/clients/client10/web19/.env
[ ] git clone z github.com/Stanektechcz/onhostik branch development
[ ] composer install --no-dev
[ ] APP_KEY je zachován (neprázdný, nezměněný)
[ ] migrate:fresh --seed --force (první deploy)
[ ] storage:link
[ ] config/route/view/event cache
[ ] Oprávnění storage + bootstrap/cache (web19:client10 nebo www-data)
[ ] onhost:doctor --production → pouze záměrné warningy

Services:
[ ] Supervisor: onhost-queue RUNNING (2 procesy)
[ ] Cron: schedule:run každou minutu

Testy:
[ ] curl https://onhost.cz/up → 200
[ ] Smoke test veřejných stránek → všechny 200
[ ] Admin přihlášení funguje
[ ] SMTP test → e-mail dorazil
[ ] E2E mock billing → payment + tax doc

Security:
[ ] Admin heslo ZMĚNĚNO po prvním loginu
[ ] SMTP heslo ROTOVÁNO v ISPConfig
[ ] ADMIN_PASSWORD odstraněno ze .env
[ ] php8.2 artisan config:cache po úpravě .env
```

---

## Důležité cesty

| Soubor/složka | Cesta na serveru |
|---------------|-----------------|
| Laravel projekt | `/var/www/clients/client10/web19/` |
| Document Root (obě domény) | `/var/www/clients/client10/web19/public` |
| Produkční .env | `/var/www/clients/client10/web19/.env` |
| Laravel logy | `/var/www/clients/client10/web19/storage/logs/laravel.log` |
| Supervisor config | `/etc/supervisor/conf.d/onhost-queue.conf` |
| Cron | `/etc/cron.d/onhost` |

---

## Klíčové příkazy rychlého přehledu

```bash
# Všechno najednou — ruční fallback bez deploy.sh:
APP=/var/www/clients/client10/web19
cd $APP

composer install --no-dev --optimize-autoloader
php8.2 artisan migrate:fresh --seed --force    # jen první deploy!
php8.2 artisan storage:link
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
php8.2 artisan event:cache
chown -R web19:client10 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
supervisorctl reread && supervisorctl update && supervisorctl start "onhost-queue:*"
php8.2 artisan onhost:doctor --production
```
