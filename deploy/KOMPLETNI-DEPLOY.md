# OnHost.cz — Kompletní deploy průvodce

**Repozitář:** https://github.com/Stanektechcz/onhostik  
**Branch:** `development`  
**Server:** s2.onhost.cz  
**PHP:** 8.2, MySQL, Apache + ISPConfig  

---

## Přehled architektury

```
GitHub repo (onhostik)
        ↓ git clone
/var/www/onhost/          ← JEDEN projekt pro obě domény
├── public/               ← Document Root pro onhost.cz i admin.onhost.cz
├── .env                  ← produkční konfigurace (z .env.production na lokálu)
└── ...

onhost.cz       → /var/www/onhost/public   (veřejný web + /panel + /partner)
admin.onhost.cz → /var/www/onhost/public   (totéž, Apache přesměruje / → /admin)
```

---

## ČÁST 1: ISPConfig — Weby a Apache

### 1.1 Vytvořte web onhost.cz

ISPConfig → **Sites → Add Website**:

| Pole | Hodnota |
|------|---------|
| Domain | `onhost.cz` |
| Auto-Subdomain | `www` |
| Document Root | `/web` (ISPConfig přidá prefix automaticky) |
| Custom Document Root | `/var/www/onhost/public` |
| PHP | FPM 8.2 |
| SSL | ✓ Let's Encrypt |
| Force SSL | ✓ |

**Options → Apache Directives** (vložte):
```apache
<Directory /var/www/onhost/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

---

### 1.2 Vytvořte web admin.onhost.cz

ISPConfig → **Sites → Add Website** (nebo Add Subdomain):

| Pole | Hodnota |
|------|---------|
| Domain | `admin.onhost.cz` |
| Custom Document Root | `/var/www/onhost/public` (STEJNÁ jako onhost.cz!) |
| PHP | FPM 8.2 |
| SSL | ✓ Let's Encrypt |
| Force SSL | ✓ |

**Options → Apache Directives** (vložte):
```apache
<Directory /var/www/onhost/public>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# Přesměruj kořen domény na /admin
RewriteEngine On
RewriteRule ^/?$ /admin [R=302,L]

Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set Strict-Transport-Security "max-age=31536000"
```

> ⚠️ Pokud ISPConfig neumožní dvě domény se stejnou složkou, přidejte ServerAlias nebo
> vytvořte admin.onhost.cz jako alias v konfiguraci onhost.cz.

---

## ČÁST 2: Přenos .env na server

Na **lokálním počítači** (ve složce projektu):

```bash
# Zkopírujte produkční .env na server:
scp .env.production root@s2.onhost.cz:/tmp/onhost-env
```

Na **serveru**:
```bash
# Vytvořte složku a přesuňte .env
mkdir -p /var/www/onhost
mv /tmp/onhost-env /var/www/onhost/.env

# Ověřte:
grep APP_ENV /var/www/onhost/.env    # → production
grep APP_DEBUG /var/www/onhost/.env  # → false
grep APP_URL /var/www/onhost/.env    # → https://onhost.cz
grep MAIL_PORT /var/www/onhost/.env  # → 587 (NE 3306!)
ls -la /var/www/onhost/.env          # → oprávnění jen pro www-data
```

---

## ČÁST 3: Clone z GitHubu

Na **serveru**:

```bash
# Klonujte do /var/www/onhost
# (pokud složka již existuje s .env, použijte --separate-git-dir nebo přidejte remote do existující)
cd /var/www
git clone https://github.com/Stanektechcz/onhostik.git onhost-git
cp -a onhost-git/. onhost/
rm -rf onhost-git

# NEBO — pokud složka /var/www/onhost je prázdná (jen .env):
cd /var/www/onhost
git init
git remote add origin https://github.com/Stanektechcz/onhostik.git
git fetch origin
git checkout development
# .env zůstane (gitignored)

# Ověřte branch a poslední commit:
git branch        # → development
git log --oneline -3
# → ba25bfc docs: staging-deploy-docs-update
# → f26b155 docs: phase-24-production-env-completion-docs
```

---

## ČÁST 4: Instalace závislostí

```bash
cd /var/www/onhost

# Composer — production mode, bez dev balíčků
composer install --no-dev --optimize-autoloader --no-interaction

