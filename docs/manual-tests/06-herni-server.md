# F6 — Herní server: objednávka, napájení, konzole, herní nástroje, zálohy, pozastavení, rychlé založení

Ruční test pro vlastníka a QA. Klientská zóna: `/panel/hry` (detail herního serveru se záložkami Konzole, Startup, Nastavení služby,
Soubory, Zálohy, Databáze, Síť, Spolupracovníci, Provoz). Stejné kroky automaticky projde E2E test
[`GameFlowTest`](../../tests/Feature/E2E/GameFlowTest.php); herní panel (Pterodactyl) a platební brána jsou v něm náhrada,
vše ostatní je skutečný produkt. Ruční test ověřuje texty, dialogy a hlavně to, že zákazník nikdy nevidí jméno herního panelu,
adresu démona ani jeho tokeny.

## Co je potřeba předem

| Co | Poznámka |
| --- | --- |
| Zákazník | Ověřený e-mail (jinak platí limit objednávky, viz F5-01 ve [VPS](05-vps.md)). |
| Druhý zákazník | Jiná organizace, slouží ke zkouškám „cizí organizace“. |
| Personál | Účet s rolí správce her (rychlé založení) a účet první linie podpory (musí být odmítnut). |
| Prostředí | Lokální vývoj nebo staging s náhradou herního panelu. Lokální panely ve vývoji míří na živé panely: **do nich se nezapisuje**. |
| Relay konzole | V prostředí nastavený klíč a adresa relay (runbook [console-relay](../runbooks/console-relay.md)). |

Plán v příkladech: **game-8** (8 GB RAM), šablona `minecraft-paper`, verze 1.21.8, popisek „Liga SMP“, region `cz1`.

---

## F6-01 Objednávka a doručení herního serveru

**Kroky**

1. Veřejný katalog her (`GET /v1/catalog/game`), košík: `PUT /v1/cart` s `product_key: game`, `plan_key: game-8`, `config: {egg: minecraft-paper, version: 1.21.8, label, region: cz1}`.
2. `POST /v1/cart/quote`, pak `POST /v1/orders` kartou (`payment: {mode: gateway, provider: comgate, method: card}`).
3. **Před zaplacením**: žádná služba, žádný server v herním panelu, žádné volání panelu.
4. Zaplatit v testovací bráně; brána volá `POST /v1/webhooks/payments/comgate` (druhé volání vrátí `duplicate`).
5. Počkat na frontu; v `/panel/hry` se objeví server.

**Očekávaný výsledek**

- Objednávka `PAID` → `ACTIVE`; služba `ACTIVE`, rodina `game`, popisek „Liga SMP“.
- V herním panelu vznikne **uživatel této organizace**: nalezen podle vnějšího id (id organizace), založený se syntetickou adresou končící `.invalid`, **nikdy správce**. Panel se nikdy neptá, komu patří e-mailová adresa; cizí účet, který náhodou nese zákazníkův e-mail, se nepoužije.
- Server má alokaci (port), limity podle plánu (paměť 8192 MB) a proměnnou verze hry; stav instalace dokončen.
- Seznam a detail služby i funkce (`GET /v1/services/{service}/features`) ukazují napájení, konzoli, stav hry, startup a zálohy — a **nejmenují** herní panel, démona ani tokeny.
- Události (každá jednou, organizaci zákazníka): `order.paid`, `service.activated`; po doručení se v outboxu objeví i „order.active“.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Neověřený e-mail, objednávka nad 5 000 Kč | 403 `email_unverified` s cestou `/v1/me/email/verification`. |
| Cizí organizace čte objednávku/službu | 404. |
| V panelu už existuje uživatel se zákazníkovým e-mailem (cizí) | Použije se jen uživatel s vnějším id této organizace; cizí účet se nedotkne. |

**Kde hledat při selhání:** záložka Provoz u služby (`GET /v1/services/{service}/operations`); personál: administrace Nastavení → Integrace (herní panel, `GET /v1/staff/game`), `GET /v1/staff/provisioning/jobs/{operation}`; doctor řádky „every product can be provisioned“, „active nodes“; události `game.setup.attention`, `game.template.missing` (hlásí chybějící přípravu panelu nebo šablonu).

---

