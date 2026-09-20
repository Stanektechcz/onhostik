# Audit připravenosti na produkci (2026-09-14)

Komplexní audit platformy ONhost po bloku §5p: co je hotové, co chybí, co je potřeba doladit a v jakém pořadí, aby
šlo do produkce bez překvapení. Zdroje: `php artisan onhost:doctor` (59 kontrol, 0 FAIL, 30 WARN na dev),
`onhost:integrations:health`, plná testovací sada (Pest 355 testů na SQLite i PostgreSQL 16 v CI, Playwright 5 specifikací), kód, konfigurace,
dokumentace v `docs/` a runbooky. Priority: **P0** blokuje spuštění, **P1** do prvního měsíce provozu, **P2** zlepšení.

## 1. Stav dnes v číslech

| Oblast | Stav |
| --- | --- |
| Testy | Pest 355 testů / 9 350 asercí zelené — v CI na SQLite **i PostgreSQL 16** (produkční databáze); Playwright 5 specifikací (veřejný checkout, panel zákazníka, game workbench, mail workbench, staff konzole); Larastan level 5 s baseline; `composer audit` bez nálezů |
| API | OpenAPI 397 cest / 443 operací (`php artisan onhost:openapi`) |
| Události | 81+ řádků katalogu (`docs/architecture/events-catalog.md`), všechny routované v `NotificationRouter` |
| Automatizace | 60 plánovaných běhů, 63 vlastních příkazů + 8 tříd, 15 pravidel s vypínačem v konzoli (`AutomationLedger::RULES`) |
| Infrastruktura jako kód | 7 Ansible rolí s molecule scénáři (vč. `onhost_clamav`), workflow `ansible-roles` (lint + matice), edge šablony Caddy/nginx; dev stack `infra/docker-compose.yml` s clamd, OTel collector, Tempo, Grafana (dashboard *ONhost · provoz*) a Alertmanager |
| Kvalita kódu | Pint, Larastan level 5 (`phpstan.neon` + baseline typového dluhu, běží v CI), `composer audit` v CI, 0 TODO/FIXME v domains/app/providers/platform |
| Živé integrace | aaPanel cz1 healthy (8.0.6, ~1,4 s); herní panel gamepanel.onhost.cz **bez uložených klíčů**; ostatní instance jsou laboratorní (disabled) |

## 1b. Co přibylo při přípravě produkce (2026-09-14, po §5u)

- **Zálohy platformy**: `onhost:platform:backup` (pg_dump / mysqldump / SQLite + tar.gz soukromých souborů, manifest se
  SHA-256, retence) denně 02:15, `onhost:platform:backup:verify` 03:15; doctor a metrika
  `onhost_platform_backup_verified_timestamp`, alert `OnhostBackupStale`.
- **Příprava produkce**: `onhost:production:prepare --purge-dev-accounts --legal --cache`; právnická osoba se čte z
  `config('onhost.legal_entity')` (po `config:cache` `env()` nefunguje — opraveno i ve VIES klientovi a doctoru);
  `.env.example` je připravený jako staging/produkční šablona se všemi klíči (prázdné hodnoty = doplní provozovatel,
  `production:` poznámky = přepínače pro produkci); `onhost:secrets:set` ukládá tajemství mimo provider instance.
- **PostgreSQL**: CI job `pest-postgres` odhalil a opravil: `nodes.remote_id` integer (Proxmox posílá `prg2-n1`),
  `rated_usage.charged_transaction_id` 40 znaků, `substr()` nad timestampem v reportu tržeb, a hlavně devět míst
  „insert + catch unique“, která na PostgreSQL zrušila celou probíhající transakci (věrnostní body při zaplacené
  objednávce, čísla tiketů/incidentů, webhook plateb, sondy SLA) — teď běží v savepointu.
- **Audit**: parametry akcí služeb už nikdy nenesou privátní klíč certifikátu ani obsah souborů (test dosud
  procházel jen kvůli tomu, že SQLite bere `"payload"` jako řetězec).
