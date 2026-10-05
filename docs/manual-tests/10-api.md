# 10 – API: ruční testy s ověřenými ukázkami curl

Tenhle scénář projde veřejné API zákazníka krok za krokem tak, jak ho projde člověk s terminálem: osobní klíč, rozsahy,
organizace, stránkování, `Idempotency-Key`, verze a limity, chyby, webhooky a servisní účet. Platí pro **staging** nebo
lokální instalaci, nikdy pro produkci. Smlouva je `contracts/openapi/onhost-v1.yaml`, přehled pravidel `docs/api/README.md`.

**Každá ukázka `curl` v tomhle souboru je zkoušená.** `tests/Feature/Docs/ApiManualExamplesTest.php` ji přehraje proti
aplikaci (metoda, cesta, hlavičky, tělo) a ověří, že odpověď má stav z řádku `# expect:`. Zároveň ověří, že cesta je ve smlouvě
a že rozsah z řádku `# scope:` je opravdu ten, který smlouva (`x-token-scope`) pro operaci uvádí. Když se API změní a ukázka
přestane platit, test selže; když ukázku upravíte, upravte i řádek se značkou.

## Značky nad příkazem

Značky jsou obyčejné komentáře v bashi, takže ukázku můžete vkládat do terminálu i s nimi.

| Značka | Význam |
| --- | --- |
| `# expect: 200` | stav odpovědi, který máte dostat |
| `# scope: services:read` | rozsah klíče, který operace potřebuje; `any` = stačí jakýkoli klíč nebo žádný (veřejné cesty, `/me`), `none` = jen přihlášený panel, klíč se nepřijímá |
| `# expect-error: slug` | pole `error` v odpovědi |
| `# expect-header: Název` nebo `Název: hodnota` | hlavička odpovědi je přítomná, případně má danou hodnotu |
| `# expect-json: cesta=hodnota` | pole v JSON odpovědi (`data.0.state=suspended`) |
| `# expect-contains:` / `# expect-not-contains:` | text, který v odpovědi je, nebo nesmí být |
| `# save: PROMENNA=cesta` | zapamatuje si pole odpovědi pro další kroky (ID, tajemství) |
| `# arrange: …` | přípravu, kterou člověk udělá rukou nebo počká (čas, souběžný požadavek); u každé je v textu napsáno, co s ní |
| `# expect-count: cesta=N` | počet prvků pole v JSON odpovědi |
| `# setup` | blok bez `curl`, jen nastavení prostředí |
| `# run: název` | ukázka kódu (bash, PHP), kterou test spustí na skutečném podpisu |

## 0. Příprava

Potřebujete účet **vlastníka** organizace na stagingu (ne zaměstnance) a druhý účet s rolí správce téže organizace
(`org_admin`). Vlastník má zapnutý autentizační kód (TOTP), správce ne. Heslo ani kód nikdy nevkládejte do příkazu napřímo,
jen do proměnných prostředí, a po testu `history -c`.

```bash
# setup
export ONHOST_BASE=https://panel.staging.example.cz
export ONHOST_API=$ONHOST_BASE/v1
export ONHOST_EMAIL=vlastnik@example.cz
export ONHOST_PASSWORD='…'
export ONHOST_ADMIN_EMAIL=spravce@example.cz
export ONHOST_ADMIN_PASSWORD='…'
export JAR=$(mktemp)
export JAR_ADMIN=$(mktemp)
```

Proměnné `ONHOST_TOTP` (šestimístný kód z aplikace; **každý kód jde použít jen jednou**, další si nechte vygenerovat) a
`ONHOST_TOKEN` (klíč, který dostanete v kroku 2) nastavujete postupně.

Webhooky potřebují příjemce na `https` (port 443), kterého ovládáte: vlastní malý skript, nebo dočasná adresa u služby na
zachytávání požadavků. Adresu uložte do `ONHOST_HOOK_URL`.

Přihlášená relace panelu potřebuje CSRF token. Pomocná funkce ho přečte z cookie souboru a odkóduje:

```bash
# setup
xsrf() { local v; v=$(awk '$6=="XSRF-TOKEN"{print $7}' "$1"); printf '%b' "${v//%/\\x}"; }
```

## 1. Veřejný koncový bod a hlavička verze

Stav služeb klíč nepotřebuje. Každá odpověď API nese `X-API-Version` (verze smlouvy, ne vaší instalace).

```bash
# expect: 200
# scope: any
# expect-header: X-API-Version
curl -si "$ONHOST_API/status" -H "Accept: application/json"
```

Cesta, kterou jednou zastaráme, dostane navíc `Deprecation`, `Sunset` a `Link`. Dnes žádná taková není, v odpovědi je
proto nenajdete.

## 2. Přihlášení, step-up a osobní klíč

Klíč se vytváří jen v panelu (nebo přes relaci panelu, jak to dělá tenhle krok), nikdy jiným klíčem. Vytvoření klíče je akce
s vysokým rizikem: chce **čerstvé potvrzení** (step-up) kódem z aplikace.

V panelu: Účet → API klíče → Nový klíč; panel si řekne o kód, vy vyberete jen rozsahy `services:read` a `tickets:write`.
Stejné přes terminál:

```bash
# expect: 204
# scope: none
curl -s -o /dev/null -w '%{http_code}\n' -c "$JAR" "$ONHOST_BASE/sanctum/csrf-cookie" -H "Referer: $ONHOST_BASE"
```

```bash
# setup
export XSRF=$(xsrf "$JAR")
```

```bash
# expect: 200
# scope: any
curl -s -X POST "$ONHOST_API/auth/login" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"email\":\"$ONHOST_EMAIL\",\"password\":\"$ONHOST_PASSWORD\",\"totp\":\"$ONHOST_TOTP\"}"
```

Po přihlášení se cookie `XSRF-TOKEN` změní, načtěte ho znovu. Pak potvrďte krok navíc (nový kód, předchozí už nejde použít):

```bash
# setup
export XSRF=$(xsrf "$JAR")
```

```bash
# expect: 200
# scope: none
# expect-json: data.method=totp
curl -s -X POST "$ONHOST_API/auth/step-up" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"method\":\"totp\",\"code\":\"$ONHOST_TOTP\"}"
```

Potvrzení platí několik minut (výchozí okno je 10). Vytvořte klíč. **Tajemství `token` je v téhle odpovědi jednou a nikde jinde**;
zkopírujte ho do `ONHOST_TOKEN`. Klíč platí pro organizaci, ve které jste ho vytvořili, a vždy končí (výchozí platnost
365 dní, `expires_in_days` ji zkrátí).

```bash
export KEY_TOKEN=$(uuidgen)
# expect: 201
# scope: none
# save: ONHOST_TOKEN=token
# save: TOKEN_ID=id
# expect-json: scopes.0=services:read
curl -s -X POST "$ONHOST_API/tokens" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_TOKEN" -d '{"name":"rucni-test","scopes":["services:read","tickets:write"],"expires_in_days":30}'
```

Totéž s `Idempotency-Key` znovu: požadavek už proběhl a tajemství se podruhé nevydá (`already_done`, viz krok 6):

```bash
# expect: 409
# scope: none
# expect-error: already_done
# expect-header: Idempotent-Replayed: true
curl -s -i -X POST "$ONHOST_API/tokens" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_TOKEN" -d '{"name":"rucni-test","scopes":["services:read","tickets:write"],"expires_in_days":30}'
```

Seznam klíčů tajemství neukáže, jen jména, rozsahy a konec platnosti:

```bash
# expect: 200
# scope: none
# expect-contains: rucni-test
curl -s "$ONHOST_API/tokens" -b "$JAR" -H "Referer: $ONHOST_BASE" -H "Accept: application/json"
```

Klíč řekne, kdo je. Odpověď obsahuje i organizaci, za kterou klíč jedná; její `id` si uložte do `ORG_ID`:

```bash
# expect: 200
# scope: any
# save: ORG_ID=data.organization.id
# expect-header: X-API-Version
curl -s -i "$ONHOST_API/me" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

## 3. Rozsahy klíče a 403

Klíč dostane jen to, co mu dáte. Co nemá, je `403` a zpráva řekne, který rozsah chybí. Vytvořte si druhý klíč jen s čtením
(potvrzení z kroku 2 ještě platí; není-li, zopakujte ho novým kódem) a vyzkoušejte zápis:

```bash
# expect: 201
# scope: none
# save: ONHOST_TOKEN_READ=token
curl -s -X POST "$ONHOST_API/tokens" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" -d '{"name":"jen-cteni","scopes":["services:read"]}'
```

```bash
# expect: 403
# scope: tickets:write
# expect-contains: lacks the tickets:write scope
curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN_READ" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" -d '{"subject":"Nesmí projít","body":"Klíč jen pro čtení."}'
```

```bash
# expect: 403
# scope: invoices:read
# expect-contains: lacks the invoices:read scope
curl -s "$ONHOST_API/invoices" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Správa klíčů, servisních účtů a webhooků je jen z panelu; klíč na ně dostane `403` bez ohledu na rozsahy:

```bash
# expect: 403
# scope: none
curl -s "$ONHOST_API/webhooks" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Neplatný nebo zrušený klíč je `401` (`ONHOST_TOKEN_INVALID` je libovolný vymyšlený řetězec, třeba `onh_live_neplatny`):

```bash
# expect: 401
# scope: services:read
# expect-error: unauthenticated
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $ONHOST_TOKEN_INVALID" -H "Accept: application/json"
```

## 4. Organizace: X-Organization

Klíč jedná za jednu organizaci, tu, ve které vznikl. Hlavička `X-Organization` s jeho vlastní organizací nic nemění,
s cizí je `403 token_organization_mismatch`. Člověk s relací panelu, který je ve víc organizacích, hlavičkou vybírá, za kterou
organizaci se ptá; organizace, do které nepatří, pro něj neexistuje (`404 not_found`, stejně jako neexistující id).

```bash
# expect: 200
# scope: services:read
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $ONHOST_TOKEN" -H "X-Organization: $ORG_ID" -H "Accept: application/json"
```

```bash
# expect: 403
# scope: services:read
# expect-error: token_organization_mismatch
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $ONHOST_TOKEN" -H "X-Organization: org_cizi" -H "Accept: application/json"
```

```bash
# expect: 200
# scope: any
# expect-json: data.organization.id=$ORG_ID
curl -s "$ONHOST_API/me" -b "$JAR" -H "Referer: $ONHOST_BASE" -H "X-Organization: $ORG_ID" -H "Accept: application/json"
```

## 5. Stránkování

Seznamy berou `limit` (výchozí 40, nejvýš 200) a `offset`, celkový počet je v `X-Total-Count`, v těle jsou `total`, `limit`
a `offset`. Hodnota nad maximem se neodmítá, ořízne se na 200. Potřebujete aspoň tři služby v organizaci (u testovací
organizace je objednejte v panelu).

```bash
# expect: 200
# scope: services:read
# arrange: services=3
# expect-header: X-Total-Count: 3
# expect-json: limit=2
# expect-json: offset=0
# expect-count: data=2
curl -si "$ONHOST_API/services?limit=2&offset=0" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Druhá stránka má zbytek a stejný celkový počet:

```bash
# expect: 200
# scope: services:read
# expect-header: X-Total-Count: 3
# expect-count: data=1
curl -si "$ONHOST_API/services?limit=2&offset=2" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

```bash
# expect: 200
# scope: services:read
# expect-json: limit=200
curl -s "$ONHOST_API/services?limit=1000" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

## 6. Idempotency-Key

Zápis s `Idempotency-Key` se provede jednou. Klíč patří jednomu klíči API, jedné organizaci a trvá 24 hodin; cizí klíč
nikdy nevrátí cizí odpověď.

Nový klíč, nový ticket:

```bash
export KEY=$(uuidgen)
# expect: 201
# scope: tickets:write
curl -si -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d '{"subject":"Zkouška API","body":"První volání přes API."}'
```

Stejný klíč a stejné tělo vrátí původní odpověď, ticket se nevytvoří podruhé, a hlavička to přizná:

```bash
# expect: 201
# scope: tickets:write
# expect-header: Idempotent-Replayed: true
curl -si -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d '{"subject":"Zkouška API","body":"První volání přes API."}'
```

Stejný klíč s jiným tělem je chyba klienta, ne opakování: `409 idempotency_key_reused`.

```bash
# expect: 409
# scope: tickets:write
# expect-error: idempotency_key_reused
curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d '{"subject":"Zkouška API","body":"Jiné tělo."}'
```

Dva souběžné požadavky se stejným klíčem se nespustí oba. Druhý, který dorazí, dokud první ještě běží, dostane
`409 idempotency_in_progress` a `Retry-After` (sekundy; 1 až 60). Správná reakce je počkat o tolik a poslat **totéž se stejným
klíčem**; dostanete odpověď prvního. Rukou to vyvoláte jen s pomalým požadavkem, který chvíli běží, proto se v testu klíč
předem „zadrží“ stejně, jako ho zadrží běžící první kopie (`reserve_key`).

```bash
export KEY_BUSY=$(uuidgen)
# expect: 409
# scope: tickets:write
# arrange: reserve_key
# expect-error: idempotency_in_progress
# expect-header: Retry-After
curl -si -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_BUSY" -d '{"subject":"Pomalý požadavek","body":"Ještě se zapisuje."}'
```

Odpověď, která vydala tajemství (nový klíč, adresa akčního háčku, vygenerované heslo), se neuchovává. Její opakování je
`409 already_done` (viz krok 2), ne druhé tajemství.

Nejdelší klíč je 200 znaků, delší je `422 invalid_idempotency_key`:

```bash
export LONG_KEY=$(head -c 201 /dev/zero | tr '\0' x)
# expect: 422
# scope: tickets:write
# arrange: long_key
# expect-error: invalid_idempotency_key
curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $LONG_KEY" -d '{"subject":"Dlouhý klíč","body":"x"}'
```

## 7. Limity a Retry-After

Každá odpověď nese `X-RateLimit-Limit` (výchozí **120 za minutu na klíč**, veřejné cesty 600 za minutu na adresu) a
`X-RateLimit-Remaining`:

```bash
# expect: 200
# scope: services:read
# expect-header: X-RateLimit-Limit: 120
# expect-header: X-RateLimit-Remaining
curl -si "$ONHOST_API/services?limit=1" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Po vyčerpání limitu je odpověď `429 rate_limited` s `Retry-After` (za kolik sekund to projde). Skutečný limit na stagingu
nevyčerpávejte smyčkou o 120 průchodech; v testu se limit pro tenhle jeden klíč dočasně sníží na jeden požadavek za minutu
(`low_rate_limit`) a požadavek navíc dostane totéž, co by dostal u 121.:

```bash
# expect: 429
# scope: services:read
# arrange: low_rate_limit
# expect-error: rate_limited
# expect-header: Retry-After
curl -si "$ONHOST_API/services?limit=1" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Požadavky s neplatným klíčem se počítají na adresu (60 neplatných za minutu, pak `429` i s platným klíčem z téže adresy),
požadavek bez klíče se nepočítá. Klient má při `429` čekat o `Retry-After` sekund a nezkoušet to dokola.

## 8. Chyby: stav, kód a `help`

Každá chyba je `{error, message, status}`, u chyb aplikace je navíc `help`: adresa vysvětlení na `/dokumentace/api#kod-s-pomlckami`.
Chybějící objekt je `404 not_found`, ne `403`: cizí a neexistující ID nejde rozlišit.

