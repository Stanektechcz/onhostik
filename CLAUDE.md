# ONhost control plane — guidance for Claude Code

Follow `AGENTS.md` (working rules) and `README.md` (layout, commands). Summary of what matters most when
changing this code base:

* PHP 8.3 is at `C:/Users/medion/php83/php.exe` on the development machine (`php` is not on PATH); run tests
  with `php artisan test --compact`. Python is not available in the shell; scripted edits use PHP.
* Every write goes through the `CommandBus` (command + handler + risk level); controllers only validate,
  dispatch and present. Risk HIGH means the user needs a fresh step-up, CRITICAL adds four-eyes approval.
* Provider work lives in `providers/*` behind contracts; never call vendor APIs from domains or controllers, never
  log vendor payloads, always write a contract test with `Http::fake()`.
* Events: `GenericEvent::of(...)` through the outbox; document them in `docs/architecture/events-catalog.md`
  and route them in `NotificationRouter`. Tests call `app(OutboxPublisher::class)->relayPending()`.
* The prototype surfaces in `apps/surfaces` must stay byte-identical; integrate through `SurfaceRenderer`
  seams and `apps/surfaces/api/*` only (docs/ui/template-inventory.md §6).
* Tests: Pest, one global function namespace across files (unique helper names), seed with `BaseSeeder`
  automatically; extra seeders (`CatalogSeeder`, `TaxRuleSeeder`, `LegalEntitySeeder`, `ContentSeeder`) are
  called explicitly where needed. Keep the suite green before finishing.
* After route changes run `php artisan onhost:openapi`; after dependency changes `php artisan onhost:oss-inventory`.
