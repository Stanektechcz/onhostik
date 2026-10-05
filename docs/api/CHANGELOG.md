# API changelog

Každý záznam je nadpis `## datum | druh | název | okno` a odstavec pod ním. Druh je `breaking`, `compatible`, `new` nebo
`security`; okno je volitelné. Stránka `/dokumentace/api` i `/api` čtou tenhle soubor (`PublicApiDocs::changelog()`),
takže tu nikdy nestojí nic jiného než na webu. Nový záznam patří nahoru.

## 2026-10-05 | compatible | Testovací událost webhooku bez Idempotency-Key narazí na limit
`POST /v1/webhooks/{endpoint}/ping` bez hlavičky `Idempotency-Key` je pokaždé nový požadavek: druhý ping v době čekání odpoví `429 webhook_ping_cooldown` s `retry_after`. Dřív se dva pingy během jedné minuty sloučily a druhý dostal znovu první odpověď `202`. Se stejnou hlavičkou `Idempotency-Key` se ping dál jen zopakuje; založení, otočení tajemství a ostatní zápisy webhooků zůstávají bez hlavičky idempotentní v rámci minuty.

## 2026-10-05 | new | GET /v1/me odpoví i klíči servisního účtu
Klíč servisního účtu se na `GET /v1/me` dozví, kdo je: `type: service_account`, účet (id, název), organizace (id, název), role účtu, rozsahy klíče a jeho platnost. Dřív dostal `403 person_required`. Odpověď osobě nese nově `type: person`, jinak je beze změny. Ostatní koncové body určené člověku odpovídají servisnímu účtu dál `403 person_required`.

## 2026-10-05 | security | Cizí organizace v X-Organization odpoví 404
Kdo pošle `X-Organization` (nebo `?organization=`) organizace, do které nepatří, dostane `404 not_found` stejně jako u identifikátoru, který neexistuje; dřív odpověď `403` prozradila, že organizace existuje. Člen organizace, kterému chybí oprávnění, dostává dál `403` s názvem chybějícího oprávnění.

## 2026-10-05 | compatible | Klíč servisního účtu na staff cestě dostane 403, ne 401
Klíč servisního účtu, který zavolá cestu pod `/v1/staff/`, dostává `403 staff_only` (účet je přihlášený, jen to není zaměstnanec). Dřív odpověď zněla `401 unauthenticated` "Sign in to continue", což klienta vyzývalo k novému přihlášení s platným klíčem.

## 2026-10-04 | new | Servisní účty organizace a rozsah dns:read (D6)
Vlastník organizace spravuje servisní účty (`/v1/service-accounts`, vydání a zrušení klíče účtu pod `/v1/service-accounts/{account}/tokens`); tajemství klíče se ukáže jen v odpovědi, která ho vydala, a každý zápis chce čerstvé potvrzení. Klíč servisního účtu jedná za svou organizaci; koncový bod určený člověku odpoví `403 person_required`. Čtení DNS zóny má vlastní rozsah `dns:read` (`dns:write` ho dál zahrnuje, takže dosavadní klíče čtou dál) a aliasy `/v1/domains/{zone}/zone` se řídí stejnými rozsahy jako `/v1/dns/zones/{zone}`.

## 2026-10-04 | breaking | Webhooky: podepsané doručení z fronty, https na portu 443 nebo 8443 (D4) | bez okna
Doručení jde z fronty a nese jen veřejná pole události v obálce `{id, event, created_at, data: {aggregate, organization_id, payload}}`; podpis je `X-ONhost-Signature: v1=<hex>` přes `<X-ONhost-Timestamp>.<tělo>`. Doručení je alespoň jednou, stejné `X-ONhost-Delivery` může přijít víckrát, takže příjemce deduplikuje. Odběr smí mířit jen na https, port 443 nebo 8443 (`webhook_port_not_allowed`); starší odběr jinam dostává neúspěšná doručení. Opakuje se po 1, 6, 36, 156 a 876 minutách od prvního pokusu (šest pokusů), odběr po 20 neúspěšných pokusech za sebou přejde do stavu `suspended`, zkušební doručení `webhook.ping` lze poslat jednou za 30 s (`webhook_ping_cooldown`) a jedno doručení lze zkusit nejvýš 10krát včetně ručních opakování.

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
