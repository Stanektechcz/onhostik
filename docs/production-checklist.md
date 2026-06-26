# Production checklist (gate review before go-live)

## Blockers — must be done

- [ ] PHP 8.4 on the server (composer.json currently allows ^8.2 for the
      Windows dev box; restore the ^8.4 target in production)
- [ ] `APP_DEBUG=false`, production `APP_KEY`, HTTPS everywhere
- [ ] MySQL/MariaDB with the credit-ledger triggers verified
      (`SHOW TRIGGERS LIKE 'credit_transactions'`)
- [ ] Queue worker(s) under systemd/Horizon + `schedule:run` cron
- [ ] Accountant sign-off: invoice numbering, proforma→tax-document flow,
      DUZP, top-up VAT regime (docs/invoices.md, docs/credits-wallet.md)
- [ ] PHPStan legacy debt decision (baseline approval or annotation pass)
- [ ] Real SMTP + sender domain (SPF/DKIM)
- [ ] Error tracking (Sentry slot) + log retention
- [ ] DB backup strategy for the platform itself

## Per-provider activation (each one separately, in this order)

- [ ] vault credentials entered via admin (never in .env/seeders)
- [ ] `is_active=true`, then `mock_mode=false`, then `dry_run=false`
- [ ] env gate opened on the server only
      (AAPANEL_ALLOW_REAL_WRITES / WAPI_ALLOW_REAL_WRITES / AI_ALLOW_REAL_CALLS)
- [ ] connection test green, supervised smoke operation, audit reviewed

## Security hardening

- [ ] Enable Fortify 2FA + enforce for admin/support roles
- [ ] Rate limiting on panel/admin POST endpoints
- [ ] CSP + security headers, cookie settings review
- [ ] Comgate webhook IP whitelist confirmed against current Comgate docs

## Partner / affiliate program

- [ ] Nastavit `PARTNER_*` proměnné v `.env` (viz `.env.production.example`)
- [ ] Vytvořit první partner profil: `/admin/partneri/novy`
- [ ] Otestovat referral link: navštívit `/?ref=KOD` — ověřit cookie `onhost_ref` v DevTools
- [ ] Otestovat registraci přes referral link — ověřit záznam v `partner_referrals`
- [ ] Vytvořit testovací objednávku přes referral zákazníka a zaplatit ji — ověřit vznik `partner_commissions` (status: pending)
- [ ] Ověřit auto-approve command: `php artisan partner:approve-eligible-commissions --dry-run`
- [ ] Ověřit scheduler: `php artisan schedule:list` — partner:approve-eligible-commissions 02:00
- [ ] Otestovat manuální approve commission v admin detailu partnera
- [ ] Otestovat vytvoření payout se zahrnutými commission checkboxy
- [ ] Otestovat mark payout paid — ověřit, že pouze navázané commissions se označí jako paid
- [ ] Ověřit, že partner notifikace dorazí (MAIL_MAILER=smtp, email v User.email)
- [ ] Nastavit `PARTNER_SELF_REFERRAL_BLOCKED=true` v produkci

## Nice to have before launch

- [ ] Invoice PDF generation job (Chromium on host)
- [ ] Uptime Kuma live monitoring + incident webhooks
- [ ] S3 backups with retention lifecycle
- [ ] Dunning lifecycle automation (config exists in billing.lifecycle)
