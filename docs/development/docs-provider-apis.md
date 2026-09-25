# Onhost — pět cizích API v produkci

> **Historický dokument — stav k 14. 9. 2026 (převzato do repozitáře, psáno pro prototyp); aktuální stav: `docs/context/CURRENT_STATE.md`.** Kontrakt integrací psaný podle dokumentace dodavatelů před stavbou adaptérů. Aktuální platforma: Laravel 13 (13.30.1, modulární monolit `onhost-platform`), PHP 8.3, PostgreSQL 16 v produkci; živý aaPanel běží ve verzi 8.0.6 (ověřeno 2026-09-13, `docs/runbooks/preproduction-audit.md` §4). Platí adaptéry a jejich dokumentace v `docs/provider-adapters/*.md`; cesty aaPanelu jsou připnuté pro 8.x v `providers/AaPanel` a hlídá je `tests/Contract/AaPanelContractTest.php`. Prototypový `onhost-integrations.js` už není zdroj pravdy pro backend.

Kontrakt integrační vrstvy pro Laravel backend. Strojově čitelná verze (cesty, jména jobů,
oprávnění, rozpočty) je v `onhost-integrations.js` — administrace (`Onhost-admin.dc.html#/sluzby`) i tento
dokument z ní vycházejí. Kdo mění endpoint, mění ho tam, ne na dvou místech.

Verze, ke kterým je to psané: WEDOS WAPI (JSON, 2025), aaPanel 7.x, Proxmox VE 8.x,
Pterodactyl 1.11.x (Wings 1.11), ISPConfig 3.2.x. Před prvním sprintem projít změny
v changelogu každého z nich — cesty se u aaPanelu a Pterodactylu mezi majoritními verzemi hýbou.

## 0. Co platí pro všech pět

1. **HTTP 200 neznamená úspěch.** WEDOS, aaPanel i ISPConfig vracejí chybu v těle s kódem 200.
   Každý konektor má vlastní `->isFailed()` a mapuje chybu na typovanou výjimku, nikdy nespoléhá na status.
2. **HTTP 200 neznamená hotovo.** Proxmox vrací UPID, ISPConfig zapisuje do `sys_datalog`,
   Pterodactyl instaluje kontejner, WEDOS potřebuje commit zóny. Aktivace služby patří za potvrzení.
3. **Klíč nikdy nejde do prohlížeče.** Do panelu jde jen krátkodobý token pro konzoli
   (`ONHOST_CONSOLE_TOKEN_TTL`, desítky sekund). Volání do cizích API dělá worker s pevnou výstupní IP.
4. **Každé volání se loguje** do `provider_calls`: konektor, akce, cesta, kód, trvání, korelační
   `job_id`, iniciátor. Těla se ukládají zbavená tajemství (`Str::mask`), retence 90 dní.
5. **Idempotence je na nás, ne na nich.** Žádné z pěti API nezná idempotency key. Řešení:
   před vytvořením objektu se hledá existující podle `resource_mappings.idempotency_key`
   (`ord-9012:provision.vps:v1`); až když nic není, se vytváří.
6. **Fronta per konektor** (`provisioning:wedos`, `provisioning:proxmox`, …) s vlastním limiterem.
   Selhání jednoho panelu nesmí zastavit provisioning na ostatních.
7. **Retry politika**: `tries = 5`, `backoff = [10, 30, 120, 600]` s jitterem, `retryUntil = +6 h`
   pro provisioning a `+30 min` pro sync joby. 4xx z autentizace se **neopakují** — vyvolají incident.

Doporučený HTTP klient: **Saloon v3** (konektor + request na akci) nebo `Http::withOptions()` fasáda.
Saloon dává mockování v testech, per-konektor retry a autentizační plugin, což je přesně
to, co těchto pět API potřebuje.

---

## 1. WEDOS WAPI — domény, DNS, kredit

| | |
| --- | --- |
| Endpoint | `https://api.wedos.com/wapi/json` (JSON) nebo `/wapi/xml` |
| Přenos | `POST application/x-www-form-urlencoded`, jedno pole `request=<urlencoded JSON>` |
| Autentizace | `auth = sha1(login . sha1(wapi_heslo) . date('H'))`, časová zóna **Europe/Prague** |
| Předpoklad | WAPI zapnuté v účtu, **WAPI heslo** (jiné než do účtu), IP workeru v whitelistu |
| Limity | `domain-create` a `domain-transfer`: 100 doménových dotazů / hodinu |
| Push | není — vše poller |

