# F4 — E-mail hosting

Ruční test pro vlastníka a QA: objednávka e-mailového tarifu pro doménu, schránky do limitu tarifu, aliasy a přesměrování,
změna hesla (i jednorázovým odkazem), vypnutí a zapnutí odesílání a DNS záznamy MX, SPF, DKIM a DMARC.

* Automatický protějšek: `tests/Feature/E2E/MailFlowTest.php`. Má tři testy: objednávka až po MX/SPF/DKIM v naší DNS,
  správa schránek v mezích tarifu a domény, a odmítnutí převzetí cizí poštovní domény. Tento dokument je prochází stejně a
  přidává pohled očima člověka a skutečné odesílání pošty.
* Předchází [F1 registrace a objednávka](01-registrace-objednavka.md), souvisí s [F3 doména a DNS](03-domena-dns.md).
  Přehled: [README.md](README.md).

## Prostředí a pravidla

* Staging (`docs/runbooks/staging-aapanel.md`), ne vývojový stroj. Doména, kterou používáte, musí být **vaše** (nikdy ne
  doména, na které už běží cizí pošta).
* Tarif **Mail Business**: 10 schránek, každá do 10 GB, 50 aliasů, 3 domény, antispam rspamd, zálohy 14 dní. Čísla jsou
  ze seedu katalogu; skutečné hodnoty ukáže panel v záložce Schránky a kvóty.
* Pošta služby je v `/panel/sluzba/mail` (Služby › Emailing). Záložky: Schránky a kvóty, Aliasy a přesměrování, SPF, DKIM a
  DMARC, Provoz a NOC (další, např. spamfilter, jsou mimo tento test).
* Akce se posílají na `POST /v1/services/{service}/actions` s `action` a `params`, vždy s novým Idempotency-Key. Přijatá akce
  vrací 202 a `operation_id`; dokončení je vidět v Provoz a NOC.
* Adresa smí být **jen v poštovní doméně, kterou služba hostuje**. Tato hranice drží i proti jiným schránkám na sdíleném
  poštovním serveru: cizí schránky ani domény platforma nemění.
* Potřebujete druhého zákazníka pro varianty „cizí organizace“ a externí schránku (např. vlastní Gmail) pro zkoušku odesílání.

## Scénář F4.1 — Objednávka a poštovní doména

**Předpoklady:** zákazník z [F1.1](01-registrace-objednavka.md) s ověřeným e-mailem; vaše doména, např. `dvorak-mail.cz`.
Variantně 1: doména je u nás v DNS (zóna z [F3](03-domena-dns.md)); variantně 2: DNS je jinde.

**Kroky**

1. Katalog `GET /v1/catalog/mail` obsahuje tarif Mail Business. V košíku zvolte tarif a zadejte doménu. Zaplaťte kartou
   (testovací Comgate, [F1.3](01-registrace-objednavka.md)). Před platbou nesmí existovat žádná služba.
2. Po platbě do minuty: služba je aktivní v `/panel/sluzba/mail`. Na uzlu vznikla poštovní doména s DKIM, platforma
   založila klienta a odesílání je zapnuté.
3. Varianta 1 (zóna u nás): v záložce SPF, DKIM a DMARC (nebo přes `GET /v1/dns/zones/{zone}`) jsou v zóně záznamy MX,
   TXT `v=spf1`, TXT `v=DMARC1` a klíč DKIM (`v=DKIM1; … p=…`). Platforma je publikovala sama.
4. Varianta 2 (DNS jinde): detail služby (`GET /v1/services/{service}`) ukazuje v části přístupu, které záznamy MX, SPF a
   DKIM má zákazník u svého DNS vytvořit. Vytvořte je u svého poskytovatele.
5. Ověřte z venku: `dig MX dvorak-mail.cz`, `dig TXT dvorak-mail.cz` (SPF, DMARC na `_dmarc.`), a
   `dig TXT <selektor>._domainkey.dvorak-mail.cz` (DKIM).

