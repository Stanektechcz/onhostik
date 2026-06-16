# Deployment (target: Linux, not yet performed)

The app is developed on native Windows (XAMPP). Production assumptions:

## Stack

- Linux host, PHP 8.3+ (8.4 target), MySQL/MariaDB 10.6+, nginx + php-fpm
- Composer install with `--no-dev --optimize-autoloader`
- `php artisan config:cache route:cache view:cache event:cache`
- Queue worker: re-add **laravel/horizon** (removed locally — needs
  ext-pcntl) or systemd-managed `php artisan queue:work` on queues:
  `provisioning-high,provisioning,provisioning-low,default`
- Scheduler: cron `* * * * * php artisan schedule:run`
- PDF: Node + puppeteer/Chromium for spatie/laravel-pdf (invoice PDFs)

## Environment

- `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`
- DB credentials with least privilege; backups of the DB itself
- Mail: real SMTP creds in the vault + `MAIL_MAILER=smtp`
- Keep ALL real-write gates closed until each provider is intentionally
  activated (see docs/integrations.md and docs/production-checklist.md)

## Order of go-live (recommended)

1. Deploy with everything in mock mode — the portal works end to end.
2. Activate SMTP (transactional mail).
3. Activate Comgate test mode → real mode after a supervised test payment.
4. Activate aaPanel on a staging node (`AAPANEL_ALLOW_REAL_WRITES=true`
   only on the server), provision a throwaway site, verify, then go live.
5. Activate WEDOS WAPI test mode → real registrations last (irreversible
   and billed).
6. Monitoring/backup providers after hosting is live.
