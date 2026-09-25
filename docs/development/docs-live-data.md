# Napojení konzole a logů na reálná data

> **Historický dokument — stav k 14. 9. 2026 (převzato do repozitáře, psáno pro prototyp); aktuální stav: `docs/context/CURRENT_STATE.md`.** Kontrakt napojení konzole a logů psaný pro prototyp. Aktuální platforma: Laravel 13 (13.30.1, modulární monolit `onhost-platform`), PHP 8.3, PostgreSQL 16 v produkci; živý aaPanel běží ve verzi 8.0.6 (ověřeno 2026-09-13, `docs/runbooks/preproduction-audit.md` §4). Živou konzoli dnes nese relay (`docs/runbooks/console-relay.md`).

Panel dnes běží na ukázkových datech. Tenhle dokument je kontrakt, aby napojení
na skutečný provoz bylo jen výměna zdroje, ne přepis obrazovek.

## Jeden formát řádku pro všechno

Konzole herního serveru, sériová konzole VPS, terminál webhostingu, logy
i fronta úloh čtou stejný objekt:

```js
{ t: '08:14:02', src: 'console', m: 'TPS 19,4 · 118 hráčů', k: 'sys', l: 'ok' }
```

| pole | co v něm je |
| --- | --- |
| `t` | čas tak, jak ho má člověk přečíst — `HH:MM:SS` pro dnešek, `26. 8. 18:02` pro starší. Panel čas nepřeformátovává. |
| `src` | krátká značka zdroje v hranatých závorkách: `console`, `systemd`, `rcon`, `deploy`, `postgres`, `onhost`. Do dvanácti znaků. |
| `m` | samotný řádek. Náš text v jazyce uživatele, výstup serveru **verbatim** — logy nepřekládáme. |
| `k` | `sys` (služba), `cmd` (příkaz zákazníka do hry/RCONu), `you` (příkaz v shellu), `join` (připojení), `us` (zásah našeho člověka). |
| `l` | `ok`, `warn`, `err`. |

Řádek s `k: 'us'` musí v `m` nést **jméno inženýra a důvod**, případně tiket.
Adaptér bez nich řádek nepostaví — je to jediné místo, kde kód vynucuje slib
z marketingu.

## Co s tím panel dělá

- Řádky drží v poli **nejnovější první** a renderuje je obráceně, takže konzole
  čte jako konzole (nejnovější dole).
- Buffer je zastropovaný: 40 řádků v konzoli, 60 v shellu. Delší historie patří
  do stahování logu, ne do DOM — jinak stránka po hodině sledování zamrzne.
- `l` a `k` řídí barvu a poznámku pod řádkem. Nic dalšího se z řádku nedovozuje.
- Vymazání výpisu maže **jen zobrazení**. Log na disku zůstává celý.

## Zdroje podle typu služby

| služba | konzole | logy |
| --- | --- | --- |
| herní server | Wings WebSocket (`/api/servers/{uuid}/ws`), event `console output` | soubory serveru + crash reports |
| VPS | sériová konzole hypervizoru (out-of-band, ne přes síť instance) | journald přes `journalctl -o json-seq` |
| webhosting | shell pod uživatelem webu, ne rootem | access, error, php-fpm, pomalé dotazy, deploye |
| fronta úloh | task API hypervizoru / provisioning worker | stejný formát, `src` = jméno operace |

## Použití

```js
import { createLiveSource, fromStaffAction } from './onhost-live-adapter.js';

const src = createLiveSource({
  url: 'wss://wings.prg1.onhost.cz/api/servers/e4c1…/ws',
  token: shortLivedToken,     // vydaný na relaci, ne API klíč účtu
  shape: 'wings',
  cap: 40,
  onLine: (row, lines) => app.setConsole(lines),
  onState: (state, detail) => app.setConsoleState(state, detail)
});

src.send('whitelist add petr', { prompt: '/' });
```

`onState` posílá `open`, `reconnecting` (s prodlevou), `error`, `queued`,
`closed`. Stavovou lištu konzole na to navažte — konzole, která se tiše
odpojí a dál ukazuje starý výpis, lže o stavu serveru.

## Panely: aaPanel, ISPConfig, Proxmox VE, Pterodactyl

Panelová vrstva v `Onhost-app.dc.html` (kategorie **Panely a technologie**) je
jedna obrazovka nad čtyřmi cizími API. `PANEL_API` v adaptéru drží reálné cesty
každého z nich, aby se napojení nehádalo z dokumentace znovu:

| panel | autentizace | konzole | fronta / úlohy |
| --- | --- | --- | --- |
| aaPanel | `request_token = md5(api_key + md5(request_time))` v těle POSTu | shell přes bastion, ne přes panel | `getData&table=sites`, log webu přes `GetFileBody` |
| ISPConfig | `login()` → `session_id` ke každému callu (REST i SOAP) | shell v jailu, bez rootu | `sys_datalog` — dokud úloha neproběhne, změna **není** hotová |
| Proxmox VE | `PVEAPIToken=user@realm!tokenid=secret`; ticket jen pro noVNC | `vncproxy` → `vncwebsocket` | `/nodes/{node}/tasks` + `tasks/{upid}/log` |
| Pterodactyl | `Bearer ptlc_…` (klient), `ptla_…` (jen NOC) | `/servers/{id}/websocket` → token + socket | `schedules`, `backups` |

Mapovače: `fromAaPanel`, `fromIspConfig`, `fromProxmox`, `fromWings`. Všechny
vracejí stejný řádek jako výše, takže noVNC, jail shell i konzole hry vypadají
v panelu identicky.

`createPanelPoller({ shape, fetchRows, onLine, onState })` je pro panely bez
WebSocketu. `fetchRows` dodává **host**, ne adaptér — autentizace nemá co dělat
ve vykreslování. Selhání zvyšuje backoff (max 30 s) a jde do `onState`; poller,
který mlčí, vypadá jako klidný panel.

### Role a audit

Panelová vrstva gatuje akce dvakrát:

- zásah do vlastní služby: admin, NOC, klient, partner (`P_MAY`),
- zásah na uzlu nebo v clusteru: jen admin a NOC (`P_NODE_MAY`).

Každá provedená akce jde na dvě místa: do auditu panelu (`wb.paudit`) a do
provozního logu ve storu (`OnhostStore.audit('panel', …)`). Dvě kopie na dvou
místech se dají porovnat; jedna se dá přepsat. `panelAudit()` odmítne postavit
záznam bez `who` a `what` — stejné pravidlo jako u `fromStaffAction`.

## Co záměrně neděláme

- **Nepřipojujeme se sami.** Adaptér nic nefetchuje, dokud ho někdo nezavolá
  s konkrétní adresou. Panel bez konfigurace běží na seedovaných datech.
- **Neposíláme heslo do relay ani API klíč účtu.** Token je krátkodobý a na
  jednu relaci; když unikne z prohlížeče, vyprší dřív, než ho někdo použije.
- **Nereconnectujeme nekonečně potichu.** Backoff je 1 → 15 s a stav je vidět.
- **Neztišujeme celou úroveň.** Ztišit jde jeden konkrétní řádek; ztišená
  úroveň znamená, že za měsíc nevíte o ničem.
- **Nepřepisujeme `t` na serveru.** Kdo posílá řádek, posílá i čas — jinak se
  po výpadku spojení celá historie sesype do jedné minuty.

## Kudy dál

Ukázkové řádky v panelu (`WB_SEED3`, `WB_SEED4` v `Onhost-app.dc.html`) mají
stejný tvar jako ty produkční, takže se dají nechat jako fallback pro demo
a pro stav „ještě se nepřipojeno“.
