# F7 — Fakturace a upomínky: obnova, upomínky, pozastavení, platba a obnovení, kredit, dobropisy, EUR a DPH v Kč, věrnostní body

Ruční test pro vlastníka, finance a QA. Klientská zóna: `/panel/fakturace` (doklady, kredit, „Co se stane, když nezaplatíte“,
Dobít kredit), `/panel/sluzby` a detail služby. Stejné kroky automaticky projde E2E test
[`BillingDunningFlowTest`](../../tests/Feature/E2E/BillingDunningFlowTest.php) (hodiny se v něm posouvají, plánované příkazy se
spouštějí po jménu, brána a hostingový panel jsou náhrada). Body a kredit, který se nevyplácí, pokrývají
[`LoyaltyClawbackTest`](../../tests/Feature/Loyalty/LoyaltyClawbackTest.php), [`G4NoCashRefundTest`](../../tests/Feature/Billing/G4NoCashRefundTest.php) a [`RefundableBalanceTest`](../../tests/Feature/Billing/RefundableBalanceTest.php).

## Co je potřeba předem

| Co | Poznámka |
| --- | --- |
| Zákazník s webhostingem | Plán Start (89 Kč bez DPH, 107,69 Kč s DPH 21 %), služba `ACTIVE`, na webu jeden cron a jeden FTP účet. |
| Platba na fakturu | Zákazník je „na fakturu“ (schválená úvěrová linka u finance), jinak se obnova strhává z kreditu (F7-04). |
| Finance | Účet s rolí finančního správce; dobropis je vysoké riziko → čerstvý step-up (`POST /v1/auth/step-up`). |
| Hodiny | Na stagingu hodiny neposunete: dny níže se čekají, nebo se plánované příkazy spouštějí ručně a data v databázi nastavuje QA. Přesné dny jsou v tabulce F7-02. |
| Plánované příkazy | `php artisan onhost:billing:renewals` (hodinově v :20), `onhost:billing:overdue` (denně 01:15), `onhost:billing:dunning` (denně 06:00). Na žádost personálu: `POST /v1/staff/dunning/run` (vyžaduje step-up). |
| Prostředí | Lokální panely ve vývoji míří na živé panely: **do nich se nezapisuje**; pozastavení webu se zkouší jen na náhradě. |

---

## F7-01 Obnovovací faktura za ceníkovou cenu

**Kroky**

1. Týden před koncem zaplaceného období spustit `php artisan onhost:billing:renewals` (nebo počkat na plánovač).
2. Zákazník: `/panel/fakturace` nebo `GET /v1/invoices` — nová faktura; `GET /v1/subscriptions` ukazuje předplatné.
3. Spustit příkaz znovu v ten samý den.

**Očekávaný výsledek**

- Vznikne **jedna** faktura za období: vystavená týden před koncem období, splatnost 14 dní, základ 89 Kč, DPH 18,69 Kč, celkem 107,69 Kč, **sleva 0** (žádná neschválená sleva; cena je ceníková).
- Opakované spuštění téhož dne nevytvoří druhou fakturu; další období se fakturuje až o měsíc později.
- Faktura nejmenuje dodavatele infrastruktury (hostingový panel, bránu).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Den před otevřením okna | Žádná faktura, žádný případ upomínek. |
| Cizí organizace čte fakturu | 404. |
| Zákazník zkusí fakturu stornovat, upravit, smazat (`POST …/cancel`, `…/void`, `DELETE`, `PUT`, `PATCH` na `/v1/invoices/{invoice}`) | 404 nebo 405: **taková cesta neexistuje**. |
| Zákazník označí fakturu za zaplacenou (`POST /v1/invoices/{invoice}/mark-paid`) | 403. |

**Kde hledat:** doctor řádky „every order being delivered was paid and documented“, „no document is credited for more than it was issued for“; událost `subscription.renewed` v outboxu.

---

## F7-02 Upomínky po 3, 7 a 14 dnech, odklad a pozastavení po 30 dnech

Žebříček: vystavení → splatnost (+14 dní) → upomínky 3., 7., 14. den po splatnosti → odklad (14. den) → **pozastavení 30. den po splatnosti** → plánované ukončení 60. den.

**Kroky:** projet dny (příklad: faktura vystavena 5. 11., splatná 19. 11.).

