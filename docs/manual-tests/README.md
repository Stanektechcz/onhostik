# Ruční testy služeb (F1–F8)

Česky psané postupy pro vlastníka a QA: co v klientském panelu přesně udělat, co má vyjít, co zkusit špatně a kde hledat,
když to nevyjde. Každý dokument odpovídá jedné službě a odkazuje na automatický end-to-end test, který prochází stejné
kroky bez prohlížeče (`tests/Feature/E2E/`). Ruční test ověřuje to, co automat nevidí: vzhled panelu, stránku platební
brány, skutečné doručení pošty a chování vnějších systémů.

## Přehled

| Fáze | Dokument | Služba | Automatický protějšek |
| --- | --- | --- | --- |
| F1 | [01-registrace-objednavka.md](01-registrace-objednavka.md) | Registrace, objednávka, guest checkout, limit 5 000 Kč (R5), platba kartou Comgate | `tests/Feature/E2E/SignupToWebTest.php` |
| F2 | [02-web.md](02-web.md) | Webhosting: zřízení, soubory, databáze, FTP, SSH klíče, PHP, SSL, zálohy, limity | `tests/Feature/E2E/SignupToWebTest.php` (zřízení), zbytek jen ručně |
| F3 | [03-domena-dns.md](03-domena-dns.md) | Doména a DNS: hledání, registrace, převod s AUTH-ID, držitel (step-up), obnova, DNS navrhnout a publikovat | `tests/Feature/E2E/DomainFlowTest.php` |
| F4 | [04-email.md](04-email.md) | E-mail: doména, schránky do limitu, aliasy, heslo, odesílání, MX/SPF/DKIM/DMARC | `tests/Feature/E2E/MailFlowTest.php` |
| F5 | [05-vps.md](05-vps.md) | Virtuální server (VPS) | `tests/Feature/E2E/VpsFlowTest.php` |
| F6 | [06-herni-server.md](06-herni-server.md) | Herní server | `tests/Feature/E2E/GameFlowTest.php` |
| F7 | [07-fakturace-upominky.md](07-fakturace-upominky.md) | Fakturace, upomínky, pozastavení | `tests/Feature/E2E/BillingDunningFlowTest.php` |
| F8 | [08-zruseni-konec-uctu.md](08-zruseni-konec-uctu.md) | Zrušení služby a konec účtu | `tests/Feature/E2E/CancellationFlowTest.php` |

F1 až F4 píše skupina „objednávka, web, doména, e-mail“, F5 až F8 jiný pracovník; soubory F5 až F8 mohou přijít
samostatným slučováním.

## Jak číst a psát dokument

Každý scénář má stejnou stavbu: **Předpoklady**, **Kroky**, **Očekávaný výsledek**, **Negativní varianty** (limit
tarifu, chybějící step-up, cizí organizace → 404, neověřený e-mail) a **Kde hledat při selhání**. Na konci je tabulka
„Pokrytí E2E testem“, která páruje kroky dokumentu s kroky automatického testu.

Pravidla zápisu, která hlídá test `tests/Feature/Docs/ManualTestsTest.php` (generický přes `docs/manual-tests/*.md`):

* Číslovaný dokument (`NN-….md`) uvádí aspoň jeden existující soubor `tests/Feature/E2E/…Test.php`.
* Cesta v zpětných apostrofech, která začíná jedním z kořenů v1, panel, sprava, partner, registrace, prihlaseni, kosik,
  overeni-emailu, obnova-hesla, mailbox, stav, webhosting nebo dokumenty (s lomítkem na začátku, volitelně s metodou před
  ní: `POST /v1/orders`), musí existovat v routeru; proměnné se píší `{id}`. Cesta v klientském panelu musí být oddílem
  panelu (mapa `TAB_SLUG` v `apps/surfaces/Onhost-app.dc.html`: prehled, sluzba/{kategorie}, domeny, posta, fakturace,
  nastaveni a další, například `/panel/fakturace`).
* Název outbox události (tečkovaný, např. `order.paid`) na řádku, který obsahuje slovo „událost“ nebo „události“, musí být v
  `docs/architecture/events-catalog.md`. Jiné tečkované názvy (akce služby jako `mailbox.create`, druhy operací jako
  `domain.register`) se píší na řádky bez toho slova.
* `README.md` vypisuje všech osm dokumentů.

## Společná pravidla pro všechny ruční testy

1. **Zkoušejte na stagingu** (`docs/runbooks/staging-aapanel.md`), nikdy na vývojovém stroji, jehož panely míří na ostré
   servery, a nikdy v produkci. Platby jsou testovací (`COMGATE_TEST=true`), registrátor ve zkušebním režimu.
2. **Žádné skutečné cizí domény a žádné skutečné karty.** Doména, na které provádíte registraci, převod nebo poštu, je vaše.
3. **Dvě organizace.** Pro každou službu zkuste i druhého zákazníka: cizí id musí odpovědět 404, jako by neexistovalo.
4. **Step-up.** Změny s vysokým rizikem chtějí čerstvé potvrzení heslem (`POST /v1/auth/step-up`); bez něj odpoví API
   403 `step_up_required`. Zkuste vždy obě cesty.
5. **Nic se nezřídí před platbou** a **žádná odpověď zákazníkovi nepojmenuje dodavatele** (registrátora, panel hostingu,
   DNS server). Tyto dvě věci kontrolujte v každém scénáři.
6. **Zapište výsledek:** číslo scénáře, čas, e-mail zákazníka, číslo objednávky nebo služby, VERIFIED / NOT TESTED.
   „Ověřit při prvním běhu“ v textu znamená, že chování vychází z kódu, ale nebylo ještě vidět v ruce.

## Kde hledat obecně

| Co | Kde |
| --- | --- |
| Operace služby (krok, chyba, opakování) | staff `/sprava/sluzby` › detail služby › Operace; API `GET /v1/staff/provisioning/jobs/{operation}`, opakování `POST /v1/staff/provisioning/jobs/{operation}/retry`; zákazník: Provoz a NOC (`GET /v1/services/{service}/operations`) |
| Objednávky a platby | staff `/sprava/objednavky`, `/sprava/doklady` |
| Odchozí pošta platformy | staff `/sprava/maily` (Odchozí pošta), `GET /v1/staff/outbox` |
| Audit akcí | staff `/sprava/audit` |
| Stav platformy | `php artisan onhost:doctor`: řádky podle oblasti (`providers`, `payments`, `automation`, `dns`, `lifecycle`, `mail`, `money`) |
| Fronta událostí | `php artisan onhost:outbox:relay`; po změně PHP kódu restart queue workeru |
| Volání na vnější systémy | tabulka `provider_calls` (bez těl odpovědí dodavatelů) |
| Log aplikace | `storage/logs/laravel.log` |
| Katalog událostí | `docs/architecture/events-catalog.md` |