## F6-02 Napájení

**Kroky**

1. Detail serveru → záložka **Konzole**: tlačítka **Start**, **Restart**, **Stop**, **Kill**, **Obnovit stav**.
2. Stop → Start → Restart. API: `POST /v1/services/{service}/power` s `power_action` z `start`, `stop`, `shutdown`, `reboot`, `reset`, `kill`.

**Očekávaný výsledek:** každý signál je operace (202), která skončí `SUCCEEDED` a **ověří se čtením z herního panelu** (offline po stopu, running po startu a restartu).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| `power_action: explode` | 422 `power_action_invalid`. |
| Pozastavený server | 4xx, panel se nevolá. |
| Cizí organizace | 404 (nebo 403), v odpovědi ani název serveru, ani adresa. |

**Kde hledat:** záložka Provoz; `GET /v1/services/{service}/operations`.

---

## F6-03 Konzole: jednorázový ticket

**Kroky**

1. Záložka **Konzole** → **Připojit živou konzoli**. API: `POST /v1/services/{service}/console-token`.
2. Odpověď: `token` ve tvaru `con_` + 26 znaků, `socket` ve tvaru `wss://<relay>/ws/<token>`; **bez** `url` a `meta` (adresa démona a jeho session token prohlížeč nikdy nedostane).
3. Kontrola: `GET /console/check/{token}` (relace zákazníka) → `valid: true`. Relay rozřeší token voláním `GET /console/ws/{token}` s hlavičkou klíče relay (bez klíče 401).
4. Rozřešit **podruhé**: 410 `console_token_expired`.

**Očekávaný výsledek:** ticket je jednorázový; první rozřešení vrátí adresu démona uvnitř relay (vlastní protokol konzole), druhé už ne.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Rozřešení bez klíče relay | 401. |
| Rozřešení podruhé | 410 `console_token_expired`. |
| Cizí uživatel kontroluje cizí token | `valid: false`. |
| API token bez `services:console` | 403 (stejně jako u VPS, viz F5-06). |

**Kde hledat:** doctor „console relay key“; události `service.console.closed`, `service.console.relay`; runbook console-relay.

---

## F6-04 Herní nástroje: proměnné, název, příkaz, startup

**Kroky**

1. Záložka **Startup**: načte se `GET /v1/services/{service}/resources/startup` (proměnné včetně `MINECRAFT_VERSION`, `MOTD`). Změnit `MOTD` (`POST /v1/services/{service}/actions`, akce `variable.set`).
2. Záložka **Nastavení služby** → **Přejmenovat** (akce `rename`), nový název „Liga SMP 2“.
3. Záložka **Konzole** → příkaz `say ahoj` (akce `command.send`).
4. Stav: `GET /v1/services/{service}/resources/status` (běží, limit paměti).
5. Reinstalace herního serveru (není v E2E): **Nastavení služby** → **Reinstalovat**; náhled, step-up, potvrzení s otiskem. Přepíše soubory serveru; před ní vznikne bezpečnostní kopie.

**Očekávaný výsledek:** každá změna je jedna operace `SUCCEEDED` a odpovídající hodnota je vidět v herním panelu (MOTD, název, příkaz doručen). Popisek služby se po přejmenování změní.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Název proměnné s mezerou (`bad key`) | 422 `action_param_invalid`. |
| Neznámá akce (`format.disk`) | 422. |
| Reinstalace bez step-upu | 403 `step_up_required`. |
| Zastaralý otisk reinstalace | 409 `target_changed`. |
| Role bez práva správy | 403; záložka funkci nenabízí nebo vysvětlí důvod. |

---

## F6-05 Zálohy a stažení jen z vlastního uzlu

**Kroky**

1. Záložka **Zálohy** → vytvořit zálohu (akce `backup`, název `e2e`). Seznam `GET /v1/services/{service}/backups`.
2. Stáhnout: `GET /v1/services/{service}/backups/{backup}/download` (v prohlížeči s odkazem z panelu).
3. Zkouška bezpečnosti (jen na náhradě panelu, nikdy na živém): panel „prozradí“ adresu, která není jeho démon (např. `intranet.internal`).

**Očekávaný výsledek**

