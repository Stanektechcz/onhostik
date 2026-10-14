# F2 — Webhosting

Ruční test pro vlastníka a QA: zřízení webhostingu po zaplacení a správa webu zákazníkem v klientském panelu: soubory,
databáze, FTP, SSH klíče, nastavení PHP, SSL, zálohy a limity tarifu.

* Automatický protějšek: `tests/Feature/E2E/SignupToWebTest.php` (od registrace po aktivní web v panelu, viz tabulka na
  konci). Správa webu (soubory, databáze, FTP, SSH, PHP, SSL, zálohy) E2E test neprochází; tyto scénáře jsou jen ruční,
  jejich logiku drží jednotkové testy akcí služby.
* Předchází [F1 registrace a objednávka](01-registrace-objednavka.md), navazuje [F3 doména a DNS](03-domena-dns.md) a
  [F4 e-mail](04-email.md). Přehled: [README.md](README.md).

## Prostředí a pravidla

* Staging (`docs/runbooks/staging-aapanel.md`), ne vývojový stroj (jeho panely míří na ostré servery).
* Tarify: **Start** (1 web, 10 GB, 1 databáze, bez SSH a stagingu, zálohy 7 dní) a **Profi** (10 webů, 50 GB, 20 databází,
  SSH a staging, zálohy 30 dní). Čísla jsou ze seedu katalogu; skutečné hodnoty ověřte v panelu v záložce Kvóty.
* Panel zákazníka je jednostránková aplikace: služby jsou v `/panel/sluzba/web` (Služby › Webhosting), detail webu se otevře
  kliknutím na řádek, záložky jsou seskupené (Web, PHP, SSL, Databáze, Přístupy, Úlohy, Soubory, Zálohy, Statistiky,
  Doplňky). Záložka se zobrazí jen tehdy, když ji tarif nabízí a služba běží.
* Zákaznické akce posílá panel na `POST /v1/services/{service}/actions` (tělo `action` a `params`), vždy s novým klíčem
  Idempotency-Key. Přijatá akce vrací 202 a `operation_id`; hotovou ji vidíte v záložce Provoz a NOC.
* Připravte si druhou organizaci (jiný zákazník) pro negativní varianty „cizí organizace“.

## Scénář F2.1 — Zřízení po zaplacení

**Předpoklady:** zaplacená objednávka tarifu Start z [F1.3](01-registrace-objednavka.md), doména webu např.
`skladomat-test.cz`.

**Kroky**

1. Po platbě otevřete `/panel/sluzba/web`. Služba je nejdřív ve stavu zřizování, do minuty aktivní.
2. Otevřete detail: stav Aktivní, doména webu, přístupové údaje k FTP a databázím (heslo se ukazuje jen jednou).
   Kontrola přes API: `GET /v1/services` a `GET /v1/services/{service}`.
3. Otevřete `GET /v1/services/{service}/features` (nebo záložky v detailu): nabízené funkce odpovídají tarifu, v žádné
   odpovědi ani na obrazovce se neobjevuje název panelu hostingu ani jiného dodavatele.
4. Na staffové straně `/sprava/sluzby` je služba aktivní a má na uzlu jeden web a jednoho klienta.

**Očekávaný výsledek:** služba aktivní, web i klient existují na uzlu právě jednou, certifikát Let's Encrypt je
objednaný (vydá se, jakmile doména míří na uzel), faktura je zaplacená. Události (outbox): `service.created`,
`service.activated`, `order.active`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Před platbou | Po potvrzení objednávky a před platbou otevřít `GET /v1/services` | Prázdné, nic není zřízeno |
| Doména už je na serveru | Objednat doménu, která už na uzlu běží jako cizí web | Zřízení selže s vysvětlením, cizí web se nedotkne (ověřit při prvním běhu) |
| Uzel nedostupný | (staging) vypnout panel uzlu | Operace čeká a zkouší znovu; zákazník vidí „trvá déle než obvykle“, ne chybu |
| Cizí organizace | Přihlásit se jako druhý zákazník a otevřít `GET /v1/services/{service}` s cizím id | 404, jako by služba neexistovala |

**Kde hledat při selhání**

* Staff `/sprava/sluzby`, detail služby, Operace: druh `provision.website`, krok, chyba. Opakování
  `POST /v1/staff/provisioning/jobs/{operation}/retry`. Události (outbox): `operation.failed`, `service.failed`.
* Doctor: `providers` › `every product can be provisioned`, `providers` › `panels report their version`, `providers` ›
  `no panel is held on an unverified version` (nová verze panelu drží nové objednávky), `automation` › `queue worker
  alive`, `lifecycle` › `no service stranded in a transient state`.
