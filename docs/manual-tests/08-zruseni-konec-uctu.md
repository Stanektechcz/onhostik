# F8 — Zrušení služby a konec účtu

Ruční test pro vlastníka a QA. Klientská zóna: `/panel/sluzby` (detail služby, záložka Provoz), `/panel/fakturace`
(předplatné), `/panel/osobni-udaje` („Osobní údaje a odchod“). Stejné kroky automaticky projde E2E test
[`CancellationFlowTest`](../../tests/Feature/E2E/CancellationFlowTest.php) (hostingový panel a DNS server jsou náhrada, soubory
a archiv jdou do paměti). Souvislosti: [`WithdrawalTest`](../../tests/Feature/Billing/WithdrawalTest.php),
runbooky [billing-dunning](../runbooks/billing-dunning.md) a [historical-site-import](../runbooks/historical-site-import.md).

## Co je potřeba předem

| Co | Poznámka |
| --- | --- |
| Zákazník s webhostingem | Služba `ACTIVE` s databází, FTP účtem, cron úlohou a aliasovým vhostem. Pro odstoupení **spotřebitel** (typ „osoba“, bez IČO), pro srovnání **firma**. |
| Historická data | Na **testovacím** panelu vedle platformního webu jeden web, databáze, FTP, cron a alias, které platforma nevytvořila (historický web). Na živém panelu se tento test **neprovádí nikdy**. |
| Druhý správce | Člen téže organizace s rolí správce (pro zkoušku, že konec účtu smí žádat jen vlastník). |
| Step-up | Okamžité zrušení, odstoupení a žádost o smazání účtu jsou vysoké riziko: `POST /v1/auth/step-up`, v panelu dialog „Potvrďte heslem“. |
| Plánované příkazy | `php artisan onhost:billing:renewals` (konec období), `onhost:services:purge` (denně 03:40, odstranění po ochranné lhůtě), `onhost:compliance:data-requests` (každých 30 minut), `onhost:withdrawals:finish` (hodinově, dokončí zaseknutá odstoupení). |
| Přepínače | Odstoupení je za pravidlem „billing.withdrawal“ (výchozí vypnuto; zapíná se v administraci Nastavení → Automatizace, `PUT /v1/staff/automation/{key}`) a za právní revizí. „Zaplatit a obnovit“ je za pravidlem „services.reinstate“ (výchozí vypnuto). |

---

## F8-01 Zrušení ke konci období a vzetí zpět

**Kroky**

1. `/panel/fakturace` nebo detail služby → předplatné → **Ukončit předplatné** (dialog „Ukončit předplatné ke konci období?“).
   API: `POST /v1/subscriptions/{subscription}/cancel` s `cancel: true`.
2. Odpověď: `cancel_at_period_end: true`, `auto_renew: false`. Služba dál běží.
3. Vzít zpět: **Zachovat předplatné** = totéž volání s `cancel: false` → `cancel_at_period_end: false` a `auto_renew` zpět tak, jak ho zákazník měl.
4. Na konci období `php artisan onhost:billing:renewals`: předplatné se z kreditu obnoví.
5. Znovu zrušit a nechat období doběhnout. Den před koncem: nic. Po konci: obnovovací běh službu ukončí.

**Očekávaný výsledek**

- Po kroku 2 se nic nemaže a hostingový panel nedostane žádné volání.
- Po kroku 4 je období prodloužené, služba `ACTIVE`, vznikl další výpis z kreditu.
- Po kroku 5: **nejdřív závěrečný archiv** (soubory webu a každá databáze, ověřený), pak se web **deaktivuje** (odeberou se FTP a SSH přístupy, data zůstávají), služba je `SUSPENDED` s datem odstranění v budoucnu, předplatné `CANCELLED`. Panel hlásí „Zrušená služba“ a „obnovit do …“.
- Události: `subscription.expired`, `service.delegations.revoked` (delegované přístupy odebrány), `service.deletion.scheduled`, `service.deactivated`. V outboxu dále `subscription.cancel_scheduled` (2×) a `subscription.cancel_revoked` (1×).
- Účet žije dál: přihlášení, `GET /v1/invoices`, kredit.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Člen bez práva spravovat kredit | 403 (zrušení a vypnutí obnovy smí vlastník nebo správce fakturace). |
| Cizí organizace | 404. |
| Zapnout automatickou obnovu bez práva | Zamítnuto; zapnout smí vlastník nebo správce fakturace. |

