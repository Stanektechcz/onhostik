# F5 — Virtuální server (VPS): objednávka, napájení, snapshoty, reinstalace, záchranný režim, konzole, pozastavení

Ruční test pro vlastníka a QA. Prochází celý život VPS z pohledu zákazníka v klientské zóně (`/panel/sluzby`, detail služby)
a přes API, na kterém klientská zóna stojí. Stejné kroky automaticky projde E2E test
[`VpsFlowTest`](../../tests/Feature/E2E/VpsFlowTest.php) (hypervizor a platební brána jsou v něm náhrada přes `Http::fake`,
vše mezi nimi je skutečný produkt). Ruční test je tu proto, aby člověk viděl to, co test vidět nemůže: texty, dialogy,
pořadí obrazovek a to, že odpověď zákazníkovi nikdy nejmenuje hypervizor.

## Co je potřeba předem

| Co | Poznámka |
| --- | --- |
| Zákazník | Nově zaregistrovaný uživatel s **ověřeným e-mailem** (`POST /v1/auth/register`, pak odkaz z e-mailu, `POST /v1/auth/verify-email`). Bez ověření platí limit objednávky, viz F5-01 negativní varianty. |
| Druhý zákazník | Jiná organizace s vlastním účtem. Slouží ke všem zkouškám „cizí organizace“. |
| Prostředí | Lokální vývoj nebo staging s **náhradami** (laboratorní instance hypervizoru, platební brána v testovacím režimu). Lokální panely ve vývoji míří na živé panely: **do nich se nezapisuje**. |
| Skutečný uzel | Reinstalace, záchranný režim a návrat na snapshot se na skutečném uzlu zkouší **jen na určeném testovacím Proxmox uzlu a jen s výslovným souhlasem vlastníka**. Na produkčním uzlu se tyto kroky neprovádějí nikdy; tam stačí automatický E2E test. |
| Step-up | Reinstalace, návrat na snapshot, smazání snapshotu, záchranný režim a nový přístup jsou vysoké riziko. Klientská zóna sama otevře dialog „Potvrďte heslem“ a požadavek zopakuje; v API je to `POST /v1/auth/step-up` s `method: password` a `code: <heslo>` těsně před akcí. |
| Hlavičky API | Každý zápis má vlastní `Idempotency-Key`; tokenem se volí organizace hlavičkou `X-Organization`. |

Plán v příkladech: **Compute 2** (`vps`, plán `compute-2`): 2 vCPU, 4 GB RAM, 80 GB disk, **3 snapshoty v tarifu**.

---

## F5-01 Objednávka VPS a doručení

**Předpoklady:** přihlášený zákazník s ověřeným e-mailem, prázdný košík. Platební brána v testovacím režimu.

**Kroky (klientská zóna / API)**

1. Veřejný web, košík (/kosik): přidat VPS Compute 2, vložit veřejný SSH klíč, zapnout IPv4. API: `PUT /v1/cart`
   s `items: [{product_key: vps, plan_key: compute-2, config: {ssh_keys: [...], options: {ipv4: true}}}]`.
2. Cena: `POST /v1/cart/quote` → `quote_id`, součet včetně DPH.
3. Objednat kartou: `POST /v1/orders` s `quote_id`, souhlasy a `payment: {mode: gateway, provider: comgate, method: card}`.
   Odpověď 201, `redirect_url` na bránu, objednávka je ve stavu čekání na platbu.
4. **Ještě před zaplacením** zkontrolovat: žádná služba neexistuje (`GET /v1/services` je prázdné), na hypervizoru nic nevzniklo.
5. Zaplatit v bráně (testovací karta). Brána zavolá `POST /v1/webhooks/payments/comgate`. Druhé stejné volání nic nezmění (odpověď `duplicate`).
6. Počkat na frontu (operace běží na pozadí). V klientské zóně `/panel/sluzby` se objeví nový server.

**Očekávaný výsledek**

