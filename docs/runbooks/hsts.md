# HSTS: sent once, by the application (P11, P-R74)

**Decision P-R74 (HS-1):** `Strict-Transport-Security` is sent by the **application only**, from `onhost.security.hsts`
(`ONHOST_HSTS_*`), **without `preload`**; `includeSubDomains` stays **off** until every subdomain of the apex (`*.onhost.cz`) is
known to serve valid TLS — on the apex it would force browsers to use HTTPS on every subdomain, including historical sites
without TLS (assumption: no inventory of the subdomains exists yet). The web server's own HSTS header (aaPanel → site → SSL →
*HSTS*) is switched **off** by the owner.

Found on staging 2026-10-13 (protocol `staging-rehearsal-2026-10-14.md`, finding 3, and 2026-10-15): the header came twice — the
application's hard-coded `max-age=31536000; includeSubDomains; preload` and the vhost's.

## What the code does

| Setting | Default | Effect |
| --- | --- | --- |
| `ONHOST_HSTS_ENABLED` | `true` | `false` sends no header |
| `ONHOST_HSTS_MAX_AGE` | `31536000` | seconds; `0` sends no header |
| `ONHOST_HSTS_INCLUDE_SUBDOMAINS` | `false` | adds `; includeSubDomains` |
| `ONHOST_HSTS_PRELOAD` | `false` | adds `; preload` **only** with `includeSubDomains` and `max-age >= 31536000` (the preload list refuses anything else) |

The header is sent on HTTPS requests (and on every request in production). Doctor row **`security | HSTS is sent once and as
configured`** (`Onhost\Domain\Platform\HstsHealth`): on staging and in production it requests `APP_URL/up` once (no other host, no
redirect followed, 5 s timeout) and expects exactly one header equal to the configured value. Elsewhere it reports OK with
"checked only on staging and production". The row is WARN, never FAIL: two headers or a missing header weaken the hardening,
they do not break the site.

| Row says | Meaning | What to do |
| --- | --- | --- |
| `1× max-age=31536000` | as decided | nothing |
| `2× (…)` | the vhost sends its own header too | switch the vhost's HSTS off (below) |
| `not sent` | something in front strips it | check `SecurityHeaders` and the proxy |
| `1× …, expected …` | the web server replaces the header | switch the vhost's HSTS off |
| `could not reach …` | the site does not answer on its own address | `nginx -t`, `curl https://<site>/up` |

## Switching the vhost's HSTS off (owner, mode V — staging first, production only with the production document)

aaPanel writes the header into the site's nginx vhost when *HSTS* is ticked in the SSL settings. Read first (no change):

```bash
SITE=staging.onhost.cz
grep -n 'Strict-Transport-Security' /www/server/panel/vhost/nginx/$SITE.conf || echo "no HSTS line in the vhost"
curl -sI https://$SITE/up | grep -i '^strict-transport-security' | cat -A | head
```

Then in aaPanel: **Website → `staging.onhost.cz` → SSL → untick *HSTS* → Save** (aaPanel removes the `add_header` line and
reloads nginx). Verify:

```bash
nginx -t
curl -sI https://$SITE/up | grep -ci '^strict-transport-security'      # expect 1
cd /www/wwwroot/$SITE && HOME=/tmp setpriv --reuid=www --regid=www --init-groups -- /www/server/php/85/bin/php artisan onhost:doctor --json \
  | jq -r '.checks[] | select(.check == "HSTS is sent once and as configured") | "\(.status) \(.detail)"'   # expect OK 1× max-age=31536000
```

**Rollback:** tick *HSTS* again in aaPanel (the application keeps sending its own header either way; the row turns WARN `2×`).

Browsers that already stored `includeSubDomains; preload` from the old header keep it until it expires (one year); the site was
never submitted to the preload list, so nothing has to be withdrawn there.

## Later: `includeSubDomains`

Only after an inventory of every DNS name under the apex shows valid TLS on each (a historical site without TLS would become
unreachable in browsers that saw the header). Then `ONHOST_HSTS_INCLUDE_SUBDOMAINS=true` + `config:cache`, a short `max-age`
first (e.g. 300), then a year. `preload` is a separate owner decision (submission to hstspreload.org, months to undo).
