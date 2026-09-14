# UI template inventory (Fable Discovery Gate, Phase 0)

Stav k 6. 9. 2026. Tento dokument je výstup povinného kroku z blueprintu §59.1: před první novou obrazovkou
musí být zmapovány obě schválené šablony. Nic v tomto repozitáři nesmí vzniknout jako „paralelní AI style“.

## 1. Kde šablony jsou

| Šablona (blueprint) | Soubor v repozitáři | Původní prototyp | Role v produkci |
| --- | --- | --- | --- |
| **FRONTEND HOMEPAGE TEMPLATE** | `apps/surfaces/Onhost.dc.html` | `../Onhost.dc.html` | veřejný web, katalog 19 produktových listů, ceny, domény, blog, KB, dokumentace, API reference, stav služeb, změnový log, košík, přihlášení/registrace |
| **ADMIN TEMPLATE — customer portal** | `apps/surfaces/Onhost-app.dc.html` | `../Onhost-app.dc.html` | klientská sekce (`/panel/*`) — 26 sekcí (`TAB_SLUG`) |
| **ADMIN TEMPLATE — staff** | `apps/surfaces/Onhost-admin.dc.html` | `../Onhost-admin.dc.html` | administrace (`/sprava/*`) — 18 sekcí (`VIEW_SLUG`), role l1/l2/lead/sales/infra/exec/editor |
| ADMIN TEMPLATE — partner | `apps/surfaces/Onhost-partner.dc.html` | `../Onhost-partner.dc.html` | partnerský portál (`/partner/*`) — 6 sekcí |
| ADMIN TEMPLATE — mobile | `apps/surfaces/Onhost-mobil.dc.html` | `../Onhost-mobil.dc.html` | mobilní aplikace (`/app/*`) — 5 obrazovek |
| Component explorer | `apps/surfaces/Onhost-widgets.dc.html` | `../Onhost-widgets.dc.html` | knihovna komponent a stavů (Storybook-like katalog požadovaný §59.4) |

Originály v kořeni workspace (`C:\Users\medion\Desktop\ONHOST-NEW\*.dc.html`) zůstávají **nedotčené** jako
visual-regression baseline. Kopie v `apps/surfaces/` jsou produkční: dostávají pouze datové seamy (viz §6),
nikdy změnu vzhledu.

Adresář je záměrně plochý (`apps/surfaces/`), protože plochy odkazují relativními cestami na `./support.js`,
`_ds/…`, `./onhost-shell.js` a ES moduly `./onhost-svc-*.js`. Rozdělení do `apps/public-web`, `apps/portal`,
`apps/staff` z blueprintu §87 je proto logické (routy a controllery v Laravelu), ne fyzické.

## 2. Frontend stack

| Vrstva | Zjištěno |
| --- | --- |
| Formát | Claude Design canvas (`.dc.html`): `<x-dc>` šablona s `{{ }}` bindingy, direktivy `sc-if` / `sc-for`, logika v `<script type="text/x-dc" data-dc-script>` jako `class Component extends DCLogic { renderVals() {…} }` |
| Runtime | `support.js` (dc-runtime, generováno z TypeScriptu) — načítá React 18.3.1 UMD + ReactDOM + Babel standalone 7.29 z unpkg a kompiluje logiku v prohlížeči |
| Router | hash routing (`ROUTES`, `TAB_SLUG`, `VIEW_SLUG`, `SCREEN_SLUG`) s `parseHash()` / `syncHash()` přes `history.replaceState`; v produkci mapováno 1:1 na serverové cesty (viz `docs/development/docs-audit-implementace.md` §2) |
| CSS strategie | jeden design-system stylesheet `_ds/modernist-…/styles.css` (tokeny + komponentní třídy) + inline styly generované v `renderVals()`; žádný Tailwind, žádný build step |
| Datové seamy | `window.ONHOST_DATA`, `window.ONHOST_PUBLIC`, `window.OnhostStore`, `window.OnhostSession`, `window.OnhostIntegrations`, `window.OnhostDomains`, ES moduly `onhost-svc-*.js`, `onhost-content.js`, `onhost-docs.js`, `onhost-live-adapter.js` |
| Persistence prototypu | IndexedDB `onhost/ops` + `localStorage` (`onhost.ops`, `onhost.session`, `onhost.cart`, `onhost.role`) — v produkci nahrazeno REST `/v1` |

