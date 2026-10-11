# F3 — Doména a DNS

Ruční test pro vlastníka a QA: vyhledání a registrace domény `.cz`, převod domény k nám s převodním kódem (AUTH-ID), změna
držitele s potvrzením heslem (step-up), obnova za ceníkovou cenu a úprava DNS ve dvou fázích (navrhnout, náhled, publikovat).

* Automatický protějšek: `tests/Feature/E2E/DomainFlowTest.php`. Má tři testy: registrace od hledání po aktivní doménu,
  DNS + držitel + obnova, převod k nám. Tento dokument pokrývá totéž a navíc pohled očima člověka v panelu.
* Předchází [F1 registrace a objednávka](01-registrace-objednavka.md). Přehled: [README.md](README.md).

## Prostředí a pravidla

* Staging (`docs/runbooks/staging-aapanel.md`): registrátor v testovacím režimu (`WEDOS_TEST_MODE=true`), Comgate
  testovací. **Nikdy nezkoušejte registraci nebo převod skutečné cizí domény.** Pro registraci použijte jméno, které vám
  patří, nebo testovací jméno, které registrátor ve zkušebním režimu přijme.
* Doménu zákazník vidí v `/panel/sluzba/domain` (Služby › Domény a DNS), nebo přes ⌘K „Domény a DNS“. Detail domény má
  záložky DNS záznamy, SOA a jmenné servery, Registrace a expirace, DNSSEC a Provoz a NOC.
* Cenová pravidla, která tento dokument ověřuje: doména se registruje **nejméně na 1 rok**, za **ceníkovou cenu**, bez
  implicitní slevy; cena převodu a obnovy je ceníková cena roku, který přinášejí.
* Změny s vyšším rizikem (držitel, jmenné servery, zámek převodu, převodní kód ven, převod dovnitř) vyžadují čerstvé
  potvrzení heslem (step-up, `POST /v1/auth/step-up`). Bez něj API odpoví 403 `step_up_required` a panel potvrzení vyžádá.
* Potřebujete druhého zákazníka (jinou organizaci) pro varianty „cizí organizace“.

## Scénář F3.1 — Hledání a registrace .cz

**Předpoklady:** přihlášený zákazník s ověřeným e-mailem a vyplněným jménem u účtu; volné jméno, např.
`skladomat-test.cz`.

**Kroky**

1. Panel › Objednat › Nová služba › Doména. Do pole zadejte celé jméno `skladomat-test.cz`, volba „registrace na 1 rok“.
   Panel ověří dostupnost (`POST /v1/domains/check`).
2. U volného jména se zobrazí ceníková cena registrace i obnovy. Cena v hledání je **táž** jako na dokladu.
3. Vyplňte držitele (jméno, e-mail, adresa, země CZ) a odsouhlaste podmínky registrátora i registru .cz. Zaplaťte kartou
   (testovací Comgate, viz [F1.3](01-registrace-objednavka.md)).
4. Před zaplacením: seznam `GET /v1/domains` je prázdný a registrátor nedostal žádný příkaz.
5. Po zaplacení do minuty: doména je v `/panel/sluzba/domain` ve stavu aktivní, s datem expirace za rok a s vlastní DNS
   zónou. Kontrola: `GET /v1/domains`, `GET /v1/domains/{domain}`.
6. Faktura: v `/panel/fakturace` je daňový doklad s cenou rovnou ceně z hledání a nulovou slevou.

