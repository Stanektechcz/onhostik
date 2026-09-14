---
name: frontend-page
description: Use when creating or changing an ONHOST browser page, component, Blade view, surface integration, responsive layout, or user journey.
---

# Frontend page

1. Read `docs/design/DESIGN_SYSTEM.md`, `docs/ui/template-inventory.md`, and the matching surface/API seam.
2. Preserve immutable surface files. Define data and behavior through `SurfaceRenderer` or `apps/surfaces/api/*`.
3. Reuse canonical tokens and components; cover loading, empty, error, success, and confirmation states.
4. Provide Czech copy with English fallback and accessible names, focus, keyboard behavior, and announcements.
5. Add or update a focused Playwright journey at desktop and narrow width.
6. Run `brain.ps1 test -E2E` and request frontend review.