**Kde hledat:** `GET /v1/subscriptions`, `GET /v1/services/{service}/operations`, `GET /v1/staff/provisioning/deletions` (fronta odstranění); doctor „no cancellation left unfinished“, „nothing past its restore window“, „archives verified“.

---

## F8-02 Odstranění po ochranné lhůtě a historické weby nedotčené

**Kroky:** po uplynutí lhůty spustit `php artisan onhost:services:purge`, počkat na frontu a porovnat panel s tím, co bylo na začátku.

**Očekávaný výsledek**

- Služba `TERMINATED`; web **a všechno, co z něj viselo** (databáze a jejich přihlášení, FTP a SSH účty, cron, aliasové vhosty) je z panelu pryč. Pořadí: přihlášení databáze až po databázi, **vhost jako poslední**.
- **Historický web, jeho databáze, FTP, cron a alias zůstaly přesně jako byly.** V záznamu smazaných věcí není ani jedno id historického webu. Platforma maže jen to, co panel prokazatelně označuje za její (vlastní klient organizace u ISPConfig, poznámka služby u aaPanelu).
- DNS: smazány jen adresní záznamy, které platforma pro web zveřejnila; **vlastní záznam zákazníka zůstává**.
- Archiv má datum uchování od odstranění a je ke stažení nebo k obnově do nové služby (`GET /v1/services/archives`); odstraněná služba už v seznamu není (`GET /v1/services/{service}` → 404).
- Nezůstalo nic na uzlu: událost `service.purge.leftover` se nesmí objevit.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Objednávka webu, který na panelu už historicky existuje | Odmítnuto (konflikt) ještě před zápisem; nic se nepřevezme. Převzetí je jen import do nového webu na žádost vlastníka (runbook historical-site-import). |
| Odstranění před koncem lhůty | 409 `grace_period_active`; předčasně jen personál s důvodem a vždy po závěrečném archivu. |
| Právní blokace | 423 `legal_hold`. |

**Kde hledat:** událost `service.purge.leftover`, `service.termination.failed`, doctor „no cancellation left unfinished“; audit akce závěrečného archivu; `docs/runbooks/provider-calls-audit.md`.

---

## F8-03 Okamžité zrušení: náhled, step-up, potvrzení, vzetí zpět

> V panelu pro okamžité zrušení není samostatné tlačítko; provádí se přes API nebo personálem.

**Kroky**

1. Náhled: `GET /v1/services/{service}/actions/terminate/preview` — co zmizí, co na tom visí, jak daleko lze zpět, otisk cíle (64 znaků). Nic nemění a nežádá step-up.
2. `POST /v1/services/{service}/terminate` s `confirm: <otisk>` a `reason` **bez** step-upu.
3. Se step-upem, ale mezitím změnit cíl (smazat databázi a založit jinou) a poslat starý otisk.
4. Nový náhled a nové potvrzení.
5. V ochranné lhůtě **vzít zpět**: detail služby → **Obnovit službu** (`POST /v1/services/{service}/resume`).
6. Po uplynutí zaplaceného období: `POST /v1/services/{service}/resume` vrátí 402; použít **Zaplatit a obnovit** (`GET /v1/services/{service}/reinstatement` s nabídkou, `POST /v1/services/{service}/reinstate`).

**Očekávaný výsledek**

- Krok 2: **403 `step_up_required`**, nic neběží. Krok 3: **409 `target_changed`**, služba dál `ACTIVE`.
- Krok 4: 202; vznikne závěrečný archiv (obsahuje databázi, která tam je **teď**), služba `SUSPENDED` s datem odstranění, předplatné `CANCELLED`, nic z dat není smazáno.
- Krok 5: služba `ACTIVE`, datum odstranění pryč, předplatné `ACTIVE` a bez konce k období (s pravidlem „services.reinstate“ se vrací i jeho fakturace); web a databáze jsou na místě; plánovaný úklid po hodinách služby, která je zpět, nic nedělá.
- Krok 6 (s pravidlem zapnutým): nové období za cenu předplatného z kreditu, při nedostatku kreditu se přání zaznamená a další dobití službu obnoví; faktura po splatnosti se musí nejdřív zaplatit.
- Události: `service.deletion.scheduled`, `service.deactivated`, `service.deletion.cancelled`.

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Zrušení služby pozastavené pro neplacení, zneužití nebo odstoupení | Zákazník ji neobnoví: 409 `service_suspension_held`; po zrušení pro neplacení jen přes zaplatit a obnovit. |
| Služba zrušená s vrácením kreditu | Zdarma se neobnoví: 409 `chargeback_cancelled`. |
| Cizí organizace | 404. |
| Nedostatek kreditu pro obnovu | Odpověď „čeká na kredit“ se schodkem; nic se nestrhne. |

