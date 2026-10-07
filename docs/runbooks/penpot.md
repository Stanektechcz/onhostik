# Penpot for web hosting (H8, TASK-0123)

Owner decision 7 of 2026-10-06: offer [Penpot](https://penpot.app/self-host) — the open-source design and prototyping tool — to
web hosting customers. This runbook says how the platform delivers it, what a server needs before the first instance is sold,
and what the operator does day to day. Code: `providers/Penpot/*`, `domains/Services/Penpot/*`,
`domains/Provisioning/Workflows/ProvisionPenpotWorkflow.php`, `config/penpot.php`. Tests: `tests/Feature/Penpot/*`,
`tests/Feature/E2E/PenpotFlowTest.php`. Manual test: `docs/manual-tests/11-penpot.md`.

## Sale and price (owner decision H-R7, 2026-10-07, TASK-0128)

Penpot is **on sale** (revision `2026-10-penpot-on-sale`, replaces the proposal `2026-10-penpot`). It is always ordered **for one
service** of the customer — never from the price list (`meta.listed = false`) and never on its own:

| Ordered next to | Price | Where it is set |
| --- | --- | --- |
| a web hosting tariff (product family `web` or `managed`) | **included (0 Kč)** by default, or the tariff's own monthly price | Nastavení → Integrace → *Penpot k tarifům webhostingu* / `PUT /v1/staff/pricing/penpot` (`CatalogCommand pricing.penpot.set`: step-up + second person, audited). Setting `pricing.penpot`, class `PenpotOffer`. A tariff without its own rule follows `web_default` (included). Price `0` = included. |
| any other service (VPS, game server, mail …) | the add-on price = catalogue price of `penpot/penpot-team`: **29 Kč / month** (EUR 1.19), a year = 12 months | the plan editor (`plan.publish`, four eyes) |

Rules the code keeps (`PenpotLine`, `PenpotParents`): the cart line is `{product_key: penpot, plan_key: penpot-team, config:
{parent_line_id | parent_service_id}}`; **one Penpot per service** (a second one, or two in one cart → `409 penpot_exists`); only for a
running service of the same organization (`penpot_parent_inactive`, foreign id → 404); no commitment, promo, loyalty or regional
discount (the configured price is the price; an included Penpot is shown "v ceně tarifu", not as a discount); the subscription renews
at the price it was sold at (0 for an included one). The customer reads the offer for a service at `GET /v1/services/{id}/penpot-offer`.
A Penpot ends with its service (terminate chain `endPenpotStep`, the cancellation preview says so).

**No node, no sale.** The cart (`409 penpot_unavailable`, nothing ordered or charged), the checkout (a quote is valid 2 h) and the
delivery (the paid line fails and the order settlement refunds it) each ask `NodeScheduler::canHost(role penpot, provider penpot)`.
The doctor row *Penpot is sold only with a Penpot node to run it* is a non-blocking FAIL while Penpot is on sale without a qualified
node. Until the node below exists, Penpot is on the books but every order is refused honestly.

**With its service (TASK-0130).** The Penpot is carried by its service like the included sites (`IncludedServices::carried`): a
suspension of the service suspends it (`included.held_by`), the resumption resumes exactly what was held, a cancellation cancels it
(own final archive, `included.ended_by`) and taking the cancellation back — or the reinstatement after payment, which is a resume —
undoes the Penpot's deletion and resumes it. A Penpot the customer stopped or cancelled on its own stays as it is.

**One per service, under concurrency.** The checkout locks the parent service row and asks again inside its transaction
(`PenpotLine::claimParents`); the partial unique index `services_one_penpot_per_parent` (migration 001110, column
`penpot_parent_id`) refuses a second Penpot row that is not TERMINATED/FAILED, and the delivery turns that into `409 penpot_exists`.

**In the panel.** The service detail (tab *Provoz a NOC* of a web hosting, server, game or mail service) has a *Penpot* row from
`GET /v1/services/{id}/penpot-offer`: included / price / why not (`penpot_unavailable`: the node is not ready, nothing is charged), and
an order button (`OnhostPanelOrder.penpot`: `PUT /v1/cart` → quote → `POST /v1/orders`, paid from credit or by proforma). Seam files
only (`apps/surfaces/api/onhost-panel-workbench.api.js`, `onhost-panel-order.api.js`); the prototypes are untouched.

## Delivery model (and why)

**One Penpot per customer service, run by the platform as its own Docker Compose project on a dedicated Penpot node.**

| Option | Verdict |
| --- | --- |
| Inside a shared aaPanel / ISPConfig web hosting | **No.** Penpot is five containers (frontend, backend, exporter, PostgreSQL, Valkey). Docker on a shared web node is root for every tenant, the aaPanel node hardening (`libusranalyse` in `ld.so.preload`) stops `www` from running any binary, and one Penpot needs gigabytes of memory a shared node sells to dozens of sites. |
| One shared Penpot with a team per customer | **No.** Teams inside one instance share one admin, one registration policy, one database and one upgrade; a customer could not get their data out on its own, and limits per customer do not exist. |
| A VPS template with Penpot (cloud-init) | Possible later, but the customer would then run a server (updates, backups, TLS) — not "Penpot for web hosting". The Proxmox adapter has no user-data snippets today. |
| ONhost Apps (Kubernetes) | The `apps` product is a draft; Kubernetes is not an operated executor. |
| **Dedicated Penpot node, one compose project per service** | **Chosen.** Isolation per customer (own database, cache, assets volume, secret key, network), limits per container (memory, CPU), backups and termination per service, the platform's existing provider/operation/backup/DNS machinery, no customer shell anywhere. |

How it is wired:

* Product `penpot` (family `penpot`, executor `penpot`), plan `penpot-team` (4 GB RAM, 2 vCPU, 20 GB, backups 14 days). Since H-R7 it
  comes from the revision **`2026-10-penpot-on-sale`** (on sale, priced; see *Sale and price* above; the old proposal
  `2026-10-penpot` is gone — an installation that applied it gets its draft priced and put on sale by the new revision). Putting an
  `admin_priced` product on sale stays refused (`price_unset`) while any price is zero.
* Provider instance `provider: penpot` (adapter `PenpotDockerProvider`, SSH as the platform's user), node(s) with role `penpot`.
* Saga `provision.penpot`: node → secrets in the vault → the stack (`docker compose up -d` + proxy site) → DNS in the platform zone
  → the owner's Penpot profile → the address answers → ACTIVE.
* Suspend, resume, backup, restore, cancel and purge are the ordinary service actions (`POST /v1/services/{service}/actions`).
  Cancelling takes a final archive (database dump + assets pulled off the node) before anything is removed.
* `onhost:penpot:sweep` (every 10 minutes): probes each running instance on its node, tells the customer and staff when one stops
  answering (two misses in a row) and when it is back; starts the daily backup when the last one is older than 23 hours.
* Customer panel: the service appears with the web services; its card (`GET /v1/services/{service}/penpot`) shows the address,
  the sign-in e-mail, limits, availability and backups. **Open Penpot** is a plain link to the instance. The owner sets the
  password of their Penpot account there (`POST /v1/services/{service}/penpot/owner-password`, HIGH, fresh step-up). Who may:
  the owner, an organization admin, or whoever holds the service's console (`service.console`) — a `svc_manage` share does not.

### What "SSO" is and is not

The panel opens the instance with a plain link; the customer signs in with their Penpot account (the organization owner's e-mail,
the password they set in the panel). A real single sign-on needs an OpenID Connect provider: Penpot supports a generic one
(`enable-login-with-oidc`, `PENPOT_OIDC_CLIENT_ID`, `PENPOT_OIDC_BASE_URI`, `PENPOT_OIDC_CLIENT_SECRET`, … in the official
configuration guide), but ONhost has no OIDC identity provider today. That is a follow-up, not something this package pretends.

## Facts from the official Penpot documentation

Verified on 2026-10-06 against help.penpot.app/technical-guide (getting started → docker; configuration) and the official
`docker/images/docker-compose.yaml`:

* Services: `penpot-frontend` (`penpotapp/frontend`, port 8080 inside), `penpot-backend`, `penpot-exporter`, `penpot-postgres`
  (`postgres:15`), `penpot-valkey` (`valkey/valkey:8.1`); the official file also has a mail catcher, an admin console and an MCP
  server — left out here.
* Environment variable names used: `PENPOT_FLAGS`, `PENPOT_PUBLIC_URI`, `PENPOT_SECRET_KEY`, `PENPOT_HTTP_SERVER_MAX_BODY_SIZE`,
  `PENPOT_HTTP_SERVER_MAX_MULTIPART_BODY_SIZE` (both 367001600 as in the official file), `PENPOT_DATABASE_URI`,
  `PENPOT_DATABASE_USERNAME`, `PENPOT_DATABASE_PASSWORD`, `PENPOT_REDIS_URI`, `PENPOT_OBJECTS_STORAGE_BACKEND` (`fs`),
  `PENPOT_OBJECTS_STORAGE_FS_DIRECTORY` (`/opt/data/assets`), `PENPOT_INTERNAL_URI`, `PENPOT_TELEMETRY_ENABLED`, `PENPOT_SMTP_*`
  (`DEFAULT_FROM`, `DEFAULT_REPLY_TO`, `HOST`, `PORT`, `USERNAME`, `PASSWORD`, `TLS`, `SSL`), `POSTGRES_*`, `VALKEY_EXTRA_FLAGS`.
* Flags used: `disable-registration`, `enable-login-with-password`, `enable-prepl-server` (needed by `manage.py create-profile`),
  `disable-onboarding`; without SMTP also `disable-email-verification` and `enable-log-emails`, with SMTP `enable-smtp`.
* `PENPOT_SECRET_KEY`: the docs generate it with `secrets.token_urlsafe(64)`; the platform generates an equivalent 64-character
  URL-safe value per instance.
* Accounts: `python3 manage.py create-profile --email --fullname --password --skip-tutorial --skip-walkthrough` and
  `update-profile --email --password` inside the backend container (connects to the PREPL server on localhost:6063 of the container).
* HTTPS needs a reverse proxy (the docs name NGINX, Caddy or Traefik). Updates: `docker compose pull`, step by step.
* The documentation states **no hardware requirement**. The 4 GB / 2 vCPU of the plan are the platform's own sizing (the task's
  assumption); adjust the plan and the node capacity after the first real instances are measured.

## Server prerequisites (before the first sale) — LIVE STEPS, not done by this package

Nothing below was done; there is no Penpot node yet. Every step is the operator's on a real server.

1. **A dedicated Linux server** (Debian 12/13 or Ubuntu 24.04), not a shared web node. Budget per instance: the plan's 4 GB RAM and
   2 vCPU plus ~20 GB disk; the node's capacity in the platform (`nodes.capacity`) must reflect it (`NodeScheduler` places by it).
2. **Docker Engine with the compose plugin** (`docker compose version` answers; Docker 27–29 are what the adapter was written for).
3. **Caddy** as the reverse proxy on ports 80/443, with this line in `/etc/caddy/Caddyfile`:
   `import /etc/caddy/onhost-penpot/*.caddy`. Caddy issues certificates by itself (HTTP-01) once a host name resolves to the node.
4. **The platform's user** (default `onhost`, option `ssh_user`): SSH key login only; member of the `docker` group; owner of
   `/srv/onhost-penpot`, `/var/backups/onhost-penpot` and `/etc/caddy/onhost-penpot`; allowed to run `systemctl reload caddy` (a
   sudoers rule, or set the instance option `proxy_reload` to the command that works). `curl` installed (the probe).
5. **Firewall**: 22 (from the control plane only), 80 and 443 open; the stacks bind 127.0.0.1 only (ports 19001–19999).
6. **DNS**: `penpot.onhost.cz` must be a platform zone (`ONHOST_PLATFORM_ZONES` contains a zone that holds it, e.g. `onhost.cz`), so
   the saga can publish `<label>.penpot.onhost.cz`. Without it the DNS step is skipped and the operator points the name by hand.
7. **SMTP (optional)**: instance option `smtp` = `{host, port, username, from, reply_to, tls, ssl}` and credential `smtp_password`.
   Without SMTP, e-mail verification is off and invitations are only logged in the backend container (`enable-log-emails`) — the
   owner then shares the instance with the team by creating their accounts or setting up SMTP first.
8. **Register the node in the platform** (staff console or API, step-up):
   * provider instance: `provider: penpot`, `key` e.g. `penpot-cz1`, `region_code: cz1`, `base_url: https://<node host>` (its host is the
     SSH host unless `ssh_host` says otherwise), options `{ssh_host, ssh_port, ssh_user, ssh_fingerprint, public_ipv4, public_ipv6,
     quota_command}` (+ any key of `config/penpot.php` to override), capabilities `{"penpot.stack": true}` (the default);
   * **`ssh_fingerprint` is required** (`SHA256:` + 43 characters, as `ssh-keyscan <host> | ssh-keygen -lf -` prints it, read on a
     trusted machine): an instance without it is refused (`instance_ssh_fingerprint_required`), and the adapter sends nothing to a
     node whose fingerprint is missing or does not match;
   * the private key: `php artisan onhost:integrations:secret penpot-cz1 ssh_private_key` (hidden prompt — never paste keys into
     chat, code or arguments);
   * a node row with `role: penpot`, its capacity and `tags.public_ipv4`;
   * check: `php artisan onhost:doctor` — rows *Penpot is sold only with a Penpot node to run it*, *Penpot has a price before it
     is on sale*, *every Penpot node pins its SSH host key*, *every Penpot node limits the storage of a stack*, *Penpot images are
     pinned by digest*.
9. **Queue worker lane** `provider-penpot` (already in `infra/aapanel/install.sh`, `staging.sh`, `infra/docker-compose.yml` and
   `QueueScaler::QUEUES`): restart the workers after the deploy.
10. **Smoke test on a test node** with a staff assisted order (placement pinned to the node), then the manual test 11-penpot.md.

11. **Port allocation**: each stack's port is chosen under `flock` on `<root>/.ports.lock` (the platform user needs `flock`, part of
    util-linux), so two provisionings on one node never share a port.

