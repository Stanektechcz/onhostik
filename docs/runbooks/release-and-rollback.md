# Release and rollback

## Before a release

* `php artisan test` green; contract tests for every adapter you touched.
* Error-budget policy for `portal`/`payments` is not `freeze` (`GET /v1/staff/reports/slo`). A freeze blocks
  releases except fixes for the incident that caused it.
* Migrations are additive (new tables/columns, no drops of data in the same release); destructive changes ship
  one release after the code stopped using the column.

## Deploy

1. `php artisan down --render=maintenance` is **not** used; the app is deployed behind a load balancer with
   rolling replacement.
2. `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache`,
   `php artisan route:cache`, `php artisan event:cache`.
3. Restart queue workers (`queue:restart`) and the scheduler; verify `/up`, `GET /v1/status` and one
   authenticated call.
4. Watch `sla.burn_rate` for 30 minutes after the release.

## Rollback

* Code: redeploy the previous image/tag; migrations are backward compatible for one release, so no down
  migration is run in production.
* Data: never roll back money or audit rows; correct with new documents/postings.
* If a release broke provisioning: freeze (`POST /v1/staff/provisioning/freeze`), roll back, thaw, retry failed
  operations.

## Configuration changes

Catalog, tax rules, SLA credit policies and notification templates are versioned rows, seeded from
`database/seeders` and changed by staff commands (`catalog.manage`, `billing.tax_rule.manage`, …), not by
editing production config. Feature flags live in `config/onhost.php` (`feature_flag.manage`).