```php
// App\Integrations\Wedos\WedosConnector
$auth = sha1($login . sha1($wapiPassword) . now()->setTimezone('Europe/Prague')->format('H'));

$payload = ['request' => [
    'user'    => $login,
    'auth'    => $auth,
    'command' => 'dns-row-add',
    'clTRID'  => $correlationId,          // do logu na obou stranách
    'test'    => app()->environment('production') ? null : 1,
    'data'    => ['domain' => $zone, 'name' => $host, 'ttl' => 600, 'type' => 'TXT', 'rdata' => $value],
]];
// tělo: 'request=' . urlencode(json_encode($payload))
```

**Kódy**: `response.code === 1000` je OK; vše ostatní je chyba s textem v `response.result`
(např. `2302` doména existuje, `3222` zónu nelze otevřít). Mapovat na `WedosException` s kódem.

**Zóna je dvoufázová.** `dns-row-add` / `dns-row-update` / `dns-row-delete` jen zapíší změnu do
připravené zóny; publikuje ji teprve `dns-domain-commit`. Prototyp to modeluje přesně tak
(klientská sekce `Onhost-app.dc.html?tab=domains` drží dávku a publikuje jedním krokem) a backend to musí zachovat:

- tabulka `dns_changes` (`zone_id`, `op`, `payload`, `who`, `reason`, `state`),
- `Wedos\WriteZoneRow` pro každý řádek,
- `Wedos\CommitZone` na konci dávky, jinak zákazník vidí „hotovo" nad nezměněnou zónou,
- při selhání commitu se dávka nechává v `pending` a vzniká ticket — nikdy se needituje po řádcích naslepo.

**Transfery**: `.cz` a `.eu` jsou synchronní (výsledek přijde hned), ostatní TLD asynchronní
s notifikací — job pro ně čeká a dotazuje `domain-info`.

**Co ještě hlídat**: `credit-info` na plánovači (pod `ONHOST_DOMAIN_CREDIT_MIN` vzniká interní
výstraha — prodloužení bez kreditu selže), `domains-list` jako zdroj pravdy o expiraci
(naše tabulka je jen kopie), `domain-renew` zkoušet 30 / 14 / 3 dny předem.

---

## 2. aaPanel — webhosting

| | |
| --- | --- |
| Endpoint | `https://{node}:8888` (API klíč je **na uzel**, ne na cluster) |
| Přenos | `POST application/x-www-form-urlencoded` na akční cesty |
| Autentizace | `request_time` = unix čas, `request_token = md5(request_time . md5(api_key))` |
| Předpoklad | API zapnuté v panelu, IP workeru v whitelistu, čas workeru z NTP (token má krátké okno) |
| Push | není — poller (stav uzlu 30 s, weby 5 min) |

```php
$time  = time();
$token = md5($time . md5($apiKey));
Http::asForm()->post("{$base}/site?action=AddSite", [
    'request_time' => $time, 'request_token' => $token,
    'webname' => json_encode(['domain' => $domain, 'domainlist' => [], 'count' => 0]),
    'path' => "/www/wwwroot/{$domain}", 'type_id' => 0, 'type' => 'PHP',
    'version' => '83', 'port' => 80, 'ps' => "onhost:{$serviceId}",
]);
```

> Pozor: dřívější poznámka v `onhost-live-adapter.js` měla pořadí v hashi obráceně.
> Platí `md5(čas + md5(klíč))`. Při nesouhlasu tokenu panel odpoví `{"status":false}` bez detailu.

**Odpovědi** jsou nejednotné: někdy `{status:true|false, msg:"…"}`, někdy prostý string, někdy
pole. Mapper musí unést všechny tři a chybu poznat z `status === false`.

