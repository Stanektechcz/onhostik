# ONHOST project context

ONHOST is a Laravel 13 control plane for hosting, domains, billing, provisioning, support, and operations. It is a modular monolith with domain code in `domains`, provider adapters in `providers`, orchestration in `platform`, HTTP entry points in `app/Http`, and immutable generated prototypes in `apps/surfaces`.

## Read in this order

1. `AGENTS.md` for invariants and safety rules.
2. `docs/context/CURRENT_STATE.md` for active priorities and known risk.
3. `docs/context/DOMAIN_MAP.md` for the relevant module and documentation.
4. The nearest test, ADR, runbook, and source files for the task.
5. `docs/generated/*` for recent Git, test, and security snapshots.

Do not bulk-load `apps/surfaces`, `storage`, logs, generated assets, or historical documents. Use Git or Serena to answer a specific question, then discard irrelevant context.

## Runtime map

- PHP 8.3, Laravel 13, SQLite for tests.
- Composer scripts: `test`, `test:unit`, `test:feature`, `test:contract`, `analyse`, `lint`.
- Node/Vite/Tailwind; Playwright for browser journeys.
- GitHub Actions for test, end-to-end, and edge-role validation.
- Local operator entry point: `brain.ps1`.

The architectural source of truth is `docs/adr`; operational truth is `docs/runbooks`; API truth is `docs/api` plus generated OpenAPI output.