* Log: `storage/logs/laravel.log`; volání na panel jsou v tabulce `provider_calls` (bez těl odpovědí dodavatele).

## Scénář F2.2 — Soubory

**Předpoklady:** aktivní web z F2.1.

**Kroky**

1. Detail webu › záložka Soubory (Správce souborů). Vytvořte složku `test`, nahrajte malý soubor `index.html`
   (nahrání jde přes `POST /v1/services/{service}/files/upload`), upravte jej, přejmenujte, zkopírujte, zabalte a rozbalte.
2. Stáhněte soubor zpět (`GET /v1/services/{service}/files/download`) a porovnejte obsah.
3. Otevřete `http://skladomat-test.cz/` (nebo adresu stagingu): vidíte nahranou stránku.
4. Smažte soubor. Smazání dat vyžaduje oprávnění „mazat data“, vlastník ho má.

**Očekávaný výsledek:** každá akce skončí v Provoz a NOC jako úspěšná operace; soubory jsou na webu vidět.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Cesta mimo web | Cesta `../` nebo absolutní mimo kořen webu | Odmítnuto, nic se neprovede (ověřit při prvním běhu) |
| Příliš velký soubor | Nahrát soubor nad limit nahrání | Odmítnuto s vysvětlením (ověřit při prvním běhu) |
| Disk plný | Zaplnit prostor tarifu (viz F2.8) | Nové zápisy odmítnuty, mazání a zvýšení tarifu zůstává možné |
| Cizí organizace | `POST /v1/services/{service}/files/upload` na cizí službu | 404 |

**Kde hledat při selhání:** záložka Provoz a NOC služby (`GET /v1/services/{service}/operations`), staff detail služby,
doctor `lifecycle` › `no service stranded in a transient state`.

## Scénář F2.3 — Databáze a FTP

**Předpoklady:** web na tarifu Start (1 databáze).

**Kroky**

1. Záložka Databáze: založte databázi `test1`. Akce `database.create` vrátí jméno, uživatele a heslo (zobrazí se jednou).
   Seznam: `GET /v1/services/{service}/resources/databases`.
2. Záložka Přístupy (FTP a WebDAV): založte FTP účet (`ftp.create`), přihlaste se klientem FTP k hostiteli webu a nahrajte
   soubor. Změňte heslo (`ftp.password`) a ověřte, že staré už nefunguje. Seznam: `GET /v1/services/{service}/resources/ftp`.
3. Zkuste založit druhou databázi.
4. Smažte první databázi: panel nejdřív ukáže náhled, co se smaže (`GET /v1/services/{service}/actions/{action}/preview`
   pro `database.delete`), a vyžádá potvrzení heslem (step-up). Pak databázi smažte.

**Očekávaný výsledek:** 1 a 2 projdou; hesla nejsou v seznamech ani v záznamu operací (jen se jednou ukážou).
Po smazání lze založit novou.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Limit tarifu | Druhá databáze na Startu | 422 `feature_limit_reached` s číslem `limit` 1 a `used` 1, panel se na uzel neptá |
| Smazání bez step-upu | Smazat databázi bez čerstvého potvrzení hesla | 403 `step_up_required` |
| Špatné jméno | Jméno s mezerou nebo delší než 24 znaků | 422 `action_param_invalid` |
| Cizí databáze | `remote_id` databáze jiného zákazníka na sdíleném uzlu | Operace selže, protože prostředek nepatří službě; cizí data se nedotknou |
| Cizí organizace | Akce na službu druhé organizace | 404 |

**Kde hledat při selhání:** Provoz a NOC služby; staff `/sprava/sluzby` › Operace; doctor `security` › `finished
operations hold no secrets`. Import SQL přepisuje živou databázi; ověřte, že před ním vznikla pojistná kopie databáze.

## Scénář F2.4 — Klíče SSH

**Předpoklady:** tarif **Profi** (SSH je v tarifu Start vypnuté). Veřejný klíč `ed25519`, který máte.

**Kroky**

1. Na Startu: v detailu webu **není** záložka Shell a SSH klíče a `GET /v1/services/{service}/features` má `shell`
   vypnuté. Akce `shell.key` se nenabízí.
2. Na Profi: záložka Přístupy › Shell a SSH klíče. Založte shell uživatele (`shell.create`) a vložte veřejný klíč
   (`shell.key`, parametry `remote_id` a `ssh_key`). Seznam klíčů: `GET /v1/services/{service}/ssh-keys`.
3. Připojte se `ssh uzivatel@host` s privátním klíčem. Uživatel je zavřený ve své složce webu.
4. Klíč odeberte (prázdný klíč v téže akci) a ověřte, že přihlášení přestalo fungovat.