## Day to day

| Situation | What to do |
| --- | --- |
| Doctor: *every Penpot instance answers* is WARN | `php artisan onhost:penpot:sweep --no-backups`; on the node `docker compose -p <stack> ps` and `docker compose -p <stack> logs --tail 200 penpot-backend`. The stack name is `penpot-` + the last 10 characters of the service id (the binding's `remote_id`). |
| A provisioning failed | The operation names the step. A failed `up` takes the half-made directory and proxy site back (compensation); retry the operation once the node is fixed (staff console, `POST /v1/staff/provisioning/jobs/{operation}/retry`). |
| Customer forgot the Penpot password | They set a new one in the panel (step-up). Staff do not see or set it. |
| Restore | The ordinary `restore` action with a backup id (`backup.restore` permission): stops frontend/backend/exporter, replaces the database and the assets, starts the stack. |
| Upgrade Penpot | Images are pinned by digest (`config/penpot.php` `images`, or the instance option `images`): set the new tag **and** its digest (`curl -s https://hub.docker.com/v2/repositories/<repo>/tags/<tag>` → `digest`) — new stacks use them. Existing stacks keep the images in their `.env`; upgrade one by one on the node (the five `*_IMAGE` lines of `.env`, `docker compose pull && docker compose up -d`), after a backup, step by step as the Penpot docs advise. |
| Cancellation | The ordinary terminate: a final archive (database dump + assets) is pulled into platform storage, the stack is stopped; the purge after the restore window removes the stack, its volumes, its node backups, its proxy site, the DNS record and the vault entry `db://penpot/<service id>`. |

## Secrets

* `db://penpot/<service id>`: `secret_key`, `db_password` — generated at the first provisioning, written to the stack's `.env`
  (mode 0600) over SFTP, never in a command line, an operation, an event or a log (tested: `PenpotLifecycleTest`).
* The owner's Penpot password is never stored and never on a command line: it is written into `<stack dir>/.owner-password` (made
  0600 before anything is written; the directory is 0750), read by `manage.py create-profile|update-profile` on stdin (Python's
  `getpass` reads stdin without a terminal — **verify on the first test node**) and removed in the same command; the operation
  forgets it (OperationSecrets). The first profile gets a random password nobody sees, and a provisioning run again over a running
  instance never touches the account (it is looked up with `search-profile`, never reset).
