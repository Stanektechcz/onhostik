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
| R5 | Neověřený e-mail blokuje výplaty partnerům a objednávky nad 5 000 Kč. Přibude endpoint pro opakované odeslání ověřovacího e-mailu. | E1 | |
| R6 | Věrnost: body se přičítají jen za platby od 100 Kč a odebírají se při dobropisu a chargebacku. | E12 | |
| R7 | Partnerská provize se stává splatnou až 30 dní po zaplacení faktury. Přibude unikátní index `(invoice_id, kind)`. | E7 | |
| R8 | SS-7: `support.customer_impersonate` se odebírá roli `support_manager`, dokud nebude impersonace přes bus se čtyřma očima. | B6 | Po dokončení TASK-0043. |
| R9 | Tokeny: výchozí platnost 365 dní (konfigurovatelný strop). `ONHOST_TOKEN_ORGANIZATION_REQUIRED=true` se zapne po spuštění `operator:tokens:unbound`. | D6 | |
| R10 | Admin rozhraní: role bez oprávnění dostane `none`, ne `admin`. Navigace se řídí oprávněními. | B2 | |
| R11 | `/m` a `/widgets` jsou do napojení jen pro staff/demo, s `noindex` a bannerem „koncept“. | C0 | |
| R12 | Doplňky se obnovují spolu s rodičem (`ONHOST_ADDON_RENEWALS=true`) po ověření testem. | C11 | |
| R13 | Platební dlaždice, které nefungují (Apple Pay, PayPal, krypto, SEPA), se skryjí. | C2 | |
| R14 | Veřejné stránky produktů bez napojení na katalog zobrazí „připravujeme“ bez ceny a bez tlačítka do košíku. | C2 | |
