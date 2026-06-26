# OnHost.cz — Staging Deploy na s2.onhost.cz

**Připraveno:** Phase 25  
**Server:** s2.onhost.cz  
**Stav .env.production:** ✅ Kompletní (billing adresa, SMTP, admin heslo)  
**Repo branch:** development (commit f26b155)  

---

## ⚠️ Bezpečnostní upozornění

1. **APP_KEY** v `.env.production` NIKDY neměnit po prvním spuštění
2. **SMTP heslo** bylo sdíleno přes chat → rotovat po prvním deployi (viz Krok 11)
3. **Admin heslo** bylo sdíleno přes chat → OKAMŽITĚ změnit po prvním přihlášení
4. `.env` NIKDY necommitovat do gitu

---

## Předpoklady

- ISPConfig weby `onhost.cz` a `admin.onhost.cz` vytvořeny (viz `DEPLOY-NOW.md` Krok 1)
- Custom Document Root: `/var/www/onhost/public`
- PHP-FPM 8.2 nastaven
- Let's Encrypt SSL aktivní
- SSH přístup na server jako root nebo www-data

---

## KROK 1 — Zkopírujte .env na server

Na lokálním počítači spusťte:

```bash
scp .env.production root@s2.onhost.cz:/tmp/onhost-env
```

Na serveru:
```bash
# Vytvořte cílovou složku, pokud neexistuje
mkdir -p /var/www/onhost

# Přesuňte .env
mv /tmp/onhost-env /var/www/onhost/.env

# Zkontrolujte
grep APP_ENV /var/www/onhost/.env    # → production
grep APP_DEBUG /var/www/onhost/.env  # → false
grep APP_URL /var/www/onhost/.env    # → https://onhost.cz
```

---

## KROK 2 — Klonujte repozitář

```bash
# Pokud repo ještě neexistuje:
cd /var/www
git clone <VÁŠ-REPO-URL> onhost

# Nebo pokud složka existuje ale prázdná:
cd /var/www/onhost
git init
git remote add origin <VÁŠ-REPO-URL>
git fetch
git checkout development

# Ověřte commit:
git log --oneline -3
# Měl by být: f26b155 docs: phase-24-production-env-completion-docs
```

> Pokud nemáte git repo URL, pokračujte manuálním nahráním souborů přes SFTP.

---

## KROK 3 — Spusťte deploy script

```bash
cd /var/www/onhost
bash deploy/deploy.sh --fresh
```

Skript provede automaticky:
- `composer install --no-dev --optimize-autoloader`
- `php artisan key:generate` (POUZE pokud APP_KEY je prázdný — náš není)
- `php artisan migrate:fresh --seed` (--fresh pro PRVNÍ deploy)
- `php artisan storage:link`
- Nastavení oprávnění (www-data)
- Config/route/view/event cache
- Production doctor check

### Pokud deploy.sh selže — manuální fallback:

```bash
cd /var/www/onhost

# 1. Composer
composer install --no-dev --optimize-autoloader

# 2. Config (APP_KEY je již v .env — nepřepisovat!)
php artisan config:cache

# 3. Databáze (PRVNÍ deploy = fresh)
php artisan migrate:fresh --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=ProductCatalogSeeder --force
php artisan db:seed --class=MockServerSeeder --force
php artisan db:seed --class=IntegrationSeeder --force
php artisan db:seed --class=AiPromptTemplateSeeder --force
php artisan db:seed --class=SiteContentSeeder --force
php artisan db:seed --class=BlogKbContentSeeder --force

# 4. Storage a cache
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Oprávnění
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## KROK 4 — Vytvořte admin uživatele

Admin seed proběhl automaticky přes `AdminUserSeeder`. Ověřte:

```bash
php artisan tinker
// V tinker konzoli:
App\Models\User::where('email', 'admin@onhost.cz')->exists();  // → true
exit;
```

Pokud admin neexistuje, vytvořte ručně:
```bash
php artisan tinker
$u = App\Models\User::create(['name'=>'Admin OnHost','email'=>'admin@onhost.cz','password'=>bcrypt('SILNE-HESLO'),'is_active'=>true,'locale'=>'cs']);
$u->assignRole('admin');
$u->givePermissionTo('access-admin');
$u->givePermissionTo('access-partner');
App\Domains\Customer\Models\Customer::create(['user_id'=>$u->id,'type'=>'company','email'=>$u->email,'company_name'=>'Adrian Staněk','registration_number'=>'08094616','preferred_currency'=>'CZK','preferred_locale'=>'cs','country_code'=>'CZ']);
exit;
```

---

## KROK 5 — Production Doctor

```bash
php artisan onhost:doctor --production
```

**Akceptovatelný výsledek pro staging:**
```
✅ APP_KEY, APP_DEBUG=false, APP_URL, PHP >= 8.2
✅ DB, migrace, storage
✅ SESSION_DOMAIN, SESSION_SECURE_COOKIE
✅ MAIL smtp, FROM=info@onhost.cz
✅ BILLING (Adrian Staněk, Molákova 2145/5, Brno, 08094616)
✅ Scheduler 4 jobs

✗ COMGATE merchant_id     → INTENTIONAL (public launch blocker)
✗ COMGATE secret           → INTENTIONAL
✗ PROVISIONING_MOCK_MODE   → INTENTIONAL (staged cutover)
```

Pokud vidíte jiné critical errors → opravte před pokračováním.

---

## KROK 6 — Supervisor (Queue Worker)

```bash
cp /var/www/onhost/deploy/supervisor-queue.conf /etc/supervisor/conf.d/onhost-queue.conf

