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