| Den | Stav případu | Co se stane |
| --- | --- | --- |
| 19. 11. (splatnost) | `DUE` | nic, dnes ještě není po splatnosti |
| 22. 11. (3. den) | upomínka | první upomínka (e-mail a upozornění v panelu) |
| 26. 11. (7. den) | upomínka | druhá upomínka |
| 3. 12. (14. den) | `GRACE` | třetí upomínka, odklad |
| 18. 12. | `GRACE` | služba ještě běží |
| 19. 12. (30. den) | `SUSPENDED` | služba pozastavena |

**Očekávaný výsledek**

- Do pozastavení se webu **nic neděje** (upomínky ani odklad ho nevypnou).
- Při pozastavení se vypne **vhost webu, cron úloha i FTP účet** a platforma si to pamatuje (blokace „payment“), aby se vše dalo vrátit; vhost se přepne přesně jednou.
- U VPS se server zastaví a zamkne, u herního serveru se pozastaví v herním panelu (stejná pozastavovací sága jako pozastavení zákazníkem, viz F5-07 a F6-06).
- E-maily: tři `dunning-notice`, po pozastavení `dunning-suspended` a `invoice-overdue`; upozornění v panelu „Upomínka — neuhrazený doklad“ třikrát a „Služba byla pozastavena pro neplacení“.
- Události v outboxu: `dunning.opened`, `dunning.notice` (3×), `service.suspended`, `invoice.overdue`; v outboxu k tomu přibudou `dunning.grace` a `dunning.suspended`.
- Zákazník to vidí předem: `/panel/fakturace` → „Co se stane, když nezaplatíte“ (lhůty z nastavení účtu), `GET /v1/dunning`.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Pozastavení, které panel odmítl | Znovu se požaduje jednou denně; personál dostane upozornění „Neplacená služba stále běží“ (událost `dunning.enforcement_failed`). |
| Při výpadku odesílání pošty | Upomínky se nevynucují (nic se nepozastaví kvůli e-mailu, který nedošel). |
| Doména | Domény se nepozastavují. |

**Kde hledat:** `GET /v1/staff/dunning`, administrace Doklady (upomínky), `php artisan onhost:billing:dunning`; doctor „no service stranded in a transient state“; runbook [billing-dunning](../runbooks/billing-dunning.md).

---

## F7-03 Zaplatit kartou → služba se vrátí

**Předpoklady:** web pozastaven pro neplacení (F7-02), zákazník přihlášen.

**Kroky**

1. Detail služby ukazuje `SUSPENDED`; `GET /v1/invoices` ukazuje fakturu po splatnosti a novější vystavenou.
2. Zaplatit nejstarší: `POST /v1/invoices/{invoice}/pay` s `method: card`. Odpověď nese částku (107,69 Kč), stav platby čeká na zákazníka a `redirect_url` na bránu.
3. **Přesměrování není platba**: faktura zůstává po splatnosti a web vypnutý.
4. Zaplatit v bráně; brána volá `POST /v1/webhooks/payments/comgate` (druhé volání `duplicate`).
5. Počkat na frontu.

**Očekávaný výsledek**

- Faktura `PAID`, případ upomínek vyřešen, **novější faktura se nezaplatí** (zůstává vystavená a běží jí vlastní žebříček).
- Služba `ACTIVE`, **vhost, cron i FTP zpět zapnuté**, blokace zmizela; v panelu právě jedno vypnutí a jedno zapnutí (žádné blikání).
- Karta se zaúčtuje po platformním způsobu: nejdřív kredit, pak úhrada této faktury; kredit zůstane 0. **Žádný příjmový doklad s DPH nevznikne** — daňovým dokladem je jen faktura, platba se k ní jen přiřadí (F7-10).
- Události `dunning.resolved`, `service.active`, `payment.succeeded`, `invoice.paid`; upozornění „Platba přijata, vše v pořádku“.
- Hlavní kniha je vyvážená; DPH se účtuje jednou za prodej a jednou je i na daňových dokladech (DPH na dokladech = DPH v knize).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Opakované volání brány | `duplicate`, nic se nezmění. |
| Platba už zaplacené faktury z kreditu | 409 `invoice_not_payable`. |
| Cizí faktura | 404. |

**Kde hledat:** `GET /v1/payments`, `POST /v1/payments/{intent}/sync` (dotaz na bránu); doctor „every order being delivered was paid and documented“; událost `payment.orphan_callback` (callback k neznámé platbě).