- Objednávka `PAID`, po doručení `ACTIVE`; služba `ACTIVE`, název hostitele končí `.cust.onhost.cz`.
- Server má přidělenou IPv4, velikost podle plánu (2 jádra, 4096 MB, disk 80 GB), firewall (výchozí zahazování příchozího provozu) a **vlastní číslo hosta, které nikdo nepoužil** (nová služba nikdy nezíská číslo, pod nímž ještě leží cizí záloha).
- Cizí servery stejného clusteru zůstaly nedotčené.
- `GET /v1/services`, `GET /v1/services/{service}`, `GET /v1/services/{service}/features`, `GET /v1/services/{service}/operations` ani `GET /v1/orders/{order}` **nikde nejmenují** hypervizor, jeho uzel ani konzolový ticket.
- Události, každá právě jednou pro organizaci zákazníka: `order.paid`, `service.activated`; po doručení se v outboxu objeví i `order.active`. V outboxu nezůstane nic nepublikovaného.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Objednávka nad 5 000 Kč s **neověřeným e-mailem** | 403, chyba `email_unverified`, v odpovědi cesta pro znovuodeslání `/v1/me/email/verification`. Po ověření projde další požadavek hned. |
| Znovu poslat ověření dřív než za minutu | `POST /v1/me/email/verification` vrátí 429 `email_verification_throttled`; u už ověřeného e-mailu 409 `email_already_verified`. |
| Druhý zákazník čte objednávku nebo službu prvního | 404 (cizí a neexistující id jsou nerozeznatelná). |

**Kde hledat při selhání**

- Zákazník: záložka Operace u služby, `GET /v1/services/{service}/operations`.
- Provoz: administrace /sprava/sluzby, `GET /v1/staff/provisioning/jobs` a `GET /v1/staff/provisioning/jobs/{operation}` (stav, pokusy, zredigovaný log volání panelu). Opakování `POST /v1/staff/provisioning/jobs/{operation}/retry` až po odstranění příčiny.
- Doctor (`php artisan onhost:doctor`): řádky „every product can be provisioned“, „active nodes“, „provider instances registered“, „every order being delivered was paid and documented“.
- Událost, která chybí: tabulka `outbox_messages` (u nepublikovaných `published_at` je prázdné); katalog událostí `docs/architecture/events-catalog.md`.

---

## F5-02 Napájení: start, stop, restart

**Předpoklady:** běžící VPS z F5-01 ve stavu `ACTIVE`.

**Kroky**

1. `/panel/sluzby` → detail serveru → záložka **Konzole a napájení**. Tlačítka: **Zapnout**, **Restartovat**, **Vypnout (ACPI)**, **Vypnout tvrdě**.
2. Stisknout **Vypnout tvrdě**, počkat na dokončení operace, pak **Zapnout**, pak **Restartovat**.
3. API ekvivalent: `POST /v1/services/{service}/power` s `power_action` jednou z `start`, `stop`, `shutdown`, `reboot`, `reset`, `kill`; nebo `POST /v1/services/{service}/actions` s `action: power`.

**Očekávaný výsledek**

- Každý signál je auditovaná operace (HTTP 202 s `operation_id`), která skončí `SUCCEEDED`, a stav serveru se po ní **přečte zpět z hypervizoru**: po stopu `stopped`, po startu a restartu `running`.
- Server je v seznamu a v detailu ve stavu `ACTIVE` po celou dobu.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| `power_action: format-disk` (nebo jakékoli jiné slovo) | 422 `power_action_invalid`, hypervizor se nevolá. |
| Server je pozastavený (F5-07) | 409, hypervizor se nevolá. |
| Cizí organizace pošle `POST /v1/services/{service}/power` | 404, server nezměněn. |
| Panel nebo uzel je v údržbě | 503 `control_plane_maintenance` s důvodem a plánovaným koncem; službu to nezastavilo, jen změny. |
| Rozběhnutá jiná operace na téže službě | 409 `operation_in_progress`. |