# Ověřte:
ls vendor/  # → složky balíčků
```

> PHP 8.2 a composer musí být dostupné. Zkontrolujte: `php8.2 --version` a `composer --version`

---

## ČÁST 5: APP_KEY — Pozor!

```bash
# Zkontrolujte, zda APP_KEY NENÍ prázdný:
grep APP_KEY /var/www/onhost/.env

# → Musí vypadat: APP_KEY=base64:XXXX...
# → Pokud je PRÁZDNÝ: php artisan key:generate --force
# → Pokud je VYPLNĚNÝ: NEZAHAJUJTE generování! APP_KEY se po prvním spuštění nesmí měnit.
```

---

## ČÁST 6: Migrace a seedery (PRVNÍ deploy = --fresh)

```bash
cd /var/www/onhost

# PRVNÍ deploy — smaže a znovu vytvoří všechny tabulky + seeduje data:
php artisan migrate:fresh --seed --force

# Ověřte:
php artisan migrate:status | grep Pending  # → žádné pending
```

Co seedery vytvoří:
- Role a oprávnění (admin, customer, partner, support)
- Admin uživatel `admin@onhost.cz`
- Produktový katalog (Webhosting, VPS, Mailhosting, Managed, Gamehosting coming soon)
- Mock server pro provisioning
- Integrace
- AI prompt šablony
- SiteContent (homepage announcement)
- Blog a KB starter obsah

> Na každém dalším deployi používejte `php artisan migrate --force` (BEZ --fresh, aby se nezmazala data)

---

## ČÁST 7: Storage, cache a oprávnění

```bash
cd /var/www/onhost

# Storage symlink (public/storage → storage/app/public)
php artisan storage:link

# Artisan caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Oprávnění — www-data musí moci zapisovat
chown -R www-data:www-data /var/www/onhost/storage
chown -R www-data:www-data /var/www/onhost/bootstrap/cache
chmod -R 775 /var/www/onhost/storage
chmod -R 775 /var/www/onhost/bootstrap/cache

# Ověřte:
ls -la public/storage       # → symlink na ../storage/app/public
ls -la storage/logs/         # → zapisovatelné
```

---

## ČÁST 8: Production Doctor

```bash
cd /var/www/onhost
php artisan onhost:doctor --production
```

**Akceptovatelný výsledek pro první staging deploy:**
```
✅ APP_KEY           configured (base64)
✅ APP_ENV           production
✅ APP_DEBUG=false   false
✅ APP_URL           https://onhost.cz
✅ DB                připojeno, migrace OK
✅ Storage           zapisovatelné, symlink OK
✅ SESSION           database, .onhost.cz, secure=true
✅ MAIL              smtp, info@onhost.cz, port 587
✅ BILLING           Adrian Staněk, 08094616, Molákova 2145/5...
✅ Scheduler         4 joby

⚠️  PROVISIONING_MOCK_MODE=true   → záměrné (staged cutover)
⚠️  AAPANEL_ALLOW_REAL_WRITES=false → záměrné
⚠️  WAPI_ALLOW_REAL_WRITES=false  → záměrné
✗  COMGATE credentials missing    → záměrné (veřejný launch blocker)
```

Pokud vidíte jiný critical error → opravte před pokračováním.

---

## ČÁST 9: Supervisor — Queue Worker

```bash
# Zkopírujte konfiguraci
cp /var/www/onhost/deploy/supervisor-queue.conf /etc/supervisor/conf.d/onhost-queue.conf

# Ověřte PHP cestu (musí odpovídat serveru):
which php8.2       # → /usr/bin/php8.2
# Pokud je na jiné cestě, upravte /etc/supervisor/conf.d/onhost-queue.conf:
# command=php8.2 /var/www/onhost/artisan queue:work ...

# Načtěte novou konfiguraci a spusťte:
supervisorctl reread
supervisorctl update
supervisorctl start "onhost-queue:*"

# Ověřte — oba procesy musí být RUNNING:
supervisorctl status
# → onhost-queue:onhost-queue_00   RUNNING   pid XXXX
# → onhost-queue:onhost-queue_01   RUNNING   pid XXXX
```

Co worker zpracovává:
- Provisioning webhostingu a VPS
- Registrace domén
- Suspend/unsuspend služeb
- Obecné queue joby

---

## ČÁST 10: Cron — Scheduler

```bash
# Metoda A — /etc/cron.d/ (doporučeno):
cp /var/www/onhost/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost
cat /etc/cron.d/onhost
# → * * * * * www-data cd /var/www/onhost && php8.2 artisan schedule:run >> /dev/null 2>&1