---

## F7-04 Předplacený účet bez kreditu → dobití a pokračování

**Předpoklady:** zákazník v režimu předplatného (výchozí), kredit 0, služba `ACTIVE`.

**Kroky**

1. Týden před koncem období proběhne obnova: kredit nestačí, předplatné je po splatnosti, **otevře se případ upomínek bez dokladu**.
2. Zákazníkovi chodí `renewal-failed` e-mail a upozornění; upomínky 3., 7., 14. den po konci období, pozastavení 30. den.
3. Zákazník dobije: `/panel/fakturace` → **Dobít kredit** (karta) nebo `POST /v1/wallet/topup` s `amount` (minimum 100 Kč) či `POST /v1/payments/init`; zaplatí v bráně.
4. Pozastavená služba se vrátí a další cron strhne i období, které začalo během výpadku.

**Očekávaný výsledek**

- Pokus o obnovu selže s příčinou „kredit“ a každý denní pokus se počítá jednou; předplatné `PAST_DUE`.
- Po dobití: kredit zaplatí období, které zákazník využil; mrtvé dny se **neúčtují dvakrát**; předplatné `ACTIVE`, počet neúspěchů 0, případ vyřešen, služba `ACTIVE`, vše zapnuté.
- Další cron: období, které začalo za pozastavení, se strhne **jednou**, za ceníkovou cenu (89 Kč + DPH), bez slevy.
- Události: `subscription.renewal_failed` (u každého pokusu), `dunning.opened`, `wallet.topup.completed`, `subscription.renewed`, `dunning.resolved`.
- Doklad k dobití: firma nebo podnikatel (IČO, DIČ; plátce i neplátce DPH) dostane daňový doklad k přijaté platbě; spotřebitel dostane **potvrzení o přijetí platby** (typ `confirmation`, bez DPH, není daňový doklad). Když ONhost není plátce DPH, dostane potvrzení každý (F7-10).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Pevný měsíční rozpočet zákazníka | Obnova se odmítne a zákazník je informován, že příčinou je rozpočet (ne kredit); zvýšení rozpočtu a další pokus ji zaplatí. |

**Kde hledat:** `GET /v1/wallet`, `GET /v1/wallet/transactions`, `GET /v1/subscriptions`; doctor „the national bank's exchange rates are fresh“ jen u cizí měny.

---

## F7-05 Platba faktury z kreditu

**Kroky**

1. Zákazník na fakturu (úvěrová linka jen 50 Kč) má neuhrazenou fakturu 107,69 Kč. `POST /v1/invoices/{invoice}/pay` s `method: wallet`.
2. Dobít 500 Kč kartou a zopakovat platbu.
3. Zkusit stejnou platbu potřetí.

**Očekávaný výsledek**

- Bez kreditu: **402 `insufficient_funds`**, v odpovědi `required` (107,69 Kč) a `available`; nic se nepohnulo, případ upomínek běží dál.
- Po dobití: faktura `PAID`, kredit klesne o 107,69 Kč, pohledávka 0, případ vyřešen.
- Podruhé: 409 `invoice_not_payable`; platba z kreditu **není přijatá platba**, nevzniká nový příjmový doklad.

**Negativní varianty:** člen bez práva platit z kreditu → 403; při zapnutém schvalování plateb z kreditu platí jen vlastník a finanční správce (`credit_spend_not_allowed`).

---

## F7-06 Dobropisy vystavuje finance (step-up)

**Kroky**

1. Zákazník zkusí `POST /v1/invoices/{invoice}/credit-note` → **403**. Totéž `mark-paid` → 403.
2. Finance s čerstvým step-upem: dobropis na část řádku (`amounts`: id řádku → hrubá částka v haléřích, např. 5000), `reason` alespoň 5 znaků, `return_to_credit: true`.
3. Zbytek: stejné volání bez `amounts`.
4. Třetí dobropis téže faktury.
5. Dobropis na **nezaplacenou** fakturu (celou), když je služba z jejího důvodu pozastavená; pak `php artisan onhost:billing:dunning`.

**Očekávaný výsledek**