- Záloha je `completed`, má vzdálené id, v seznamu je právě ona; seznam nejmenuje herní panel.
- Stažení je přesměrování na podepsaný odkaz **démona tohoto panelu** (uzel ze seznamu uzlů panelu).
- U cizí adresy se **nic nevydá**: odpověď není přesměrování a stav je 4xx; hostitel z podvržené adresy se v odpovědi neobjeví.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Adresa mimo uzly panelu | 4xx, bez přesměrování. |
| Cizí organizace | 403 nebo 404, bez úniku názvu. |
| Smazání zálohy (`gbackup.delete`) bez práva herního operátora | 403. |

**Kde hledat:** záložka Provoz; `GET /v1/staff/game` (uzly panelu); egress ochrana je popsána v runbooku [security-boundaries](../runbooks/security-boundaries.md).

---

## F6-06 Pozastavení a obnovení

**Kroky:** `POST /v1/services/{service}/suspend` s `reason`; pokus o `POST /v1/services/{service}/power` (start); pak `POST /v1/services/{service}/resume`; pak start.

**Očekávaný výsledek**

- Po pozastavení: služba `SUSPENDED`, v herním panelu `suspended: true`, server vypnutý. Detail ukazuje `SUSPENDED`.
- Power na pozastavený server je odmítnut platformou dřív, než se zeptá panelu.
- Po obnově: služba `ACTIVE`, `suspended: false`, start funguje.
- Události `service.suspended`, `service.active`.

**Negativní varianty:** pozastavení vynucené platformou (neplacení, zneužití, odstoupení) zákazník neobnoví (409 `service_suspension_held`); cizí organizace 404.

---

## F6-07 Cizí organizace

**Kroky:** přihlásit se jako druhý zákazník a zkusit všechna čtení (`GET /v1/services/{service}` a `.../features`, `.../resources/status`, `.../resources/startup`, `.../backups`, `.../operations`, `.../usage`, `.../spec`), zápisy (`power`, `suspend`, `resume`, `terminate`, `console-token`, `actions` s `command.send`) a stažení zálohy.

**Očekávaný výsledek:** vše 403 nebo 404; v odpovědi není jméno serveru, popisek, adresa ani jméno herního panelu; `GET /v1/services` je prázdné; **herní panel nedostal žádné volání**, nevznikla žádná čekající operace, server zůstal `ACTIVE`; ticket konzole vlastníka u cizího uživatele dává `valid: false`.

---

## F6-08 Rychlé založení personálem (bez objednávky)

**Předpoklady:** zákazník z F6-01 s jedním serverem; personál s rolí správce her.

**Kroky**

1. Administrace: Nastavení → Integrace → instance herního panelu → **Založit herní server**. Zadat organizaci (id nebo slug), tarif `game-8`, šablonu, verzi, název. API: `POST /v1/staff/customers/{organization}/services` s `product_key: game`.
2. Počkat na operaci.
3. Totéž zkusit účtem první linie podpory.

**Očekávaný výsledek**

- Správce her: 201, nový server `ACTIVE`, patří téže organizaci; **v herním panelu stále jeden uživatel organizace**, dva servery na dvou různých portech.
- První linie: 403, zpráva jmenuje chybějící právo „staff.service.manage“ (odmítnuto pro právo, ne jako „neznámé“); nový server nevznikne.

**Kde hledat:** audit v administraci (`/v1/staff/provisioning/jobs`), doctor „no service stranded in a transient state“.

---

## Pokrytí E2E testem

| Případ | Test v `GameFlowTest` |
| --- | --- |
| F6-01 | „takes a customer from the order form to an ACTIVE game server on a panel user of their own organization“ |
| F6-02, F6-03, F6-04, F6-05 | „runs the server: power, the console, game tools, a backup and its download from a daemon of the panel only“ |
| F6-06 | „suspends and resumes the server and leaves the panel and the platform saying the same“ |
| F6-07 | „keeps another organization away from the server: not found, no console, no panel call“ |
| F6-08 | „lets staff open a game server for the same customer without an order, on the same panel user“ |

Související: [`GameToolsFeatureTest`](../../tests/Feature/Provisioning/GameToolsFeatureTest.php), [`GameQuickCreateTest`](../../tests/Feature/Provisioning/GameQuickCreateTest.php).
