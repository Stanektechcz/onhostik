# Audit připravenosti na produkci (2026-09-14)

Komplexní audit platformy ONhost po bloku §5p: co je hotové, co chybí, co je potřeba doladit a v jakém pořadí, aby
šlo do produkce bez překvapení. Zdroje: `php artisan onhost:doctor` (59 kontrol, 0 FAIL, 30 WARN na dev),
`onhost:integrations:health`, plná testovací sada (Pest 322+ testů, Playwright 5 specifikací), kód, konfigurace,
dokumentace v `docs/` a runbooky. Priority: **P0** blokuje spuštění, **P1** do prvního měsíce provozu, **P2** zlepšení.

## 1. Stav dnes v číslech

| Oblast | Stav |
| --- | --- |
| Testy | Pest 322+ testů / 8 500+ asercí zelené; Playwright 5 specifikací (veřejný checkout, panel zákazníka, game workbench, mail workbench, staff konzole) |
| API | OpenAPI 377 cest / 421 operací (`php artisan onhost:openapi`) |
| Události | 81+ řádků katalogu (`docs/architecture/events-catalog.md`), všechny routované v `NotificationRouter` |
| Automatizace | 60 plánovaných běhů, 63 vlastních příkazů + 8 tříd, 15 pravidel s vypínačem v konzoli (`AutomationLedger::RULES`) |
| Infrastruktura jako kód | 6 Ansible rolí s molecule scénáři, workflow `ansible-roles` (lint + matice), edge šablony Caddy/nginx |
| Kvalita kódu | Pint, Larastan (`composer` skripty `phpstan analyse`), 0 TODO/FIXME v domains/app/providers/platform |
| Živé integrace | aaPanel cz1 healthy (8.0.6, ~1,4 s); herní panel gamepanel.onhost.cz **bez uložených klíčů**; ostatní instance jsou laboratorní (disabled) |

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
   herní panel: uložit klíče → `onhost:game:bootstrap pterodactyl-gamepanel` (uzly, šablony vč. `minecraft-spigot`,
   porty, placement) → první server přes `onhost:game:create` nebo konzoli.
8. **Edge a konzole** — `ONHOST_CONSOLE_RELAY_KEY` + `ONHOST_CONSOLE_RELAY_URL` (relay pro živou konzoli),
   `ONHOST_METRICS_TOKEN`, nasazení edge role (`infra/ansible/edge.yml`) s on-demand TLS pro stavové domény
   zákazníků.

## 3. P1 — první měsíc provozu

**Provoz a spolehlivost**
- Zálohy databáze a `storage/app/private` (soubory důkazů z marketplace, exporty dat) mimo server + test obnovy;
  PBS instance pro zálohy VPS (`pbs-cz1` je lab). Runbook `release-and-rollback.md` doplnit o migrace zpět.
- Externí sondy na třech lokalitách (role `onhost_probe`) + registrace tokenů v Incidenty → sondy; 2-of-3 kvorum
  je připraveno v `SlaService`.
- Alerting mimo platformu: interní notifikace jsou v konzoli a Discordu (`onhost:discord:register-commands`);
  chybí on-call eskalace (pager) pro `platform.queue.stalled`, `integration.down`, `node.bmc.alert`,
  `capacity.forecast.low` — připojit PagerDuty/Opsgenie webhook přes `WebhookDispatcher`.
- Centrální logy a error tracking: `LOG_STACK=single`; přidat Sentry/OpenTelemetry kanál (není v `composer.json`),
  retence auditu a logů podle GDPR (Compliance runbook).
- Kapacita: `capacity.auto_order` nechat vypnuté do první ruční objednávky; nastavit `options.node_order` na
  instanci s dodavatelem (Hetzner token v secret store); `ONHOST_NODE_BOOTSTRAP_SSH_KEY` + callback base.
- BMC inventář: `tags.bmc` na produkčních uzlech (Redfish URL, chassis, secret_ref), práh
  `ONHOST_BMC_TEMP_WARN_C` podle datacentra.

**Bezpečnost**
- Rotace všech tajemství, která prošla chatem nebo laboratoří (Pterodactyl, WEDOS heslo, Subreg).
- CSP (`SecurityHeaders`) rozšířit o relay a CDN origin produkce; `throttle:auth` 10/min a `throttle:probes`
  600/min zkontrolovat proti reálnému provozu; CAPTCHA/turnstile na registraci a veřejném checkoutu (dnes jen
  rate limit + risk model).
- Dependabot/`composer audit` v CI (`tests.yml` spouští Pint a Pest, ne audit); Larastan přidat do CI jako krok.
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
- Šablony e-mailů a notifikací v EN (dnes většina CZ) pro zahraniční zákazníky.

## 4. P2 — zlepšení

- Terminál přímo v konzoli (dnes stránka `/sprava/konzole/{service}` s logem a příkazy; živá konzole = token pro
  relay) — websocket klient v prohlížeči.
- Molecule na reálných dodavatelích (noční běh proti lab instancím; dnes noční `onhost:nodes:check` hlásí regrese
  prerekvizit, role Proxmox/ISPConfig mají stub CLI).
- Přílohy důkazů z asset úložiště (dnes lokální disk, 10 MB, pdf/png/jpg/txt/csv/zip/log) → S3-kompatibilní disk
  s podepsanými odkazy a retencí.
- Jedna tabulka vah rizika je hotová; přidat review přesnosti přes čas (trend) a export do BI.
- Odhad nákladů kampaní do měsíčního reportu financí; A/B test kampaní.
- Fleet: BMC teploty a PSU jsou v řádku; přidat graf a historii ze vzorků (`node_usage_samples`).
- Automatické objednání uzlu podle forecastu má vypínač; doplnit rozpočtový strop na měsíc.

## 5. Kontrolní seznam před dnem D

1. `php artisan onhost:doctor` → 0 FAIL, WARN jen vědomé.
2. `php artisan onhost:integrations:health` → všechny produkční instance up, `onhost:nodes:check` bez varování.
3. `php artisan test --compact` + `npm run e2e` proti stagingu; `vendor/bin/phpstan analyse`.
4. Zálohy ověřené obnovou; runbooky `incident-response.md`, `provider-outage.md`, `release-and-rollback.md`
   projité s provozem.
5. Právní texty schválené, `LegalEntitySeeder` s produkčními hodnotami, DPH pravidla (`TaxRuleSeeder`) zkontrolované.
6. Klíče panelů uložené, rotované, `ONHOST_SECRETS_DRIVER=openbao`; vývojové účty pryč; MFA pro staff.
7. Sondy, on-call webhook a stavová stránka nasazené; první uzly s `tags.bmc`.

## 6. Návrhy dalšího bloku (§5q)

1. **On-call eskalace**: PagerDuty/Opsgenie adaptér za `WebhookDispatcher` s potvrzením a eskalací po X minutách.
2. **Error tracking a trasování**: Sentry + OpenTelemetry pro operace a fronty; korelace s `CommandContext`.
3. **Websocket konzole ve staff stránce**: klient k relay, přehrávání posledních řádků, přenos souborů.
4. **S3 úložiště důkazů a exportů** s podepsanými odkazy a retencí.
5. **Rozpočtový strop kapacity**: měsíční limit objednávek uzlů, schválení nad limit financemi.
6. **Turnstile na registraci a checkoutu** jako další signál rizikového modelu.
7. **EN lokalizace notifikací a e-mailů**: šablony podle jazyka organizace.
