# Deployment on the aaPanel host — staging.onhost.cz (and onhost.cz)

The control plane runs as a normal Laravel site under aaPanel's nginx + PHP-FPM 8.3, with PostgreSQL and Redis from the
aaPanel App Store, the queue workers and the scheduler as systemd units, and the websocket console relay as a Node
service. `infra/aapanel/install.sh` does the first installation; every later release goes through the gated deployer
(`infra/aapanel/deploy.sh`, installed root-owned as `/usr/local/sbin/onhost-deploy` by `infra/aapanel/install-deployer.sh`;
stages, gate and exit codes: `docs/runbooks/release-and-rollback.md`). The scripts default to `SITE=staging.onhost.cz`;
production is the same procedure with `SITE=onhost.cz`.

**Staging = the production test bed — for the code path, not for live resources.** It runs the production configuration
with the money and registry switches in test mode (`COMGATE_TEST=true`, `WEDOS_TEST_MODE=true`, Let's Encrypt staging
directory). What it proves about panels depends on which panel instances it may reach: phase 1 of the staging launch
(`docs/runbooks/staging-launch.md`) registers none and keeps provisioning frozen, so provisioning on live panels is NOT
proven there.

## 1. aaPanel preparation (once, in the aaPanel UI)

| Step | Where | Value |
| --- | --- | --- |
| PHP 8.3 with extensions `pdo_pgsql`, `intl`, `bcmath`, `mbstring`, `redis`, `fileinfo`, `zip`, `gd`, `opcache`; `disable_functions` must not contain `proc_open`, `exec`, `shell_exec` (pg_dump, Composer) | App Store → PHP 8.3 → Install extensions / Disabled functions | `memory_limit=512M`, `upload_max_filesize=2048M`, `post_max_size=2048M`, `max_execution_time=300` |
| PostgreSQL 16 | App Store → PostgreSQL | database `onhost_staging`, user `onhost`, a strong password (`DB_PASSWORD`) |
| Redis | App Store → Redis | `requirepass` set (`REDIS_PASSWORD`) |
| Website `staging.onhost.cz` | Website → Add site | PHP 8.3, root `/www/wwwroot/staging.onhost.cz/public`, SSL (Let's Encrypt) with force HTTPS |
| DNS | your DNS | `staging.onhost.cz A <server IPv4>` (+ AAAA); `gamepanel.onhost.cz` already points at the game panel |
| Node.js 20+ (console relay) | App Store → Node.js version manager | — |
| Firewall | Security | 80/443 open; 8090 (relay), 5432, 6379 **closed** to the internet |
| Cron / supervisor | not needed — systemd units below | — |

## 2. Installation

```bash
SHA=<40-hex sha of the release record>
curl -fsSL "https://raw.githubusercontent.com/Stanektechcz/onhostik/$SHA/infra/aapanel/install.sh" -o /root/onhost-install.sh
sha256sum /root/onhost-install.sh            # must equal the value in the release record (.ai/releases/)
REF=$SHA EXPECTED_SHA=$SHA START_UNITS=0 bash /root/onhost-install.sh   # clones at $SHA, writes /etc/onhost/app.env, stops
nano /etc/onhost/app.env                     # fill the values from § 4
REF=$SHA EXPECTED_SHA=$SHA START_UNITS=0 bash /root/onhost-install.sh   # composer, key, migrations, seed, units (enabled, not started), caches
```

(`SITE=onhost.cz …` for production; `APP_DIR`, `PHP`, `RUN_USER` are overridable the same way; `BRANCH` is refused.)
`install.sh` refuses a site that is already installed — the `installed` marker in `/var/lib/onhost-deploy/<site>/`, or,
for installs older than the marker, an `APP_KEY` in `app.env` — because its seed step must never run on a live database
again. `INSTALL_REPAIR=1` repairs only storage/bootstrap ownership and the systemd units. The Composer installer is
checked against `composer.github.io/installer.sig` (or install Composer yourself at `COMPOSER`). The doctor line at the
end is informational; install.sh does not gate.

Then install the gated deployer from the same SHA (root-owned, outside the site tree; `.git` must be root's):

```bash
chown -R root:root /www/wwwroot/staging.onhost.cz/.git && chmod -R go-w /www/wwwroot/staging.onhost.cz/.git
git -C /www/wwwroot/staging.onhost.cz show $SHA:infra/aapanel/install-deployer.sh > /root/install-deployer.sh
SHA=$SHA FIRST=1 bash /root/install-deployer.sh   # production: SHA=$SHA TAG=<the owner's signed tag> FIRST=1 …
cat /usr/local/lib/onhost-deploy/source-sha  # = $SHA
```

The installer holds the judge to the release rules: in production (`APP_ENV` of `/etc/onhost/app.env`, fail closed) it
takes a SHA only together with the owner's SSH-signed tag pointing at it (verified against `allowed_signers`, as a
release is); it only moves forward; `FIRST=1` is refused on a host that already has a deployer. The deployer reads
`APP_ENV` from `/etc/onhost/app.env` itself and refuses to run when `$APP_DIR/.env` is not that file (install.sh links it).

Then paste `infra/aapanel/nginx-site.conf` into Website → staging.onhost.cz → Config (root = `…/public`), reload nginx
and open `https://staging.onhost.cz/up` (200) and `https://staging.onhost.cz/healthz`. On staging the site sits behind
basic auth or an IP allow-list that **lets loopback through** (the realm is `off` for `127.0.0.1` and `::1` — the snippet's
staging block): the deployer checks `/up` and `/v1/status` on `127.0.0.1` and refuses to start when they answer 401/403.

Console relay:

```bash
mkdir -p /opt/onhost-console-relay && cp -r /www/wwwroot/staging.onhost.cz/infra/console-relay/* /opt/onhost-console-relay/
cd /opt/onhost-console-relay && npm ci --omit=dev
useradd -r -s /usr/sbin/nologin onhost-relay; chown -R onhost-relay:onhost-relay /opt/onhost-console-relay
printf 'ONHOST_API=https://staging.onhost.cz\nONHOST_CONSOLE_RELAY_KEY=<the same value as in app.env>\nPORT=8090\n' > /etc/onhost/relay.env; chmod 600 /etc/onhost/relay.env
cp /www/wwwroot/staging.onhost.cz/infra/systemd/onhost-console-relay.service /etc/systemd/system/ && systemctl enable --now onhost-console-relay
```

## 3. Releases

```bash
REF=<sha|tag> EXPECTED_SHA=<40-hex sha> DEPLOY_OPERATOR=<name> PHP_FPM_RELOAD='/etc/init.d/php-fpm-83 reload' \
  /usr/local/sbin/onhost-deploy; echo rc=$?
# drain → down → backup + verify → switch + build → gate (doctor by row name, /up + /v1/status) → start → up → record
```

Production (`APP_ENV=production`) takes only an annotated tag signed by the owner's SSH key listed in
`/var/lib/onhost-deploy/onhost.cz/allowed_signers` (root-owned; without it every production deploy is refused), plus
`EXPECTED_SHA`. A target that carries a newer `deploy.sh`/`deploy-gate.php` than the installed deployer is refused
until the deployer is installed from it (`install-deployer.sh`, the command is printed).

Rollback: run the command the deployer printed (the last good release from `last-good.json`, through the same gate, the
backup is taken — not skipped); migrations are backward compatible for one release (docs/runbooks/release-and-rollback.md).

## 4. Údaje k doplnění (co potřebujeme od vás pro plnohodnotné testování produkce)

Vše se zapisuje do `/etc/onhost/app.env` (šablona `.env.example`, sekce v ní odpovídají tabulkám níže) nebo skrytými
příkazy na serveru — nikdy do chatu.

**A. Server a aplikace (`app.env`)**

| Klíč | Hodnota pro staging |
| --- | --- |
| `APP_ENV=staging`, `APP_URL=https://staging.onhost.cz`, `SANCTUM_STATEFUL_DOMAINS=staging.onhost.cz` | už v šabloně |
| `DB_HOST=127.0.0.1`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL z aaPanelu |
| `REDIS_HOST=127.0.0.1`, `REDIS_PASSWORD` | Redis z aaPanelu |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | SMTP účet (SPF/DKIM/DMARC pro odesílatele, jinak testovací maily končí ve spamu) |
| `ONHOST_STAFF_DIGEST_TO` | e-mail provozu pro denní přehled |
| `ONHOST_CONSOLE_RELAY_KEY`, `ONHOST_CONSOLE_RELAY_URL=wss://staging.onhost.cz/relay` | náhodný řetězec (`openssl rand -hex 32`), stejný v `/etc/onhost/relay.env` |
| `ONHOST_METRICS_TOKEN` | náhodný řetězec pro Prometheus |
| `ONHOST_PLATFORM_ZONES`, `ONHOST_VM_HOSTNAME_SUFFIX`, `ONHOST_WEB_PREVIEW_SUFFIX`, `ONHOST_STAGING_SUFFIX` | subdomény, pod kterými staging zřizuje weby a VM (šablona: `*.staging.onhost.cz`) |

**B. Právnická osoba a platby**

| Klíč | Hodnota |
| --- | --- |
| `ONHOST_LEGAL_NAME`, `ONHOST_ICO`, `ONHOST_DIC`, `ONHOST_VAT_ID`, `ONHOST_STREET`, `ONHOST_CITY`, `ONHOST_ZIP` | firma na dokladech (i na stagingu skutečná — testujete PDF faktur) |
| `ONHOST_BANK_IBAN`, `ONHOST_BANK_BIC`, `ONHOST_BANK_ACCOUNT` | účet na proformách a QR kódech |
| `ONHOST_BANK_FIO_TOKEN` | Fio API token (jen čtení) — párování převodů; na stagingu lze vynechat a platby zapisovat ručně v *Nastavení → Bankovní platby* |
| `COMGATE_MERCHANT`, `COMGATE_SECRET` | **testovací** merchant Comgate; `COMGATE_TEST=true` zůstává |
| `WEDOS_TEST_MODE=true` | registrace domén nanečisto (WAPI test flag); WEDOS login/heslo přes `onhost:integrations:secret` |

**C. První staff účet a instance integrací (z terminálu, bez konzole)**

```bash
php artisan onhost:staff:create ops@onhost.cz --name="Provoz" --role=platform_owner   # heslo skrytým promptem; TOTP při prvním přihlášení
php artisan onhost:integrations:register pterodactyl-gamepanel pterodactyl https://gamepanel.onhost.cz --name="Herní panel" --region=cz1
php artisan onhost:integrations:register aapanel-managed01 aapanel https://<aapanel-host>:7800 --name="aaPanel CZ1" --region=cz1
php artisan onhost:integrations:register ispconfig-shared01 ispconfig https://<ispconfig-host>:8080 --region=cz1 --option=server_id=1
php artisan onhost:integrations:register proxmox-cz1 proxmox https://<pve-host>:8006 --region=cz1 --option=storage=local-lvm --option=bridge=vmbr0
php artisan onhost:integrations:register pbs-cz1 pbs https://<pbs-host>:8007 --region=cz1 --option=datastore=onhost
php artisan onhost:integrations:register powerdns-hidden01 powerdns http://<pdns-host>:8081 --region=cz1
```

Klíče instancí (skrytý prompt, `--check` ověří spojení a prerekvizity):

```bash
php artisan onhost:integrations:secret pterodactyl-gamepanel application_key --check   # herní panel (po testech klíč z chatu přegenerovat)
php artisan onhost:integrations:secret pterodactyl-gamepanel client_key --check
php artisan onhost:integrations:secret aapanel-managed01 api_key --check              # webhosting/WordPress na aaPanelu
php artisan onhost:integrations:secret ispconfig-shared01 remote_user                   # + remote_password (ISPConfig, pošta)
php artisan onhost:integrations:secret proxmox-cz1 token_id                             # + token_secret — VPS, VDS, databáze, IPv4
php artisan onhost:integrations:secret pbs-cz1 token_id                                 # + token_secret — zálohy VPS
php artisan onhost:integrations:secret powerdns-hidden01 api_key                        # DNS zóny
php artisan onhost:integrations:secret wedos-zone …                                     # WEDOS WAPI login + heslo; allow-list IPv4 serveru u WEDOS
php artisan onhost:integrations:secret subreg …                                         # Subreg API (dnes odmítá login 500.104 — zapnout API u uživatele)
```

Bez Proxmox/PBS klíčů se produkty VPS, VDS, databáze, IPv4 a zálohy nedají zřídit (doctor to hlásí); webhosting, hry,
domény a pošta fungují s aaPanel/Pterodactyl/WEDOS/ISPConfig.

**D. Tajemství platformy (`onhost:secrets:set`, skrytý prompt)**

```bash
php artisan onhost:secrets:set db://oncall/pager routing_key        # PagerDuty/Opsgenie; ONHOST_ONCALL_PROVIDER=pagerduty v app.env
php artisan onhost:secrets:set db://integrations/discord token      # Discord bot; APPLICATION_ID + PUBLIC_KEY v app.env
php artisan onhost:secrets:set db://ai/anthropic api_key            # asistent podpory; ONHOST_AI_ENABLED=true
php artisan onhost:secrets:set db://cdn/cloudflare token            # + account_id — doplněk CDN
php artisan onhost:game:operator-variable STEAM_USER                # + STEAM_PASS — bez nich se DayZ nenabízí
```

**E. Volitelné, ale doporučené i pro staging**

`TURNSTILE_SITE_KEY/SECRET_KEY` (Cloudflare má testovací klíče, které vždy projdou), `SENTRY_DSN`,
`OTEL_EXPORTER_OTLP_ENDPOINT` + `ONHOST_TRACE_URL` (Grafana/Tempo z `infra/docker-compose.yml` nebo vlastní),
`ONHOST_CLAMAV_HOST` (`apt install clamav-daemon` na stejném stroji nebo role `onhost_clamav`), `AWS_*` S3 bucket pro
zálohy a soubory mimo server (jinak `ONHOST_PLATFORM_BACKUP_DISK=local`, `ONHOST_FILES_DISK=local`).

## 5. Production test on staging

```bash
php artisan onhost:doctor                       # 0 FAIL; WARN only for what you decided to leave off
php artisan onhost:integrations:health          # every instance up
php artisan onhost:game:bootstrap pterodactyl-gamepanel   # nodes, 22 templates, ports, placement
php artisan onhost:platform:backup && php artisan onhost:platform:backup:verify
```

Never run `DevAccountSeeder` on staging or production: it creates known demo accounts (and, on some versions, service
records bound to real panel ids). Staff come only from `onhost:staff:create --role=…` (§ 4 C); the staging launch
procedure is `docs/runbooks/staging-launch.md`.

Smoke test as a customer (docs/runbooks/go-live-checklist.md § 5): register → order web hosting by bank transfer →
record the payment (Nastavení → Bankovní platby or Fio sync) → service ACTIVE on aaPanel → invoice PDF → card top-up
against the Comgate test merchant → game server (Minecraft Paper) → console, file upload → domain check and order in
WEDOS test mode → ticket → cancel → data export. Staff sign in with TOTP (`ONHOST_STAFF_MFA_REQUIRED=true`) with the
accounts created by `onhost:staff:create`; each person enrols MFA themselves. Steps that provision on a panel need a
panel instance the owner released for staging (phase 2 of `docs/runbooks/staging-launch.md`); in phase 1 provisioning
stays frozen. What passes here is what production does; production differs only in `SITE=onhost.cz`,
`APP_ENV=production`, live Comgate, `WEDOS_TEST_MODE=false`, the production ACME directory, and
`onhost:production:prepare --purge-dev-accounts --legal --cache` before the first customer.

## 6. Ověření produkčního provozu (na stagingu, pak stejně na onhost.cz)

> Pozor: `onhost:nodes:discover` a `onhost:smoke:order` níže pracují se **živými** panely a zakládají skutečné služby.
> Na stagingu jen ve fázi 2 s písemným souhlasem vlastníka pro konkrétní panel (`docs/runbooks/staging-launch.md`, O2/O3);
> ve fázi 1 se nespouští.

```bash
cd /www/wwwroot/staging.onhost.cz
P=/www/server/php/83/bin/php
# uzly panelů přes jejich API (bez nich plánovač služby nikam neumístí)
$P artisan onhost:nodes:discover ispconfig-shared01 --region=cz1
$P artisan onhost:nodes:discover aapanel-managed01 --region=cz1
$P artisan onhost:nodes:discover pterodactyl-gamepanel --region=cz1
# produkty bez připojeného panelu z prodeje
$P artisan onhost:catalog:state draft vps vds database ipv4 backup-plus backup-hourly
# živá konzole herních serverů
bash infra/aapanel/relay-install.sh
# e-mail a domény
$P artisan onhost:mail:test <váš e-mail>
$P artisan onhost:smoke:order ops@onhost.cz --domain-check=onhost-test-overeni.cz
# ostré objednávky všech prodávaných typů služeb (objednávka z kreditu → zřízení → ověření na panelu → zrušení)
$P artisan onhost:smoke:order ops@onhost.cz --web=ispconfig-shared01 --web=aapanel-managed01 --game=minecraft-vanilla@1.21.8 \
  --product=web-custom@ispconfig-shared01 --product=wordpress@aapanel-managed01 --product=eshop@aapanel-managed01 --product=mail@ispconfig-shared01 \
  --fund --cleanup --timeout=1200
$P artisan onhost:doctor
```

Pojistky instance po opakovaných chybách: `onhost:integrations:breaker <instance> [--reset]` (reset až po opravě příčiny).

### 6a. Životní cyklus zrušení (audit §5ab)

Po nasazení ověřte, že se zálohy před zrušením daří sestavit na **každém** panelu — dřív, než něco zruší zákazník.
`--create` nic nemaže, jen projde stejnou cestou jako zrušení:

```bash
cd /www/wwwroot/staging.onhost.cz
P=/www/server/php/83/bin/php
$P artisan onhost:services:archive <služba na ISPConfigu> --create   # ověření identity + archiv (soubory, databáze, metadata)
$P artisan onhost:services:archive <služba na aaPanelu> --create     # padne-li přenos souborů, nastoupí záloha panelu
$P artisan onhost:services:archive <herní služba> --create
$P artisan onhost:services:purge --dry-run                           # co je po lhůtě a čeká na odstranění
```

V tabulce archivu musí být u dokončeného archivu `site-files…`, `database-…` a `service.json`; sloupec *pokusy / chyba*
ukazuje, kterou cestou soubory přišly. Denní odstraňování po vypršení lhůty jede v plánovači
(`Schedule::command(onhost:services:purge)->dailyAt(03:40)`), takže ověřte i běžící `onhost-scheduler`.

Služba, která uvázla v `TERMINATING` (starší nasazení bez záložní cesty k souborům), se rozjede takto:

```bash
$P artisan onhost:provisioning:jobs 2>/dev/null || true   # případnou zaseknutou operaci zrušte v administraci (Provoz)
$P artisan onhost:services:purge --service=<srv_…> --force --reason="dokončení zrušení po opravě archivu"
```