**Kde hledat:** záložka Operace; `GET /v1/services/{service}/operations`; administrace /sprava/sluzby; doctor „no service stranded in a transient state“.

---

## F5-03 Snapshoty do limitu tarifu, návrat a smazání (chráněný snapshot před návratem)

**Předpoklady:** VPS Compute 2 (limit 3 snapshoty), zákazník má čerstvé heslo pro step-up.

**Kroky**

1. Záložka **Snapshoty**. Formulář **Nový snapshot**: název `s1`, popis, **Vytvořit**. Totéž pro `s2` a `s3`. Hlavička tabulky ukazuje `3 / 3`.
   API: `POST /v1/services/{service}/snapshot` nebo `POST /v1/services/{service}/actions` s `action: snapshot`, `params: {name, description}`.
2. Zkusit čtvrtý snapshot `s4`.
3. Seznam: `GET /v1/services/{service}/resources/snapshots` — každý řádek má příznak `counts`.
4. **Návrat na snapshot**: u `s2` tlačítko **Vrátit se**. Klientská zóna nejdřív načte náhled
   (`GET /v1/services/{service}/actions/rollback_snapshot/preview?params[name]=s2`), ukáže dialog co se ztratí a jak daleko lze zpět, potvrzení nese otisk cíle (64 znaků).
   Bez čerstvého step-upu se otevře dialog „Potvrďte heslem“.
5. Po dokončení znovu seznam snapshotů.
6. **Smazání vlastního snapshotu** `s1` (akce `snapshot.delete`, vysoké riziko, step-up) a pak znovu **Nový snapshot** `s4`.

**Očekávaný výsledek**

- Čtvrtý snapshot: **422 `feature_limit_reached`**, v odpovědi `limit: 3`, `used: 3`; k hypervizoru se nic neposílá. Panel říká „limit tarifu je vyčerpaný (3); nový snapshot vytvoříte po smazání některého ze stávajících“.
- Návrat bez step-upu: **403 `step_up_required`**, server se nezměnil.
- Potvrzení s cizím otiskem (jiný snapshot, zastaralý náhled): **409 `target_changed`**, nic se nevrátí.
- Návrat se step-upem a správným otiskem: operace `SUCCEEDED`, server po návratu běží.
- **Před návratem vznikne chráněný bezpečnostní snapshot platformy** (druh `pre_rollback`, chráněný, dokončený, patří této operaci) a hypervizor dostane nejdřív vytvoření snapshotu a **až potom** návrat. Tento snapshot v seznamu nese `counts: false`, v hlavičce je `(+1 bezpečnostní)` a **nepočítá se do tří snapshotů tarifu**: čtvrtý vlastní snapshot je i po návratu dál odmítnut, dokud nesmažete vlastní.
- Smazání chráněného bezpečnostního snapshotu: **409 `backup_protected`**.
- Smazání vlastního `s1` projde, a pak je místo pro `s4`.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Snapshot nad limit (viz výše) | 422 `feature_limit_reached`. |
| Návrat/mazání bez step-upu | 403 `step_up_required`. |
| Zastaralý otisk | 409 `target_changed`. |
| Mazání bezpečnostního snapshotu | 409 `backup_protected`. |
| Právní blokace (legal hold) na službě | 423 `legal_hold` — snapshoty se nemažou. |
| Snapshot na službě, která není `ACTIVE`/`DEGRADED` | 409 `service_state_invalid`. |
| Cizí organizace | 404. |
| Uživatel bez práva obnovy (role bez „backup.restore“) | 403, v záložce se akce nenabízí. |

**Kde hledat:** záložka Operace; `GET /v1/services/{service}/backups`; doctor „no service stranded in a transient state“; provozní log `GET /v1/staff/provisioning/jobs/{operation}`.

---

## F5-04 Reinstalace systému (step-up, náhled, potvrzení)

