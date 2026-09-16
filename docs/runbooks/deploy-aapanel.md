# Deployment on the aaPanel host — staging.onhost.cz (and onhost.cz)

The control plane runs as a normal Laravel site under aaPanel's nginx + PHP-FPM 8.3, with PostgreSQL and Redis from the
aaPanel App Store, the queue workers and the scheduler as systemd units, and the websocket console relay as a Node
service. `infra/aapanel/install.sh` does the first installation, `infra/aapanel/deploy.sh` every later release. The
scripts default to `SITE=staging.onhost.cz`; production is the same procedure with `SITE=onhost.cz`.

**Staging = the production test bed.** It runs with the production configuration and real integrations, only the
money and registry switches stay in test mode (`COMGATE_TEST=true`, `WEDOS_TEST_MODE=true`, Let's Encrypt staging
directory). Everything the checklist in § 5 passes on staging is what production will do.

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
curl -fsSL https://raw.githubusercontent.com/Stanektechcz/onhostik/development/infra/aapanel/install.sh -o /root/onhost-install.sh
bash /root/onhost-install.sh                 # clones the repository, writes /etc/onhost/app.env from .env.example, stops
nano /etc/onhost/app.env                     # fill the values from § 4
bash /root/onhost-install.sh                 # composer, key, migrations, seed, systemd units, caches, doctor
```

(`SITE=onhost.cz bash /root/onhost-install.sh` for production; `APP_DIR`, `PHP`, `RUN_USER`, `BRANCH` are overridable
the same way.)

Then paste `infra/aapanel/nginx-site.conf` into Website → staging.onhost.cz → Config (root = `…/public`), reload nginx
and open `https://staging.onhost.cz/up` (200) and `https://staging.onhost.cz/healthz`.

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
bash /www/wwwroot/staging.onhost.cz/infra/aapanel/deploy.sh      # backup → pull → composer → migrate → caches → restart workers → doctor
```

Rollback: `git checkout <previous tag>` + the same script with `SKIP_BACKUP=1`; migrations are backward compatible for
one release (docs/runbooks/release-and-rollback.md).

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
php artisan db:seed --class=DevAccountSeeder --force      # staging only: demo customer, partner and staff accounts
```

Smoke test as a customer (docs/runbooks/go-live-checklist.md § 5): register → order web hosting by bank transfer →
record the payment (Nastavení → Bankovní platby or Fio sync) → service ACTIVE on aaPanel → invoice PDF → card top-up
against the Comgate test merchant → game server (Minecraft Paper) → console, file upload → domain check and order in
WEDOS test mode → ticket → cancel → data export. Staff sign in with TOTP (`ONHOST_STAFF_MFA_REQUIRED=true`): the staff
accounts from `DevAccountSeeder` (`admin@onhost.cz`, `noc@`, `finance@`, `support@`) — change their passwords and enrol
MFA on first sign-in. What passes here is what production does; production differs only in `SITE=onhost.cz`,
`APP_ENV=production`, live Comgate, `WEDOS_TEST_MODE=false`, the production ACME directory, and
`onhost:production:prepare --purge-dev-accounts --legal --cache` before the first customer.

## 5. Ověření produkčního provozu (na stagingu, pak stejně na onhost.cz)

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

### 5a. Životní cyklus zrušení (audit §5ab)

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