**Očekávaný výsledek:** na Profi funguje přihlášení klíčem do vlastního webu, na Startu funkce není. Přístup k shellu je
vyhrazen oprávnění „konzole“: člen týmu se samotným „spravovat“ jej nedostane.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Start | `POST /v1/services/{service}/actions` s `shell.key` | Funkce není v tarifu, odmítnuto |
| Člen týmu bez oprávnění konzole | Přihlásit se jako člen s rolí spravovat | Záložka není, akce vrací zákaz (403) |
| Neplatný klíč | Vložit text, který není veřejný klíč | Odmítnuto, klíč se neuloží (ověřit při prvním běhu) |
| Cizí organizace | `GET /v1/services/{service}/ssh-keys` na cizí službu | 404 |

**Kde hledat při selhání:** Provoz a NOC; po odebrání člena týmu se jeho klíče na shell účtech odvolávají (události
`access.ssh_keys.revoked`, `security.ssh_key.revocation.stuck`); doctor `access` › `SSH key revocations confirmed by the
panels`.

## Scénář F2.5 — Nastavení PHP

**Předpoklady:** aktivní web. Tarif Start nabízí PHP 8.2, 8.3 a 8.4.

**Kroky**

1. Záložka PHP › PHP a PHP-FPM: přepněte verzi na 8.3 (`php.set`, parametr `version`).
2. Nahrajte `info.php` s `phpinfo();`, otevřete ho a ověřte verzi. Poté soubor smažte.
3. Změňte hodnoty `memory_limit` a `upload_max_filesize` (`php.settings`, parametr `settings`).
4. Ověřte novou hodnotu opět přes `phpinfo()`.

**Očekávaný výsledek:** verze i hodnoty se projeví na webu; operace jsou v Provoz a NOC úspěšné.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Verze, kterou tarif neprodává | Zadat 5.6 nebo verzi mimo nabídku | Odmítnuto, verze se nezmění |
| Nepovolený parametr | Zkusit `disable_functions` | 422 `action_param_invalid` se seznamem editovatelných hodnot |
| Špatný tvar verze | Zadat `8` | 422, verze musí vypadat jako `8.3` |
| Služba pozastavená | Pozastavená služba (viz [F7](07-fakturace-upominky.md)) | Funkce je skrytá s vysvětlením „služba teď neběží“ |

**Kde hledat při selhání:** Provoz a NOC; staff `/sprava/sluzby`; doctor `providers` › `every panel runs a version its
adapter was verified on`.

## Scénář F2.6 — SSL

**Předpoklady:** web, jehož doména míří na uzel. Na stagingu je Let's Encrypt **staging**: prohlížeč jeho certifikát
označí jako nedůvěryhodný, to je v pořádku.

**Kroky**