---

## F8-04 Odstoupení do 14 dnů: spotřebitel a firma

**Předpoklady:** zapnuté pravidlo „billing.withdrawal“; spotřebitel objednal web (den 1), firma také.

**Kroky (spotřebitel, 5. den)**

1. Detail služby, záložka Provoz, řádek „Odstoupení od smlouvy (14 dní)“, `GET /v1/services/{service}/withdrawal`: způsobilé, lhůta do konce 14. dne od objednávky, odhad vratky.
2. **Odstoupit od smlouvy**: `POST /v1/services/{service}/withdrawal` s `confirm_refund_to_credit: true` (výslovný souhlas s vratkou na kredit) a volitelným `statement`. Nejdřív bez souhlasu, pak bez step-upu, pak se step-upem.
3. Opakovat odstoupení a pokusit se službu obnovit.
4. Po lhůtě odstranit (`onhost:services:purge`).

**Očekávaný výsledek**

- Bez souhlasu 422; bez step-upu 403 `step_up_required`; se step-upem 202.
- Pořadí: služba se **pozastaví** (blokace „withdrawal“) → dobropis na **nevyužitou část** zaplacených řádků (den oznámení se počítá jako využitý) a kredit se zvýší o odhadovanou částku → služba se zruší běžnou ságou se závěrečným archivem. Automatická obnova se vypíná při oznámení.
- Opakované odstoupení: 409. Obnovení: 409 `service_suspension_held` (blokace „withdrawal“).
- Po lhůtě je služba `TERMINATED`, kredit zůstává.
- Vrácená částka je **vrácený kredit**: nejde na kartu ani na účet a v hotovosti se nevyplácí (G-R4, VOP čl. 2 bod 3). Žádné vrácení platby u brány nevznikne a `GET /v1/wallet` neukazuje žádnou „vyplatitelnou“ částku.
- Událost `withdrawal.accepted` právě jednou (zákonné oznámení, e-mail „withdrawal-accepted“); události `withdrawal.refunded`, `withdrawal.completed`; v auditu „billing.withdrawal.accept“, „billing.withdrawal.refund“.

**Kroky (firma a pozdě)**

- Firma: `GET …/withdrawal` → nezpůsobilé, důvod `withdrawal_consumers_only`; `POST` → **403 `withdrawal_consumers_only`**, služba `ACTIVE`, žádný dobropis.
- Spotřebitel o 15 dní později: nezpůsobilé, důvod `withdrawal_period_over`; `POST` → **409 `withdrawal_period_over`**.

**Další negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Registrovaná doména | Nezpůsobilé (`withdrawal_not_applicable`, doména je provedena zápisem do registru); upozornění je už v košíku. |
| Doplněk nebo web přibalený k jiné službě | Nemá vlastní smlouvu; odstupuje se od hlavní služby. |
| Pravidlo vypnuto | 409 `withdrawal_disabled` s odkazem na dokument o odstoupení. |
| Zaplacená objednávka, z níž nic není dodané | `GET` a `POST /v1/orders/{order}/withdrawal` zruší objednávku a vrátí vše na kredit. |
| Dopis nebo e-mail | Finance zaznamenají `POST /v1/staff/withdrawals` (step-up a druhá osoba). |
| Spotřebitel nesouhlasí s vrácením na kredit | Panel i `POST /v1/staff/withdrawals` bez souhlasu odpoví 422. Zákon (§ 1831 OZ) ale ukládá vrátit **platbu** za odstoupenou smlouvu původním způsobem (kartou zaplacenou objednávku na tutéž kartu); je to jediná výjimka z G-R4 a nejde o výplatu kreditu (zda lze odstoupit i od dobití kreditu, je otevřená otázka vlastníka a právníka; do rozhodnutí se dobití nevrací). Finance ji provede v systému (G6), viz F8-06. Viz `docs/audit/2026-10-full-readiness/ROZHODNUTI.md`, G-R4. |
| Pokus vyplatit kredit po odstoupení | Neexistuje cesta v panelu, administraci, API ani příkazech (`G4NoCashRefundTest`). |
| Zaseknutý krok | Hlášen finanční schránce (událost `withdrawal.stalled`), opakuje se hodinově; doctor „consumer withdrawals move on“. |

---

## F8-05 Konec účtu: smazání jen vlastníkem, step-up, 14 dní

