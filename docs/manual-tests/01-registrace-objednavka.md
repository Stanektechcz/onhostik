# F1 — Registrace a objednávka

Ruční test pro vlastníka a QA: cizí člověk se stane zákazníkem a zaplatí první objednávku. Pokrývá registraci a ověření
e-mailu, objednávku s přihlášením i bez účtu (guest checkout), limit 5 000 Kč pro neověřený e-mail (pravidlo R5) a platbu
kartou přes testovací bránu Comgate.

* Automatický protějšek (stejné kroky): `tests/Feature/E2E/SignupToWebTest.php`. Co tento dokument přidává: pohled očima
  člověka v prohlížeči, guest checkout a limit R5, které E2E test neprochází.
* Přehled všech dokumentů F1–F8: [README.md](README.md). Navazuje [F2 web](02-web.md).

## Prostředí a pravidla

* Zkoušejte na **stagingu** (`docs/runbooks/staging-aapanel.md`), kde je `COMGATE_TEST=true`, registrátor v testovacím
  režimu a Let's Encrypt staging. Vývojový stroj má panely napojené na ostré servery: zápisové scénáře na něm nespouštějte.
* Potřebujete: čistý prohlížeč (anonymní okno), e-mailovou schránku, do které se dostanete (u stagingu odchozí pošta
  v staff panelu `/sprava/maily`, sekce Odchozí pošta), a přístup do staff panelu `/sprava/` pro kontroly „kde hledat“.
* Každý scénář si poznamenejte: čas, e-mail zákazníka, číslo objednávky (`ON-…`). Bez nich se špatně hledá v logu.
* Pro každý scénář platí: bez úspěšné platby nesmí vzniknout žádná služba ani doména.

## Scénář F1.1 — Registrace a ověření e-mailu

**Předpoklady:** e-mail, který v systému ještě není. Heslo alespoň 12 znaků, písmena i číslice, nesmí být v seznamu
uniklých hesel.

**Kroky**

1. Otevřete `/registrace`. Vyplňte jméno, e-mail, heslo, název organizace, typ (osoba / firma), IČO u firmy, zemi CZ a
   zaškrtněte souhlas s podmínkami. Odešlete. Formulář volá `POST /v1/auth/register`.
2. Po odeslání jste přihlášeni (relace) a v panelu `/panel/prehled` vidíte prázdný přehled organizace.
3. Otevřete e-mail s ověřovacím odkazem. Odkaz vede na `/overeni-emailu?token=…` a stránka volá
   `POST /v1/auth/verify-email`.
4. V panelu otevřete `/panel/nastaveni`, sekce Nastavení účtu: e-mail je označen jako ověřený. Kontrola přes API:
   `GET /v1/me`.

**Očekávaný výsledek:** účet i organizace (role vlastník) existují, relace je přihlášená, po kroku 3 je e-mail ověřený.
Události (outbox): `identity.registered`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Slabé nebo krátké heslo | Heslo o 8 znacích | 422, chyba u pole heslo, účet nevznikne |
| Bez souhlasu s podmínkami | Nezaškrtnout | 422, účet nevznikne |
| E-mail už existuje | Registrace znovu na stejný e-mail | 422, chyba u pole e-mail, účet nevznikne |
| Odkaz použit podruhé | Otevřít ověřovací odkaz znovu | 422 `verify_token_invalid`, e-mail zůstává ověřený |
| Odkaz nepřišel | V panelu požádat o nový odkaz (`POST /v1/me/email/verification`) | Nový odkaz; nejvýš 1× za minutu a 5× za hodinu; ověřený e-mail dostane 409 `email_already_verified` |

**Kde hledat při selhání**

* Mail nepřišel: staff panel `/sprava/maily` (Odchozí pošta), API `GET /v1/staff/outbox`; doctor řádek `mail` › `outbox is
  leaving`, `transactional mailer`, `sender address set`.
* Registrace skončila chybou 5xx: `storage/logs/laravel.log` v čase pokusu (hledejte e-mail zákazníka, ne heslo).
* Událost `identity.registered` chybí: fronta outboxu neběží, `php artisan onhost:outbox:relay`; doctor `automation` ›
  `queue worker alive`.

## Scénář F1.2 — Katalog, košík a rekapitulace

**Předpoklady:** přihlášený zákazník z F1.1 (e-mail ověřený).

**Kroky**

1. Na veřejné stránce `/webhosting` vyberte tarif Start a pokračujte do košíku `/kosik`. Košík volá `PUT /v1/cart`,
   katalog `GET /v1/catalog/web-hosting`.
2. Zadejte doménu webu (např. `skladomat-test.cz`), délku závazku 1 měsíc, měnu CZK.
3. V rekapitulaci zkontrolujte cenu: **žádná implicitní sleva**, DPH rozepsané zvlášť, seznam dokumentů k odsouhlasení
   (podmínky, zpracování údajů, DPA, SLA, výslovná žádost o zahájení před koncem lhůty pro odstoupení). Rekapitulace volá
   `POST /v1/cart/quote`.
