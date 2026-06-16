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

## Nice to have before launch

- [ ] Invoice PDF generation job (Chromium on host)
- [ ] Uptime Kuma live monitoring + incident webhooks
- [ ] S3 backups with retention lifecycle
- [ ] Dunning lifecycle automation (config exists in billing.lifecycle)