**Předpoklady:** organizace s vlastníkem a správcem; jedna běžící služba.

**Kroky**

1. Vlastník: `/panel/osobni-udaje` → **Nová žádost**, druh „smazání účtu a dat“ → **Odeslat žádost** (potvrzovací dotaz). API: `POST /v1/data-requests` s `kind: deletion`, `reason`. Zatímco služba běží.
2. Zrušit službu (F8-03), zkusit hned znovu, když odstranění teprve čeká; potom po odstranění služby.
3. Správce (nikoli vlastník) se step-upem zkusí totéž.
4. Vlastník bez čerstvého step-upu; pak se step-upem. Pak podruhé.
5. Uvnitř čtrnáctidenního okna: správce zruší `POST /v1/data-requests/{dataRequest}/cancel`; zopakovat zrušení.
6. Vlastník požádá znovu a okno nechá doběhnout: pět minut před koncem, pak po konci `php artisan onhost:compliance:data-requests`.
7. Po provedení se pokusit přihlásit.

**Očekávaný výsledek**

- Krok 1 a 2: **409 `deletion_blocked`** (běžící služba, čekající odstranění, neuhrazené doklady); žádná žádost se nezapíše.
- Správce: **403**; nic se nenaplánuje.
- Vlastník bez step-upu 403 `step_up_required`; se step-upem **202**, žádost ve stavu `requested`, provedení za **14 dní** od podání; druhá žádost 409 `data_request_pending`.
- Zrušení uvnitř okna: stav `cancelled`; po 15 dnech se nic nestane (vlastník aktivní, organizace neuzavřena, archiv na místě); druhé zrušení 409 `data_request_not_cancellable`.
- Nová žádost, pět minut před koncem: stále `requested`. Po konci: `completed`, organizace uzavřena, vlastník anonymizován, závěrečné archivy smazány (stav `purged`), **daňové doklady zůstávají**, hlavní kniha i audit také. Člen, který patří ještě do jiné organizace (správce), **není smazán**.
- Přihlášení smazaného vlastníka: 422.
- Událost `compliance.data_request.deletion_scheduled` (2× za dvě žádosti); audit „compliance.data_request.deletion“, „compliance.data_request.deletion_cancelled“, „compliance.data_request.deleted“.
- **Účet přežije konec služeb**: bez služby se lze přihlásit, číst doklady i kredit; uzavře se teprve provedenou žádostí.

**Negativní varianty:** token služebního účtu nebo osobní token bez step-upu nemůže (step-up nemá); cizí organizace přes cizí hlavičku nic nevidí.

**Kde hledat:** `GET /v1/data-requests`; personál `GET /v1/staff/data-requests` a `POST /v1/staff/data-requests/process`; runbook [compliance-requests](../runbooks/compliance-requests.md); doctor „restore window and retention set“.

---

## F8-06 Vrácení platby objednávky na kartu nebo účet při odstoupení (G6)

**Pravidlo (G-R1, G-R4, § 1831 OZ):** spotřebitel, který odstoupil do 14 dnů a **nesouhlasil** s vrácením na kredit, dostane zpět
**platbu objednávky** původním způsobem. Je to vrácení platby, ne výplata kreditu: dobití kreditu (`purpose: topup`) se nevrací nikdy.

**Předpoklady:** spotřebitel (objednávka uzavřená jako `b2c`) zaplatil objednávku kartou 3. den, oznámení o odstoupení poslal e-mailem
5. den; finance (`billing_finance_admin`) se step-upem. Druhý případ: objednávka zaplacená bankovním převodem.

**Kroky (karta)**

1. Jako finance bez čerstvého step-upu `POST /v1/staff/payments/{payment}/refund` s `amount`, `sent_at` (den odeslání oznámení), `reason` a `ticket_id` (tiket zákazníka s oznámením o odstoupení). Služby z vracených řádků už musí být zrušené.
2. Totéž se step-upem.
3. Stejný požadavek se stejným `Idempotency-Key` znovu.
4. Zákazník: `/panel/fakturace` a e-mail.

**Očekávaný výsledek**

- Bez step-upu 403; se step-upem 200, `state: succeeded`, `credit_note` = číslo dobropisu k dokladu objednávky.
- Dobropis na vrácenou částku (po řádcích dokladu); kredit zákazníka se **nezmění** (peníze jdou na kartu, ne na kredit).
- V hlavní knize: tržba a DPH zpět proti `liability:refund_payable:<brána>`, výplata z účtu brány; závazek skončí na nule.
- Platba je `PARTIALLY_REFUNDED` nebo `REFUNDED`. Událost `payment.refunded` jednou (s `credit_note`); zákazník dostane oznámení
  v panelu a e-mail „payment-refunded“ ve svém jazyce; věrnostní body platby se odeberou podle pravidel R6.
