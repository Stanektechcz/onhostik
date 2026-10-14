# Owner steps for the launch inputs (phase W7)

What the owner sets, where, how to check it without changing anything, and what happens until it is done. Key names and
commands are the ones the code reads (`config/onhost-sell.php`, `config/onhost.php`, `.env.example`, `routes/console.php`).
Secrets (the DQS key, Comgate credentials) go on the server only: never into a chat, the repository or a log.

Conventions: `art` is `php artisan` run as the site user in the release directory. Environment variables live in the
site's `app.env` (`.env` of the release). After any change of an environment variable that config reads, run
`art config:cache` and restart the queue workers and PHP-FPM, otherwise the running processes keep the old value.

| Input | What it unlocks | Until it is done |
| --- | --- | --- |
| SELL-1 | staging keeps selling, staging deploys pass the gate | the staging deploy is refused |
| SELL-2 | production sells new services | nothing is for sale (fail-closed) |
| CG-6 | Comgate payment notifications are accepted | every notification is refused |
| SP1-DNS | the mail reputation monitor can ask Spamhaus and others | those lists are never asked; a doctor WARN says so |
| REV-1 | the 2026-09 catalogue revisions reach the live catalogue | the old plan versions stay on sale; a doctor WARN says so |

## SELL-1: staging sells (`ONHOST_SELL_DEFAULT=true`)

* **Set:** `ONHOST_SELL_DEFAULT=true` in the staging `app.env` (`infra/aapanel/staging.sh` writes it into a fresh file; an
  existing file needs the line added by hand).
* **Why:** the sell matrix is fail-closed (`config/onhost-sell.php`: an unset or empty value is off). The staging deploy
  gate (`deploy-gate.php env-assert`) holds `app.env` to an expected list that pins `ONHOST_SELL_DEFAULT=true`, so a deploy
  without the line is refused (reported as rc 13 on the staging run) and staging could not run its drills or smoke orders.
* **Apply:** edit `app.env`, then `art config:cache`, restart queue workers and PHP-FPM (a deploy does this by itself).
* **Verify, read-only:** `art config:show onhost-sell` lists `default` as `true`.
* **Until done:** nothing is orderable on staging; existing services, renewals and invoices are not affected.

## SELL-2: production sell switches

* **Set:** in the production `app.env`, either `ONHOST_SELL_DEFAULT=true` (every family) or `ONHOST_SELL_DEFAULT=false` plus
  one key per family you want on:

  | Family | Key |
  | --- | --- |
  | web hosting | `ONHOST_SELL_WEB` |
  | managed (WordPress, e-shop) | `ONHOST_SELL_MANAGED` |
  | apps | `ONHOST_SELL_APPS` |
  | cloud (VPS) | `ONHOST_SELL_CLOUD` |
  | data (databases) | `ONHOST_SELL_DATA` |
  | game servers | `ONHOST_SELL_GAME` |
  | mail | `ONHOST_SELL_MAIL` |
  | ai | `ONHOST_SELL_AI` |
  | penpot | `ONHOST_SELL_PENPOT` |
  | add-ons | `ONHOST_SELL_ADDON` |

  A family key is `true`, `false` or empty (empty follows `ONHOST_SELL_DEFAULT`); a family key always wins over the default.
  Domains are not in the matrix (they are sold through the TLD policy).
* **Apply:** edit `app.env`, `art config:cache`, restart queue workers and PHP-FPM. The public surface data
  (`/surfaces/onhost-data.js`, the `onhost-data` cache) is kept for 60 seconds, so the storefront shows the change up to a
  minute later.
* **Verify, read-only:** `art config:show onhost-sell` shows the default and each family as `null`, `true` or `false`.
* **Until done:** new listing and ordering are off for the family; existing orders, renewals, invoices and running services
  never read the switch. To turn a family off again, set it to `false`, cache and restart the same way.

## CG-6: Comgate notification addresses (`COMGATE_CALLBACK_IPS`)