**Očekávaný výsledek:** doména je aktivní, má DNS zónu u nás a držitele uloženého u registrátora, žádná odpověď zákazníkovi
nepojmenovává dodavatele (registrátora ani DNS server). Události (outbox): `order.paid`, `domain.registered`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Obsazená doména | Hledat jméno, které už existuje | Nedostupné, registrace není nabídnuta |
| Prémiová doména | Hledat jméno, které registr označuje jako prémiové | Panel vysvětlí, že cenu určuje registr, a nabídne kontakt s podporou; objednat nelze |
| Neznámá koncovka | Hledat `.xyz`, který neprodáváme | Hláška „Koncovku .xyz zatím nenabízíme“ se seznamem nabízených |
| Doména na méně než rok | Zadat 0 let | Odmítnuto (minimum 1 rok) |
| Bez souhlasu s podmínkami | Neodsouhlasit podmínky registru | Objednávku nelze potvrdit |
| Neověřený e-mail a částka nad 5 000 Kč | Např. registrace na 10 let | 403 `email_unverified`, viz [F1.5](01-registrace-objednavka.md) |
| Registrátor odmítne | (staging) registrace rozbitého jména | Objednávka se nesplní, zaplacené se vrátí zákazníkovi; událost `domain.registration_failed` |

**Kde hledat při selhání**

* Staff `/sprava/zakaznici` › zákazník › doména, a `/sprava/sluzby` › Operace: druh `domain.register`; opakování
  `POST /v1/staff/provisioning/jobs/{operation}/retry`. Událost `order.fulfilment_failed`.
* Doctor: `providers` › `registrar available`, `providers` › `WEDOS test mode off` (na stagingu má upozornit, v ostrém
  musí být v pořádku), `providers` › `registrar cost prices known for every TLD`, `automation` › `queue worker alive`.
* Cena v hledání se liší od dokladu: porovnejte ceník koncovky `GET /v1/catalog/tlds` s dokladem
  (`GET /v1/invoices/{invoice}`); rozdíl je chyba.

## Scénář F3.2 — DNS: navrhnout, náhled, publikovat

**Předpoklady:** aktivní doména z F3.1 (např. `zona-test.cz`) s DNS zónou u nás.

**Kroky**

1. Záložka DNS záznamy domény: seznam záznamů (`GET /v1/dns/zones`, `GET /v1/dns/zones/{zone}`; stejné dává alias podle
   jména `GET /v1/domains/{zone}/zone`). Jmenné servery zóny: `ns1.onhost.cz`, `ns2.onhost.cz`.
2. Přidejte záznam A `api` → `89.187.160.6`, TTL 300 s důvodem „api host“. Záznam se **zatím jen navrhne**:
   `POST /v1/dns/zones/{zone}/changes`. Panel hlásí „Zóna má nepublikované změny“.
3. Otevřete náhled (`GET /v1/dns/zones/{zone}/preview`): obsahuje navržený záznam. Živý stav zóny
   (`GET /v1/dns/zones/{zone}`) ho **ještě nemá**; DNS dotaz (`nslookup api.zona-test.cz`) také ne.
4. Publikujte (`POST /v1/dns/zones/{zone}/commit`). Teď je záznam v zóně i ve skutečném DNS.
5. Záložka Verze a návrat (`GET /v1/dns/zones/{zone}/versions`): přibyla nová verze. Zkuste se vrátit na předchozí
   (`POST /v1/dns/zones/{zone}/rollback`) a zase publikovat.
6. Navrhněte další změnu a zahoďte ji (`POST /v1/dns/zones/{zone}/discard`): živá zóna se nezmění.

**Očekávaný výsledek:** nic nejde do DNS bez publikace; po ní je zóna u poskytovatele shodná s tím, co ukazuje panel.
Události (outbox): `dns.zone.committed`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Neplatný záznam | A záznam s obsahem `999.1.1.1` | 422 už při navržení, nic nečeká na publikaci |
| CNAME na kořeni | CNAME pro jméno zóny | Odmítnuto (`dns_cname_apex`) |
| Duplicita | Stejný záznam podruhé | Odmítnuto (`dns_duplicate_record`) |
| Příliš mnoho záznamů | Naplnit limit záznamů zóny | 409 `dns_record_limit` s číslem `limit` |
| Publikace bez změn | Commit bez navržených změn | Odmítnuto (`dns_nothing_to_commit`) |
| Vrácení na neexistující verzi | Rollback na verzi 99 | 404 `dns_version_not_found` |
| Chráněný záznam | Smazat záznam, který spravuje platforma | Odmítnuto (`dns_protected_record`), viz [F4](04-email.md) (ověřit, které záznamy jsou chráněné) |
| Cizí organizace | Druhý zákazník otevře `GET /v1/dns/zones/{zone}` nebo `POST /v1/dns/zones/{zone}/commit` | 404 |