- Část: dobropis −50 Kč (základ −41,32, DPH −8,68), 50 Kč se vrátí na kredit; faktura zůstává `PAID`.
- Zbytek: −57,69 Kč (DPH −10,01); součet obou = celá faktura 107,69 Kč a DPH 18,69 Kč; faktura `CREDITED`; kredit vrácen jen o to, co zákazník skutečně zaplatil.
- Třetí: 409 `invoice_nothing_to_credit` (další kódy: `invoice_line_already_credited`, `invoice_credit_exceeds_line`, `invoice_not_creditable`).
- Hlavní kniha vyvážená, DPH zrušené faktury zmizelo.
- Nezaplacená faktura: dobropis bez peněz na kreditu (nic nebylo zaplaceno), další běh upomínek **případ ukončí a službu obnoví** (blokace zmizí); novější faktura nadále běží.
- Vrácené peníze jsou „vrácený kredit“, ne dobití: viz F7-09.

**Negativní varianty:** finance bez čerstvého step-upu 403 `step_up_required`; cizí faktura 404.

**Kde hledat:** audit akcí finance, doctor „no document is credited for more than it was issued for“, událost `invoice.issued` (dobropis je doklad).

---

## F7-07 Faktura v EUR: DPH v Kč

**Předpoklady:** zákazník fakturovaný v EUR (např. 4,00 EUR základ, 21 %); doctor „the national bank's exchange rates are fresh“ zelený.

**Kroky:** vystavit obnovovací fakturu (`onhost:billing:renewals`), otevřít ji (`GET /v1/invoices/{invoice}`, PDF `GET /v1/invoices/{invoice}/pdf`). Pak simulovat výpadek ČNB, vystavit druhou fakturu a spustit `php artisan onhost:fx:sync`. Nakonec dobropis k první faktuře.

**Očekávaný výsledek**

- Faktura: 4,00 EUR + DPH 0,84 = 4,84 EUR a v odpovědi blok `czk`: zdroj `cnb`, základ „den zdanitelného plnění“, kurz 24,335, základ 97,34 Kč, DPH 20,44 Kč; kurz a datum seznamu jsou na PDF.
- Při neodpovídající bance se doklad **vystaví přesto**, částky nezměněné, se značkou čekání; příští `onhost:fx:sync` doplní rekapitulaci a PDF.
- Dobropis má **kurz původní faktury** (−97,34 Kč, DPH −20,44 Kč), nikoli kurz dne.
- Zálohová faktura a výpis z kreditu rekapitulaci v Kč nenesou (nejsou daňové doklady).

**Kde hledat:** doctor „every tax document in another currency states its VAT in CZK“ a „the national bank's exchange rates are fresh“; runbook billing-dunning, oddíl „VAT in CZK“.

---

## F7-08 Věrnostní body od 100 Kč a odebrání při dobropisu

**Kroky**

1. Zákazník: `GET /v1/account/rewards` (body, úroveň, odznaky).
2. Zaplatit objednávku 400 Kč, pak platbu 99,99 Kč a 100 Kč.
3. Finance vystaví dobropis na polovinu dokladu (200 Kč), pak na zbytek.

**Očekávaný výsledek**

- **Bod za každých 100 Kč objednávky** a +10 bodů za přijatou platbu, ale **jen platby a objednávky od 100 Kč** (u EUR od 4 €); 99 Kč nebo 99,99 Kč nedají nic; opakované doručení téže události bod nepřidá.
- Příklad: objednávka 400 Kč = 4 body za objednávku + 10 za platbu = 14.
- Polovina dobropisem: odebrány 2 body z objednávky, bod za platbu zůstává (12). Zbytek: body klesnou na 0, **nikdy pod nulu**, jen jednou, a nikdy jiné organizaci.
- Body nejsou peníze: žádný pohyb na kreditu. Úroveň už jednou dosažená (a její bonus) zůstává.
- Zákazník dostane upozornění a záznam v historii; událost `loyalty.clawback`.

---

## F7-09 Kredit se v hotovosti nevrací; zakoupený kredit se čerpá první

**Pravidlo (G-R4):** kredit se **nikdy nevyplácí** v hotovosti, na bankovní účet ani na kartu. Vrácený kredit (z dobropisu,
z odstoupení, ze zrušení služby), bonus a kredit od personálu zůstávají na účtu k čerpání. **G-R3:** zakoupený kredit se čerpá
jako první, potom ostatní. Jediná zákonná výjimka je vrácení **platby** za smlouvu, od níž spotřebitel odstoupil a s vrácením
na kredit nesouhlasil (§ 1831 OZ, viz `08-zruseni-konec-uctu.md`, F8-04).