## 3. Design systém „Modernist“ (`apps/surfaces/_ds/modernist-31154b91-…`)

Zdroj pravdy: `styles.css` (tokeny v `:root`), popis v `readme.md`, strojově `_ds_manifest.json`.
Extrakt tokenů pro backend (PDF faktur, e-maily) je v `packages/design-tokens/tokens.json`.

| Token | Hodnota | Použití |
| --- | --- | --- |
| `--color-bg` | `#f3f2f2` | základní plocha |
| `--color-surface` | `#eae9e9` | karty, panely, pole |
| `--color-text` | `#201e1d` | ink |
| `--color-accent` | `#ec3013` | jediný akcent — primární akce, malé zvýraznění |
| `--color-accent-700` | `#ae1800` | text v akcentu (kontrast 6,4:1) |
| `--color-divider` | `color-mix(#201e1d 40 %)` | 2px linky mezi sekcemi |
| neutral/accent ramp | 100–900 (OKLCH) | tinty, hover, pressed |
| `--font-heading` / `--font-body` | Archivo (400–900), Google Fonts | vše, včetně tlačítek |
| `--space-1…8` | 4 / 8 / 12 / 16 / 24 / 32 px | rytmus |
| `--radius-*` | **0 px** | žádné zaoblení |
| `--shadow-sm/md/lg` | ink-tinted | elevace |
| Ikony | Lucide, inline SVG na `currentColor` | |
| Fotografie | `.grayscale` wrapper | vždy černobíle |

Plochy nad tím přidávají vlastní theme proměnné (inline `themeVars`):