# Metoda B — crontab pro www-data:
crontab -u www-data -e
# Přidejte řádek:
* * * * * cd /var/www/onhost && php8.2 artisan schedule:run >> /dev/null 2>&1

# Ověřte registrované joby:
php artisan schedule:list
# → billing:create-renewals   00:30 daily
# → billing:mark-overdue      01:00 daily
# → billing:suspend-overdue   01:15 daily
# → partner:approve-eligible  02:00 daily
```

---

## ČÁST 11: Smoke Test — Základní ověření

```bash
# Health endpoint:
curl -I https://onhost.cz/up
# → HTTP/2 200

# Hlavní stránky:
for url in / /webhosting /vps /mailhosting /managed-hosting /domeny /gamehosting /login /register /blog /znalostni-baze; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://onhost.cz$url" --max-time 10 -L)
    echo "$CODE https://onhost.cz$url"
done
# → všechny musí být 200

# Admin redirect:
curl -I https://admin.onhost.cz/
# → 302 Location: https://admin.onhost.cz/admin

# Admin přihlášení v prohlížeči:
# https://onhost.cz/admin
# Email: admin@onhost.cz
# Heslo: (z .env.production ADMIN_PASSWORD)
```

---

## ČÁST 12: SMTP Live Test

```bash
cd /var/www/onhost
php artisan tinker
```

V tinker konzoli:
```php
\Illuminate\Support\Facades\Mail::raw(
    'Onhost.cz SMTP test - ' . now(),
    fn ($m) => $m->to('info@onhost.cz')->subject('Onhost SMTP staging test')
);
exit;
```

Ověřte:
- Žádná exception v tinker
- `tail -f /var/www/onhost/storage/logs/laravel.log` nevypisuje SMTP error
- E-mail dorazil na info@onhost.cz

---

## ČÁST 13: E2E Mock Billing Test

```bash
cd /var/www/onhost
php artisan tinker
```

V tinker konzoli:
```php
// Admin user jako zákazník
$user = App\Models\User::where('email', 'admin@onhost.cz')->first();
$customer = $user->customer;

// Přidej billing adresu pokud ještě není
if (!$customer->billingAddress()) {
    App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $customer->id, 'type' => 'billing',
        'street' => 'Molákova 2145/5', 'city' => 'Brno',
        'zip' => '62800', 'country_code' => 'CZ', 'is_primary' => true,
    ]);
    $customer->update(['type' => 'company', 'company_name' => 'Adrian Staněk', 'registration_number' => '08094616']);
}

// Order → Proforma → Mock payment
$plan = App\Domains\Products\Models\PricingPlan::whereHas('product',
    fn($q) => $q->where('slug', 'webhosting')
)->where('is_active', true)->first();

$order   = app(App\Domains\Billing\Actions\CreateOrderAction::class)->execute($customer, $plan, []);
$invoice = app(App\Domains\Billing\Actions\IssueProformaInvoiceAction::class)->execute($order);
$payment = app(App\Domains\Billing\Actions\ProcessMockPaymentAction::class)->execute($invoice);
$invoice->refresh();

echo "Payment: " . $payment->status->value . "\n";         // → completed
echo "Invoice: " . $invoice->status->value . " " . $invoice->number . "\n"; // → paid CZ-2026-000001

// Tax document
$taxDoc = app(App\Domains\Billing\Actions\IssueTaxDocumentAction::class)->execute($invoice);
echo "Tax doc: " . $taxDoc->number . "\n";                 // → CZ-2026-000002

exit;
```

Pak ve frontě zpracujte provisioning job:
```bash
php artisan queue:work --stop-when-empty --max-jobs=5
php artisan queue:work --stop-when-empty --max-jobs=5  # druhé kolo pro CheckProxmoxTask
```

Ověřte v admin panelu:
- `https://onhost.cz/admin/objednavky` — objednávka existuje
- `https://onhost.cz/admin/faktury` — faktura + daňový doklad
- `https://onhost.cz/admin/sluzby` — služba Active (MOCK-AAP-*)

---

## ČÁST 14: Post-Deploy Security (NUTNÉ!)