# Ověřte cestu k PHP:
which php8.2 || which php
# Upravte command v konfig souboru, pokud php8.2 není na /usr/bin/php8.2

supervisorctl reread
supervisorctl update
supervisorctl start "onhost-queue:*"
supervisorctl status
# → onhost-queue:onhost-queue_00   RUNNING
# → onhost-queue:onhost-queue_01   RUNNING
```

---

## KROK 7 — Cron (Scheduler)

```bash
cp /var/www/onhost/deploy/cron.txt /etc/cron.d/onhost
chmod 644 /etc/cron.d/onhost

# Ověřte PHP binary v /etc/cron.d/onhost:
cat /etc/cron.d/onhost
# Mělo by být: php8.2 artisan schedule:run
# Pokud php8.2 neexistuje, upravte na správnou cestu

# Ověřte scheduler:
php artisan schedule:list
# → 4 joby: renewal, overdue, suspend, partner-approve
```

---

## KROK 8 — Staging Smoke Test

```bash
# Health endpoint:
curl -I https://onhost.cz/up
# → HTTP 200

# Public pages:
for url in / /webhosting /vps /mailhosting /managed-hosting /domeny /gamehosting /login /blog /znalostni-baze; do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://onhost.cz$url" --max-time 10 -L)
    echo "$CODE $url"
done

# Přihlaste se do adminu v prohlížeči:
# https://onhost.cz/admin
# Login: admin@onhost.cz / <váš admin password>
```

---

## KROK 9 — SMTP Live Test

```bash
php artisan tinker
// V tinker konzoli:
\Illuminate\Support\Facades\Mail::raw('Onhost SMTP test ' . now(), function($m) {
    $m->to('info@onhost.cz')->subject('Onhost SMTP staging test');
});
exit;
```

Zkontrolujte:
1. Žádná SMTP exception v tinker
2. Zkontrolujte `storage/logs/laravel.log` pro chyby
3. E-mail dorazil na `info@onhost.cz`

---

## KROK 10 — E2E Mock Billing Test

```bash
php artisan tinker
// V tinker konzoli — celý flow:
$user = App\Models\User::where('email', 'admin@onhost.cz')->first();
$customer = $user->customer;

// Přidej billing adresu pokud chybí
if (!$customer->billingAddress()) {
    App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $customer->id, 'type' => 'billing',
        'street' => 'Molákova 2145/5', 'city' => 'Brno',
        'zip' => '62800', 'country_code' => 'CZ', 'is_primary' => true,
    ]);
    $customer->update(['type' => 'company', 'company_name' => 'Adrian Staněk']);
}

$plan = App\Domains\Products\Models\PricingPlan::whereHas('product', fn($q) => $q->where('slug','webhosting'))->where('is_active',true)->first();
$order = app(App\Domains\Billing\Actions\CreateOrderAction::class)->execute($customer, $plan, []);
$invoice = app(App\Domains\Billing\Actions\IssueProformaInvoiceAction::class)->execute($order);
$payment = app(App\Domains\Billing\Actions\ProcessMockPaymentAction::class)->execute($invoice);
$invoice->refresh();
echo "Payment: " . $payment->status->value . "\n";
echo "Invoice: " . $invoice->status->value . " (" . $invoice->number . ")\n";

// Tax document
try {
    $taxDoc = app(App\Domains\Billing\Actions\IssueTaxDocumentAction::class)->execute($invoice);
    echo "Tax doc: " . $taxDoc->number . "\n";
} catch (Exception $e) {
    echo "Tax doc error: " . $e->getMessage() . "\n";
}
exit;
```

Očekávaný výstup:
```
Payment: completed
Invoice: paid (CZ-2026-000001)
Tax doc: CZ-2026-000002
```

---

## KROK 11 — Post-Deploy Security

**Okamžitě po prvním přihlášení:**

```bash
# 1. Změňte admin heslo v prohlížeči:
# https://onhost.cz/admin → Resetovat heslo → nebo Zapomenuté heslo na login stránce

# 2. Rotujte SMTP heslo (sdíleno přes chat):
# ISPConfig → Email → Mailboxes → info@onhost.cz → Change password
# Poté aktualizujte MAIL_PASSWORD v /var/www/onhost/.env

# 3. Po změně hesla obnovte config:
php artisan config:cache

# 4. Smažte ADMIN_PASSWORD ze .env (bezpečnostní hygiene):
# nano /var/www/onhost/.env
# Smažte řádek ADMIN_PASSWORD=...
php artisan config:cache
```

---

## Kontrolní checklist

- [ ] .env je na serveru, NOT ve veřejném webu
- [ ] `grep APP_DEBUG /var/www/onhost/.env` → false
- [ ] `grep APP_ENV /var/www/onhost/.env` → production
- [ ] `php artisan migrate:status` → 0 Pending
- [ ] `php artisan onhost:doctor --production` → pouze Comgate a mock jako critical
- [ ] `supervisorctl status` → queue RUNNING
- [ ] `crontab -l` nebo `/etc/cron.d/onhost` → schedule:run existuje
- [ ] `curl -I https://onhost.cz/up` → 200
- [ ] Admin přihlášení funguje
- [ ] Admin heslo změněno
- [ ] SMTP test proběhl bez chyby
- [ ] SMTP heslo rotováno
- [ ] E2E mock billing test proběhl

---

## ⚠️ NO-GO pro veřejný launch

Dokud není splněno:
1. **Comgate credentials** (portal.comgate.cz)
2. **Právní kontrola** (TOS/GDPR/SLA/Cookies)
3. Comgate sandbox test (viz `COMGATE-CUTOVER.md`)
4. `COMGATE_TEST_MODE=false` po sandbox testu