- veřejný web a klientská sekce: `--fg`, `--bg`, `--acc`, `--accInk`/`--accDeep` (#ae1800), `--ink` (#1a1918), `--neon` (#b8ff2e), `--field` (#f8f4f4), `--surface`;
- administrace (tmavá, „NOC“): `--a-bg` (#0d0c0c), `--a-panel`, `--a-field`, `--a-fg`, `--a-soft`, `--a-muted`, `--a-line`, `--a-line2`, `--a-hover`, `--a-track`, `--a-acc` (default neon #b8ff2e, volitelně red/blue/amber/violet/teal), `--a-accText`, `--a-accTint`, `--a-toast`; světlý režim `paper|warm|cool`.

## 4. Layouty, komponenty a breakpointy

### Sdílené komponenty (napříč plochami)

| Komponenta | Kde je definována | Poznámka |
| --- | --- | --- |
| `.btn`, `.btn-primary/-secondary/-ghost/-icon/-block` | `_ds/styles.css` | label flush left, hover/pressed z akcentní rampy |
| `.tag*`, `.card*`, `.field`/`.input`/`.radio`/`.seg`, `.nav`, `.table`, `.dialog*`, `.hr`, `.grayscale` | `_ds/styles.css` | jediná komponentní vrstva |
| `pill(kind)`, `bar(pct)`, `dot(kind)`, `btn(primary)` | `renderVals()` každé plochy | inline-style generátory stavů `ok/warn/hot/off/acc` |
| Přepínač ploch, role gate, palette ⌘K, asistent ⌘J | `onhost-shell.js`, `onhost-command.js` | **lešení prototypu** — v produkci vypnuto (`?embed=1`, `ONHOST.demo=false`) |
| Konzole / log řádek `{t, src, m, k, l}` | `onhost-live-adapter.js` | jeden formát pro Wings WS, noVNC, jail shell, journald, task log |
| iOS rámeček | `ios-frame.jsx` | pouze mobilní plocha |

### Page-specific layouty

| Plocha | Layout | Breakpointy (max-width) |
| --- | --- | --- |
| Onhost.dc.html | top bar (status + telefon) → `.nav` s mega menu (`oh-mega`, z 60/70) → sekce `oh-split`, `oh-cards`, `oh-cards3/4`, `oh-rows`, `oh-tab`, `oh-docgrid`, `oh-aside`; košíkový drawer (z 120), přepínač vzhledu (z 90) | 1360, 1240, 1100, 1000, 900, 860, 820, 720, 700, 640, 560, 520, 420 |
| Onhost-app.dc.html | `oh-shell` grid (aside 246px + main), `oh-aside` off-canvas pod 860px, service desk `svc-split` (246px nav + obsah), `oh-det-split`, `oh-form-2`, `oh-kv-2`, dokovaný chat `oh-chat` | 1180, 900, 860, 560, 360 |
| Onhost-admin.dc.html | `adm-shell` grid (aside 216–260px + main), horní alert bar, toast, notifikace, command palette | 1240, 900, 420, 380 |
| Onhost-partner.dc.html | jednosloupcový obsah s tabulkami klientů/provizí | 1520, 1000, 360, 340 |
| Onhost-mobil.dc.html | `IOSDevice` rámeček 390×844, lock screen, push | 1520, 420 |

## 5. Referenční screenshoty a visual-regression baseline

- Baseline: `tests/visual/baseline/` (desktop 1440×1100, tablet 768×1024, mobile 390×844) — generuje `npm run visual:baseline`
  (Playwright, `tests/visual/surfaces.spec.ts`) z **originálních** souborů v kořeni workspace.
- Porovnání: `npm run visual:test` renderuje `apps/surfaces/*` servírované Laravelem a porovnává s baseline
  (`maxDiffPixelRatio: 0.002`). Změna vzhledu = failing test, změna dat = OK, protože testy běží nad fixture snapshotem.
- Náhled bez prohlížeče: `apps/surfaces/.thumbnail` (miniatury z Claude Design) není součástí testu.

## 6. Co se v produkčních kopiích smí měnit (a co ne)

Smí (pouze datové seamy, žádný vzhled):

1. `onhost-store.js` → nahrazeno `onhost-store.api.js` (stejné API, data z `/v1`);
2. `onhost-shell.js` → session z `window.ONHOST.user`, přepínač rolí jen v `ONHOST.demo`;
3. `onhost-integrations.js`, `onhost-domains.js` → API-backed varianty se stejným rozhraním;
4. `onhost-data.js` → generováno serverem z katalogu (`/surfaces/onhost-data.js`);
5. `Onhost-app.dc.html`: jediný zásah — `SVC_DATA().services` a `state.servers` čtou `window.ONHOST_PANEL`, `liveSimulation` default `false`;
6. injektovaný `<script>` s `window.ONHOST = { apiBase, csrf, user, surface, demo }` před `onhost-shell.js`.

Nesmí: typografie, barevné tokeny, radius/shadows, spacing, navigační charakter, formulářové komponenty,
tabulky/karty, responsivní chování (blueprint §59.2). Nová komponenta vzniká jen podle §59.4 a musí být
nejdřív ukázána v `Onhost-widgets.dc.html`.

## 7. Známé limity prototypového runtime (zaznamenáno, ne skryto)

- React/Babel se načítají z unpkg za běhu → produkční nasazení musí buď vendorovat UMD buildy do
  `apps/surfaces/vendor/` (CSP bez `unpkg.com`), nebo předkompilovat logiku (backlog `UI-01`).
- `support.js` kompiluje logiku Babelem v prohlížeči; první render ~300–600 ms na desktopu. Backlog `UI-02`: build step.
- Šablony jsou CS/EN; SK lokalizace (§83) je backlog `UI-03` — překladová vrstva `_()` je připravená.