**Kroky**

1. Dobít 1 000 Kč, utratit 800 Kč, nechat vrátit 800 Kč (vrácený kredit).
2. V panelu `/panel/fakturace` a v `GET /v1/wallet` zkontrolovat zůstatek a hledat jakoukoli „vyplatitelnou“ částku nebo tlačítko
   „Vyplatit kredit“.
3. Jako finance (`billing_finance_admin`) v administraci u zákazníka hledat výplatu nebo vrácení kreditu na účet či kartu.
4. Přečíst VOP (`/dokumenty/vop`, čl. 2 bod 3) a článek znalostní báze „Firemní faktury, DPH a kredit“.

**Očekávaný výsledek**

- Dostupný zůstatek 1 000 Kč; odpověď `GET /v1/wallet` ani výpis transakcí neobsahuje žádné pole `refundable`.
- Panel ani administrace nenabízí výplatu kreditu; API nemá žádnou cestu k výplatě kreditu a žádný plánovaný příkaz kredit
  nevyplácí. Odběr webhooku nenabízí událost výplaty kreditu.
- VOP i znalostní báze říkají, že kredit v hotovosti vrátit nelze.
- Zakoupený kredit se utrácí jako první: z 1 000 Kč zakoupených zbývá po kroku 1 nevyčerpaných 200 Kč (hlavní kniha,
  `RefundableBalanceTest`); ani těch 200 Kč se nevyplácí.

**Poznámka:** v této verzi kredit nevyplácí žádná cesta (dřívější `WalletService::refund` je odstraněný). Plné pokrytí
dávají `G4NoCashRefundTest` a `RefundableBalanceTest`.

---

## F7-10 Daňovým dokladem je jen faktura (G1)

**Pravidlo (rozhodnutí vlastníka G-R1):** jeden prodej má jeden daňový doklad. Zaplacení už vystavené faktury — kartou, převodem
nebo z kreditu — fakturu jen uhradí; **žádný další doklad s DPH nevzniká**. Dříve se každá platba kartou či převodem připsala na
kredit s příjmovým dokladem (s DPH) a pak se z kreditu zaplatila faktura, takže faktura i příjmový doklad byly oba daňovými doklady.

| Platba | Doklad |
| --- | --- |
| Faktura zaplacená kartou, převodem nebo z kreditu | žádný nový; faktura je daňový doklad |
| Platba vyšší, než kolik faktura ještě dluží (mezitím dobropis) | zbytek jde na kredit a dostane doklad k dobití |
| Objednávka zaplacená kartou nebo převodem | daňový doklad k přijaté platbě (vyúčtování z kreditu daňovým dokladem není); objednávka převodem na zálohovou fakturu končí u plátce konečnou fakturou, která zálohu odečte (F7-11) |
| Dobití kreditu firmou nebo podnikatelem (IČO, DIČ; plátce i neplátce DPH, i zahraniční) | daňový doklad k přijaté platbě (sazbu, přenesení daňové povinnosti či OSS určí daňový modul) |
| Dobití kreditu spotřebitelem | potvrzení o přijetí platby, **není daňový doklad**, bez DPH |
| Cokoli z výše uvedeného, když ONhost **není plátce DPH** | potvrzení o přijetí platby; faktura je jen „Faktura“, žádný doklad se nejmenuje daňový |
| Dvě platby téže faktury (dvě záložky, karta i převod) | uhradí ji jen jedna; druhá zůstane kreditem s dokladem k dobití |

**Kroky**

1. Zákazník na fakturu zaplatí vystavenou fakturu kartou (F7-03) a druhou převodem (`POST /v1/invoices/{invoice}/pay` s `method: bank`, finance zapíše řádek výpisu `POST /v1/staff/payments/bank/lines`).
2. Spotřebitel (osoba bez IČO a DIČ) dobije kredit (`POST /v1/wallet/topup`) a zaplatí z něj další fakturu (F7-05); firma dobije také.
3. Otevřít doklady (`/panel/fakturace`, `GET /v1/invoices`) a PDF (`GET /v1/invoices/{invoice}/pdf`).
4. Finance spustí `php artisan onhost:billing:double-tax-report` (volitelně `--organization=`, `--since=2026-01-01`, `--json`).

**Očekávaný výsledek**

