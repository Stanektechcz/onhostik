# ADR-0005 — Prototype surfaces served verbatim, integrated through data seams only

**Status:** accepted (2026-09) · **Blueprint:** §59, §80 · **Handoff:** docs/ui/template-inventory.md

## Context

The five product surfaces (`Onhost.dc.html`, `Onhost-app.dc.html`, `Onhost-admin.dc.html`,
`Onhost-partner.dc.html`, `Onhost-mobil.dc.html`) and the Modernist design system are the approved UI. The
blueprint forbids visual changes and demands that both UI templates stay usable.

## Decision

The files in `apps/surfaces` stay byte-identical to the prototype. `SurfaceRenderer` applies only the seams the
template inventory (§6) allows at serve time and caches the result:

1. relative assets → `/surfaces/…`;
2. `onhost-store.js` → `api/onhost-store.api.js`; `onhost-integrations.js` / `onhost-domains.js` → API-backed
   modules with identical interfaces;
3. `onhost-data.js` → generated from the catalog, status page and content (`/surfaces/onhost-data.js`);
4. `window.ONHOST = { apiBase, csrf, user, surface, demo, hash }` + `onhost-session-bridge.js` injected before
   `onhost-shell.js` (session from the server, role switcher locked unless `ONHOST_UI_DEMO=true`);
5. panel only: `SVC_DATA().services` and `state.servers` read `window.ONHOST_PANEL` (`/surfaces/onhost-panel.js`),
   `liveSimulation` defaults to `false`.

Server paths (`/panel/sluzby`, `/stav`, `/blog/{slug}`) pre-set the surface's hash route.

## Consequences

* Design updates are a file drop; the transform is covered by `tests/Feature/Http/SurfaceTest.php`.
* Known prototype runtime limits (React/Babel from unpkg, in-browser compilation) remain backlog items UI-01/02;
  the CSP for production must vendor those builds under `apps/surfaces/vendor/`.
