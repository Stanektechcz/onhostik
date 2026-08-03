# OnHost.cz — Deploy Instrukce

## Rychlý start

```bash
# 1. Naklonovat script do webu (už by měl být)
cd /var/www/clients/client10/web19/web
ls -la deploy.sh

# 2. Udělat executable
chmod +x deploy.sh

# 3. Spustit deploy
./deploy.sh --branch development

# 4. Nebo s potvrzením
./deploy.sh --force --branch development

# 5. Logování
tail -f storage/logs/deploy-*.log
```

---

## Příkazy

### Standardní deploy (s potvrzením)

```bash
cd /var/www/clients/client10/web19/web
./deploy.sh --branch development
```

**Co se stane:**
- Pull z `origin/development`
- Composer install (bez dev packages)
- Databázové migrace
- Cache clear (view, config, route, app)
- Asset build (pokud existuje npm)
- Scheduler check (48 úloh musí být)
- Restart Supervisoru, PHP-FPM, nginx
- Ověření (PHP syntax, Laravel health, queue status)

### Deploy bez migrací (rychlý hotfix)

```bash
./deploy.sh --branch development --no-migrate
```

### Force deploy (bez potvrzování, pro CI/CD)

```bash
./deploy.sh --force --branch development
```

### Deploy z main (produkce)

```bash
./deploy.sh --branch main --force
```

---

## Nastavení Supervisoru (queue workers)

Pokud ještě nemáte, vytvořte `/etc/supervisor/conf.d/onhost-queue.conf`:

```ini
[program:onhost-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/clients/client10/web19/web/artisan queue:work --queue=provisioning,default,backup,billing --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/clients/client10/web19/web/storage/logs/queue.log
stopasgroup=true
startsecs=10
```

Pak:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart onhost-queue:*
```

---

## Nastavení Cronu pro Scheduler

Pokud chcete, aby se plánované úlohy spouštěly, přidejte do `crontab`:

```bash
sudo crontab -e
```

A přidejte:

```cron
* * * * * cd /var/www/clients/client10/web19/web && php artisan schedule:run >> /dev/null 2>&1
```

Nebo lépe, pokud máte Laravel 11+, spusťte na pozadí:

```bash
cd /var/www/clients/client10/web19/web
php artisan schedule:work &
```

---

## Monitorování po deployi

### Obecně

```bash
cd /var/www/clients/client10/web19/web

# Poslední deploy log
tail -50 storage/logs/deploy-*.log

# Laravel error log (posledních 50 řádků)
tail -50 storage/logs/laravel.log | grep -E "ERROR|CRITICAL"

# Queue status
php artisan queue:monitor --max=1000

# Scheduled tasks status
php artisan schedule:list
```

### Konkrétní kritické příkazy

```bash
# Ověřit, že se v posledních 5 minutách něco stalo v queue
php artisan queue:work --once --tries=1 2>&1 | head -20

# Test databáze
php artisan tinker --execute="echo DB::connection()->getDatabaseName();"

# Ověřit, že marketplace instalace pracuje
php artisan tinker --execute="echo \App\Domains\Marketplace\Models\AppInstallation::count() . ' installations';"

# Ověřit, že webhooky jsou aktivní
php artisan tinker --execute="echo \App\Models\WebhookSubscription::where('active',true)->count() . ' active hooks';"
```

---

## Troubleshooting

### Deploy selhal s rollback

Script automaticky vrátí kód na předchozí verzi. Pokud chcete znát, co se stalo:

```bash
# Vidět log
tail -100 storage/logs/deploy-2026MMDD-HHMMSS.log

# Vrátit se manuálně
cd /var/www/clients/client10/web19/web
git log --oneline -10
git reset --hard <commit-hash>
php artisan migrate --force
php artisan cache:clear
```

### Queue workers nejsou živí

```bash
# Zkontrolovat status
sudo supervisorctl status onhost-queue:*

# Restart
sudo supervisorctl restart onhost-queue:*

# Logovat
tail -50 /var/www/clients/client10/web19/web/storage/logs/queue.log
```

### Cron/Scheduler neběží

```bash
# Ověřit crontab
sudo crontab -l | grep artisan

# Zkusit jednu úlohu ručně
php artisan billing:expire-credit

# Spustit schedule:work na pozadí
nohup php artisan schedule:work > /dev/null 2>&1 &

# Nebo v Supervisoru:
echo "[program:onhost-schedule]
command=php /var/www/clients/client10/web19/web/artisan schedule:work
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/www/clients/client10/web19/web/storage/logs/schedule.log" | sudo tee /etc/supervisor/conf.d/onhost-schedule.conf
sudo supervisorctl reread && sudo supervisorctl update
```

### Aplikace vrací 500

```bash
# Vidět error log
tail -100 storage/logs/laravel.log

# Cache clear
php artisan cache:clear && php artisan view:clear && php artisan route:clear

# Ověřit, že je app key nastavený
grep APP_KEY .env

# Ověřit práva ke storage
sudo chown -R www-data:www-data storage/ bootstrap/cache/

# Znovu
sudo systemctl reload nginx
```

---

## Nastavení Aliasu pro snadnější spouštění

Přidejte do `/root/.bashrc` nebo `/home/user/.bashrc`:

```bash
alias onhost-deploy='cd /var/www/clients/client10/web19/web && ./deploy.sh'
alias onhost-tail='tail -50 /var/www/clients/client10/web19/web/storage/logs/laravel.log'
alias onhost-queue='sudo supervisorctl status onhost-queue:*'
alias onhost-log='cd /var/www/clients/client10/web19/web && php artisan schedule:list'
```

Pak:

```bash
onhost-deploy --force --branch development
onhost-tail
onhost-queue
onhost-log
```

---

## Plánované úlohy — co se měnilo v tomto kole

| Úloha | Čas | Změna |
|---|---|---|
| `billing:process-auto-topups` | 01:10 | **NOVÉ** — nyní skutečně běží |
| `announcements:publish-scheduled` | každých 5 minut | **NOVÉ** — naplánované oznámení se zveřejňují |
| `drip:process` | každých 15 minut | **NOVÉ** — drip e-maily se posílají |
| `monitoring:evaluate-alerts` | každých 5 minut | **NOVÉ** — prahy se vyhodnocují |
| `bi:compute-insights` | 02:30 | **NOVÉ** — churn skóre se počítá |
| `billing:escalate-reminders` | 08:30 | **NOVÉ** — eskalace upomínek D+7/14/30 |
| Zbylé 42 úloh | — | beze změny |

Po deployi ověřte:

```bash
php artisan schedule:list | grep -E "billing:process-auto-topups|announcements:publish|drip:process|monitoring:evaluate"
```

Mělo by být **4 nové úlohy plus zbylých 44** = **48 celkem**.

---

## Rollback v nouzi

Pokud cokoliv jde divně:

```bash
cd /var/www/clients/client10/web19/web

# Vidět poslední commity
git log --oneline -10

# Vrátit se na konkrétní commit
git reset --hard <commit-hash>

# Obnovit dependence
/usr/local/bin/composer install --no-dev

# Obnovit cache
php artisan cache:clear

# Obnovit fronta
sudo supervisorctl restart all

# Ověřit
php artisan schedule:list | grep -c "Next Due"
```

---

## Support

- **Laravel docs:** https://laravel.com/docs/11
- **Scheduler:** https://laravel.com/docs/11/scheduling
- **Queue:** https://laravel.com/docs/11/queues
- **OnHost audit:** `docs/SYSTEM-AUDIT-2026-08-03.md`
- **Commit history:** `git log --oneline -20`