* `.env` and `.env.smtp` are created 0600 (`install -m 0600 /dev/null`) before their content is written.
* The node's SSH key: the provider instance's credential `ssh_private_key`; SMTP password: `smtp_password`.

## Container hardening

Every container: `security_opt: no-new-privileges:true`, `cap_drop: [ALL]`, `pids_limit`, memory and CPU limits.

| Container | Capabilities back | Why |
| --- | --- | --- |
| frontend, backend, exporter | none | the Penpot images run as `USER penpot:penpot` (docker/images/Dockerfile.{frontend,backend,exporter} on `develop`; frontend nginx listens on 8080) |
| postgres | CHOWN, DAC_OVERRIDE, FOWNER, SETGID, SETUID | the official entrypoint starts as root, fixes the owner and mode of `PGDATA`, then `gosu postgres` |
| valkey | CHOWN, SETGID, SETUID | the official entrypoint chowns `/data`, then `setpriv` to the `valkey` user |

**Verify on the first test node** (not done — there is no node yet): all five containers become `healthy`/`running`, and
`docker inspect` shows the capabilities above. If a pinned release runs a Penpot image as root after all, give it back only what
its entrypoint needs and write it down here.

## Storage quota (server step, XFS project quota)

Docker volumes on ext4 have no size limit, so the plan's `storage_gb` is enforced on the server:

1. `/var/lib/docker` on its own **XFS** filesystem mounted with `prjquota` (`/etc/fstab`: `… /var/lib/docker xfs defaults,prjquota 0 2`).
2. The helper `/usr/local/sbin/onhost-penpot-quota` (root-owned, 0755), allowed to the platform user by one sudoers line
   (`onhost ALL=(root) NOPASSWD: /usr/local/sbin/onhost-penpot-quota`):

   ```sh
   #!/bin/sh
   # onhost-penpot-quota <stack> <GB>: one XFS project per Penpot stack over its two volumes (TASK-0123)
   set -eu
   stack="$1"; gb="$2"
   case "$stack" in penpot-[a-z0-9]*) ;; *) echo "invalid stack" >&2; exit 2 ;; esac
   case "$gb" in ''|*[!0-9]*) echo "invalid size" >&2; exit 2 ;; esac
   mnt=$(df --output=target /var/lib/docker | tail -n 1)
   id=$(( $(printf '%s' "$stack" | cksum | cut -d' ' -f1) % 2000000000 + 1000 ))
   for v in "${stack}_penpot_assets" "${stack}_penpot_postgres_v15"; do
     dir=$(docker volume inspect -f '{{.Mountpoint}}' "$v")
     xfs_quota -x -c "project -s -p $dir $id" "$mnt"
   done
   xfs_quota -x -c "limit -p bhard=${gb}g $id" "$mnt"
   ```

3. Instance option `quota_command: "sudo /usr/local/sbin/onhost-penpot-quota"`. The adapter runs it after every new stack and after a
   resize, with the stack name and the plan's GB; a failure fails the step. Without it the doctor row *every Penpot node limits the
   storage of a stack* is WARN and the plan's storage is only measured.
4. Check: `xfs_quota -x -c 'report -p -h' /var/lib/docker`.

Before a new stack is started the adapter also checks the free space (`df -Pk` of the stacks root): less than the plan's storage
plus `min_free_gb` (default 10 GB) refuses the stack (CAPACITY, the provisioning fails and the customer's order is followed up).

## Limits

Memory and CPU are hard limits per container (`deploy.resources.limits`: backend 45 %, exporter 25 %, PostgreSQL 20 %, frontend 10 % of
the plan's memory minus 256 MB for Valkey). Storage (`storage_gb`) is a hard limit only where the node runs the quota helper (above). The adapter can
measure it (`PenpotDockerProvider::usage`: assets + database volume), but nothing feeds that reading into the usage watch yet (open
follow-up); until then the plan's 20 GB is a fair-use number the operator watches on the node (`docker system df -v`). Upload size: 350 MB per request (proxy + Penpot).