> Na skutečném uzlu **jen na testovacím Proxmox uzlu a se souhlasem vlastníka**. Reinstalace přepíše systémový disk.

**Předpoklady:** VPS `ACTIVE`; u produktu je u uzlu připravená šablona (zlatý obraz) — nabízí se jen systémy, které platforma pro server povoluje (v laboratoři `debian-13`).

**Kroky**

1. Záložka **Konzole a napájení** → **Reinstalovat systém**. Zobrazí se výzva s přesným seznamem povolených systémů; napsat přesně jeden (`debian-13`).
2. Klientská zóna načte náhled: `GET /v1/services/{service}/actions/reinstall/preview?params[image]=debian-13`.
   Dialog „Reinstalovat … na debian-13?“ vypíše co se ztratí (**Systémový disk**), co na tom visí a cestu zpět („bezpečnostní kopie“). Potvrdit **Reinstalovat**.
3. Step-up dialog (heslo). Pak odchází `POST /v1/services/{service}/actions` s `action: reinstall`, `params: {confirm: true, image}` a `confirm: <otisk z náhledu>`.
4. Počkat na operaci. Server naběhne s novým systémem.
5. Nový přístup po reinstalaci: **Nový přístup k serveru** (akce `access.reset`, vysoké riziko, step-up) — veřejný SSH klíč, případně heslo.

**Očekávaný výsledek**

- Pořadí kroků na hypervizoru: **bezpečnostní snapshot → vypnutí → výměna disku → zvětšení na velikost plánu → start**.
- Starý systémový disk je **odpojený, nesmazaný** (zůstává jako nepoužitý disk); nový disk má velikost podle plánu.
- Vznikl chráněný snapshot druhu `pre_reinstall`, dokončený, patří této operaci.
- Služba je po operaci `ACTIVE`.
- Heslo zadané při novém přístupu se **nikdy neobjeví** v odpovědi, v seznamu operací ani v uložených záznamech operací.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Bez step-upu | 403 `step_up_required`, hypervizor se nevolá. |
| Bez `confirm: true` | 422, pole `confirm`. |
| Systém mimo povolený seznam (`windows-11`) | 422 `action_param_invalid`, odpověď nese `allowed` (seznam povolených); hypervizor se nevolá. |
| Cizí otisk potvrzení | 409 `target_changed`. |
| Právní blokace | 423 `legal_hold`. |
| Přerušená reinstalace | Bezpečnostní snapshot a nahrazený disk zůstávají; nic dalšího se neničí; operace `FAILED` a událost `operation.failed`. |
| Cizí organizace | 404. |

**Kde hledat:** záložka Operace; administrace /sprava/sluzby; `GET /v1/staff/provisioning/jobs/{operation}`; událost `operation.failed`.

---

## F5-05 Záchranný režim a jeho samovolné ukončení

**Předpoklady:** VPS `ACTIVE`, zákazník s čerstvým step-upem. Uzel nabízí záchranný obraz (seznam obrazů si určuje uzel, ne zákazník).

**Kroky**

1. Záložka **Konzole a napájení** → **Záchranný režim**. Potvrzení říká, že disky zůstanou nedotčené a režim sám skončí za několik hodin (výchozí okno panel ukazuje v dialogu; nejvýše 72 h).
   API: `POST /v1/services/{service}/actions` s `action: rescue.start`, `params: {image, hours}`.
2. Server restartuje ze záchranného obrazu. Záložka nyní nabízí **Ukončit záchranný režim**; `GET /v1/services/{service}/features` u položky záchranného režimu ukazuje běžící relaci (obraz, předchozí pořadí startu).
3. Buď ukončit ručně (**Ukončit záchranný režim**, akce `rescue.stop`), nebo počkat na konec okna. Samovolné ukončení dělá naplánovaný příkaz `php artisan onhost:services:rescue-expire` (každých 10 minut). Výstup: `put back: 1`.

**Očekávaný výsledek**

