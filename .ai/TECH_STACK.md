# Tech stack (verified 2026-09-23, counts re-measured 2026-09-25 on the stack tip `edb635b`)

Facts below were read from the repository or measured by commands; see `.ai/baseline/baseline.json` for the numbers.

## Runtime

| Area | Fact | Source |
| --- | --- | --- |
| Framework | Laravel 13.30.1 (`laravel/framework ^13.17`), modular monolith | `composer.json`, `php artisan about` |
| PHP | `^8.3`; CI uses 8.3; local 8.3.33 at `C:/Users/medion/php83/php.exe` (**not on PATH**) | `composer.json`, `.github/workflows/tests.yml` |
| Autoload | `App\`→`app/`, `Onhost\Domain\`→`domains/` (23 modules), `Onhost\Providers\`→`providers/`, `Onhost\Platform\`→`platform/` | `composer.json` |
| Key packages | Sanctum 4 (auth), dompdf (invoices), firebase/php-jwt, phpseclib 3 (SSH), symfony/yaml (OpenAPI) | `composer.json` |
| Database | Production PostgreSQL 16; local dev SQLite `database/database.sqlite`; tests SQLite `:memory:` | `.env.example`, `phpunit.xml`, CI `pest-postgres` |
| Migrations | 61 (last `0001_01_01_000870_create_withdrawals_table`), one sequence `0001_01_01_000NNN_*` in steps of 10 (parallel tasks collide on numbers) | `database/migrations` |
| Cache / session / queue | Production Redis (phpredis) for all three; local dev file cache/session + database queue; tests array/sync | `.env.example`, `phpunit.xml` |
| Queues | `default`, `mails`, `provider-{proxmox,ispconfig,aapanel,pterodactyl,powerdns,registrar,kubernetes}` | `infra/systemd/onhost-queue@.service`, `routes/console.php` |
| Scheduler | 88 `Schedule::` entries in `routes/console.php`; `infra/systemd/onhost-scheduler.service` | `routes/console.php` |
| Frontend | Prototype surfaces `apps/surfaces/*.dc.html` (kept byte-identical) + integration modules `apps/surfaces/api/*.js` + `app/Http/Support/SurfaceRenderer.php` seams; Blade in `resources/views` (admin, auth, legal, mail, invoices); Vite 8 + Tailwind 4. **No React, Vue or Livewire.** | `package.json`, `docs/ui/template-inventory.md` |
| Payments | `providers/Payments/{Comgate,GoPay,Stripe,Bank}` behind `providers/Contracts/PaymentProvider.php` | `providers/` |
| External systems | Proxmox, PBS, ISPConfig, aaPanel, Pterodactyl, PowerDNS, WEDOS, Subreg, Kubernetes, Cloudflare, Hetzner, Redfish, IpGeo, OnCall, AI, SSH shell | `providers/`, `docs/provider-adapters/` |

## Tooling

| Tool | Command (from the worktree root) | Notes |
| --- | --- | --- |
| Tests | `php artisan test --compact` | Pest 4; suites Unit, Feature, Contract (`phpunit.xml`); 337 files, 1 364 tests / 18 258 assertions at `edb635b` (856 at `8e4614a`) |
| Style | `vendor/bin/pint --test` | CI fails on style |
| Static analysis | `vendor/bin/phpstan analyse --memory-limit=2G` | Larastan level 5 + `phpstan-baseline.neon` (typing debt; never add entries) |
| Frontend build | `npm run build` | Vite |
| E2E | `npm run e2e` | Playwright, `e2e/tests/surfaces.spec.ts`; CI `e2e.yml` |
| Audits | `composer audit --locked`, `npm audit --audit-level=high` | CI `tests.yml`, `security.yml` |
| Secrets | `.githooks/pre-commit` (private paths + gitleaks if installed), `.gitleaks.toml`, CI `security.yml` | `git config core.hooksPath` = `.githooks` |
| Brain | `brain.ps1 status|update|doctor|test|security|audit|release|gate|task` | `scripts/ai/*.ps1` (PowerShell 5.1 compatible) |
| Readiness | `php artisan onhost:doctor [--json]` | never against production from a workstation |

Every PHP command needs the explicit binary on this machine: `C:/Users/medion/php83/php.exe`. Composer is
`C:/Users/medion/php83/composer.phar`. Node 24.16, npm 11.13. No Docker, PostgreSQL or Python locally: **CI job
`pest-postgres` is the only PostgreSQL run** — check it after every push (`gh run list --branch development --workflow tests --limit 3`).

## Git

- Integration (default) branch: **`development`** (`origin/HEAD`). There is no `main` branch; CI also triggers on `main`.
- Remote: `origin` = `github.com/Stanektechcz/onhostik`. Dependabot opens branches under `dependabot/`.
- Commit subjects: conventional (`feat(scope): …`, `fix(…)`, `docs: …`, `chore: …`) plus some plain-sentence subjects.
- Open pull requests on 2026-09-25: #20–#23 (TASK-0018, TASK-0003, TASK-0017, TASK-0019 pushed separately; the stack
  branch of TASK-0027 contains all of them) and Dependabot #3–#5. Merged task PRs: #6–#19 (TASK-0001, TASK-0005 … TASK-0016).
- Unmerged `origin/chore/onhost-brain` (7 ahead / 196 behind at 2026-09-23) holds the Brain's original `.claude`, `.gemini`, `.mcp.json`, `.serena` config.
