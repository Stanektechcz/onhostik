# Rozhodnutí vlastníka k auditu 2026-10 (přijata 2026-10-03)

Vlastník svěřil všechna otevřená rozhodnutí doporučeným výchozím hodnotám („Všechna rozhodnutí rozhodni a zajisti.
Zajisti doporučené.“). Rozhodnutí, která by vyžadovala zápis do živého panelu nebo do produkce, se tímto **přijímají
jako směr**. Jejich provedení na živých systémech dál potřebuje výslovný pokyn vlastníka pro konkrétní akci.

| # | Rozhodnutí | Provede balík | Poznámka |
|---|---|---|---|
| R1 | Zámky TASK-0043/0044 se uvolňují (provedeno 2026-10-03). | – | Worktree obsahují necommitnutou práci. Nesahá se na ni a balíky, které mění `RoleCatalog`/`PermissionCatalog` (B6), počkají na dokončení 0043. |
| R2 | O1: terminál, Node projekty a cron na **sdíleném** aaPanel uzlu jsou vypnuté (`terminal`/`node_projects` = `!shared`). | C5 | Kód a test, bez zápisu do živého panelu. |
| R3 | `backups.compute` se zapne až po kontrole úložiště PBS (`onhost:backups:compute-plan` naprázdno). | E11 | Zapnutí na produkci jen s pokynem vlastníka. |
| R4 | Schránky v e-shop tarifech a `dedicated_ipv4` u webu se stahují z prodeje revizí katalogu, dokud nejsou dodatelné. PlanPromises hlídá, že prodaný klíč jde na executoru dodat. | C5, C6 | Revize přes `CatalogRevisions` (čtyři oči). |
| R5 | Neověřený e-mail blokuje výplaty partnerům a objednávky nad 5 000 Kč. Přibude endpoint pro opakované odeslání ověřovacího e-mailu. | E1 | Provedeno v TASK-0096 (PR #88). Známý limit: pravidlo hlídá jednu objednávku, takže několik objednávek těsně pod 5 000 Kč projde; kumulativní strop by byl nové rozhodnutí. |
| R6 | Věrnost: body se přičítají jen za platby od 100 Kč a odebírají se při dobropisu a chargebacku. | E12 | |
| R7 | Partnerská provize se stává splatnou až 30 dní po zaplacení faktury. Přibude unikátní index `(invoice_id, kind)`. | E7 | |
| R8 | SS-7: `support.customer_impersonate` se odebírá roli `support_manager`, dokud nebude impersonace přes bus se čtyřma očima. | B6 | Po dokončení TASK-0043. |
| R9 | Tokeny: výchozí platnost 365 dní (konfigurovatelný strop). `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` se zapne po spuštění `operator:tokens:unbound`. | D6 | |
| R10 | Admin rozhraní: role bez oprávnění dostane `none`, ne `admin`. Navigace se řídí oprávněními. | B2 | |
| R11 | `/m` a `/widgets` jsou do napojení jen pro staff/demo, s `noindex` a bannerem „koncept“. | C0 | |
| R12 | Doplňky se obnovují spolu s rodičem (`ONHOST_ADDON_RENEWALS=true`) po ověření testem. | C11 | |
| R13 | Platební dlaždice, které nefungují (Apple Pay, PayPal, krypto, SEPA), se skryjí. | C2 | |
| R14 | Veřejné stránky produktů bez napojení na katalog zobrazí „připravujeme“ bez ceny a bez tlačítka do košíku. | C2 | |

## Rozhodnutí přijatá ve fázi D (2026-10-04)

| # | Rozhodnutí | Balík | Poznámka |
|---|---|---|---|
| D-1 | Opakování webhooku čeká celé zpoždění mezi pokusy: 6 pokusů, dohromady asi 14,6 h. | D4 | TASK-0077 #70. |
| D-2 | Verze API je 1.0.0 a je oddělená od verze aplikace 4.0. | D7 | TASK-0076 #69; hlavičky `X-API-Version`, `Deprecation`, `Sunset`. |
| D-3 | Throttle neúspěšné autentizace počítá jen požadavky s bearer tokenem, limit 60 za minutu. | D7 | TASK-0076 #69. |
| D-4 | Okno rozpracované idempotentní operace je `max(600, 2 × max_execution_time + 60)` sekund. | D5 | TASK-0075 #68. |
| D-5 | Odchozí webhooky smějí na porty 443 a 8443. | D4 | TASK-0077 #70. |
| D-6 | R9 (platnost tokenů) se ve fázi D řeší jako dokumentace. | D6 | TASK-0079 #72. |

## Rozhodnutí přijatá ve fázi E

| # | Rozhodnutí | Provedeno | Poznámka |
|---|---|---|---|
| E-R1 | R7 platí od nasazení dál a i pro marketplace; existující řádky svůj stav nemění. | TASK-0097 #89 | Migrace se zastaví nad skutečným dvojím naúčtováním (rozhoduje finance). |
| E-R2 | Pořadí čerpání kreditu: nejdřív zakoupený. | TASK-0099 #92 | Změna je na jednom místě, `RefundableCredit::replay`. |
| E-R3 | R5 je zavedeno úzce podle textu rozhodnutí (objednávky nad 5 000 Kč, výplaty partnerům). | TASK-0096 #88 | Rozdělení objednávky na menší je známý limit. |
| E-R4 | Drop-in usranalyse se dosazuje jen tam, kde je knihovna zjištěna, s výjimkami podle hostu. | TASK-0082 #77 | Ověření direktivy na serveru provede operátor. |
| E-R5 | `EnsureStaff` a oracle existence: člen organizace bez oprávnění dostane 403, cizí 404. | TASK-0098 #91 | |

## Rozhodnutí přijatá ve fázi F (2026-10-05)

| # | Rozhodnutí | Provedeno | Poznámka |
|---|---|---|---|
| F-1 | Dokumenty ručních testů hlídají testy: cesty, události, odkazy E2E a matice rolí. Matice rolí se generuje (`onhost:docs:roles --check` v CI). | #94, #95, #97, #98 | Dokument, který se rozejde s kódem, shodí CI. |
| F-2 | Čekající (nepotvrzený) refund se zákazníkovi nikdy neoznamuje. | #99 | `payment.refunded` jde až po potvrzení refundu. |
| F-3 | Seeder nastavuje stav poskytovatele jen při vytvoření záznamu. | #99 | Stav, který nastavil operátor, opakovaný seed nepřepíše. |

## Rozhodnutí vlastníka ve fázi G (2026-10-05)

| # | Rozhodnutí | Provedeno | Poznámka |
|---|---|---|---|
| G-R2 | **Věrnostní body lze uplatnit jako slevu** — jen výslovnou akcí zákazníka (`loyalty.redeem` přes bus, riziko NORMAL, bez step-upu) a vždy jako **samostatný řádek** objednávky a dokladu, nikdy skrytě v ceně. | TASK-0114 (G3) | Pravidla níže (výchozí doporučené hodnoty), v `config/loyalty.php`. Hlídá `G3RedeemTest` a `LoyaltyRedeemFlowTest`. |
| G-R3 | Čerpání kreditu zůstává: **nejdřív zakoupený kredit**, potom vrácený, bonusový a kredit od personálu; co nepokryje ani jeden, je dluh, který další kredit zaplatí jako první. | TASK-0112 (G4) | `RefundableCredit::replay` se nemění (potvrzuje E-R2). `RefundableCredit::of` už není strop výplaty, jen měřítko nevyčerpaného zakoupeného kreditu; pořadí hlídá `RefundableBalanceTest`. |
| G-R4 | **Kredit nelze vrátit v hotovosti** (na účet ani na kartu) v žádné vrstvě. | TASK-0112 (G4) | `WalletService::refund` a `refundableBalance` jsou odstraněné, webhook `wallet.refund.requested` zmizel z katalogu, žádná cesta API, staff ani příkaz kredit nevyplácí (`G4NoCashRefundTest`). VOP čl. 2 bod 3 a článek znalostní báze „Firemní faktury, DPH a kredit“ to říkají výslovně. Tabulka `wallet_refunds` zůstává kvůli historii. |

### G-R2: pravidla uplatnění věrnostních bodů (TASK-0114, G3)

Vlastník rozhodl, že body lze uplatnit jako slevu; konkrétní pravidla jsou doporučené výchozí hodnoty a jsou v
`config/loyalty.php` (změna pravidla je nové rozhodnutí vlastníka, zapíše se sem).

1. **Hodnota:** 1 bod = 1 Kč slevy **z ceny bez DPH**; DPH se počítá z ceny po slevě. Objednávka v eurech dostane hodnotu
   v Kč přepočtenou kurzem ČNB platným v den nabídky (zaokrouhleno dolů); dokud kurz není známý, body se neuplatní.
2. **Minimum:** jedno uplatnění je nejméně **100 bodů**; nikdy víc, než má organizace volných (zůstatek minus body
   zarezervované nezaplacenými objednávkami).
3. **Strop:** všechny slevy na řádcích, které body smějí zlevnit (promo kód, sleva za závazek, věrnostní sleva série
   a body), dohromady nejvýš **20 % jejich ceníkové ceny bez DPH**. Body vyplní jen to, co ze stropu zbývá — promo kód
   se s body **nesčítá nad strop** (sám promo kód stropem omezen není). Strop se počítá z ceny bez DPH, protože tak se
   počítají všechny slevy v nabídce i na dokladu.
4. **Co se nezlevňuje:** **doména** (zůstává za ceníkovou cenu, pravidlo vlastníka „domény nejméně rok za ceníkovou
   cenu“), **změna tarifu** (nenese žádné slevy) a **dobití kreditu** (není řádek košíku, je to platba). Doména není ani
   v základu stropu.
5. **Samostatný řádek:** sleva je řádek `loyalty-redeem` objednávky i dokladu („Sleva za věrnostní body (N bodů)“),
   se svou DPH; ceny služeb zůstávají ceníkové. Obnovy jsou za ceníkovou cenu (sleva je jednorázová).
6. **Rezervace a čerpání:** nabídka (quote) slevu spočítá, **objednávka body zarezervuje** (pod zámkem řádku organizace,
   takže dvě souběžné objednávky tytéž body neutratí — druhá dostane 409 `loyalty_points_unavailable`), **zaplacení je
   spotřebuje** (řádek historie `redeem`), **zrušení nezaplacené objednávky** (zákazníkem, personálem nebo vypršením
   lhůty) rezervaci uvolní. Volba v košíku se po objednávce maže: další objednávka je bez bodů, dokud je zákazník znovu
   nezvolí.
7. **Vrácení:** dobropis (vrácení peněz, nedodaná položka při vypořádání, zrušení zaplacené objednávky, odstoupení,
   vrácení nevyužitého období, ruční dobropis finance) vrací spolu s řádky, které body zlevnily, **stejný podíl řádku
   bodů** — zákazník dostane zpět to, co za ty řádky skutečně zaplatil, a stejný podíl bodů (polovina řádku = polovina
   bodů, poslední dobropis zbytek). Body se nikdy nemění v kredit ani v peníze. Vrácené body se připisují ke dni vrácení.
8. **Propadnutí:** body propadají **24 měsíců po připsání**; čerpá se od nejstarších, body zarezervované nezaplacenou
   objednávkou propadnout nemohou. Zákazník dostane upozornění **30 dní předem** (v panelu a e-mailem, jednou za měsíc
   propadnutí). Body připsané před zavedením pravidla se počítají jako připsané **5. 10. 2026**. Úroveň věrnostního
   programu se řídí **získanými** body: uplatnění ani propadnutí úroveň nesnižují.
9. **Idempotence a oddělení:** každý pohyb bodů je řádek s vlastním pravidlem a referencí (dvakrát doručená událost nic
   nezdvojí); organizace vidí, rezervuje a dostává zpět jen své body a jen svůj košík.
10. **Dluh bodů (review G3):** zůstatek bodů **nikdy neklesne pod nulu**. Když dobropis bere zpět víc bodů, než je volných
    (zůstatek minus body zarezervované nezaplacenou objednávkou, které se při zaplacení spotřebují), zapíše se celé odebrání
    (pravidlo `clawback.*`) a nepokrytá část jako **dluh** (`debt.carry`). Dluh se **započte proti dalším bodům**: nově
    získaným, uvolněným zrušením nezaplacené objednávky i vráceným dobropisem (řádky `clawback.debt`), dokud není splacen.
    Zákazník vidí dluh v `GET /v1/account/rewards` (`debt`). Úroveň dluh nesnižuje znovu, odebrání ji snížilo už jednou.
11. **Co body nezlevňují dál:** řádky doplňků (add-on k službě) a dokoupené navýšení limitu se body nezlevňují a nejsou
    ani v základu stropu, stejně jako domény.

**Otevřená otázka pro účetní:** sleva za body je sleva poskytnutá při prodeji (snižuje základ daně stejně jako promo kód),
ne platba; potvrďte prosím, že to tak účetní vede (řádek dokladu se zápornou cenou a DPH).

### G-R4: zákonná výjimka – odstoupení spotřebitele (§ 1831 OZ)

Citace § 1831/§ 1832 OZ je výklad pro vývoj a **musí ji potvrdit právník** (viz `resources/legal/LEGAL_REVIEW_withdrawal.md`).
Zákon (§ 1831 občanského zákoníku, čl. 13 směrnice 2011/83/EU) podle tohoto výkladu ukládá vrátit spotřebiteli, který odstoupil od smlouvy
uzavřené na dálku, přijaté peníze do 14 dnů **stejným způsobem, jakým je zaplatil**; jiným způsobem jen tehdy, když s tím
spotřebitel výslovně souhlasí a nevzniknou mu tím náklady. Proto platí:

- **Vratka na kredit** při odstoupení je zákonná jen s výslovným souhlasem. Systém ho vyžaduje: v panelu
  `confirm_refund_to_credit`, u oznámení e-mailem nebo dopisem `refund_to_credit_agreed` (personál potvrzuje, že souhlas je
  v oznámení). Vrácená částka je vrácený kredit (`refundable = false`) a v hotovosti se nevyplácí.
- **Bez souhlasu** se vrací **platba za odstoupenou smlouvu** původním způsobem: platba kartou zpět na tutéž kartu
  (`PaymentService::refund` platby objednávky), převod převodem. Nejde o výplatu kreditu a nikdy víc, než kolik stála
  odstoupená smlouva. Objednávka zaplacená z kreditu se vrací na kredit (to je stejný způsob). Zda lze odstoupit i od
  **dobití kreditu** a vrátit jeho nevyčerpanou část na kartu, je **otevřené rozhodnutí** vlastníka a právníka (otázka 1
  níže); do jeho rozhodnutí systém dobití nevrací: `PaymentService::refund` platbu s `purpose = topup` odmítne
  (`422 topup_not_refundable`, G1 #103, TASK-0111).
- **Stav dnes:** tato cesta v systému není. Panel i staff odstoupení bez souhlasu odmítnou (422), poučení slibuje vrácení
  původním způsobem „na žádost podpoře“ a `PaymentService::refund` nikdo nevolá. Napojení (staff akce se step-upem, jen
  platba objednávky, nikdy `purpose = topup`) patří do G6.

### Otevřené otázky pro vlastníka (G-R4)

1. **Dobití kreditu a odstoupení** (vlastník + právník). Je dobití kreditu spotřebitelem samostatná smlouva, od níž lze do
   14 dnů odstoupit s vrácením na kartu? Pokud ano, je to druhá zákonná výjimka (jejím stropem by mohl být nevyčerpaný
   zakoupený kredit, `RefundableCredit::of`). Poučení (čl. 2) dřív slibovalo „předplacený kredit nevyužitý v době
   odstoupení vracíme v plné výši“, což odporovalo VOP čl. 2 bod 3; v G4 je věta přeformulovaná neutrálně (platby se
   vracejí podle čl. 8 VOP a zákona, kredit se v hotovosti nevyplácí) a otázku nerozhoduje.
2. **Zůstatek kreditu při zrušení účtu** (žádost o výmaz). Kredit propadne, nebo se smí vyplatit? Dnes výmaz zůstatek neřeší.
3. **Nová verze VOP.** Čl. 2 bod 3 VOP byl upřesněn bez změny verze `2026-09` (stejně jako dřívější úpravy, viz
   `resources/legal/LEGAL_REVIEW_withdrawal.md` otázka 6). Totéž platí pro upravený čl. 2 poučení `withdrawal_waiver`.
   Rozhodněte, zda vydat nové verze.
4. **Prototypové obrazovky stále slibují vrácení kreditu** (jsou byte-identické, v G4 se neměnily):
   `apps/surfaces/onhost-content.js:244` (článek znalostní báze v demo režimu), `apps/surfaces/Onhost-admin.dc.html:1460`,
   `:2864`, `:2957`, `:2961` a `:4565` (vyprávěná data administrace). Produkční režim čte článek z databáze (opraveno);
   prototypové texty se opraví přes seam v G8.