- Po spuštění: server běží z CD-obrazu a pořadí startu je „nejdřív obraz“; událost `service.rescue.started`.
- Po konci okna **bez dotazu na zákazníka**: pořadí startu je přesně takové jako před režimem, obraz je odpojen, relace zmizela; událost `service.rescue.ended`.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Bez step-upu | 403 `step_up_required`, nic se nezměnilo. |
| Obraz vymyšlený zákazníkem (např. s `../` v cestě) | Operace skončí `FAILED`, server se nedotkl, žádná relace. |
| Uživatel s rolí bez konzole | Záchranný režim je „service.console“ (shell/root úroveň); roli „jen správa“ se nenabízí. Ukončit smí i správce (`rescue.stop` je správa). |
| Cizí organizace | 404. |

**Kde hledat:** záložka Operace; příkaz `onhost:services:rescue-expire` ručně (výpis „put back“); události v outboxu.

---

## F5-06 Konzole noVNC a API token bez `services:console`

**Předpoklady:** VPS `ACTIVE`; v prostředí nastavený relay konzole (doctor řádek „console relay key“ musí být v pořádku).

**Kroky**

1. Záložka **Konzole a napájení** → **Otevřít konzoli (VNC)**. Otevře se stránka konzole (noVNC), která si sama vyžádá jednorázový token.
   API: `POST /v1/services/{service}/console-token` (nebo `GET`).
2. Odpověď: `kind: novnc`, `token` začíná `con_`, `socket` je `wss://<relay>/ws/<token>`, jednorázové VNC heslo. **Nikdy** v ní není ticket hypervizoru ani `vncwebsocket`.
3. Vytvořit dva API tokeny (`POST /v1/tokens`, vysoké riziko → step-up): čtenář s `services:read` a konzolový s `services:read` + `services:console`.
4. S tokenem čtenáře: `GET /v1/services/{service}` projde; `POST` i `GET` na `/v1/services/{service}/console-token` jsou **403** se zprávou „The API token lacks the services:console scope.“
5. S konzolovým tokenem: 200 a nový token (jiný než dřívější).
6. Kontrola tokenu: `GET /console/check/{token}` (relace uživatele) vrátí `valid: true` jen jeho vlastníkovi; `GET /console/ws/{token}` bez klíče relay vrátí 401.

**Očekávaný výsledek**

- Odmítnutá volání **nedoputují k hypervizoru** (počet vydaných ticketů se nezvýší).
- Token je jednorázový: druhé rozřešení stejného tokenu relayem selže (410 `console_token_expired`).
- Cizí uživatel: `GET /console/check/{token}` vrací `valid: false`.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Token bez `services:console` | 403, zpráva výše. |
| Členu organizace chybí právo konzole | 403; žádný ticket. |
| Cizí organizace | 404; kontrola cizího tokenu `valid: false`. |
| Relay klíč chybí v prostředí | Doctor řádek „console relay key“ červený (viz runbook console-relay). |

**Kde hledat:** doctor „console relay key“; `docs/runbooks/console-relay.md`; události `service.console.closed`, `service.console.relay`.

---

## F5-07 Pozastavení a obnovení (zákazník sám)

**Předpoklady:** VPS `ACTIVE`; zákazník s právem správy služby.

**Kroky**

1. API: `POST /v1/services/{service}/suspend` s `reason`; pak pokus o start `POST /v1/services/{service}/power`; pak `POST /v1/services/{service}/resume`.
2. Zopakovat u serveru, který zákazník **předtím sám vypnul**: vypnout, pozastavit, obnovit.

**Očekávaný výsledek**

