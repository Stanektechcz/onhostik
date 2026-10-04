# API changelog

Každý záznam je nadpis `## datum | druh | název | okno` a odstavec pod ním. Druh je `breaking`, `compatible`, `new` nebo
`security`; okno je volitelné. Stránka `/dokumentace/api` i `/api` čtou tenhle soubor (`PublicApiDocs::changelog()`),
takže tu nikdy nestojí nic jiného než na webu. Nový záznam patří nahoru.

## 2026-10-04 | compatible | Hlavičky verze a zastaralosti u každé odpovědi (D7)
Každá odpověď nese `X-API-Version`. Cesty, které jednou zastaráme, dostanou hlavičky `Deprecation`, `Sunset` a `Link`; zatím žádnou nemáme. Požadavky s neplatným klíčem se počítají na adresu a po překročení limitu dostanou 429; požadavek bez klíče se nepočítá. Seznamy se řadí stabilně i při stejném čase vzniku.

## 2026-10-04 | compatible | Idempotency-Key je v rozsahu klíče a atomický (D5)
Klíč se hledá jen v rozsahu volajícího (klíč nebo uživatel a organizace), takže cizí klíč nikdy nevrátí cizí odpověď. Dva souběžné požadavky se stejným klíčem se už nespustí oba: druhý dostane `409 idempotency_in_progress` a `Retry-After`. Odpověď s tajemstvím se neuchovává; její opakování je `409 already_done`. Nejdelší klíč je 200 znaků, `422 invalid_idempotency_key`.

## 2026-10-04 | breaking | Koncové body /v1/staff/* jen pro personál (D2) | 0 dní
Cesty `/v1/staff/*` ověřují, že volající jedná jako personál a má alespoň jedno oprávnění personálu; ostatním odpovídají `403 staff_only`, ještě před hledáním objektu. `GET /v1/staff/services/{service}/panel-login` zmizel (měnil stav); odpovídá `405`, přihlášení do panelu je `POST`. Zákaznický klíč na tyhle cesty nikdy nesměl, takže zákaznický skript se nemění.

## 2026-10-04 | breaking | Nová operationId, smlouva odpovídá trasám (D1) | bez okna
`operationId` je teď složené z metody a cesty (`getServices`, `postDomainsByDomainHolder`) a je jedinečné; dřívější názvy se opakovaly, takže generátory klientů je přepisovaly. Smlouva obsahuje jen trasy, které existují, bearer klíč jen tam, kam ho pustí kontrola rozsahů, a u každé operace pole `x-token-scope` s rozsahem, který klíč potřebuje. Kdo ze smlouvy generuje klienta, vygeneruje ho znovu.

## 2026-10-04 | new | Dokumentace API mluví pravdu (D3)
Stránky /api a /dokumentace už neukazují koncové body, hlavičky ani limity, které API nemá (ukázkové koncové body serverů, záloh, auditu a exportu a potvrzovací hlavičku u mazání). Limity, hlavičky, opakování webhooků a seznam chybových kódů se skládají z kódu a konfigurace; `/dokumentace/api` obsahuje referenci z `openapi.yaml`.