**Kde hledat při selhání**

* Události `dns.zone.republished` (platforma srovnala, co poskytovatel servíruje) a `dns.drift.detected` (noční srovnání
  našlo rozdíl). Oprava: `POST /v1/dns/zones/{zone}/republish`.
* Doctor `dns`: `every DNS zone equals what its provider serves`, `every DNS zone could be compared with its provider`,
  `no DNS zone outlives its domain unnoticed`.
* Staff `/sprava/zakaznici` › zóna; log `storage/logs/laravel.log`; volání na DNS server v tabulce `provider_calls`.

## Scénář F3.3 — Změna kontaktu držitele (step-up)

**Předpoklady:** aktivní doména, zákazník zná heslo. Pozor na rozdíl: změna **kontaktu** držitele (e-mail, adresa, telefon)
je povolená; změna **samotného držitele** (jiné jméno nebo firma) je převod domény na jiného držitele.

**Kroky**

1. Záložka Registrace a expirace › Kontakt držitele › Změnit kontakt. Vyplňte jen to, co se mění: nový e-mail
   `nova@example.cz`, město Brno, PSČ 60200. Odešlete bez potvrzení hesla: `POST /v1/domains/{domain}/holder`.
2. Očekávejte zákaz 403 `step_up_required`; registrátor nedostal žádný příkaz.
3. Potvrďte heslem (`POST /v1/auth/step-up`) a změnu odešlete znovu. Panel ohlásí „Kontakt držitele změněn“ a
   „Registrátor změnu přijal“.
4. Zkontrolujte seznam kontaktů (`GET /v1/domains/contacts`): nový e-mail je tam. Údaje u domény jsou stejné.
5. Zkuste změnit jméno držitele na „Petr Nový“.

**Očekávaný výsledek:** po step-upu odpověď uvádí změněná pole (`email`, `city`, `postal_code`) a
`registrar_updated`; registrátor dostal jedinou aktualizaci kontaktu. Změna jména je odmítnuta.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Bez step-upu | Viz krok 1 | 403 `step_up_required`, nic se nezměnilo |
| Změna jména | Krok 5 | 422 `domain_holder_identity_change` (to je převod na jiného držitele) |
| Prázdná změna | Odeslat bez jediného údaje | Panel: „Vyplňte alespoň jeden údaj“ |
| Špatný tvar | E-mail bez zavináče nebo země o 3 písmenech | Panel odmítne („E-mail nemá platný tvar“, „Země musí mít dvě písmena“) |
| Cizí organizace | Druhý zákazník zavolá `POST /v1/domains/{domain}/holder` | 404 |
| Přes kontakt sdílený s dalšími doménami | Kontakt používá víc domén | Panel upozorní „Kontakt sdílí…“ a změní se u všech |

**Kde hledat při selhání:** staff `/sprava/audit` (záznam o změně držitele s kdo a kdy); registrátor nepřijal změnu: doctor
`providers` › `registrar available`; operace domény ve staff detailu zákazníka.

## Scénář F3.4 — Obnova za ceníkovou cenu

**Předpoklady:** aktivní doména; zákazník má kredit alespoň na roční obnovu (jinak viz negativní varianty).

**Kroky**

1. Dobijte kredit přes panel (Fakturace › Dobít kredit, karta, testovací Comgate; API `POST /v1/wallet/topup`).
   Zůstatek v `GET /v1/wallet`.
2. Záložka Registrace a expirace › Obnovit na 1 rok: `POST /v1/domains/{domain}/renew` s `years` 1. Odpověď je 202,
   operace `domain.renew` doběhne.