- Po pozastavení: služba `SUSPENDED`, server vypnutý, nespouští se při startu hostitele a je zamčený proti spuštění (ochrana). Pokus o `power` vrátí 409 a hypervizor se nevolá.
- Po obnově: služba `ACTIVE`, **běžící server zase běží**, ochrana i start při bootu se vrátily.
- U serveru, který zákazník měl vypnutý: po pozastavení a obnově **zůstává vypnutý** (stav napájení se pamatuje).
- Události `service.suspended` a `service.active` (každá dvakrát při dvou cyklech).
- Číslo hosta na hypervizoru se po celou dobu nemění; cizí servery nedotčeny.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Pozastavení vynucené platformou (neplacení, zneužití, odstoupení) | Zákazník službu **neobnoví**: 409 `service_suspension_held` s `hold` (`payment`, `abuse`, `withdrawal`); obnoví se platbou nebo rozhodnutím podpory. |
| Pozastavení během jiné operace | 409 `operation_in_progress`. |
| Cizí organizace | 404. |

**Kde hledat:** záložka Operace; `GET /v1/services/{service}`; administrace /sprava/sluzby.

---

## F5-08 Vlastní ISO jen tam, kde ho tarif obsahuje (rozhodnutí vlastníka G-R5)

**Předpoklady:** dva běžící servery stejné organizace — jeden na tarifu **bez** vlastního ISO (dnes každý tarif katalogu),
druhý na tarifu, který ho obsahuje (`custom_iso: true`, velikost jednoho obrazu `custom_iso_max_mb`; dnes jen po schválení návrhu
[custom-iso-plans](../proposals/custom-iso-plans.md) a jeho revize, na testovacím prostředí verzí tarifu v administraci).
Instance hypervizoru má nastavené úložiště `custom_iso_storage` a server platformy dostupný clamd (`ONHOST_CLAMAV_HOST`).
Na skutečném uzlu **jen na určeném testovacím Proxmox uzlu a jen s výslovným souhlasem vlastníka**. Testovací obraz:
malé instalační ISO (např. netinst, do limitu tarifu) a pro antivirus soubor EICAR zabalený do ISO.

**Kroky (klientská zóna / API)**

1. Server **bez** vlastního ISO: `/panel/sluzby` → detail → záložka **Disky**: řádek „Vlastní ISO“ říká **„Není v tarifu“**, žádné tlačítko.
   `GET /v1/services/{service}/features` → `custom_iso: {enabled: false, reason: plan}`. `POST /v1/services/{service}/isos` s
   obrazem → **403** `custom_iso_not_in_plan`; `POST /v1/services/{service}/actions` s `action: iso.attach` → 403 stejně.
2. Server **s** vlastním ISO: záložka Disky → **Nahrát ISO** (API: `POST /v1/services/{service}/isos`, multipart `file`).
   Odpověď 201 s obrazem (`name`, `size_bytes`, `sha256`, `scan: clean`); seznam `GET /v1/services/{service}/isos` ukazuje kvótu organizace.
3. **Připojit** (API: `action: iso.attach`, `params: {iso_id, boot_first: true, reboot: false}`). Operace skončí `SUCCEEDED`; obraz se
   nahraje na uzel a stane se CD-ROM serveru, první v pořadí bootování. Restartovat tlačítkem napájení a v konzoli (noVNC)
   ověřit, že server nabootoval z ISO.
4. **Odpojit** (`action: iso.detach`, volitelně `reboot: true`). Server nabootuje ze svého disku, pořadí bootování je přesně jako před připojením.
5. **Smazat** (`action: iso.delete`, `params: {iso_id}`) — vysoké riziko, klientská zóna si vyžádá step-up. Připojený obraz se smaže
   až po odpojení; obraz zmizí ze seznamu, z uzlu i z úložiště platformy.

**Očekávaný výsledek**

