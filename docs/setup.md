# OnHost — Local Setup

## Requirements

- **PHP 8.2+** (project targets 8.4 in production; local XAMPP 8.2.4 works).
  Required extensions: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`,
  `fileinfo`, `curl`, `gd`, `zip`, `bcmath`.
  - **`intl` is strongly recommended** (exact locale money formatting).
    The current local XAMPP build cannot load it (ICU DLLs missing from
    `XAMPP\php`), so `App\Domains\Shared\Support\MoneyFormatter` falls back
    to a manual format. **Production MUST have ext-intl.**
- **Composer 2.x**
- **MySQL/MariaDB** — required for a real dev database. The credit ledger
  migration installs **MySQL-only triggers** that make the ledger
  append-only at the DB level. SQLite skips them silently.
- Node 18+ only for future asset pipeline work (not needed in Phase 1 —
  template assets are prebuilt and published into `public/`).

## Install

```bash
composer install
copy .env.example .env
php artisan key:generate
```

`laravel/horizon` is intentionally **not** a local dependency: it requires
`ext-pcntl`/`ext-posix`, which do not exist on native Windows. Horizon is a
production (Linux) component to be re-added at deployment time; locally use
`php artisan queue:work`.

## Database

Start MySQL (XAMPP), then:

```sql
CREATE DATABASE onhost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env` defaults match XAMPP (`root`, empty password, `127.0.0.1:3306`,
database `onhost`). Then:

```bash
php artisan migrate          # never use migrate:fresh on shared/production data
php artisan db:seed          # roles, local admin, sample hosting plans
```

Seeded admin: `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`
(defaults `admin@onhost.local` / `password` — local only).

## Run

```bash
php artisan serve            # http://localhost:8000
php artisan queue:work       # queue worker (instead of Horizon on Windows)
```

- Public site: `/` — customer panel: `/panel` — admin: `/admin`.

## Tests

```bash
php artisan test
```

Tests run on **SQLite in-memory**. Consequences:

- The credit-ledger **DB triggers are not exercised** (MySQL-only). The
  model-level guards in `CreditTransaction` are tested instead. Before
  production, run the ledger tests against MySQL
  (`DB_CONNECTION=mysql php artisan test --filter=CreditLedger`).

## Validation commands

```bash
composer validate
php artisan about
php artisan migrate:status
php artisan route:list
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Windows caveats summary

| Issue | Consequence | Resolution |
| --- | --- | --- |
| No `ext-pcntl`/`ext-posix` | Horizon cannot run | `php artisan queue:work`; Horizon on Linux prod |
| `ext-intl` not loadable in this XAMPP | `Money::formatTo()` unavailable | `MoneyFormatter` fallback; install intl-capable PHP for exact locale output |
| PHP 8.2.4 local vs 8.4 target | None today (constraint `^8.2`) | Use PHP 8.4 in production |
| `spatie/laravel-pdf` needs Chrome/Puppeteer | Invoice PDFs (Phase 6) won't render locally yet | Install Node + puppeteer when Phase 6 starts |

## External integrations — development policy

Everything is **mock/test-only** locally: `PROVISIONING_MOCK_MODE=true`,
`WAPI_TEST_MODE=true`, `COMGATE_TEST_MODE=true`, `AI_MOCK_MODE=true`.
Real credentials never go into `.env.example` or Git. Switching any
integration to real mode is a Phase 17+ decision requiring explicit approval.