- Opakovaný požadavek vrátí totéž vrácení: žádná druhá platba u brány, žádný druhý dobropis.

**Kroky (bankovní převod)**

1. `POST /v1/staff/payments/{payment}/refund` se step-upem → `state: pending`; `GET /v1/staff/payments/refunds?state=pending` ho ukazuje.
2. Finance pošle příkaz z banky, pak `POST /v1/staff/payments/refunds/{refund}/confirm` s `reference` (reference platby v bance) a `reason`.
3. Potvrdit podruhé.

**Očekávaný výsledek**

- Do potvrzení: žádná událost, žádný e-mail, platba zůstává `SUCCEEDED` (částka je jen rezervovaná proti dalšímu vrácení), závazek
  `liability:refund_payable:bank` drží částku.
- Po potvrzení: vrácení `succeeded` s referencí banky a časem potvrzení, platba `REFUNDED`, událost `payment.refunded` a e-mail jednou.
- Druhé potvrzení: 409 `refund_not_pending`. Vrácení kartou potvrdit nelze (409 `refund_not_pending` / `refund_not_bank_payout`).
- Potvrzuje **jiná osoba** než ta, která vrácení zadala (403 `refund_self_confirm`); výjimka jen v režimu jednoho operátora (`ONHOST_FOUR_EYES=false`).
- Vrácená platba z banky (špatný účet): `POST /v1/staff/payments/refunds/{refund}/cancel` (step-up) → `cancelled`, rezervace na platbě se uvolní, dobropis zůstává a další výplata stejné částky ho použije (žádný druhý dobropis).

**Negativní varianty**

| Varianta | Očekávání |
| --- | --- |
| Dobití kreditu | 422 `topup_not_refundable` (kredit se nevyplácí, G-R4). |
| Platba faktury, ne objednávky | 422 `refund_payment_not_order`. |
| Více, než zbývá z platby | 409 `refund_exceeds_payment`. |
| Více, než zbývá na dokladu po dřívějších dobropisech (např. odstoupení vrácené na kredit) | 409 `refund_exceeds_document`; nic se nevyplatí dvakrát. |
| Objednávka uzavřená na firmu | 403 `withdrawal_consumers_only`. |
| Oznámení odeslané po 14 dnech | 409 `withdrawal_period_over`. |
| `sent_at` v budoucnosti | 422 `withdrawal_sent_in_future`. |
| Bez tiketu nebo s tiketem jiné organizace | 422 (`ticket_id`), `refund_evidence_mismatch`. |
| Služba z vracených řádků ještě běží | 409 `refund_service_still_running` — nejdřív ji zrušit. |
| Dvě vrácení, která spolu dosáhnou prahu | Druhé chce druhou osobu (rozhoduje se pod zámkem platby, `refund_approval_required` při souběhu). |
| Fakturace na splatnost (postpaid faktura) | 409 `refund_document_booked` (její dobropis vrací zaplacené na kredit sám). |
| Částka od prahu `onhost.billing.refund_approval_threshold` (20 000 Kč / 800 €) | 403 `approval_required`, po schválení druhou osobou 200. |
| Podpora (`support_l1`) | 403 — vrací jen finance. |

**Automaticky:** `tests/Feature/Payments/G6/OrderPaymentRefundTest.php`.

---

## Pokrytí E2E testem

| Případ | Test v `CancellationFlowTest` |
| --- | --- |
| F8-01, F8-02 | „ends a web hosting at the end of the paid period: undo, archive, deactivation, removal of everything under the site, the historical rows untouched“ |
| F8-03 | „cancels at once behind a destructive preview and a fresh step-up, refuses a confirmation that went stale, and brings the service back inside the window“ |
| F8-04 | „lets a consumer withdraw within 14 days: the unused part goes back to the credit, the service ends, and it cannot be resumed for free“; „refuses a company the 14-day withdrawal, and a consumer the day after the deadline“; kredit bez výplaty: `G4NoCashRefundTest`, `WithdrawalTest` |
| F8-06 | mimo E2E; viz `OrderPaymentRefundTest` (G6) |
| F8-05 | „ends an account only for its owner, behind a step-up and 14 days, can be stopped inside the window, and is carried out by the scheduled command“ |