- Bez tarifu se vlastní ISO nikde nenabízí a server ho odmítne s důvodem „není v tarifu“; v administraci ho nelze zapnout jedné službě, jen změnou tarifu.
- Nahrání: soubor projde kontrolou ISO 9660, velikostí z tarifu, antivirem a kvótou organizace; uloží se pod jménem platformy, ne pod jménem souboru zákazníka.
- Odpovědi zákazníkovi nejmenují hypervizor, jeho uzel ani úložiště.
- V auditu akce `service.iso.upload`, `service.iso.attach`, `service.iso.detach`, `service.iso.delete`; v outboxu `service.iso.uploaded`,
  `service.iso.attached`, `service.iso.detached`, `service.iso.deleted` (popis v runbooku [custom-iso](../runbooks/custom-iso.md)).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Soubor s EICAR (antivirus ho najde) | 422 `upload_infected`, soubor smazán, bezpečnostní hlášení `files.infected`; v knihovně nic. |
| clamd vypnutý nebo nedostupný | 503 `iso_scan_unavailable` s jasnou zprávou; soubor **není** uložen ani „na později“. |
| clamd bez `AlertExceedsMax yes` (`php artisan onhost:isos:scanner-check` hlásí FAILED) | Každé nahrání 503 `iso_scanner_untrusted`. |
| Tři nahrání jedné organizace najednou | Třetí 429 `iso_upload_in_progress`; probíhající nahrání se počítají do kvóty. |
| Jiný soubor se stejným `Idempotency-Key` | 409 `idempotency_key_reused`. |
| Nahrání nebo připojení u pozastaveného serveru | 409 `service_not_active`. |
| Obraz větší než tarif (nebo než antivirus přečte celý) | 422 `iso_too_large` ještě před kontrolou. |
| Soubor, který není ISO 9660 (např. `.exe` přejmenovaný na `.iso`) | 422 `iso_not_iso9660`. |
| Plná kvóta organizace (počet nebo velikost) | 422 `iso_quota_exceeded`; stejný soubor podruhé kvótu nečerpá (vrátí se týž obraz). |
| Připojit nebo odpojit během záchranného režimu | 409 `iso_rescue_active`; záchranný režim po skončení vrátí připojené ISO zpět. |
| Smazat obraz připojený k jinému serveru | 409 `iso_attached_elsewhere`; nejdřív odpojit tam. |
| Změna tarifu na takový bez vlastního ISO | Nahrát ani připojit nejde (403), **odpojit a smazat ano** (`custom_iso_exit`). |
| Druhý zákazník: seznam, nahrání, připojení cizího `iso_id` na vlastní server | 404 všude; cizí obraz ani server se nezmění. |
| Opakovaný požadavek se stejným `Idempotency-Key` | Stejná odpověď, žádný druhý obraz ani druhá operace. |

**Kde hledat:** záložka Operace (`GET /v1/services/{service}/operations`, druh `service.iso`); seznam `GET /v1/services/{service}/isos`;
runbook [custom-iso](../runbooks/custom-iso.md) (úložiště, clamd, limity, ruční úklid); doctor řádek antiviru.

---

## F5-09 Oznámení k vlastnímu ISO, doplněk „Vlastní ISO“ a úklid (H1)

**Předem:** server s vlastním ISO podle F5-08. Pro doplněk server s tarifem **bez** vlastního ISO (např. Compute 2) a doplněk
`custom-iso` zveřejněný v administraci (v katalogu je jako koncept s navrženou cenou 99 Kč měsíčně; cenu a zveřejnění určuje vlastník,
viz [custom-iso-plans](../proposals/custom-iso-plans.md)). Na skutečném uzlu jen na určeném testovacím uzlu a se souhlasem vlastníka.

**Kroky**

1. Jako člen organizace, který nic nenahrával, otevřít v panelu oznámení (zvoneček) po krocích 2–5 z F5-08 (nahrát, připojit, odpojit, smazat).
2. Organizace s jazykem angličtina: totéž, oznámení musí být anglicky.
3. Doplněk: do košíku server Compute 2 a k němu doplněk „Vlastní ISO do 4 GB“ (`POST /v1/cart/quote`, řádek `custom-iso` s
   `parent_line_id` serveru), objednat a zaplatit. Na záložce Disky se objeví „Nahrát ISO“; `GET /v1/services/{service}/features`
   vrátí `custom_iso` zapnuté.