```bash
# expect: 404
# scope: services:read
# expect-error: not_found
# expect-json: status=404
# expect-json: help=/dokumentace/api#not-found
curl -s "$ONHOST_API/services/neexistuje" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

Neplatné tělo je `422 validation_failed` a `errors` říká, které pole:

```bash
# expect: 422
# scope: tickets:write
# expect-error: validation_failed
# expect-contains: errors
curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" -d '{"subject":""}'
```

Akce s vysokým rizikem bez čerstvého potvrzení je `403 step_up_required` a `help` míří na `/v1/auth/step-up` (potvrzení
vypršelo, v testu ho zruší `revoke_step_up`):

```bash
# expect: 403
# scope: none
# arrange: revoke_step_up
# expect-error: step_up_required
# expect-json: help=/v1/auth/step-up
curl -s -X POST "$ONHOST_API/webhooks" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)" -H "Content-Type: application/json" -d "{\"url\":\"$ONHOST_HOOK_URL\",\"events\":[\"service.*\"]}"
```

Potvrďte znovu (další nový kód):

```bash
# expect: 200
# scope: none
curl -s -X POST "$ONHOST_API/auth/step-up" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"method\":\"totp\",\"code\":\"$ONHOST_TOTP\"}"
```

## 9. Webhooky

Webhook je adresa, na kterou ONhost pošle podepsané `POST` s událostí. Zakládá se v panelu (Účet → Webhooky) nebo přes relaci
panelu, vždy se čerstvým potvrzením. Adresa musí být `https` na portu **443 nebo 8443**; jinak je odmítnuta.

Každý zápis na webhooky posílejte s novým `Idempotency-Key`, jako to dělají ukázky níže. Bez něj se klíč příkazu odvodí z minuty,
takže dva stejné příkazy v téže minutě (dvě zapnutí odběru, dvě otočení tajemství) se berou jako jeden a druhý jen zopakuje
odpověď prvního. Ping je výjimka: bez hlavičky je každý požadavek nový, takže druhý ping během čekání dostane
`429 webhook_ping_cooldown`; se stejnou hlavičkou se ping jen zopakuje.

Čistě `http` se odmítne při ověření těla:

```bash
# expect: 422
# scope: none
# expect-error: validation_failed
curl -s -X POST "$ONHOST_API/webhooks" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)" -H "Content-Type: application/json" -d '{"url":"http://hooks.example.cz/onhost","events":["service.*"]}'
```

`https`, ale jiný port než 443 a 8443:

```bash
# expect: 422
# scope: none
# expect-error: webhook_port_not_allowed
curl -s -X POST "$ONHOST_API/webhooks" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)" -H "Content-Type: application/json" -d '{"url":"https://hooks.example.cz:8080/onhost","events":["service.*"]}'
```

Neznámá událost:

```bash
# expect: 422
# scope: none
# expect-error: webhook_event_unknown
curl -s -X POST "$ONHOST_API/webhooks" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)" -H "Content-Type: application/json" -d '{"url":"https://hooks.example.cz/onhost","events":["service.exploded"]}'
```

Správný odběr. **Podpisové tajemství `whsec_…` je jen v téhle odpovědi**; uložte si ho do `ONHOST_WEBHOOK_SECRET`:

```bash
# expect: 201
# scope: none
# save: ENDPOINT_ID=data.id
# save: ONHOST_WEBHOOK_SECRET=data.secret
curl -s -X POST "$ONHOST_API/webhooks" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)" -H "Content-Type: application/json" -d '{"url":"https://hooks.example.cz/onhost","events":["service.*","order.*"]}'
```

Výpis odběrů tajemství neukazuje, jen katalog událostí a popis podpisu:

```bash
# expect: 200
# scope: none
# expect-contains: v1=hex(hmac_sha256
# expect-not-contains: $ONHOST_WEBHOOK_SECRET
curl -s "$ONHOST_API/webhooks" -b "$JAR" -H "Referer: $ONHOST_BASE" -H "Accept: application/json"
```

### 9.1 Zkušební událost a rozmezí mezi nimi

Zkušební doručení `webhook.ping` jde poslat **jednou za 30 sekund** na odběr, jinak `429 webhook_ping_cooldown` s `retry_after`.
Doručení jde z fronty; než se zpráva objeví u příjemce, vyčkejte pár sekund.

```bash
# expect: 202
# scope: none
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/ping" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