- Po kroku 1 a 2: u každé zaplacené faktury **žádný příjmový doklad**; u dobití spotřebitele jen potvrzení o přijetí platby (DPH 0), u dobití firmy daňový doklad k přijaté platbě.
- Potvrzení o přijetí platby nemá UBL: stažení UBL odpoví 409 `document_not_exportable`.
- PDF potvrzení, vyúčtování z kreditu a zálohové faktury nese „není daňový doklad“; daňový doklad k přijaté platbě se tak jmenuje.
- DPH na daňových dokladech organizace = DPH v hlavní knize (účtuje se jednou).
- Report vypíše **historické** dvojice faktura + příjmový doklad k její vlastní platbě (číslo faktury, číslo dokladu, DPH dokladu, součty za měnu). **Nic neoznačí, nestornuje ani nesmaže**; opravu (např. dobropis dokladu) rozhoduje účetní.

**Kde hledat:** runbook [billing-dunning](../runbooks/billing-dunning.md), oddíl „Only the invoice is a tax document“. Automaticky:
[`G1OnlyInvoiceTest`](../../tests/Feature/Billing/G1OnlyInvoiceTest.php) a E2E `BillingDunningFlowTest` (F7-03, F7-05).

---

## F7-11 Režim plátce DPH, zálohová faktura a zúčtování zálohy (G2)

**Pravidlo (G-R1, G2):** režim plátce / neplátce DPH je **konfigurace** (`ONHOST_VAT_PAYER`) zapsaná do právnické osoby
(finance se step-upem a druhou osobou, `POST /v1/staff/tax/vat-payer-mode`). Doklad si pamatuje dodavatele, s nímž byl vystaven: přepnutí režimu **nezmění žádný
vystavený doklad**. Zálohová faktura (řada PF) daňovým dokladem není. Plátce k přijaté platbě vystaví daňový doklad k přijaté
platbě (řada PP, DUZP = den připsání platby) a konečná faktura (řada FV) zálohu odečte; neplátce vystaví potvrzení o přijetí
platby a žádný daňový doklad.

**Předpoklady:** firma s DIČ CZ ověřeným ve VIES; účet finance; doctor řádek „VAT payer mode“ zelený.

**Kroky**

1. `php artisan onhost:vat:payer-mode` — vypíše režim (plátce), hodnotu právnické osoby, `ONHOST_VAT_PAYER` a daňových pravidel.
2. Zákazník objedná webhosting s platbou převodem (`POST /v1/orders`, platba `bank`); otevře zálohovou fakturu v `/panel/fakturace`.
3. Finance zapíše řádek výpisu s variabilním symbolem zálohové faktury (`POST /v1/staff/payments/bank/lines`), datum připsání včera.
4. Otevřít doklady (`GET /v1/invoices`) a PDF (`GET /v1/invoices/{invoice}/pdf`): zálohovou fakturu, daňový doklad k přijaté platbě, konečnou fakturu.
5. Finance vystaví dobropis k daňovému dokladu k přijaté platbě (F7-06, `POST /v1/invoices/{invoice}/credit-note`).
6. Na stagingu nastavit `ONHOST_VAT_PAYER=false`, spustit `php artisan onhost:doctor` (řádek „VAT payer mode“ ukáže nesoulad a radu),
   pak finance se step-upem pošle `POST /v1/staff/tax/vat-payer-mode` (`payer: false`, důvod) → 403 `approval_required`, druhá
   osoba schválí (`/sprava/nastaveni/schvalovani`), finance pošle stejný požadavek s `approval_ids` a zopakuje kroky 2–4.
   `php artisan onhost:vat:payer-mode --apply` musí skončit odmítnutím. Nakonec režim vrátit stejnou cestou (`ONHOST_VAT_PAYER=true`).

**Očekávaný výsledek**

- Zálohová faktura: číslo `PF-…`, nadpis „Zálohová faktura (není daňový doklad)“, **bez DUZP**, bez rekapitulace v Kč.
- Daňový doklad k přijaté platbě: číslo `PP-…`, **DUZP = den připsání platby** (ne den spárování), DPH po sazbách přesně jako
  objednávka (ne jedna sazba z celé částky), odkaz na číslo zálohové faktury.