1. Záložka SSL › SSL certifikáty: stav certifikátu po zřízení (Let's Encrypt, objednaný při zřízení).
2. Pokud ještě není vydán, vyžádejte vydání (`ssl.issue`). Vynucení HTTPS zapněte akcí `https.force`.
3. Otevřete `https://skladomat-test.cz/` a zkontrolujte vydavatele a platnost (staging: nedůvěryhodný vydavatel).
4. Doplňkově: vlastní certifikát (`ssl.upload`) a wildcard (`ssl.wildcard`) na tarifech, které je nabízejí.

**Očekávaný výsledek:** certifikát je vydaný a platný, HTTP přesměrovává na HTTPS po zapnutí.
Události (outbox): `certificate.requested`, `certificate.issued`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Doména míří jinam | Vydat certifikát, dokud DNS míří na cizí server | Vydání selže s vysvětlením, zkoušení se opakuje; `certificate.failed` |
| Cizí jméno | Vydat certifikát pro jméno, které služba nemá | Odmítnuto, jméno nepatří službě |
| Limit CA | Příliš mnoho pokusů za týden | Zákazník dostane čekací dobu, nikoli opakované pokusy |

**Kde hledat při selhání:** události `certificate.failed` a `service.certificate.problem`; staff `/sprava/sluzby` ›
certifikát; zda DNS míří na uzel (`GET /v1/dns/zones/{zone}`), viz [F3](03-domena-dns.md); doctor `providers` › `registrar
available` pro dostupnost domény.

## Scénář F2.7 — Zálohy a obnova

**Předpoklady:** web s několika soubory a jednou databází. Záloha webu je sada, kterou dělá platforma: soubory
**a všechny databáze** najednou, jinak záloha selže (nikdy jen půlka).

**Kroky**

1. Záložka Zálohy a obnova: seznam záloh (`GET /v1/services/{service}/backups`) a plán záloh (frekvence, počet dní,
   počet generací z tarifu). Plán lze změnit (`PUT /v1/services/{service}/backups/schedule`) jen v mezích tarifu.
2. Vytvořte zálohu hned (`POST /v1/services/{service}/backup`). Počkejte na stav hotová.
3. Smažte na webu soubor a změňte řádek v databázi.
4. Obnovte zálohu (`POST /v1/services/{service}/restore`). Panel nejdřív ukáže náhled, co se přepíše, a vyžádá potvrzení
   heslem (step-up).
5. Ověřte, že soubor i řádek jsou zpět. Zálohu si můžete stáhnout (`GET /v1/services/{service}/backups/{backup}/download`).

**Očekávaný výsledek:** záloha je kompletní sada, obnova vrátí soubory i databáze; pojistnou kopii současného stavu před obnovou ověřte při prvním běhu.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Obnova bez step-upu | Spustit obnovu bez čerstvého potvrzení | 403 `step_up_required` |
| Plán nad tarif | Nastavit 90 dní na Startu | Odmítnuto, strop je číslo z tarifu (ověřit při prvním běhu) |
| Smazání zálohy | Akce smazání zálohy (`backup.delete`) | Náhled a step-up jako u obnovy; po smazání přijde `backup.deleted` (outbox) |
| Uzel bez obnovy | Služba na uzlu, jehož panel obnovu nenabízí | Funkce není v seznamu, panel vysvětlí proč |
| Cizí organizace | `GET /v1/services/{service}/backups` na cizí službu | 404 |

**Kde hledat při selhání:** doctor `lifecycle` › `no backup stuck in progress`, `backup schedules keeping up`, `no backup
schedule is waiting for a person`; události `service.backup.schedule.stalled`, `service.backup.schedule.paused`,
`service.restore_test.failed`; staff `/sprava/sluzby` › Operace.

## Scénář F2.8 — Limity tarifu a zvýšení

**Předpoklady:** web na tarifu Start.

**Kroky**

1. Záložka Kvóty (`GET /v1/services/{service}/usage`): využití disku, databází, schránek a webů vůči limitům.
2. Naplňte limit databází (F2.3), schránek (F4) nebo disku. Při vysokém využití přijde upozornění
   (`service.usage.high`).
3. Při 100 % využití diskového prostoru jsou nové zápisy odmítnuté, ale cesty ven zůstávají otevřené: smazání souborů,
   záloha a zvýšení tarifu.
4. Zvyšte tarif na Profi (`GET /v1/services/{service}/plans`, změna přes `POST /v1/services/{service}/resize`;
   zaplatí se rozdíl). Limity se po změně hned zvednou.

**Očekávaný výsledek:** každý limit má jednoznačný text („tarif dovoluje N“) a nabídku zvýšení; po zvýšení projde, co
předtím neprošlo.

**Negativní varianty:** druhý web na Startu (1 web) je odmítnutý jako překročení tarifu (ověřit při prvním běhu); zvýšení tarifu bez platby
nezvýší limity (změna je placená objednávka, viz F1.3); cizí organizace dostane u `usage` i `resize` 404.

**Kde hledat při selhání:** `service.plan_changed` (událost po zaplacené změně); doctor `lifecycle` › `no service
stranded in a transient state`; staff `/sprava/sluzby`, Operace druh změny tarifu.

## Pokrytí E2E testem

`tests/Feature/E2E/SignupToWebTest.php` prochází krok za krokem totéž, co scénáře F1.1–F1.3 a F2.1:

| Krok testu | Tento dokument |
| --- | --- |
| 1–2 registrace a ověření e-mailu | [F1.1](01-registrace-objednavka.md) |
| 3 katalog `GET /v1/catalog/web-hosting` | F1.2 |
| 4 košík a rekapitulace | F1.2 |
| 5 objednávka čeká na platbu, před platbou žádná služba | F1.3, F2.1 negativní „před platbou“ |
| 6 callback Comgate, opakovaný je `duplicate` | F1.3 |
| 7 outbox a saga proti panelu, jeden web a jeden klient, Let's Encrypt `y` | F2.1 kroky 1–4 |
| 8 služba, detail a funkce `databases`, `logs`, `monitoring`, žádný dodavatel v odpovědi | F2.1 kroky 2–3 |
| 9 události `service.activated`, `order.active`, operace bez nedokončené | F2.1 události a „kde hledat“ |

Scénáře F2.2–F2.8 (soubory, databáze, FTP, SSH, PHP, SSL, zálohy, limity) jsou jen ruční.
