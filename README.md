# OnHost Platform

Laravel-based hosting business operating system for **Onhost.cz** — webhosting,
domains, billing (CZ/EU VAT), credits, provisioning (aaPanel), domain registry
operations (WEDOS WAPI), with future gamehosting (Pterodactyl) and VPS (Proxmox)
support.

## Stack

- PHP 8.2+ (production target 8.4), Laravel 12
- MySQL/MariaDB (required in dev — the credit ledger uses MySQL triggers)
- Fortify (auth) + spatie/laravel-permission (roles), Livewire 3
- brick/money — all amounts stored as **integer minor units**
- Templates: **Antler** (public web, `public/front`) and **Cuba** (panel/admin, `public/panel`)

## Local setup (Windows)

```bash
composer install
copy .env.example .env
php artisan key:generate
# create the database (XAMPP MySQL): CREATE DATABASE onhost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate
php artisan db:seed
php artisan serve
```

Queue worker (Horizon does **not** run on native Windows — pcntl/posix missing):

```bash
php artisan queue:work
```

Default local admin (from `.env`): `admin@onhost.local` / `password`.

See [docs/setup.md](docs/setup.md) for details and Windows caveats, and
[docs/architecture.md](docs/architecture.md) for the system design.

## Non-negotiable development rules

- **All external integrations are mock/test mode in development.** No real
  calls to WEDOS, aaPanel, Comgate, AI providers, or monitoring/backup
  services without explicit approval (`PROVISIONING_MOCK_MODE=true`).
- No business logic in controllers; external calls only from queued jobs
  behind driver interfaces.
- Money is always `Brick\Money\Money` over integer minor units — never floats.
- The credit ledger is append-only; balances change only through
  `CreditLedger` (never direct writes).
- Every payment path must be idempotent.
- No secrets in Git. `.env` is ignored; only `.env.example` is committed.