```bash
# 1. Změňte admin heslo — přihlaste se a jděte na:
# https://onhost.cz/admin → "Zapomenuté heslo" nebo přes nastavení

# 2. Rotujte SMTP heslo (bylo sdíleno přes chat):
# ISPConfig → Email → Mailboxes → info@onhost.cz → Change password
# Pak aktualizujte MAIL_PASSWORD v /var/www/onhost/.env:
nano /var/www/onhost/.env    # nebo vim, nebo sed

# 3. Po změně .env znovu cachujte config:
cd /var/www/onhost
php artisan config:cache

# 4. Smažte ADMIN_PASSWORD ze .env (není potřeba po vytvoření admina):
# V /var/www/onhost/.env smažte řádek ADMIN_PASSWORD=...
php artisan config:cache
```

---

## ČÁST 15: Budoucí deploye (ne --fresh!)

Pro každý příští deploy (po prvním):

```bash
cd /var/www/onhost

git pull origin development

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force          # BEZ --fresh! Data se zachovají

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data storage bootstrap/cache

supervisorctl restart "onhost-queue:*"

php artisan onhost:doctor --production
```

---

## ČÁST 16: Staged Cutover (po prvním testování)

| Krok | Akce | Kdy |
|------|------|-----|
| 1 | SMTP heslo rotace | Okamžitě po deployi |
| 2 | Admin heslo změna | Okamžitě po prvním loginu |
| 3 | WEDOS WAPI test | Po ověření availability check |
| 4 | aaPanel server přidání | V adminu → /admin/servery |
| 5 | Comgate sandbox | Po aktivaci merchant účtu |
| 6 | `COMGATE_TEST_MODE=false` | Po úspěšném sandbox testu |
| 7 | `AAPANEL_ALLOW_REAL_WRITES=true` | Po Connection Test v adminu |
| 8 | `PROVISIONING_MOCK_MODE=false` | Po ověření aaPanel provisioning |
| 9 | Marketing launch | Po Comgate + právní kontrole |

---

## GO/NO-GO

| Tier | Podmínky |
|------|---------|
| ✅ **Technický staging** | Části 1–11 splněny, doctor OK |
| ✅ **Neveřejný staging** | + SMTP test + E2E billing test |
| ❌ **Veřejný launch** | + Comgate credentials + právní kontrola |

---

## Checklist

```
[ ] ISPConfig: onhost.cz vytvořen, doc root = /var/www/onhost/public
[ ] ISPConfig: admin.onhost.cz vytvořen, doc root = /var/www/onhost/public
[ ] SSL certifikáty aktivní na obou doménách
[ ] .env přenesen na server (ne v gitu!)
[ ] git clone z https://github.com/Stanektechcz/onhostik branch development
[ ] composer install --no-dev
[ ] APP_KEY zachován (ne regenerován)
[ ] migrate:fresh --seed proběhlo
[ ] storage:link
[ ] config/route/view/event cache
[ ] Oprávnění www-data na storage + bootstrap/cache
[ ] onhost:doctor --production → 0 neočekávaných critical
[ ] Supervisor: onhost-queue RUNNING (2 procesy)
[ ] Cron: schedule:run každou minutu
[ ] curl https://onhost.cz/up → 200
[ ] Admin přihlášení funguje
[ ] Admin heslo ZMĚNĚNO
[ ] SMTP test proběhl (e-mail dorazil)
[ ] SMTP heslo ROTOVÁNO
[ ] E2E mock billing test proběhl
[ ] ADMIN_PASSWORD odstraněno ze .env
```

---

## Důležité soubory

| Soubor | Popis |
|--------|-------|
| `.env.production` | Produkční env (lokálně, nikdy do gitu) |
| `deploy/supervisor-queue.conf` | Queue worker konfigurece |
| `deploy/cron.txt` | Cron řádek pro scheduler |
| `deploy/apache-onhost.cz.conf` | Apache vhost pro přímé nasazení bez ISPConfig |
| `deploy/apache-admin.onhost.cz.conf` | Apache vhost admin subdomény |
| `docs/PRODUCTION-BLOCKERS.md` | Zbývající blockery |
| `docs/COMGATE-CUTOVER.md` | Průvodce Comgate integrací |
| `docs/LEGAL-REVIEW-CHECKLIST.md` | Právní kontrola |