- **Monitoring**: metriky `onhost_automation_alive`, `onhost_virus_scanner_up`, `onhost_oncall_*`; alerty
  `OnhostAutomationDead`, `OnhostVirusScannerDown`, `OnhostOnCallUnacknowledged`, `OnhostNobodyOnCall`; Alertmanager
  posílá pravidla do `POST /v1/webhooks/alertmanager` → on-call alert s eskalací a rotou; Grafana dashboard.
- **CI**: Pest na SQLite + PostgreSQL, Larastan, `composer audit`, e2e a Ansible role běží i pro větev `development`.

## 2. P0 — blokátory spuštění

1. **Prostředí a runtime** — `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` s https; přechod z SQLite na
   PostgreSQL (`DB_CONNECTION`), Redis pro cache/session/queue (`CACHE_STORE`, `SESSION_DRIVER`,
   `QUEUE_CONNECTION` jsou dnes `database`), `php artisan schedule:run` v cronu každou minutu a `queue:work`
   pod systemd (doctor hlásí „no tick / no heartbeat“). Fronty: `default, mails, provider-*`; `onhost:queue:scale`
   pro škálování workerů.
2. **Tajemství** — `ONHOST_SECRETS_DRIVER=openbao` (nebo `db`), nikdy `env`. Uložit klíče všech produkčních instancí
   přes `php artisan onhost:integrations:secret <instance> <klíč> --check`; **klíče herního panelu, které se objevily v
   chatu, po uložení v panelu regenerovat**. Klíče, které dnes chybí: `pterodactyl-gamepanel` (`application_key`,
   `client_key`), a všechny produkční instance Proxmox/PBS/ISPConfig/PowerDNS/RKE2 (dnes jen lab `env://`).
3. **Identita a přístup** — `ONHOST_STAFF_MFA_REQUIRED=true`, smazat vývojové účty (`DevAccountSeeder`, doctor: 6
   účtů), platform_owner jen jako break-glass, JIT přístupy (`iam.jit.request`) zapnuté, revize rolí v
   `RoleCatalog` (nové oprávnění `capacity.manage`).
4. **Platby a doklady** — Comgate merchant + live mód + `COMGATE_RECURRING` pro uložené karty; bankovní účet
   (`ONHOST_BANK_IBAN` / `ONHOST_BANK_ACCOUNT`) na proformách; `ONHOST_BANK_FIO_TOKEN` pro import výpisů;
   `LegalEntitySeeder` s reálným IČO/DIČ/IBAN (dnes placeholder); revize `resources/legal/*.md` právníkem a
   bump verzí; e-fakturace (`EInvoiceProvider`) podle rozhodnutí o povinnosti.
5. **Doručování pošty** — `MAIL_MAILER` mimo `log`, odesílatel místo `hello@example.com`, SPF/DKIM/DMARC,
   `ONHOST_STAFF_DIGEST_TO` pro provozní přehledy; kontrola šablon (`resources/views/mail`) na reálném klientu.
6. **Registrátoři** — `WEDOS_TEST_MODE=false`, allow-list produkční IPv4 u WEDOS; Subreg API přístup (login
   `onhost_api` dnes odmítán — `500.104`); `onhost:registrar:costs` + aktualizace ceníků (10 TLD s cenou, subreg
   ceny zastaralé), pinování TLD, worker konzumuje `provider-registrar`.
7. **Provisioning** — placement pro každý produkt (doctor: `vps, vds, database, ipv4, backup-plus` bez instance);
   herní panel: klíče uložené, bootstrap hotový (5 šablon, placement); limity uzlu se nastavují z konzole přes
   Application API (*Herní uzly → Limity uzlu / Změřit RAM*), paměť uzlu ONHOST-GAME-TEST-01 nastavena na 14 964 MB,
   disk uzlu 200 000 MB — **první Spigot 1.21.8 běží** (`srv_01m2era5hx2abepxmy79v1rfp5`, 45.67.217.22:6665,
   sestavený BuildTools skriptem, který platforma zapsala přes API; start, příkazy i log z konzole ONhost).
   Katalog prodává 22 šablon (nest Minecraft + Onhost Gamehosting), všechny namapované přes API; povinné proměnné
   eggů hlídá quote (§5s) — **DayZ se nenabízí, dokud se neuloží Steam účet** (konzole *Šablony her → Proměnné
   provozovatele* nebo `onhost:game:operator-variable STEAM_USER` a `STEAM_PASS`), CS2 si token GSLT vyžádá od zákazníka při objednávce.