```bash
# expect: 429
# scope: none
# expect-error: webhook_ping_cooldown
# expect-contains: retry_after
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/ping" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

### 9.2 Ověření podpisu

Každé doručení nese `X-ONhost-Event`, `X-ONhost-Delivery`, `X-ONhost-Timestamp` (unixové sekundy) a
`X-ONhost-Signature: v1=<hex>`. Podpis je **HMAC-SHA256 z řetězce `časová_značka.tělo`** s celým řetězcem `whsec_…` jako
tajemstvím, počítaný přes **přesné bajty těla** (ne přes znovu zakódovaný JSON). Porovnávejte v konstantním čase a odmítněte
značku starší než 5 minut. Kontrola v bashi (`TS`, `SIG` a `BODY` jsou hlavičky a tělo přijatého požadavku):

```bash
# run: signature-bash
expected="v1=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$ONHOST_WEBHOOK_SECRET" -r | cut -d' ' -f1)"
now=$(date +%s)
if [ "$expected" = "$SIG" ] && [ $(( now - TS )) -le 300 ] && [ $(( TS - now )) -le 300 ]; then echo OK; else echo FAIL; fi
```

Totéž v PHP (funkce, kterou vložíte do příjemce; `$rawBody` je `file_get_contents('php://input')`):

```php
<?php
// run: signature-php
function onhostSignatureValid(string $secret, string $timestamp, string $rawBody, string $header, ?int $now = null): bool
{
    $now ??= time();
    if (! ctype_digit($timestamp) || abs($now - (int) $timestamp) > 300) {
        return false; // starší než 5 minut (nebo z budoucnosti): pravděpodobně přehrání
    }
    $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

    return hash_equals($expected, $header);
}
```

Doručení je **alespoň jednou**: stejná událost může přijít víckrát (opakování po chybě, ruční opakování), vždy se **stejným
`X-ONhost-Delivery`**. Příjemce proto musí podle něj deduplikovat: uložit si ID, a když ho už zná, odpovědět `2xx` a nic
neudělat. Odpověď `2xx` znamená přijato; cokoli jiného (i timeout po 8 s) je neúspěšný pokus.

```php
<?php
// run: dedupe-php
function onhostFirstSeen(PDO $db, string $deliveryId): bool
{
    // UNIQUE index na delivery_id: druhý pokus o vložení téhož ID selže, a tedy vrací false
    $insert = $db->prepare('INSERT OR IGNORE INTO seen_deliveries (delivery_id) VALUES (?)');
    $insert->execute([$deliveryId]);

    return $insert->rowCount() === 1;
}
```

### 9.3 Doručení, opakování a ruční opakování

Seznam doručení odběru (nejnovější první) a ruční zopakování jednoho z nich. Zopakuje se **stejné `X-ONhost-Delivery` a stejné tělo**
jako další pokus; jedno doručení jde zkusit nejvýš 10krát celkem (pak `409 webhook_redeliver_limit`) a odběr snese 20 žádostí
o zopakování za hodinu (pak `429 webhook_redeliver_rate`).

```bash
# expect: 200
# scope: none
# save: DELIVERY_ID=data.0.id
curl -s "$ONHOST_API/webhooks/$ENDPOINT_ID/deliveries" -b "$JAR" -H "Referer: $ONHOST_BASE" -H "Accept: application/json"
```

```bash
# expect: 202
# scope: none
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/deliveries/$DELIVERY_ID/redeliver" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

Neúspěšné doručení se opakuje po **1, 5, 30, 120 a 720 minutách** od předchozího pokusu: dohromady **6 pokusů** a poslední
přijde asi **14,6 hodiny** (876 minut) po prvním. Když i ten selže, je doručení mrtvé (`dead`); dá se poslat ručně, v mezích výše.
Víc než 60 pokusů za minutu na jeden odběr se nepošle naráz, zbytek počká ve frontě.

### 9.4 Pozastavení odběru

Po **20 neúspěšných pokusech za sebou** se odběr pozastaví (`state: suspended`), organizace dostane oznámení v panelu i e-mailem
(`webhook.endpoint.suspended`) a nové události k odběru nejdou, dokud ho nezapnete. Dvacet pokusů by trvalo dny, proto se
v testu čítač neúspěchů nastaví na 19 a příjemce začne odpovídat chybou 503 (`near_suspension`), takže další pokus je dvacátý:

```bash
# expect: 202
# scope: none
# arrange: advance_31s
# arrange: near_suspension
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/ping" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

```bash
# expect: 200
# scope: none
# expect-json: data.0.state=suspended
# expect-json: data.0.failures=20
curl -s "$ONHOST_API/webhooks" -b "$JAR" -H "Referer: $ONHOST_BASE" -H "Accept: application/json"
```

Pozastavený odběr zkušební událost nepřijme (`409 webhook_not_active`):

```bash
# expect: 409
# scope: none
# arrange: advance_31s
# expect-error: webhook_not_active
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/ping" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

Až příjemce opravíte, zapněte odběr (čítač neúspěchů se vynuluje):

```bash
# expect: 200
# scope: none
# arrange: healthy_receiver
# expect-json: data.state=active
# expect-json: data.failures=0
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/enable" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

Nové tajemství od téhle chvíle podepisuje každý pokus, i opakování starších doručení. Staré tajemství přestane platit hned:

```bash
# expect: 200
# scope: none
# save: ONHOST_WEBHOOK_SECRET=data.secret
curl -s -X POST "$ONHOST_API/webhooks/$ENDPOINT_ID/rotate-secret" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

Odstranění odběru je nevratné:

```bash
# expect: 200
# scope: none
curl -s -X DELETE "$ONHOST_API/webhooks/$ENDPOINT_ID" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Idempotency-Key: $(uuidgen)"
```

## 10. Servisní účet

Servisní účet je přihlašovací údaj organizace pro skripty a pipeline, který nepatří žádnému člověku. **Spravuje ho jen
vlastník** organizace (ani správce `org_admin` ne), každá změna chce čerstvé potvrzení a klíče účtu se nepřijímají na správu
klíčů a účtů.

Správce se přihlásí vlastním heslem a potvrdí heslem (účet bez autentizační aplikace potvrzuje `method: password`):

```bash
# expect: 204
# scope: none
curl -s -o /dev/null -w '%{http_code}\n' -c "$JAR_ADMIN" "$ONHOST_BASE/sanctum/csrf-cookie" -H "Referer: $ONHOST_BASE"
```

```bash
# setup
export XSRF_ADMIN=$(xsrf "$JAR_ADMIN")
```

```bash
# expect: 200
# scope: any
curl -s -X POST "$ONHOST_API/auth/login" -b "$JAR_ADMIN" -c "$JAR_ADMIN" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF_ADMIN" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"email\":\"$ONHOST_ADMIN_EMAIL\",\"password\":\"$ONHOST_ADMIN_PASSWORD\"}"
```

```bash
# setup
export XSRF_ADMIN=$(xsrf "$JAR_ADMIN")
```

```bash
# expect: 200
# scope: none
curl -s -X POST "$ONHOST_API/auth/step-up" -b "$JAR_ADMIN" -c "$JAR_ADMIN" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF_ADMIN" -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"method\":\"password\",\"code\":\"$ONHOST_ADMIN_PASSWORD\"}"
```

Správce má oprávnění i čerstvé potvrzení a přesto dostane `403`:

```bash
# expect: 403
# scope: none
# expect-contains: Only the owner of the organization manages its service accounts.
curl -s -X POST "$ONHOST_API/service-accounts" -b "$JAR_ADMIN" -c "$JAR_ADMIN" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF_ADMIN" -H "X-Organization: $ORG_ID" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" -d '{"name":"GitHub Actions","role":"viewer","scopes":["services:read"]}'
```

Vlastník (relace z kroku 2, potvrzení platí; není-li, zopakujte krok 8) účet založí. Role (`viewer`, `developer`, …) je strop:
klíč účtu nikdy nesmí víc, než dovolí role. **Tajemství klíče `token` je opět jen v téhle odpovědi**:

```bash
# expect: 201
# scope: none
# save: SA_ID=data.id
# save: SA_TOKEN=token
# save: SA_TOKEN_ID=data.tokens.0.id
# expect-json: data.role=viewer
curl -s -X POST "$ONHOST_API/service-accounts" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" -d '{"name":"GitHub Actions","description":"nasazuje obchod","role":"viewer","scopes":["services:read"]}'
```

Klíč účtu jedná za organizaci a čte, co má v rozsazích:

```bash
# expect: 200
# scope: services:read
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $SA_TOKEN" -H "Accept: application/json"
```

Servisní účet není člověk, ale `/me` mu řekne, kdo je: `type: service_account`, účet, jeho organizaci, roli a rozsahy klíče.
Koncové body, které jednají za člověka (třeba tikety), mu odpovídají `403 person_required`, ne `401` (klíč je platný):

```bash
# expect: 200
# scope: any
# expect-json: data.type=service_account
# expect-json: data.organization.id=$ORG_ID
# expect-json: data.role=viewer
curl -s "$ONHOST_API/me" -H "Authorization: Bearer $SA_TOKEN" -H "Accept: application/json"
```

Po zrušení klíče je další požadavek `401`. Klíč se zruší vlastníkem v panelu nebo takto:

```bash
# expect: 200
# scope: none
# expect-json: revoked=true
curl -s -X DELETE "$ONHOST_API/service-accounts/$SA_ID/tokens/$SA_TOKEN_ID" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json"
```

```bash
# expect: 401
# scope: services:read
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $SA_TOKEN" -H "Accept: application/json"
```

Odstranění účtu ukončí všechny jeho klíče najednou:

```bash
# expect: 200
# scope: none
# expect-json: deleted=true
curl -s -X DELETE "$ONHOST_API/service-accounts/$SA_ID" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json"
```

## 11. Úklid: zrušení osobního klíče

Zrušený klíč přestane platit při nejbližším požadavku, ostatní klíče jedou dál. Zrušení klíčem nejde (`403`, klíč nespravuje klíče);
dělá se z panelu:

```bash
# expect: 403
# scope: none
curl -s -X DELETE "$ONHOST_API/tokens/$TOKEN_ID" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

```bash
# expect: 200
# scope: none
curl -s -X DELETE "$ONHOST_API/tokens/$TOKEN_ID" -b "$JAR" -c "$JAR" -H "Referer: $ONHOST_BASE" -H "X-XSRF-TOKEN: $XSRF" -H "Accept: application/json"
```

```bash
# expect: 401
# scope: services:read
curl -s "$ONHOST_API/services" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

```bash
# expect: 200
# scope: services:read
curl -s "$ONHOST_API/services?limit=1" -H "Authorization: Bearer $ONHOST_TOKEN_READ" -H "Accept: application/json"
```

Nakonec smažte cookie soubory (`rm -f "$JAR" "$JAR_ADMIN"`), zrušte i klíč `jen-cteni` (v panelu: Účet → API klíče) a zrušte `unset`
proměnných s tajemstvími.

## Kontrolní seznam

- [ ] 1: `/status` odpoví bez klíče a nese `X-API-Version`
- [ ] 2: klíč se dá vytvořit jen se step-upem, tajemství se ukáže jednou a podruhé je `409 already_done`
- [ ] 3: zápis bez rozsahu `tickets:write` je `403` se jménem chybějícího rozsahu; cizí `X-Organization` je `403 token_organization_mismatch`
- [ ] 5: `X-Total-Count`, `limit` ořezané na 200
- [ ] 6: opakování = `Idempotent-Replayed: true`, jiné tělo = `409 idempotency_key_reused`, běžící první kopie = `409 idempotency_in_progress` s `Retry-After`
- [ ] 7: `X-RateLimit-*` na odpovědích, `429 rate_limited` s `Retry-After`
- [ ] 8: chyba má `error`, `message`, `status` a `help`
- [ ] 9: webhook jen na `https` 443/8443, podpis `v1=HMAC` sedí, duplicita podle `X-ONhost-Delivery`, po 20 neúspěších `suspended`
- [ ] 10: servisní účet jen vlastník, `/me` s ním řekne `type: service_account` s rolí a rozsahy, po zrušení klíče `401`