- Konečná faktura: číslo `FV-…`, „Faktura – daňový doklad“, oddíl **„Zúčtování zálohy“** s číslem daňového dokladu k přijaté platbě,
  odečtený základ a DPH po sazbách, rozdíl k vyúčtování 0 a „Zbývá uhradit 0 Kč“.
- Na každém daňovém dokladu plátce: dodavatel a odběratel s IČO a DIČ, číslo, datum vystavení, DUZP, popis, množství, cena za
  jednotku bez DPH, rekapitulace DPH po sazbách (základ, sazba, daň), celkem; poznámka o zaokrouhlení DPH na haléře (§ 37).
  U faktury v EUR rekapitulace v Kč kurzem ČNB ke dni DUZP (F7-07); u firmy z jiného státu EU s platným DIČ „Daň odvede zákazník“
  a obě DIČ.
- Dobropis k dokladu k přijaté platbě: řada `DK-…`, záporné DPH, odkaz na opravovaný doklad.
- Po přepnutí na neplátce: nová objednávka převodem dostane jen potvrzení o přijetí platby a výpis z kreditu; faktury nesou
  „Faktura“, „Neplátce DPH“ a „Tento doklad není daňovým dokladem“, žádné DUZP ani DPH; faktura v EUR **nemá** rekapitulaci v Kč.
  Doklady vystavené před přepnutím zůstanou beze změny (PDF i data).

**Kde hledat:** runbook [vat-payer-mode](../runbooks/vat-payer-mode.md) a [billing-dunning](../runbooks/billing-dunning.md), oddíl „VAT
payer mode, proformas and the final invoice“. Automaticky: [`G2VatPayerTest`](../../tests/Feature/Tax/G2VatPayerTest.php) (mimo E2E).

---

## F7-12 Podklady pro kontrolní a souhrnné hlášení (jen pro účetní)

**Kroky:** finance spustí `php artisan onhost:vat:export kh --period=2026-10 --format=xml` a `--format=csv`, pak
`php artisan onhost:vat:export sh --period=2026-10` (měsíc `RRRR-MM` nebo čtvrtletí `RRRR-Qn`).

**Očekávaný výsledek**

- KH: oddíl A.4 po dokladech pro odběratele s českým DIČ nad 10 000 Kč vč. DPH (DIČ, číslo dokladu, DUZP, základ a daň v Kč
  po sazbách; dobropis jde tam, kam opravovaný doklad), oddíl A.5 součty ostatních tuzemských plnění po sazbách. Konečná faktura
  se zálohou přispívá jen rozdílem (zálohu vykázal daňový doklad k přijaté platbě). OSS a plnění mimo EU v KH nejsou.
- SH: přenesení daňové povinnosti do jiného státu EU po odběrateli — stát, DIČ bez předpony, kód plnění 3, počet plnění,
  hodnota v celých Kč.
- XML je **koncept** struktury EPO (DPHKH1 / DPHSHV) s poznámkou, že se nic neodeslalo; účetní ho zkontroluje a podá sám.
  Doklady neplátce, doklady mimo období a doklady bez známého kurzu (vypíše varování) v podkladech nejsou. Příkaz nic nezapisuje.
- Neplatné období (např. „říjen“) příkaz odmítne (exit 1), nikdy nehádá.

**Kde hledat:** runbook [vat-payer-mode](../runbooks/vat-payer-mode.md), oddíl „KH and SH drafts“. Automaticky: `G2VatPayerTest`.

---

## Pokrytí E2E testem

| Případ | Test v `BillingDunningFlowTest` |
| --- | --- |
| F7-01, F7-02, F7-03 | „renews by invoice, reminds on the dunning schedule, suspends the unpaid hosting and brings all of it back when the card payment arrives“ |
| F7-04 | „lets a prepaid hosting go past due without credit, reminds, suspends it, and renews and resumes it when a card top-up arrives“ |
| F7-05, F7-06 | „pays an invoice from credit only when there is credit, and finance credits it back exactly once, VAT included“ |
| F7-06 (nezaplacená) | „lets the credit note of an unpaid invoice end its dunning case and the suspension it caused — and offers no other way to cancel one“ |
| F7-07 | „states the VAT of a renewal invoice in euro in crowns at the bank rate of the supply day, and its credit note at the rate of the invoice“ |
| F7-08, F7-09 | mimo E2E; viz `LoyaltyClawbackTest`, `G4NoCashRefundTest`, `RefundableBalanceTest` |