4. Do košíku přidejte druhou položku (doména nebo doplněk) a ověřte, že se každý doplněk účtuje na vlastním řádku košíku.

**Očekávaný výsledek:** součet je kladný, položky odpovídají výběru, rekapitulace vypisuje povinné dokumenty. Nic se
zatím nezaložilo (`GET /v1/services` je prázdné).

**Negativní varianty**

* Neznámý tarif nebo produkt v košíku: 422 s názvem pole, košík se nezmění.
* Doména s nepovoleným znakem nebo bez koncovky: odmítnuto při sestavení rekapitulace.
* Doména kratší než jeden rok registrace: nelze, minimum je 1 rok za ceník (viz [F3](03-domena-dns.md)).

**Kde hledat při selhání:** katalog nenabízí tarif: staff panel `/sprava/tarify`; doctor `providers` › `every product can
be provisioned`; doctor `catalog` › `every plan on sale keeps its WAF promise on its own panel`.

## Scénář F1.3 — Objednávka a platba kartou (Comgate, testovací režim)

**Předpoklady:** košík z F1.2 s jednou položkou; v prostředí `COMGATE_TEST=true` a testovací merchant.

**Kroky**

1. V rekapitulaci zvolte platbu Platební karta a odsouhlaste všechny dokumenty. Potvrďte. Volá se `POST /v1/orders`
   s platbou typu brána. Objednávka má stav čeká na platbu a dostanete přesměrování na stránku Comgate.
2. Na stránce Comgate dokončete **testovací** platbu (v testovacím režimu brána nabízí simulaci úspěšné platby; žádnou
   skutečnou kartu nepoužívejte). Brána zavolá naše `POST /v1/webhooks/payments/comgate`.
3. Vraťte se do panelu `/panel/fakturace`. Zálohový doklad je zaplacený, vznikl daňový doklad o přijaté platbě.
4. Počkejte do minuty. V `/panel/prehled` se objednávka po zřízení označí jako aktivní a služba se objeví v
   `/panel/sluzba/web` (viz [F2](02-web.md)).

**Očekávaný výsledek:** objednávka čeká na platbu, dokud brána nepotvrdí; po potvrzení se zaplatí a splní. Opakované
zavolání bránou stejné platby nic nezmění (odpověď `duplicate`). Události (outbox): `order.placed`, `order.paid`,
`payment.succeeded`, `invoice.paid`, `service.activated`, `order.active`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Platba zrušena na bráně | Na Comgate zvolit zrušení | Objednávka zůstává čekat na platbu, nic se nezřídí; v Fakturaci lze zaplatit znovu |
| Callback nedorazil | Zaplatit, ale zablokovat zpětné volání | Platbu lze znovu ověřit u brány (`POST /v1/payments/{intent}/sync`); bez toho zůstane objednávka čekat na platbu |
| Dvojklik na Potvrdit | Odeslat dvakrát | Jediná objednávka (Idempotency-Key), druhý požadavek dostane tutéž odpověď |
| Platba z kreditu bez kreditu | Zvolit „Z kreditu“ s nulovým zůstatkem | Odmítnuto s textem o nedostatku kreditu, nabídka převodu nebo karty |

**Kde hledat při selhání**

* Platba proběhla, objednávka nepřešla: doctor `money` › `every order being delivered was paid and documented`; staff
  `/sprava/objednavky`, detail objednávky; API `GET /v1/staff/orders`. Událost `order.fulfilment_failed`.
* Služba se nezřídila: staff `/sprava/sluzby`, detail služby, záložka Operace (`provision.website`); zásah
  `POST /v1/staff/provisioning/jobs/{operation}/retry`. Zákazník vidí totéž v záložce Provoz a NOC služby
  (`GET /v1/services/{service}/operations`).
* Fronta stojí: doctor `automation` › `queue worker alive`, `operation backlog`, `scheduler running`; po každé změně PHP
  kódu restartujte queue worker.
* Brána: doctor `payments` › `card gateway live mode` (na stagingu má být testovací); tabulka `provider_calls` (volání
  bez těl vendorů).

## Scénář F1.4 — Objednávka bez účtu (guest checkout)

**Předpoklady:** nepřihlášený prohlížeč; e-mail, který v systému není. Částka objednávky včetně DPH do 5 000 Kč.

**Kroky**

1. Na `/kosik` sestavte košík bez přihlášení (např. doména + hosting Start).
2. Vyplňte kontaktní údaje (jméno, e-mail, volitelně firma, IČO, DIČ, adresa) a zvolte kartu. Odeslání volá
   `POST /v1/checkout/guest`.
3. Zaplaťte u Comgate (testovací režim) jako v F1.3.
4. Otevřete e-mail „účet jsme pro vás založili“ a nastavte heslo přes `/obnova-hesla`.
5. Přihlaste se na `/prihlaseni`: v panelu je objednávka, doklady i služba.

