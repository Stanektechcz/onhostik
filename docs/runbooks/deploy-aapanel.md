# Deployment on the aaPanel host (onhost.cz)

The control plane runs as a normal Laravel site under aaPanel's nginx + PHP-FPM 8.3, with PostgreSQL and Redis from the
aaPanel App Store, the queue workers and the scheduler as systemd units, and the websocket console relay as a Node
service. `infra/aapanel/install.sh` does the first installation, `infra/aapanel/deploy.sh` every later release.

## 1. aaPanel preparation (once, in the aaPanel UI)

| Step | Where | Value |
| --- | --- | --- |
| PHP 8.3 with extensions `pdo_pgsql`, `intl`, `bcmath`, `mbstring`, `redis`, `fileinfo`, `zip`, `gd`, `opcache`; `disable_functions` must not contain `proc_open`, `exec`, `shell_exec` (pg_dump, Composer) | App Store → PHP 8.3 → Install extensions / Disabled functions | `memory_limit=512M`, `upload_max_filesize=2048M`, `post_max_size=2048M`, `max_execution_time=300` |
| PostgreSQL 16 | App Store → PostgreSQL | database `onhost`, user `onhost`, a strong password (goes into `DB_PASSWORD`) |
| Redis | App Store → Redis | `requirepass` set (`REDIS_PASSWORD`) |
| Website `onhost.cz` (+ `www.onhost.cz`) | Website → Add site | PHP 8.3, root `/www/wwwroot/onhost.cz/public`, SSL (Let's Encrypt) with force HTTPS |
| Node.js 20+ (console relay) | App Store → Node.js version manager | — |
| Firewall | Security | 80/443 open; 8090 (relay) and 5432/6379 **closed** to the internet |
| Cron / supervisor | not needed — systemd units below | — |

## 2. Installation

```bash
curl -fsSL https://raw.githubusercontent.com/Stanektechcz/onhostik/development/infra/aapanel/install.sh -o /root/onhost-install.sh
bash /root/onhost-install.sh                 # clones the repository, writes /etc/onhost/app.env from .env.example, stops
nano /etc/onhost/app.env                     # fill the values from § 4
bash /root/onhost-install.sh                 # composer, key, migrations, seed, systemd units, caches, doctor
```

Then paste `infra/aapanel/nginx-onhost.cz.conf` into Website → onhost.cz → Config (root = `…/public`), reload nginx and
open `https://onhost.cz/up` (200) and `https://onhost.cz/healthz`.

Console relay:

```bash
mkdir -p /opt/onhost-console-relay && cp -r /www/wwwroot/onhost.cz/infra/console-relay/* /opt/onhost-console-relay/
cd /opt/onhost-console-relay && npm ci --omit=dev
useradd -r -s /usr/sbin/nologin onhost-relay; chown -R onhost-relay:onhost-relay /opt/onhost-console-relay
printf 'ONHOST_API=https://onhost.cz\nONHOST_CONSOLE_RELAY_KEY=<the same value as in app.env>\nPORT=8090\n' > /etc/onhost/relay.env; chmod 600 /etc/onhost/relay.env
cp /www/wwwroot/onhost.cz/infra/systemd/onhost-console-relay.service /etc/systemd/system/ && systemctl enable --now onhost-console-relay
```

## 3. Releases

```bash
bash /www/wwwroot/onhost.cz/infra/aapanel/deploy.sh      # backup → pull → composer → migrate → caches → restart workers → doctor
```

Rollback: `git checkout <previous tag>` + the same script with `SKIP_BACKUP=1`; migrations are backward compatible for
one release (docs/runbooks/release-and-rollback.md).

## 4. Údaje k doplnění (co potřebujeme od vás)

Vše se zapisuje do `/etc/onhost/app.env` (šablona `.env.example`) nebo skrytými příkazy — nikdy do chatu.

**A. Server a aplikace (`app.env`)**

| Klíč | Hodnota |
| --- | --- |
| `APP_ENV` | `production` (na onhost.cz), `APP_URL=https://onhost.cz`, `SANCTUM_STATEFUL_DOMAINS=onhost.cz,www.onhost.cz` |
| `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD` | PostgreSQL z aaPanelu |
| `REDIS_HOST/REDIS_PASSWORD` | Redis z aaPanelu |
| `MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS` | SMTP účet (a SPF/DKIM/DMARC pro odesílatele) |
| `ONHOST_STAFF_DIGEST_TO` | e-mail provozu pro denní přehled |
| `ONHOST_CONSOLE_RELAY_KEY`, `ONHOST_CONSOLE_RELAY_URL=wss://onhost.cz/relay` | náhodný řetězec (např. `openssl rand -hex 32`), stejný v `relay.env` |
| `ONHOST_METRICS_TOKEN` | náhodný řetězec pro Prometheus |

**B. Právnická osoba a platby**

| Klíč | Hodnota |
| --- | --- |
| `ONHOST_LEGAL_NAME/ONHOST_ICO/ONHOST_DIC/ONHOST_VAT_ID/ONHOST_STREET/ONHOST_CITY/ONHOST_ZIP` | firma na dokladech |
| `ONHOST_BANK_IBAN/ONHOST_BANK_BIC/ONHOST_BANK_ACCOUNT` | účet na proformách a QR kódech |
| `ONHOST_BANK_FIO_TOKEN` | Fio API token (jen čtení) pro párování převodů |
| `COMGATE_MERCHANT`, `COMGATE_SECRET`, `COMGATE_TEST` | pro testování produkce: **testovací merchant + `COMGATE_TEST=true`**; ostrý provoz: live merchant + `false` |
| `WEDOS_TEST_MODE` | `true` po dobu testování (registrace domén nanečisto), potom `false` |

**C. Klíče integrací (skrytý prompt, nikdy do souboru ani chatu)**

```bash
php artisan onhost:integrations:secret pterodactyl-gamepanel application_key --check   # herní panel (už uloženo, po testech přegenerovat)
php artisan onhost:integrations:secret pterodactyl-gamepanel client_key --check
php artisan onhost:integrations:secret aapanel-managed01 api_key --check              # webhosting na aaPanelu
php artisan onhost:integrations:secret ispconfig-shared01 remote_user                   # + remote_password (ISPConfig)
php artisan onhost:integrations:secret proxmox-cz1 token_id                             # + token_secret (VPS/VDS/databáze)
php artisan onhost:integrations:secret pbs-cz1 token_id                                 # + token_secret (zálohy VPS)
php artisan onhost:integrations:secret powerdns-hidden01 api_key                        # DNS
php artisan onhost:integrations:secret wedos-zone …                                     # WEDOS: login + heslo WAPI, allow-list IP serveru
php artisan onhost:integrations:secret subreg …                                         # Subreg API (dnes odmítá login 500.104)
```

Instance vytvoříte v konzoli *Nastavení systému → Integrace* (URL panelu, region), klíč uložíte příkazem; `--check`
rovnou ověří spojení a prerekvizity.

**D. Tajemství platformy (`onhost:secrets:set`)**

```bash
php artisan onhost:secrets:set db://oncall/pager routing_key        # PagerDuty/Opsgenie (+ ONHOST_ONCALL_PROVIDER v app.env)
php artisan onhost:secrets:set db://integrations/discord token      # Discord bot (+ APPLICATION_ID a PUBLIC_KEY v app.env)
php artisan onhost:secrets:set db://ai/anthropic api_key            # asistent podpory (ONHOST_AI_ENABLED=true)
php artisan onhost:secrets:set db://cdn/cloudflare token            # + account_id; CDN doplněk
php artisan onhost:game:operator-variable STEAM_USER                # + STEAM_PASS — bez nich se DayZ nenabízí
```

**E. Volitelné pro plný provoz**

`TURNSTILE_SITE_KEY/SECRET_KEY` (ochrana registrace), `SENTRY_DSN`, `OTEL_EXPORTER_OTLP_ENDPOINT` + `ONHOST_TRACE_URL`
(Grafana/Tempo), `ONHOST_CLAMAV_HOST` (role `onhost_clamav` nebo clamd na stejném stroji), `AWS_*` bucket pro zálohy
a soubory mimo server (`ONHOST_PLATFORM_BACKUP_DISK=s3`, `ONHOST_FILES_DISK=s3`), `ONHOST_ONCALL_PROVIDER`.

## 5. Test production before the first customer

```bash
php artisan onhost:doctor                       # 0 FAIL; WARN only for what you decided to leave off
php artisan onhost:integrations:health          # every instance up
php artisan onhost:platform:backup && php artisan onhost:platform:backup:verify
php artisan onhost:production:prepare --purge-dev-accounts --legal --cache   # before real customers, not before testing
```

Smoke test as a customer (docs/runbooks/go-live-checklist.md § 5): register → order web hosting by bank transfer →
record the payment (Nastavení → Bankovní platby or Fio sync) → service ACTIVE on aaPanel → invoice PDF → game server
(Minecraft Paper) → console → ticket → cancel. Card payments run against the Comgate test merchant, domains against
WEDOS test mode. Staff sign in with TOTP (`ONHOST_STAFF_MFA_REQUIRED=true`); the first staff account comes from
`DevAccountSeeder` (`admin@onhost.cz`) — change its password and enrol MFA immediately, purge the other dev accounts
before go-live.
