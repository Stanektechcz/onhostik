# Development guide

## Golden rules

1. **Mock-first.** `PROVISIONING_MOCK_MODE=true` locally, always. No real
   WEDOS / aaPanel / Comgate / AI / monitoring calls — the `DriverResolver`
   throws if mock mode is off, on purpose.
2. Controllers stay thin: validation + authorization + action call + redirect.
   Business logic lives in domain **actions/services/jobs**.
3. External operations run **only** in queued jobs, are idempotent, write a
   `ProvisioningTask`, and are retryable from `/admin/provisioning`.
4. Money = `Brick\Money\Money` (integer minor units). Ledger is append-only.
5. Every state transition is audited (Spatie activity log + model logs).

## Exercising the Phase 2 vertical slice locally

```bash
php artisan migrate            # safe, additive
php artisan db:seed            # roles, admin, catalog, MOCK-AAP-01 server
php artisan serve
php artisan queue:work         # second terminal — processes provisioning jobs
```

1. Register at `/register` (creates user + customer profile).
2. `/webhosting` → pick a plan → checkout `/panel/objednavky/nova`
   (optionally add a domain, e.g. `muj-web.cz`; `taken-*.cz` is always taken).
3. Submit — order + proforma invoice appear; open the invoice.
4. Pay with **mock gateway** (or credit, if an admin deposited some via
   `CreditLedger::deposit()` in tinker).
5. Watch `queue:work` provision the service (Active, `MOCK-AAP-…`) and
   register the domain (`MOCK-WD-…`); check `/panel/sluzby`, `/panel/domeny`.
6. Failure drill: tick *DEV: simulate provisioning failure* at checkout,
   pay, see the failed task in `/admin/provisioning`, hit **Retry** — it
   succeeds (the simulated failure is one-shot by design).

Admin login: `admin@onhost.local` / `password` (local seed).

## Tests

```bash
php artisan test                      # full Pest suite (SQLite :memory:, sync queue)
vendor/bin/pint --test                # code style
vendor/bin/phpstan analyse --memory-limit=1G
```

- Tests run on SQLite with a **sync** queue: queued jobs execute inline, so
  feature tests cover the whole slice end to end without a worker.
- The credit ledger's MySQL triggers do NOT exist on SQLite — model-level
  guards cover unit/feature tests; trigger enforcement is verified manually
  against local MariaDB.
- PHPStan level 9: new code must be clean. The pre-existing `app/Domains`
  errors are tracked (baseline pending approval) — do not add new ones.

## Conventions

- Routes: Czech slugs, `front.*` / `panel.*` / `admin.*` names.
- Views: Antler components (`x-front.*`) for the public site, Cuba
  components (`x-panel.*`) for panel/admin. Templates in `homepage-sablona/`
  and `laravel-admin-panel/` are **read-only**.
- Localization: every customer-facing string exists in `lang/cs` + `lang/en`.
- UUIDv7 public identifiers (`HasUuid`), integer PKs internally.