8. **Edge a konzole** — `ONHOST_CONSOLE_RELAY_KEY` + `ONHOST_CONSOLE_RELAY_URL` (relay pro živou konzoli),
   `ONHOST_METRICS_TOKEN`, `ONHOST_CLAMAV_HOST` (antivir nahrávek, §5r-4; role `onhost_clamav`, doctor hlídá stáří signatur), `ONHOST_TRACE_URL`, nasazení edge role (`infra/ansible/edge.yml`) s on-demand TLS pro stavové domény
   zákazníků.

## 3. P1 — první měsíc provozu

**Provoz a spolehlivost**
- Zálohy databáze a `storage/app/private` — **hotovo**: `onhost:platform:backup` (pg_dump custom / mysqldump / SQLite,
  tar.gz soukromých souborů, manifest se SHA-256, retence) a `onhost:platform:backup:verify` (kontrolní součty +
  `pg_restore --list` / integrity check); zbývá S3 bucket mimo server a cvičná obnova na stagingu. PBS instance pro
  zálohy VPS (`pbs-cz1` je lab).
- Externí sondy na třech lokalitách (role `onhost_probe`) + registrace tokenů v Incidenty → sondy; 2-of-3 kvorum
  je připraveno v `SlaService`.
- Alerting mimo platformu — **hotovo v §5q-1**: on-call eskalace (`ONHOST_ONCALL_PROVIDER` pagerduty | opsgenie |
  webhook, klíč v secret store, `ONHOST_ONCALL_INBOUND_SECRET` pro zpětný webhook pageru); zbývá nastavit provider,
  rotaci služeb v pageru a ověřit `POST /v1/staff/oncall/test`.
- Centrální logy a error tracking — **hotovo v §5q-2** bez SDK: `SENTRY_DSN` (redigované obálky s korelačním id),
  `OTEL_EXPORTER_OTLP_ENDPOINT` (spany příkazů, kroků operací a volání providerů); zbývá nasadit collector a Sentry
  projekt, retence auditu a logů podle GDPR (Compliance runbook).
- Kapacita: `capacity.auto_order` nechat vypnuté do první ruční objednávky; nastavit `options.node_order` na
  instanci s dodavatelem (Hetzner token v secret store); `ONHOST_NODE_BOOTSTRAP_SSH_KEY` + callback base.
- BMC inventář: `tags.bmc` na produkčních uzlech (Redfish URL, chassis, secret_ref), práh
  `ONHOST_BMC_TEMP_WARN_C` podle datacentra.

**Bezpečnost**
- Rotace všech tajemství, která prošla chatem nebo laboratoří (Pterodactyl, WEDOS heslo, Subreg).
- CSP (`SecurityHeaders`) rozšířit o relay a CDN origin produkce; `throttle:auth` 10/min a `throttle:probes`
  600/min zkontrolovat proti reálnému provozu; Turnstile na registraci a checkoutu je **hotový v §5q-6** — nastavit
  `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` (bez klíčů vypnuto).
- `composer audit` a Larastan (level 5, `phpstan-baseline.neon` = dnešní typový dluh, nový kód musí projít) běží v CI —
  **hotovo**; zbývá Dependabot a postupné umazávání baseline.
- Penetrační test API (idempotence, step-up, org scope) a prototypových ploch; kontrola, že `demo` mód je v
  produkci vypnutý (`ONHOST_DEMO=false`).

**Finance a obchod**
- Rozhodnout výchozí sazby: provize partnerů (`partners.tiers`), marketplace komise 20 %, chargeback procento,
  věrnostní úrovně a kredity (`loyalty.levels`), regionální ceny; vše je v nastavení/konfiguraci, ale výchozí
  hodnoty jsou z prototypu.
