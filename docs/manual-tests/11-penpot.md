# H8 — Penpot pro zákazníky webhostingu: objednávka, karta v panelu, heslo, zálohy, výpadek, zrušení

Ruční test pro vlastníka a QA. Penpot je open-source nástroj pro design a prototypy; ONhost ho zákazníkovi webhostingu
provozuje jako **vlastní instanci** na samostatném uzlu Penpot (Docker Compose, Caddy, HTTPS). Stejné kroky bez prohlížeče
projde E2E test [`PenpotFlowTest`](../../tests/Feature/E2E/PenpotFlowTest.php) (uzel je v něm náhrada přes SSH/SFTP double,
platební brána přes `Http::fake`) a životní cyklus [`PenpotLifecycleTest`](../../tests/Feature/Penpot/PenpotLifecycleTest.php).
Provozní postup a požadavky na server: `docs/runbooks/penpot.md`.

## Co je potřeba předem

| Co | Poznámka |
| --- | --- |
| Uzel Penpot | **Zatím žádný neexistuje.** Ruční test jde spustit až po krocích runbooku (server, Docker, Caddy, registrace instance `penpot` a uzlu s rolí `penpot`). Nikdy ne na sdíleném webovém uzlu a nikdy v produkci bez souhlasu vlastníka. |
| Produkt | Od H-R7 (2026-10-07) je Penpot v prodeji: revize `2026-10-penpot-on-sale` (`php artisan onhost:catalog:revise 2026-10-penpot-on-sale`, pak `--apply`) ho vytvoří s cenou 29 Kč/měs. a zapíše pravidla „v ceně“ pro tarify webhostingu. Objednává se vždy ke službě. |
| Zákazník | Ověřený e-mail; e-mail vlastníka organizace je přihlašovací e-mail do Penpotu. |
| Druhý zákazník | Jiná organizace — zkoušky „cizí organizace“. |
| Step-up | Nastavení hesla k Penpotu je vysoké riziko: panel otevře „Potvrďte heslem“, v API `POST /v1/auth/step-up` těsně před akcí. |

---

## H8-01 Penpot v prodeji, ale bez uzlu se nic neprodá (H-R7)

**Kroky**

1. `php artisan onhost:catalog:revise 2026-10-penpot-on-sale` (náhled) — vypíše vytvoření produktu `penpot` s cenou a zápis pravidel u tarifů; nic nezapíše. `--apply` provede.
2. Bez uzlu Penpot: `GET /v1/services/{webhosting}/penpot-offer` → `orderable: false`, `reason: penpot_unavailable`, `included: true`.
3. Košík s `{product_key: penpot, plan_key: penpot-team, config: {parent_service_id: <webhosting>}}` → `POST /v1/cart/quote` vrátí **409 `penpot_unavailable`**; nic se neobjednalo ani nestrhlo.
4. Košík s Penpotem bez rodiče → **422 `penpot_parent_required`**.
5. `php artisan onhost:doctor` — *Penpot is sold only with a Penpot node to run it* = FAIL (neblokuje), *Penpot has a rule for every web hosting tariff* = OK.
6. Administrace: Nastavení → Integrace → *Penpot k tarifům webhostingu* — u tarifu Start zrušit „V ceně“, cena 1900 haléřů; uložení chce step-up a schválení druhou osobou; po schválení a opakování je Penpot u Startu za 19 Kč.

**Očekávaný výsledek:** Penpot je v katalogu, u webhostingu v ceně, k jiné službě za 29 Kč; bez uzlu ho nikdo nezaplatí.

## H8-02 Objednávka a zřízení

**Kroky**

1. Košík: `PUT /v1/cart` s `items: [{product_key: penpot, plan_key: penpot-team, config: {label: Návrhy, parent_service_id: <služba zákazníka>}}]`, pak
   `POST /v1/cart/quote` a `POST /v1/orders` (karta, testovací režim brány).
2. **Před zaplacením:** žádná služba (`GET /v1/services` prázdné), na uzlu žádný adresář v `/srv/onhost-penpot`.
3. Zaplatit; brána zavolá `POST /v1/webhooks/payments/comgate`.
4. Počkat na operaci `provision.penpot` (záložka Provoz a NOC): uzel, tajné klíče, instance, DNS, účet vlastníka, ověření.