3. Datum expirace se posune přesně o rok. Doklad v `/panel/fakturace` je za **ceníkovou cenu obnovy** jednoho roku,
   se slevou 0 a s DPH navíc. Kredit se čerpá (rezervace se zachytí).
4. Automatická obnova se zapíná zvlášť: `POST /v1/domains/{domain}/auto-renew`.

**Očekávaný výsledek:** expirace +1 rok, doména aktivní, doklad zaplacený z kreditu v ceníkové ceně. Události
(outbox): `domain.renewed`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Méně než rok | `years` 0 | 422, nikdy méně než 1 rok |
| Bez kreditu | Obnova s nulovým zůstatkem | Chyba (4xx), registrátor nedostal příkaz obnovy |
| Doména ve špatném stavu | Obnova zrušené nebo převedené domény | 409 `domain_not_renewable` (obnovit lze aktivní, prošlou nebo v ochranné lhůtě) |
| Platba obnovy selhala (automatika) | Kredit nestačí při automatické obnově | Opakuje se denně až do expirace i v ochranné lhůtě; událost `domain.renewal_payment_failed` |
| Cizí organizace | `POST /v1/domains/{domain}/renew` na cizí doménu | 404 |

**Kde hledat při selhání:** ve staff `/sprava/sluzby` operace druhu `domain.renew`.
Dále události `domain.renewal_payment_failed`,
`domain.renewal_failed`; doctor `providers` › `registrar cost prices known for every TLD` (chybějící nákupní cena);
staff `/sprava/doklady` pro doklad obnovy.

## Scénář F3.5 — Převod domény k nám (AUTH-ID)

**Předpoklady:** doména u jiného registrátora; zákazník má od něj převodní kód (AUTH-ID) a doménu odemčenou pro převod.
Na stagingu použijte jen testovací jméno, které registrátor ve zkušebním režimu povolí.

**Kroky**

1. Hledání `prevod-test.cz` (`POST /v1/domains/check`): obsazená doména nabídne převod; cena převodu je ceníková cena roční
   obnovy a převod přináší **1 rok**.
2. V objednávce zvolte akci Převést k nám (panel: Převod domény k nám), zaplaťte kartou (testovací Comgate).
3. Po zaplacení objednávka **čeká na kód**: je ve stavu zřizování, doména ještě v seznamu není a registrátor nedostal
   žádný příkaz převodu ani registrace.
4. V seznamu domén otevřete řádek čekající na kód a zadejte AUTH-ID (panel: Zahájit převod). Potvrďte heslem (step-up).
   API: `POST /v1/domains/transfer-in` s `fqdn`, `order_item_id`, `auth_info`, držitelem a souhlasem.
5. Po zahájení panel hlásí „Převod zahájen“. Za pár minut je doména aktivní u nás, objednávka aktivní.
6. Stejný kód znovu odeslat nelze.

**Očekávaný výsledek:** registrátor dostal právě jeden převodní příkaz s kódem a zaplaceným rokem; nic se neregistrovalo;
převodní kód se neobjeví v žádné odpovědi, v notifikaci ani v záznamu událostí. Události (outbox): `order.paid`,
`domain.transfer.code_needed` (když se zaplacený řádek čeká na kód déle), `domain.transferred_in`.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Bez step-upu | Krok 4 bez potvrzení hesla | 403 `step_up_required`, převod se nezačal |
| Převod bez objednávky | `POST /v1/domains/transfer-in` pro jméno bez zaplaceného řádku | 422 `transfer_needs_order` (převod obnovuje jméno, kupuje se v košíku) |
| Kód podruhé | Stejný kód znovu | 409 `transfer_already_submitted` |
| Špatný tvar kódu | Příliš krátký nebo s mezerami | Panel: „AUTH-ID nemá platný tvar“ |
| Koncovka nepřevádíme | Převod `.xyz` | Panel: „Tuto koncovku zatím nepřevádíme“ |
| Zákazník čeká na kód dlouho | Nezadat kód | Připomínky a upozornění provozu (`domain.transfer.code_needed`); co se děje po čekací lhůtě, ověřte u provozu |
| Cizí organizace | Druhý zákazník pošle `order_item_id` cizí objednávky | 404 |

