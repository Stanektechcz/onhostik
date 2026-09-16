# ONHOST design system

The canonical implementation is the preserved Modernist design system in `apps/surfaces/_ds/modernist-31154b91-2bfe-4cc9-a4d9-6ffc68c8a498/styles.css`. Read `docs/ui/template-inventory.md` before UI work. Files declared immutable in `AGENTS.md` must remain byte-identical.

## Foundations

- Accent: `#ec3013`; secondary accent: `#e15b47`.
- Text: `#201e1d`; warm off-white backgrounds around `#f3f2f2`.
- Heading and body font: Archivo with system fallbacks; headings use weight 800.
- Use existing spacing, radius, shadow, color, and component variables from the canonical stylesheet. Do not create a parallel token set in Blade or Tailwind.

## Interaction rules

- Use semantic HTML, keyboard operation, visible `:focus-visible`, and meaningful accessible names.
- Keep customer copy in Czech with an English fallback.
- Preserve loading, empty, error, success, and destructive-confirmation states.
- Use the established component classes and `apps/surfaces/api/*` seams. New Blade-only screens should reference the same CSS custom properties.
- Test key customer and staff journeys in Playwright at desktop and narrow viewports.

Before review, compare the implementation with its existing surface, check contrast and focus, verify long Czech strings, and run `brain.ps1 test -E2E` when the change affects a browser journey.