**Očekávaný výsledek:** jedna odpověď založí účet, organizaci a objednávku a přihlásí relaci; heslo se do e-mailu
neposílá, jen odkaz pro jeho nastavení. Události (outbox): `identity.registered` (s původem `guest_checkout`),
`order.placed`, `order.paid`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| E-mail už má účet | Guest checkout s existujícím e-mailem | 409 `account_exists`, výzva k přihlášení; objednávka nevznikne |
| Opakované odeslání | Stejný Idempotency-Key a stejný e-mail | Vrátí se táž objednávka, nikdo se nepřihlásí a žádné údaje účtu se nevrací |
| Stejný klíč, jiný e-mail | Stejný klíč s cizím e-mailem | 409 `idempotency_key_reused` |
| Přihlášený zákazník | Zavolat guest checkout s relací | Odmítnuto, přihlášený objednává přes `POST /v1/orders` |
| Částka nad 5 000 Kč | Viz scénář F1.5 | Účet vznikne, objednávka ne |

**Kde hledat při selhání:** duplicitní nebo cizí účet: staff `/sprava/zakaznici`; e-mail s odkazem: `/sprava/maily`;
audit záznam `auth.register` s původem guest checkout: staff `/sprava/audit`.

## Scénář F1.5 — Limit 5 000 Kč pro neověřený e-mail (R5)

Pravidlo: objednávka s celkovou částkou **nad 5 000 Kč včetně DPH** (v EUR nad 200) se od osoby s neověřeným e-mailem
nepřijme. Přesně 5 000 Kč projde. Staff ani servisní účty se neptají.

**Předpoklady:** účet s neověřeným e-mailem (zaregistrujte se podle F1.1 a odkaz zatím neotevírejte). Košík, jehož
součet s DPH přesáhne 5 000 Kč (např. roční závazek vyššího tarifu webhostingu nebo několik domén; částku ukáže
rekapitulace). Druhý košík do 5 000 Kč.

**Kroky**

1. Přihlášený neověřený zákazník potvrdí objednávku nad limit: `POST /v1/orders`.
2. Panel zobrazí hlášku o ověření e-mailu s nabídkou poslat odkaz znovu. Odpověď API je 403 `email_unverified` s cestou
   `resend`: `/v1/me/email/verification`.
3. Pošlete odkaz znovu a ověřte e-mail (jako F1.1 krok 3). Stejnou objednávku potvrďte znovu: projde.
4. V jiném, také neověřeném účtu potvrďte objednávku do 5 000 Kč včetně DPH: projde bez ověření.
5. Guest checkout nad limit (F1.4): účet i ověřovací e-mail vzniknou, objednávka **ne** (403 `email_unverified`).
   Po ověření e-mailu nastavte heslo přes `/obnova-hesla` a objednávku zadejte znovu.

**Očekávaný výsledek:** nad limit nikdy nevznikne objednávka ani platba, pod limitem ano; zákazník vždy dostane
vysvětlení a cestu ven (znovu poslat odkaz). Stejné pravidlo drží pro vyplacení provize partnerovi.

**Negativní varianty:** opakované žádosti o odkaz (víc než 1× za minutu nebo 5× za hodinu) dostanou odmítnutí s dobou
čekání; ověřený e-mail dostane 409 `email_already_verified`; v EUR je hranice 200.

**Kde hledat při selhání:** odkaz nedorazil: `/sprava/maily`; limit je jiný než v dokumentu: konfigurace
`onhost.identity.unverified_order_limit_minor` (výchozí 500000 haléřů pro CZK); kód
`domains/Identity/EmailVerificationGuard.php`. Doctor řádky `mail` jako v F1.1.

## Pokrytí E2E testem

Automatický test `tests/Feature/E2E/SignupToWebTest.php` prochází stejné kroky bez prohlížeče:

| Krok testu | Tento dokument |
| --- | --- |
| 1. registrace `POST /v1/auth/register` | F1.1 kroky 1–2 |
| 2. ověření e-mailu, druhé použití tokenu je 422 | F1.1 krok 3 a negativní varianta |
| 3. katalog | F1.2 krok 1 |
| 4. košík a rekapitulace | F1.2 |
| 5. objednávka, čeká na platbu, nic se nezřídí před platbou | F1.3 krok 1 |
| 6. callback Comgate, opakovaný callback je `duplicate` | F1.3 kroky 2–3 |
| 7. outbox, saga, služba aktivní | F1.3 krok 4, další viz [F2](02-web.md) |
| 8. služba, doklady a funkce v panelu | F1.3 krok 4, F2 |
| 9. události a notifikace | Události u F1.1 a F1.3 |

Co E2E test nepokrývá a ruční test ano: guest checkout (F1.4), limit R5 (F1.5), skutečný vzhled panelu a stránka brány.