**Kde hledat při selhání:** událost `domain.transfer.code_needed` (kdo čeká a jak dlouho); staff `/sprava/objednavky` a
operace `domain.transfer_in` ve `/sprava/sluzby`; doctor `lifecycle` › `no domain is missing at its registrar`. Kód se
neloguje: hledejte po čase, id operace a čísle objednávky, nikdy po kódu.

## Scénář F3.6 — Převod domény od nás (kód ven), zámek a jmenné servery

**Předpoklady:** aktivní doména. Všechny tři kroky jsou vysoké riziko: vyžadují čerstvé potvrzení heslem.

**Kroky**

1. Registrace a expirace › Zámek převodu: zapnout a vypnout (`POST /v1/domains/{domain}/transfer-lock`).
2. Převodní kód ven (`POST /v1/domains/{domain}/auth-info`): kód buď přijde e-mailem držiteli od registrátora, nebo se
   jednou zobrazí zákazníkovi a po 7 dní zůstane šifrovaně uložen.
3. Jmenné servery: změna na vlastní (`POST /v1/domains/{domain}/nameservers`), návrat k našim přes
   `POST /v1/domains/{domain}/use-onhost-dns`. Panel u domény s DNS jinde upozorní „tato doména má DNS jinde“.

**Očekávaný výsledek:** každá změna chce step-up, bez něj 403 `step_up_required`; po změně jmenných serverů zóna u nás
už není autoritativní. Události (outbox): `domain.auth_info_requested`, `domain.nameservers_changed`.

**Negativní varianty:** bez step-upu 403 pro všechny tři; cizí organizace 404; změna jmenných serverů na neplatné jméno je
odmítnuta s názvem pole.

**Kde hledat při selhání:** staff `/sprava/audit`; operace `domain.update_ns`; po odchodu domény `domain.closed`.

## Pokrytí E2E testem

`tests/Feature/E2E/DomainFlowTest.php` pokrývá tyto kroky a jejich negativní varianty:

| Test | Krok testu | Tento dokument |
| --- | --- | --- |
| registrace | 1 hledání `POST /v1/domains/check`, bez názvů dodavatelů | F3.1 kroky 1–2 |
| registrace | 2 objednávka a platba, před platbou nic u registrátora | F3.1 kroky 3–4 |
| registrace | 3 saga: příkaz domain-create jednou, doména aktivní se zónou | F3.1 krok 5 |
| registrace | 4 seznam, detail, doklad v ceníkové ceně se slevou 0 | F3.1 kroky 5–6 |
| registrace | 5 události `order.paid` a `domain.registered` jednou | F3.1 událost |
| DNS, držitel, obnova | zóna, navržení, neplatný záznam 422, náhled, živá zóna bez záznamu, publikace, verze | F3.2 |
| DNS, držitel, obnova | držitel bez step-upu 403, po něm změna, změna jména 422 | F3.3 |
| DNS, držitel, obnova | obnova: `years` 0 je 422, bez kreditu chyba, s kreditem +1 rok v ceníkové ceně, `domain.renewed` | F3.4 |
| převod k nám | cena převodu = cena obnovy, 1 rok | F3.5 krok 1 |
| převod k nám | zaplacený řádek čeká na kód, bez step-upu 403, bez objednávky 422, kód zahájí převod, podruhé 409, kód v žádné odpovědi | F3.5 kroky 2–6 |

Jen ručně: kód ven, zámek a jmenné servery (F3.6), skutečné vzhledy panelu, zahození návrhu a rollback verze DNS (E2E
prochází jen navržení, náhled, publikaci a seznam verzí).