4. Doplněk zrušit (zrušení služby doplňku v panelu). Na záložce Disky už nahrávání ani připojení nejde, odpojit a smazat ano.
5. Úklid: `php artisan onhost:isos:sweep` (běží každou hodinu).

**Očekávaný výsledek**

- Oznámení (jen v panelu, žádný e-mail): „Vlastní ISO nahráno: …“ (velikost v MB), „Vlastní ISO připojeno k serveru …“ (varování,
  když server z obrazu nabootuje), „Vlastní ISO odpojeno od serveru …“, „Vlastní ISO smazáno: …“. Při smazání připojeného obrazu
  přijde jen oznámení o smazání, ne navíc o odpojení. Popis událostí `service.iso.uploaded`, `service.iso.attached`, `service.iso.detached`
  a `service.iso.deleted` je v katalogu událostí.
- V angličtině žádná česká věta („Custom ISO uploaded: …“ atd.).
- Doplněk změní tarif jen tohoto serveru: `custom_iso` a `custom_iso_max_mb` (4096, nebo víc, pokud tarif prodává víc); kvóta organizace
  se nemění. Zrušení vrátí obě hodnoty, jak byly před nákupem; pokud je mezitím někdo změnil, nechá je obě.
- Sweep vypíše `… orphaned image file(s)`: smaže soubory obrazů, ke kterým už není žádný platný obraz, i zbytky ve složkách pod
  `incoming/` — ale jen starší než `ONHOST_CUSTOM_ISO_STAGING_HOURS`. Soubor obrazu, který organizace má, nikdy.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Doplněk `custom-iso` k webhostingu | V košíku se nenabízí; přímé použití odmítne `addon_parent_mismatch` (jen cloudový server). |
| Doplněk ještě není zveřejněný (koncept) | Nikde se nenabízí; objednávka ho nepřijme. |

**Automaticky:** [`CustomIsoNotificationsTest`](../../tests/Feature/Compute/H1/CustomIsoNotificationsTest.php),
[`CustomIsoAddonTest`](../../tests/Feature/Compute/H1/CustomIsoAddonTest.php), [`CustomIsoSweepTest`](../../tests/Feature/Compute/H1/CustomIsoSweepTest.php).

---

## Pokrytí E2E testem

| Případ | Test v `VpsFlowTest` |
| --- | --- |
| F5-01 | „takes a customer from the cart to a running server …“ |
| F5-02 | „starts, stops and reboots a server, and refuses what is no power action“ |
| F5-03 | „keeps the snapshots the plan sells, and rolls back and deletes only behind a fresh step-up, a preview and a safety copy“ |
| F5-04 | „reinstalls a server only behind a step-up, a preview and a safety snapshot …“ |
| F5-05 | „boots a rescue image only behind a step-up … and puts the server back by itself when the window ends“ |
| F5-06 | „issues a console token to the owner, and to an API token only when it carries the console scope“ |
| F5-07 | „pauses a server on its owner's word and brings it back as it was …“ |
| F5-08 | [`CustomIsoTest`](../../tests/Feature/Compute/CustomIsoTest.php) proti stavovému hypervizoru (`e2ePveClusterWithIsoStorage`): bez tarifu 403, nahrání → připojení → odpojení → smazání, antivirus, limity, cizí organizace, opakování, názvy a cesty, záchranný režim, cesta ven po změně tarifu |
| F5-09 | mimo E2E; viz `CustomIsoNotificationsTest`, `CustomIsoAddonTest`, `CustomIsoSweepTest` (H1) |

Související: [`VmReinstallTest`](../../tests/Feature/Provisioning/VmReinstallTest.php), [`RescueModeTest`](../../tests/Feature/Provisioning/RescueModeTest.php),
runbooky [provisioning-queue](../runbooks/provisioning-queue.md) a [console-relay](../runbooks/console-relay.md).
