# Archived prototypes

Prototype files that nothing loads any more. They are kept here **byte for byte** as they were (moved with `git mv`, never
edited) so the design history stays readable; they are **not served** (`SurfaceRenderer::assetPath` refuses `_archive/`) and
no surface, shell or test loads them.

| File | Archived | Why | SHA-256 |
| --- | --- | --- | --- |
| `onhost-domains.js` | 2026-10-06, H0 (owner decision H-R6) | No prototype page loads it by a tag and the shell does not lazy-load it; the product uses `api/onhost-domains.api.js` (the same `window.OnhostDomains` interface backed by the API), which `SurfaceRenderer` preloads. The only mention left is the seam that would swap a `<script src="/surfaces/onhost-domains.js">` tag for the API variant if a prototype ever added one. | `cd38a8691554899adb5fc38e928ef9483f3402ff582f0a5792e26f829c901d6c` |

To bring a file back, `git mv` it to `apps/surfaces/` again and record why in `docs/audit/2026-10-full-readiness/ROZHODNUTI.md`.