**Očekávaný výsledek:** služba je aktivní, v panelu je v sekci webů (`/panel/sluzba/web`) s kartou Penpot; adresa
`https://<8 znaků>.penpot.onhost.cz` se otevře s platným certifikátem a přihlašovací stránkou Penpotu. Do panelu dorazí
oznámení „Penpot je připraven“ (událost `penpot.instance.ready`).

**Negativní varianty:** uzel bez Dockeru nebo s chybou stahování obrazů → operace selže, služba je ve stavu selhání a na uzlu
nezůstane rozpracovaný adresář ani web v proxy (kompenzace).

## H8-03 Karta a heslo vlastníka

**Kroky**

1. Karta (`GET /v1/services/{id}/penpot`): adresa, přihlašovací e-mail, „Heslo účtu: zatím nenastaveno“, paměť/CPU, úložiště.
2. „Nastavit heslo“ bez čerstvého potvrzení → dialog step-up; v API `POST /v1/services/{id}/penpot/owner-password` → 403 `step_up_required`.
3. Po potvrzení: 202, operace `penpot.owner_password` doběhne, karta ukáže „nastaveno“.
4. Přihlásit se do Penpotu e-mailem vlastníka a novým heslem.

**Očekávaný výsledek:** heslo se nikde nezobrazí ani neuloží (operace ho po doběhnutí zapomene); přijde oznámení o změně
hesla (událost `penpot.owner_password.changed`). Heslo kratší než 12 znaků → 422.

**Negativní varianty:** jiná organizace na stejné id → 404 (karta i heslo). Host se sdílením „Služba: správa“ (`svc_manage`) kartu
vidí, heslo nastavit nesmí → 403; smí vlastník, admin organizace a držitel konzole služby (`svc_console`). V příkazech na uzlu
(`journalctl`/historie) se heslo nesmí objevit: jde do souboru 0600 a do `manage.py` na stdin.

## H8-04 Zálohy a obnova

1. „Zálohovat teď“ na kartě (akce `backup`) → v záložce Zálohy přibude dokončená záloha (databáze + soubory).
2. Denní záloha: `php artisan onhost:penpot:sweep` spustí zálohu, je-li poslední starší než 23 hodin.
3. Obnova vybrané zálohy (akce `restore`, oprávnění k obnově) → instance se krátce zastaví a vrátí stav ze zálohy.

## H8-05 Výpadek

1. Na testovacím uzlu zastavit frontend (`docker compose -p <stack> stop penpot-frontend`).
2. Dvakrát `php artisan onhost:penpot:sweep --no-backups`.

**Očekávaný výsledek:** zákazník dostane „Penpot neodpovídá“ a technici interní upozornění (událost `penpot.instance.unreachable`);
kontrola služby (`GET /v1/services/{id}/health`) hlásí chybu. Po startu kontejneru a dalším průchodu přijde „Penpot opět
běží“ (událost `penpot.instance.recovered`).

## H8-06 Pozastavení, zrušení, odstranění

1. Pozastavení (neplacení nebo podpora) → kontejnery stojí, adresa neodpovídá; obnovení je spustí znovu.
2. Zrušení → závěrečný archiv (výpis databáze + soubory) se stáhne z uzlu do úložiště platformy, instance se zastaví.
3. Po uplynutí ochranné lhůty odstranění → stack, jeho svazky, zálohy na uzlu, web v proxy, DNS záznam i klíče v trezoru zmizí.

## Kde hledat při selhání

| Co | Kde |
| --- | --- |
| Operace a krok, který selhal | záložka Provoz a NOC, `GET /v1/services/{id}/operations` |
| Stav kontejnerů | na uzlu `docker compose -p penpot-<10 znaků id služby> ps` a `logs --tail 200 penpot-backend` |
| Certifikát | na uzlu `journalctl -u caddy`; název musí v DNS mířit na uzel |
| Doktor | `php artisan onhost:doctor` (oblast `penpot`) |

## Pokrytí E2E testem

| Krok dokumentu | Automatický test |
| --- | --- |
| H8-01 | `tests/Feature/Penpot/PenpotCatalogTest.php` |
| H8-02, H8-03 | `tests/Feature/E2E/PenpotFlowTest.php` |
| H8-02 negativní, H8-04, H8-05, H8-06 | `tests/Feature/Penpot/PenpotLifecycleTest.php` |