**Používáme**: `AddSite`, `DeleteSite`, `AddDatabase`, `SetPHPVersion`, `SetSSL` (Let's Encrypt),
`HttpToHttps`, `getData&table=sites` (inventura), `GetSystemTotal` (zátěž uzlu pro kapacitní report),
`GetFileBody` (čtení logu webu do panelu), `AddCrontab`.

**Nepoužíváme**: shell zákazníka. Ten jde přes bastion pod uživatelem webu, nikdy přes panel
a nikdy pod rootem — tak to slibuje marketing i `docs-live-data.md`.

**Kapacita**: `GetSystemTotal` na každý uzel je vstup pro pool „Webhosting" v reportech
(`Onhost-admin.dc.html#/reporty`). Interval 30 s; víc panel neunese, protože sdílí CPU s weby zákazníků.

---

## 3. Proxmox VE — VPS a dedikované instance

| | |
| --- | --- |
| Endpoint | `https://{node}:8006/api2/json` |
| Autentizace | `Authorization: PVEAPIToken=user@realm!tokenid=secret` |
| Oprávnění | provisioning: `VM.Allocate`, `VM.Config.*`, `Datastore.AllocateSpace`; NOC: `VM.Audit`, `VM.Console` |
| TLS | z instalace self-signed → vlastní CA do trust store, `verify: false` je v produkci zakázané |
| Push | není — poller; `cluster/resources` max 1×/2 s |

**Asynchronnost je pravidlo, ne výjimka.** Klon, config, resize, vzdump i power akce vracejí
UPID string. Hotovo je až `exitstatus: "OK"` z `/nodes/{node}/tasks/{upid}/status`:

```php
$upid = $proxmox->post("/nodes/{$node}/qemu/{$template}/clone", [
    'newid' => $vmid, 'name' => $hostname, 'full' => 1, 'target' => $node,
])->json('data');

// AwaitTask job: release(15) dokud status.exitstatus není OK; po 6 h → fail + incident
```

**vmid** brát z `/cluster/nextid` a **hned rezervovat u nás** (`resource_mappings` s unikátním
indexem), jinak dvě paralelní objednávky sáhnou po stejném čísle. Cloud-init (`ciuser`, `sshkeys`,
`ipconfig0`) se nastavuje přes `PUT /nodes/{node}/qemu/{vmid}/config` před prvním startem.

**Konzole**: `POST /nodes/{node}/qemu/{vmid}/vncproxy` → ticket + port, pak
`GET /nodes/{node}/vncwebsocket`. Ticket vydává backend, platnost desítky sekund, do prohlížeče
jde jen on — nikdy token účtu. Sériová konzole je out-of-band, takže funguje i když instance nejede.

**Zálohy**: `POST /nodes/{node}/vzdump` (mode `snapshot`, storage z konfigurace uzlu), retence
podle tarifu; výsledek je opět UPID a jeho výsledek patří do `provisioning_jobs`.

**Metriky**: `/nodes/{node}/qemu/{vmid}/status/current` (cpu, mem, disk, netin/netout) →
`capacity_snapshots` pro reporty a pool „VPS".

---

## 4. Pterodactyl — herní servery

| | |
| --- | --- |
| Endpoint | `https://panel.onhost.cz` |
| Autentizace | `Bearer ptla_…` pro `/api/application` (interní), `Bearer ptlc_…` pro `/api/client` |
| Obálka | JSON:API — `{ object, attributes }`, relace přes `?include=` |
| Limity | výchozí 240 požadavků / min na klíč; 429 nese `Retry-After` a musí se respektovat |
| Push | **ano** — Wings WebSocket na konzoli, stav a metriky |

**Vytvoření serveru je dvoufázové.** `POST /api/application/servers` vrátí 201, ale server je
použitelný teprve po dokončení instalace (`attributes.container.installed = 1`). Job `AwaitInstall`
se releasuje, dokud to není splněné, a teprve pak se objednávka aktivuje.

**Alokaci vybírá backend**, ne panel: `GET /api/application/nodes/{id}/allocations?filter[assigned]=0`
→ výběr podle lokality a portu z tarifu → `allocation.default` v požadavku na vytvoření.

**Konzole**: `GET /api/client/servers/{id}/websocket` vrací `{ token, socket }`. Token má krátkou
platnost a obnovuje se přes stejný endpoint; do prohlížeče jde jen token, klientský klíč zůstává
na serveru. Formát řádků z Wings se mapuje `fromWings()` z `onhost-live-adapter.js` — konzole
v panelu tak vypadá stejně jako noVNC nebo jail shell.

**Chyby** jsou v poli `errors[]` (`code`, `detail`, `meta.rule`); mapovat `detail`, ne HTTP kód.
Validační 422 se neopakuje, 409 (konflikt instalace) ano.

**Životní cyklus**: `suspend` při neplacení (data zůstávají), `unsuspend` po zaplacení,
`DELETE` až po retenční lhůtě z SLA. Zálohy: `POST /api/client/servers/{id}/backups` + plán
(`schedules`) při zakládání.

---

## 5. ISPConfig 3 — mail, DNS na vlastních NS, korporátní weby

| | |
| --- | --- |
| Endpoint | `https://{node}:8080/remote/json.php?<funkce>` |
| Autentizace | `?login` s remote uživatelem → `session_id` do každého dalšího volání, na konci `?logout` |
| Oprávnění | remote uživatel má práva **po funkcích** — provisioning účet nesmí mít `server_*` |
| Odpověď | `{ code: 'ok'\|'remote_fault', message, response }` — status je vždy 200 |
| Push | není — poller; během provisioningu `sys_datalog` každých 5 s |

**Session cachovat** (Redis, TTL 10 min, jeden zámek na uzel). Zakládat novou session pro každý
job znamená stovky přihlášení denně a zbytečné řádky v logu panelu.

**Zápis není hotový po odpovědi.** Změna leží v `sys_datalog` a aplikuje ji server cron (typicky
do minuty). Job `AwaitDatalog` čeká na zpracování; bez toho zákazník dostane potvrzení
o mailboxu, který ještě neexistuje.

**Používáme**: `client_add` / `client_get` (limity), `sites_web_domain_add` / `_update`,
`sites_database_add`, `mail_domain_add`, `mail_user_add`, `dns_zone_add` / `dns_a_add`,
`server_get_serverid_by_ip` (číslo serveru nikdy nehardkódovat), `sys_datalog_get`.

**Doručitelnost**: po `mail_domain_add` musí do zóny u WEDOSu jít MX, SPF, DKIM (klíč z ISPConfigu)
a DMARC — a pak commit zóny. Workflow `provision.mail` to má jako kroky 5–6; bez nich mail
odchází, ale nedoručuje se.

---

## 6. Mapa: co je push a co poller

| Zdroj | Push | Poller | Interval |
| --- | --- | --- | --- |
| Platební brána | webhook (podepsaný) | — | — |
| Wings (Pterodactyl) | WebSocket konzole a stavy | doplňkový sync | 15 s |
| Proxmox | — | cluster/resources, task log | 2 s / dle UPID |
| aaPanel | — | GetSystemTotal, sites | 30 s / 5 min |
| ISPConfig | — | sys_datalog, kvóty | 5 s během změny, 60 s jinak |
| WEDOS | — | domains-list, credit-info | 1 h / 1 d |

Poller, který mlčí, vypadá jako klidný systém. Proto každý konektor hlásí do
`integration_health` (poslední úspěch, chybovost, p95) a plocha Integrace to ukazuje —
mrtvý konektor musí být vidět dřív, než ho najde zákazník.

## 7. Reconciliace a drift

Plánovaná úloha `integrations:reconcile` (1×/h) srovnává, co má zákazník zaplacené, s tím,
co je v cizím systému: CPU/RAM/disk u Proxmoxu, PHP verze a kvóty u aaPanelu, kvóta mailboxu
u ISPConfigu, suspend stav u Pterodactylu, cílová IP záznamů u WEDOSu.

Rozdíl se **nikdy neopravuje automaticky**. Zapíše se do `resource_drifts`, objeví se v plochách
Integrace a Provozní centrum a čeká na rozhodnutí člověka. Automatická oprava umí smazat data,
která někdo ručně nastavil z dobrého důvodu.

## 8. Testování konektorů

- **Contract testy** proti nahraným odpovědím (Saloon `MockClient` + fixtures z reálného
  volání, zbavené tajemství). Každý konektor má fixture pro úspěch, chybu v těle při HTTP 200,
  429 a timeout.
- **Sandbox**: WEDOS má `test: 1`; Proxmox a Pterodactyl vlastní staging cluster s jedním uzlem;
  aaPanel a ISPConfig lokální kontejner s předinstalovanou instancí.
- **Chaos**: v CI běží scénář, kde každý konektor jednou selže v každém kroku workflow —
  testuje se, že objednávka zůstane v `provisioning`, vznikne ticket a nic se neaktivuje napůl.
- **Role matice**: allowlisty rolí z `onhost-shell.js` se překlápějí na policy testy
  (kdo smí power akci, kdo smí uzel, kdo smí jen svoje).