- Dunning žebřík a texty (`billing-dunning.md`), `onhost:billing:runway` na reálných datech, DPH OSS pro EU B2C.
- Pravidla automatického schvalování (`partners.auto_approve`, `ONHOST_PARTNER_AUTO_*`) potvrdit s financemi.

**Zákaznická zkušenost**
- Prototypové části bez dat (seam #17, `docs/ui/data-seams.md`): KPI směny, hardwarový inventář, grafy
  monitoringu, SK lokalizace (UI-03), in-browser Babel (UI-02 → předkompilace pro rychlost načtení).
- Playwright rozšířit o partnerský portál (smluvní podmínky, marketplace), admin kapacitu/věrnost a staff konzoli
  serveru; mobilní průchody.
- Šablony e-mailů v EN existují, in-app notifikace v EN jsou **hotové v §5q-7** (`Lexicon`); zbývá projít
  překlady rodilým mluvčím a doplnit fráze, které test `NotificationLocaleTest` neprochází.

## 4. P2 — zlepšení

- Terminál přímo v konzoli — **hotovo v §5q-3** (websocket klient k relay, přehrání posledních řádků, textové
  soubory do 512 kB); zbývá binární přenos souborů a VNC pro Proxmox.
- Molecule na reálných dodavatelích (noční běh proti lab instancím; dnes noční `onhost:nodes:check` hlásí regrese
  prerekvizit, role Proxmox/ISPConfig mají stub CLI).
- Přílohy důkazů a exporty na S3 — **hotovo v §5q-4** (`ONHOST_FILES_DISK=s3`, podepsané odkazy, `onhost:files:prune`);
  zbývá bucket, IAM klíče v `.env` a antivirový sken nahrávek.
- Jedna tabulka vah rizika je hotová; přidat review přesnosti přes čas (trend) a export do BI.
- Odhad nákladů kampaní do měsíčního reportu financí; A/B test kampaní.
- Fleet: BMC teploty a PSU jsou v řádku; přidat graf a historii ze vzorků (`node_usage_samples`).
- Automatické objednání uzlu má vypínač i měsíční rozpočtový strop — **hotovo v §5q-5**
  (`ONHOST_CAPACITY_BUDGET_MONTHLY_MINOR`, `PUT /v1/staff/capacity/budget`); zbývá strop odsouhlasit s financemi.

## 5. Kontrolní seznam před dnem D

1. `php artisan onhost:doctor` → 0 FAIL, WARN jen vědomé. Předtím `php artisan onhost:production:prepare --purge-dev-accounts --legal --cache`
   (smaže vývojové účty, zapíše právnickou osobu z `ONHOST_LEGAL_*`/`ONHOST_BANK_*`, nacachuje konfiguraci) a
   `php artisan onhost:platform:backup && php artisan onhost:platform:backup:verify` (záloha DB + soukromých souborů
   na `ONHOST_PLATFORM_BACKUP_DISK`, ověření obnovitelnosti; denně 02:15/03:15, doctor hlídá stáří).
2. `php artisan onhost:integrations:health` → všechny produkční instance up, `onhost:nodes:check` bez varování.
3. `php artisan test --compact` + `npm run e2e` proti stagingu; `vendor/bin/phpstan analyse`.
4. Zálohy ověřené obnovou; runbooky `incident-response.md`, `provider-outage.md`, `release-and-rollback.md`
   projité s provozem.
5. Právní texty schválené, `LegalEntitySeeder` s produkčními hodnotami, DPH pravidla (`TaxRuleSeeder`) zkontrolované.
6. Klíče panelů uložené, rotované, `ONHOST_SECRETS_DRIVER=openbao`; vývojové účty pryč; MFA pro staff.
7. Sondy, on-call webhook a stavová stránka nasazené; první uzly s `tags.bmc`.

## 6. Návrhy dalšího bloku (§5q) — realizováno 2026-09-14, viz `preproduction-audit.md` §5q a §5r

1. **On-call eskalace**: PagerDuty/Opsgenie adaptér za `WebhookDispatcher` s potvrzením a eskalací po X minutách.
2. **Error tracking a trasování**: Sentry + OpenTelemetry pro operace a fronty; korelace s `CommandContext`.
3. **Websocket konzole ve staff stránce**: klient k relay, přehrávání posledních řádků, přenos souborů.
4. **S3 úložiště důkazů a exportů** s podepsanými odkazy a retencí.
5. **Rozpočtový strop kapacity**: měsíční limit objednávek uzlů, schválení nad limit financemi.
6. **Turnstile na registraci a checkoutu** jako další signál rizikového modelu.
7. **EN lokalizace notifikací a e-mailů**: šablony podle jazyka organizace.

## 7. Review 2026-09-20 — co revize našla a co z toho zůstává otevřené

Čtyři souběžné revize (registrátoři a DNS, adaptéry čtyř panelů, obslužná strana a účty, cesta objednávka → peníze).
Opravené nálezy jsou v `docs/runbooks/security-boundaries.md` a `docs/runbooks/billing-dunning.md`. Tady je zbytek:
sloupec **Ověřeno** říká, jestli jsem tvrzení sám přečetl v kódu (✔), nebo ho zatím jen hlásí revize (–). Nic z toho
není ověřeno proti skutečným panelům.

### Před spuštěním (P0)

| # | Oblast | Nález | Ověřeno | Návrh |
| --- | --- | --- | --- | --- |
| 1 | Zálohy na vyžádání | **Opraveno 2026-09-20** (`docs/runbooks/backups.md`): záloha webu je vlastní archiv platformy (soubory + každá databáze, mimo uzel), čerstvá, nebo operace selže; panelová záloha (hry, VM) se pozná podle jména z odpovědi, jinak jako položka, která před krokem v seznamu nebyla; obnova na Pterodactylu čeká, až panel přestane hlásit `restoring_backup`; totéž pravidlo platí pro závěrečný archiv VM a herního serveru. Cestou nalezeno a opraveno: oba souborové transporty odmítaly cestu `.` (zabalení celého webu nikdy nefungovalo proti skutečnému uzlu), `backup`/`restore` na mailové službě sahaly na cizí web, volání `sites_web_domain_backup` a `mail_user_backup` posílala id webu místo id zálohy, export databáze na aaPanelu bral a mazal „nejnovější“ dump. Zbývá: ověřit na stagingu (ISPConfig volání podle dokumentace API, ne podle živého panelu) a rozhodnout frekvence záloh v tarifech — každá záloha webu teď jde přes řídicí rovinu | ✔ | Kontroly v `backups.md` → „Staging checks“; velikost záložního disku a `backup_frequency` tarifů |
| 2 | ISPConfig | `awaitStatus` sleduje délku fronty úloh serveru, ne svou úlohu: prázdná fronta = „úspěch" i po chybě, cizí úloha = čekání až do timeoutu | – | Po zápisu ověřit výsledek čtením (`*_get`) místo fronty |
| 3 | Registrace domény | **Zčásti opraveno 2026-09-20:** po ztracené odpovědi musí jméno zůstat registru neznámé `ONHOST_DOMAIN_RECREATE_AFTER_SECONDS` (600 s), než se `domain-create` pošle znovu, a opakovaný příkaz se zapíše jako další pokus (`…:r2`) — dřív spadl na unikátním `clTRID` a operace selhala, i když registr doménu zaregistroval. Zbývá: Subreg objednávky nenesou vlastní identifikátor, ztracená odpověď `Make_Order` je nedohledatelná | ✔ | U Subregu posílat vlastní identifikátor a objednávku dohledat |
| 4 | Limity tarifu | ISPConfig nastaví limity klienta (schránky, databáze, weby, SSH, cron) jen při založení; změna tarifu je nezmění. aaPanel neposílá žádné kvóty. Entitlementy `outbound_mail_per_hour`, `db_connections`, `inodes`, `traffic` se neposílají nikam | – | `resize` má aktualizovat klienta; zbylé entitlementy buď uplatnit, nebo je neprodávat |
| 5 | aaPanel | `resize` volá `SetPHPMaxChildren` bez webu → mění PHP-FPM pool **celého uzlu** | – | Nastavovat pool webu, ne verze PHP |
| 6 | Schvalování | **Opraveno 2026-09-20** (`docs/runbooks/approvals.md`): odmítnutí kritické akce samo otevře žádost, rozhodne ji někdo jiný s právem akci provést a s vlastním druhým ověřením, schválení platí jednou pro přesně ten příkaz; příkaz už nemůže kritické oprávnění obsluhy prohlásit za „vysoké“ (právní blokaci dřív zadával jeden člověk). Provoz jednoho člověka = `ONHOST_FOUR_EYES=false` na serveru, ne v aplikaci. Zbývá rozhodnutí vlastníka: příkazy smějí dál říct, že jedna jejich operace pod HIGH oprávněním druhé ověření nepotřebuje — `CatalogCommand` (koncepty tarifů), `ComplianceCommand` (cyber.*, timer.submit, abuse kromě pozastavení), `DomainCommand` (`contact` = změna držitele, `publish_ds`), `LoyaltyCommand` (vše pod `staff.customer.manage`), `IncidentCommand`, `InvoiceCommand` (zákaznické operace) | ✔ | Projít seznam operací bez druhého ověření; změna držitele domény a úpravy věrnostních bodů by ho mít měly |
| 7 | Role obsluhy | **Opraveno 2026-09-20:** sedm oprávnění nedržela žádná pojmenovaná role (`catalog.manage`, `notification.template.manage`, `feature_flag.manage`, `staff.customer.manage` a tři nouzová) — ceník, šablony, přepínače a věrnost šly obsluhovat jen jako PlatformOwner. Nová role `product_manager` (katalog, šablony, přepínače); věrnost, sandboxový kredit a ceny k `billing_finance_admin` (servisní role na peníze dál nedosáhnou, H348). Bez pojmenované role zůstávají záměrně jen `iam.break_glass`, `provider.secret.view`, `secret.rotate`; hlídá to `PermissionMatrixTest`. Po nasazení přiřadit role skutečným lidem (seeder rolí běží při každém deployi) | ✔ | Rozdat role; PlatformOwner nechat jako nouzový účet |
| 8 | Transfer lock | Jen místní příznak; registr o zámku neví. Po opravě úniku kódu je to aspoň skutečná brána k vydání AUTH-ID | ✔ | Zámek u registrátora, kde ho API nabízí; jinak to tak v UI pojmenovat |
| 9 | Prémiové domény | Subreg vrací cenu a příznak `premium`; hledání i registrace je ignorují → prodej za ceníkovou cenu | – | Prémiové jméno neprodávat (nebo jen za cenu registru + marže) |
| 10 | NSSET | Sdílený NSSET je v tabulce klíčovaný jen handle (unikátní), takže u druhého registrátora se založení přeskočí. U `.cz` to nejspíš nevadí — NSSET je objekt registru a doména u jiného registrátora na něj smí odkazovat; u registrů, kde to neplatí, by registrace selhala po zaplacení | ✔ kód, – registr | Ověřit na stagingu registrací `.cz` přes druhého registrátora; teprve podle výsledku měnit klíč |
| 11 | Pozastavení | Zastaví jen vhost / VM; FTP, cron, databáze, Node projekty běží dál. `resume` spustí i VM, kterou si zákazník sám vypnul | – | Pozastavit i přístupy a plánovače; pamatovat si stav před pozastavením |
| 12 | Jména na uzlu | 6znakový prefix služby je i jméno unixového agenta; kolize (řádově procenta při desítkách tisíc služeb) dá zákazníkovi práva k cizímu webu | – | Delší prefix + kontrola existence při zakládání |
| 13 | Tajemství | **Opraveno 2026-09-20** (`security-boundaries.md` §11): operace po dokončení zapomene hesla a klíče, která nesla (databáze, FTP, schránky, fetchmail, klíč certifikátu); vygenerované heslo správce WordPressu se ukáže 30 minut a jen tomu, kdo službu spravuje — dřív ho četl každý, kdo směl vypsat operace, i čtenář a host; neúspěšný běh si vstup drží 7 dní kvůli opakování; průběžný úklid vyčistí i všechny starší řádky. Po nasazení: migrace `000740`, první běh `onhost:operations:forget-secrets` projde historii po dávkách | ✔ | Po nasazení zkontrolovat, že `operations` bez `secrets_scrubbed_at` ubývají |

### První měsíc (P1)

| # | Oblast | Nález | Ověřeno | Návrh |
| --- | --- | --- | --- | --- |
| 14 | Doklady | Storno zaplacené objednávky (`PAID → CANCELLED`) nevystaví dobropis; vratky nevystavují dobropis vůbec → DPH se neopraví, provize a body se nevrátí | – | Dobropis ke každé vratce; provize a body vázat na dobropis |
| 15 | Vratky | `ChargebackService` počítá z ceníkové ceny bez DPH, ne ze zaplacené částky, a účtuje vratku jako vratný kredit proti „bankovnímu" účtu | – | Počítat ze zaplaceného dokladu; vlastní účet vratek |
| 16 | Spotřebitel | Čtrnáctidenní odstoupení není mechanika (jen souhlas se zřeknutím) | – | Žádost, lhůta, poměrná část, vratka |
| 17 | DPH | Faktura v EUR nenese částku DPH v CZK ani kurz | – | Kurz ČNB ke dni plnění na dokladu |
| 18 | Čas | Doklady a „splatné dnes" se počítají v UTC: objednávka 1. 1. v 00:30 dostane číslo a DUZP minulého roku | ✔ (`config/app.php`) | Účetní den v `Europe/Prague` |
| 19 | Dobíjení | Denní strop automatického dobití se nikdy neuplatní; měsíční limit se nečte | – | Počítat pokusy za den; číst limit |
| 20 | Ceník | `promo_codes.first_period_only` a `prices.promo_periods` se ukládají a nečtou | – | Buď číst, nebo z administrace odstranit |
| 21 | Dunning | Nepovedené pozastavení se neopakuje a případ se zavře jako `TERMINATED`, služba běží dál; `onhost:services:release-stranded` **běží od 2026-09-20 každých deset minut** | – | Opakovat; uzavřít až po potvrzení stavu |
| 22 | Domény | Subreg `listDomains` vrací prázdný stav → reconcile oživí doménu v karanténě jako `ACTIVE`; WEDOS `awaitStatus` hlásí prodloužení hotové hned; doména po expiraci se už neprodlužuje, ani v ochranné lhůtě; smazání domény neexistuje | – | Stav brát z `domainInfo`; prodloužení potvrzovat datem expirace; plánovat i `EXPIRED/GRACE` |
| 23 | DNS | `DnsService::drift()` nikdo nevolá; změny ve WEDOS zóně nejsou atomické; chybí limit počtu záznamů; glue záznamy a změna registranta (`updateContact`) nejsou dostupné | – | Noční kontrola driftu; limit; glue v API |
| 24 | Pterodactyl | Ignoruje `tls_ca` / `verify_tls` instance; `importArchive` obchází `ProviderHttpClient` (dva šestihodinové timeouty) | – | `TlsOptions` jako ostatní adaptéry; import po částech mimo krok |
| 25 | Kompenzace | Kompenzační cesty volají `terminate()` bez pětibodové kontroly identity | – | Stejná brána jako u zrušení |
| 26 | Audit | Čtení citlivých dat obsluhou (přehled zákazníka, fronta e-mailů, integrace) se nezapisuje | – | Auditovat čtení |
| 27 | Košík | Množství > 1 se odmítá (dřív se účtovalo ×N a zřídila jedna služba) | ✔ | Rozpad řádku na N položek při zadání objednávky |
| 28 | Hesla | Změna hesla neruší API tokeny (obnova ano) | ✔ | Rozhodnutí vlastníka: rušit, nebo upozornit |