**Očekávaný výsledek:** služba aktivní, poštovní doména existuje právě jednou, MX, SPF, DKIM a DMARC jsou publikované
(varianta 1) nebo zákazníkovi přesně vypsané (varianta 2). Události (outbox): `order.paid`, `service.activated`,
`order.active`, `dns.zone.committed` (varianta 1).

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Doména už má poštu na serveru | Objednat tarif pro doménu, která na poštovním serveru běží pro někoho jiného | Zřízení selže (`provision.mail`, text „not created by ONhost“), služba není aktivní, **cizí doména ani schránky se nezměnily**, zákazník se dozví, že to nevyšlo |
| Před platbou | Po potvrzení objednávky a před platbou `GET /v1/services` | Prázdné |
| Neověřený e-mail a částka nad 5 000 Kč | Roční závazek na vyšší tarif | 403 `email_unverified`, viz [F1.5](01-registrace-objednavka.md) |
| Cizí organizace | Druhý zákazník otevře `GET /v1/services/{service}` | 404 |
| DNS nepublikované (varianta 2) | Záznamy u svého DNS nevytvořit | Pošta nechodí; platforma neručí za DNS jinde, panel záznamy stále ukazuje |

**Kde hledat při selhání**

* Staff `/sprava/sluzby`, detail služby, Operace: druh `provision.mail`; opakování
  `POST /v1/staff/provisioning/jobs/{operation}/retry`. Události (outbox): `operation.failed`, `service.failed`.
* DNS: události `service.dns.problem`, `dns.zone.republished`, `dns.drift.detected`; doctor `dns` › `every DNS zone equals
  what its provider serves`.
* Doctor: `providers` › `every product can be provisioned`, `automation` › `queue worker alive`, `lifecycle` › `no service
  stranded in a transient state`.
* Log `storage/logs/laravel.log`; volání na panel pošty v tabulce `provider_calls` (bez hesel a těl odpovědí).

## Scénář F4.2 — Schránky do limitu tarifu

**Předpoklady:** aktivní služba z F4.1, tarif s 10 schránkami.

**Kroky**

1. Záložka Schránky a kvóty (`GET /v1/services/{service}/resources/mailboxes`): seznam je prázdný, nahoře limit „0 / 10“.
   Funkce služby: `GET /v1/services/{service}/features` (`mailboxes` zapnuté s limitem 10).
2. Založte schránku `info@dvorak-mail.cz` s heslem (alespoň 12 znaků; tlačítko Vygenerovat heslo ho vytvoří) a jménem
   (`mailbox.create`). Schránka je v seznamu, v poštovním klientovi se do ní přihlásíte (IMAP, SMTP; automatické
   nastavení nabízí `/mail/config-v1.1.xml`; webmail se otevře z detailu).
3. Založte dalších 9 schránek (`box2` až `box10`). Seznam ukazuje „10 / 10“.
4. Zkuste jedenáctou (`box11`).
5. Smažte jednu schránku (`mailbox.delete`) a založte `box11` znovu.

**Očekávaný výsledek:** do desítky vše projde, schránka je v klientově vlastním klientovi na uzlu vedle domény. Jedenáctá
je odmítnuta s číslem tarifu a panel se na uzel neptá. Po smazání se uvolní místo.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Limit tarifu | 11. schránka | 422 `feature_limit_reached`, `limit` 10, `used` 10 |
| Cizí doména | Schránka `info@konkurence.cz` | 422 `mail_domain_not_yours`, zpráva jmenuje doménu služby, panel se na uzel neptá |
| Slabé heslo | Heslo kratší než 12 znaků | Odmítnuto s názvem pole |
| Cizí schránka na sdíleném serveru | `remote_id` schránky, kterou platforma nezaložila (historická pošta) | Operace selže (hláška, že schránka nepatří službě); cizí heslo ani existence schránky se nezmění |
| Člen týmu bez oprávnění mazat data | Smazat schránku s rolí „spravovat“ | Zákaz (403); mazání schránek je oprávnění „mazat data“ |
| Cizí organizace | Akce na službu druhé organizace | 404 |

**Kde hledat při selhání:** Provoz a NOC služby; události `operation.failed`, `operation.succeeded`; staff
`/sprava/sluzby` › Operace; doctor `security` › `finished operations hold no secrets`. Zda se hesla nedostala do záznamů:
tabulka `provider_calls` a operace nesmějí obsahovat heslo.