* **Set:** `COMGATE_CALLBACK_IPS` in `app.env`: the addresses Comgate sends notifications from, comma-separated, each an
  address or a CIDR range (IPv4 or IPv6). The owner obtains the list from Comgate (support or the merchant portal
  documentation); it is not in this repository. Entries are trimmed; blank entries and `/0` ranges are ignored.
* **Apply:** `art config:cache`, restart queue workers and PHP-FPM. Also check `TRUSTED_PROXIES` as described in
  [comgate.md](comgate.md), section *Notification set-up*.
* **Verify, read-only:** the administration (Nastavení, Integrace, Comgate) or `GET /v1/staff/payments/comgate` shows
  `callback_allowlist` as the number of entries and no `callback_notice`. Details in [comgate.md](comgate.md).
* **Until done:** an empty list refuses every notification with HTTP 403 (`webhook_source_rejected`). A payment is then
  settled only by the payer's return to the portal or by the sweep `art onhost:payments:sweep` (every ten minutes).

## SP1-DNS: a resolver or a Spamhaus DQS key for the mail reputation monitor

* **Set, one of:**
  * `ONHOST_DNSBL_RESOLVER`: an own recursive resolver (`host`, `host:port` or `[v6]:port`) with a reverse name that
    identifies you, reachable over UDP 53. Public resolvers (8.8.8.8, 1.1.1.1) and an empty value mean Spamhaus,
    Barracuda, SURBL and URIBL are never asked.
  * `ONHOST_SPAMHAUS_DQS_KEY`: the key of Spamhaus' Data Query Service (free for low volume after sign-up). It is a secret:
    never logged and never stored with a listing.

  Optional tuning keys with defaults: `ONHOST_DNSBL_TIMEOUT` (2 s), `ONHOST_DNSBL_DISABLED`,
  `ONHOST_DNSBL_MIN_INTERVAL_MINUTES` (60), `ONHOST_DNSBL_MAX_QUERIES_PER_RUN` (2000), `ONHOST_DNSBL_MAX_RUN_SECONDS` (1800),
  and `ONHOST_ANTISPAM_SENDING_ADDRESSES` for extra sending addresses. Check the list operators' terms for commercial use
  before enabling a resolver (see `docs/proposals/antispam-reputation.md`).
* **Apply:** `art config:cache` and restart queue workers. The job `art onhost:mail:blocklist` runs hourly (minute 25).
* **Verify, read-only:** `art onhost:doctor` row *mail blocklist resolver is accepted* is OK, with the detail naming how
  the lists are asked; the rows *sending addresses are on no major blocklist* and *customer mail domains are on no major
  domain blocklist* fill in after the first run. A run by hand: `art onhost:mail:blocklist` (it only reads public DNS).
* **Until done:** the doctor shows the resolver row as a WARN (non-blocking) and says nothing is known about our listings
  on the lists that refuse public resolvers.

## REV-1: apply the 2026-09 catalogue revisions

* **Set:** nothing in the environment. Publish the revisions defined in code (`domains/Catalog/CatalogRevisions.php`;
  details in [pricing.md](pricing.md), *Catalogue revisions*).
* **Run:**
  1. Dry run, read-only: `art onhost:catalog:revise` lists the plans, the keys each loses, who keeps the old version and the
     promo prices carried over. Read it.
  2. `art onhost:catalog:revise --apply` publishes the new plan versions. It asks first; the publication is a critical
     action, so it runs under four eyes (a second person, [approvals.md](approvals.md)) unless the single-operator switch is
     set on the server.
* **Verify, read-only:** run the dry run again, it answers *Nothing pending*; `art onhost:doctor` shows *every catalogue
  revision is applied* OK.
* **Until done:** the old plan versions stay on sale, including the promises the revisions withdraw, and the doctor shows
  *every catalogue revision is applied* as a WARN.
* **Undo:** publish the previous plan version in the plan editor; customers keep the version they hold either way.