## Scénář F4.3 — Aliasy a přesměrování

**Předpoklady:** aktivní služba, alespoň jedna schránka.

**Kroky**

1. Záložka Aliasy a přesměrování: založte alias `sales@dvorak-mail.cz` → `petr@example.org` (`alias.create`) a
   přesměrování `office@dvorak-mail.cz` → `petr@example.org` (`forward.create`).
2. Pošlete zprávu z externí schránky na `sales@dvorak-mail.cz` a na `office@dvorak-mail.cz`: obě dorazí na cíl.
3. Zkuste alias, jehož zdroj je v cizí doméně (`ceo@konkurence.cz`).
4. Smažte alias (`alias.delete`) a přesměrování (`forward.delete`).

**Očekávaný výsledek:** alias i přesměrování existují právě jednou, doručení funguje, po smazání zmizí.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Cizí zdroj | Krok 3 | 422 `mail_domain_not_yours`, na uzlu nic nepřibude |
| Limit aliasů | Přesáhnout počet aliasů tarifu (50) | 422 `feature_limit_reached` (ověřit při prvním běhu) |
| Cizí organizace | Akce na službu druhé organizace | 404 |

**Kde hledat při selhání:** Provoz a NOC služby; staff `/sprava/sluzby` › Operace; pokud zpráva nedojde, doctor `mail` ›
`transactional mailer` se týká jen platformní pošty, ne pošty zákazníků: hledejte v logu poštovního uzlu u provozu.

## Scénář F4.4 — Změna hesla schránky

**Předpoklady:** schránka z F4.2.

**Kroky**

1. Záložka Schránky a kvóty › Změnit u schránky `info@dvorak-mail.cz`: zadejte nové heslo (`mailbox.update`, parametry
   `remote_id` a `password`). Ověřte přihlášení novým heslem, staré nesmí fungovat.
2. Jednorázový odkaz, když nechcete znát heslo uživatele: v záložce vyberte Odkaz pro změnu hesla
   (`POST /v1/services/{service}/mailbox-password-link`). Odpověď obsahuje adresu `/mailbox/password/{token}` (podepsanou).
3. Otevřete odkaz v jiném prohlížeči: stránka ukazuje adresu schránky. Zadejte nové heslo dvakrát a odešlete.
4. Otevřete ten samý odkaz znovu.

**Očekávaný výsledek:** po kroku 3 funguje nové heslo; po kroku 4 je hláška „Odkaz už neplatí“ (odkaz platí jednou).
Hesla se neobjeví v záznamu operací ani ve volání na uzel (`provider_calls`).

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| Odkaz podruhé | Krok 4 | „Odkaz už neplatí“ |
| Upravený odkaz | Změnit znak v podpisu | Podpis nesedí, stránka se neotevře |
| Schránka cizí | `remote_id` historické schránky | Operace selže (nepatří službě), heslo se nezmění |
| Člen týmu bez oprávnění | Role bez „spravovat“ | Zákaz (403) |
| Cizí organizace | `POST /v1/services/{service}/mailbox-password-link` na cizí službu | 404 |

**Kde hledat při selhání:** Provoz a NOC; staff `/sprava/audit`; odkaz je podepsaný a platí jednou: vystavte nový.

## Scénář F4.5 — Odesílání vypnout a zapnout

**Předpoklady:** schránky z F4.2.

**Kroky**

1. Záložka Schránky a kvóty (nebo Provoz): Odesílání vypnout (`sending.set`, `enabled` false). Všechny schránky domény
   přestanou odesílat; příjem pošty zůstává.
2. Z poštovního klienta odešlete zprávu: SMTP odmítne. Příchozí zpráva dorazí.
3. Odesílání zapněte (`enabled` true). Odeslání funguje.
4. Pokud na sdíleném serveru leží historická schránka, kterou platforma nezaložila: její odesílání se vypnutím **nemění**.

**Očekávaný výsledek:** vypnutí platí pro všechny schránky poštovní domény služby a nikoho jiného; zapnutí je vrátí.

**Negativní varianty:** člen týmu bez oprávnění dostane 403; cizí organizace 404; pozastavená služba (viz
[F7](07-fakturace-upominky.md)) odesílání nenabízí a vysvětlí proč.

**Kde hledat při selhání:** Provoz a NOC (operace `sending.set`); staff `/sprava/sluzby` › Operace; stav po pozastavení a
obnově služby vidí zdravotní kontrola služby.

## Scénář F4.6 — DNS záznamy MX, SPF, DKIM a DMARC a skutečné doručení

**Předpoklady:** F4.1 hotové, DNS publikované (variantou 1 nebo ručně u svého DNS).

**Kroky**

1. Záložka SPF, DKIM a DMARC: výpis záznamů, které služba potřebuje (MX 1×, SPF začínající `v=spf1`, DKIM pod
   `<selektor>._domainkey`, DMARC začínající `v=DMARC1`).
2. Porovnejte s tím, co vrací veřejné DNS (`dig`, viz F4.1 krok 5): obsah se shoduje.
3. Pošlete zprávu ze schránky `info@dvorak-mail.cz` na vlastní externí schránku a v ní otevřete původ zprávy
   (hlavičky): `spf=pass`, `dkim=pass`, `dmarc=pass`.
4. Pošlete zprávu z externí schránky na `info@dvorak-mail.cz`: dorazí do schránky (webmail nebo IMAP).
5. Úmyslně smažte záznam DKIM v naší zóně a publikujte (F3.2). Pošlete zprávu znovu: `dkim` neprojde. Noční srovnání
   zóny s poskytovatelem hlásí rozdíl (`dns.drift.detected`); ruční obnova je `POST /v1/dns/zones/{zone}/republish`.
   Zda ji platforma pro záznam provede, ověřte při prvním běhu.

**Očekávaný výsledek:** všechny čtyři typy záznamů existují a odpovídají panelu; odchozí zprávy projdou SPF, DKIM i DMARC.

**Negativní varianty**

| Varianta | Postup | Očekávání |
| --- | --- | --- |
| DNS jinde, záznamy chybí | Nevytvořit záznamy | Příjem ani odesílání nefunguje správně, panel záznamy stále ukazuje jako potřebné |
| Cizí organizace | `GET /v1/dns/zones/{zone}` cizí zóny | 404 |

**Kde hledat při selhání:** události `service.dns.problem`, `dns.drift.detected`, `dns.zone.republished`; doctor `dns` ›
`every DNS zone equals what its provider serves`; autokonfigurace klienta `/mail/config-v1.1.xml`.

## Pokrytí E2E testem

`tests/Feature/E2E/MailFlowTest.php` pokrývá stejné kroky:

| Test | Krok testu | Tento dokument |
| --- | --- | --- |
| objednávka až po DNS | zóna u nás, objednávka a platba, služba aktivní, poštovní doména s DKIM, odesílání zapnuté | F4.1 kroky 1–2 |
| objednávka až po DNS | v zóně MX, SPF, DMARC a DKIM; DNS uzel byl uvědomen | F4.1 krok 3, F4.6 |
| správa schránek | co panel ukazuje: tarif, záznamy k publikaci, akce `mailbox.create` až `sending.set` | F4.1 krok 4, F4.2 krok 1 |
| správa schránek | schránka v doméně služby, cizí doména 422 `mail_domain_not_yours` | F4.2 kroky 2 a negativní varianty |
| správa schránek | alias a přesměrování, cizí zdroj 422 | F4.3 |
| správa schránek | nové heslo, hesla nejsou v záznamech; jednorázový odkaz platí jednou | F4.4 |
| správa schránek | odesílání vypnout a zapnout, historická schránka se nemění | F4.5 |
| správa schránek | strop 10 schránek, 11. je `feature_limit_reached`; smazání uvolní místo | F4.2 kroky 3–5 |
| správa schránek | cizí (historická) schránka se nedá změnit ani smazat | F4.2 negativní varianta |
| správa schránek | události `order.paid`, `service.activated`, `order.active`, `operation.failed` pro dvě odmítnutí | Události a „kde hledat“ |
| cizí poštovní doména | zřízení selže s „not created by ONhost“, cizí pošta beze změny | F4.1 negativní varianta |

Jen ručně: skutečné odeslání a příjem pošty, hlavičky `spf` `dkim` `dmarc` (F4.6), vzhled panelu a webmail.
