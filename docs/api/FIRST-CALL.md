# První volání za 5 minut

Příklady jsou pro `curl`. `{API_BASE}` nahraďte adresou API, kterou ukazuje stránka `/dokumentace/api` (u každé instalace
jiná, končí `/v1`). Stejné kroky jsou tam i v prohlížeči; `ApiDocsTest` hlídá, že jsou řádek po řádku stejné.

## 1. Nastavte adresu a zkuste veřejný koncový bod

Stav služeb nepotřebuje klíč, takže hned poznáte, že se k API dostanete.

```bash
export ONHOST_API={API_BASE}
curl -s "$ONHOST_API/status" -H "Accept: application/json"
```

## 2. Vytvořte klíč

V panelu v části Účet → API klíče vyberte jen rozsahy, které skript potřebuje, případně expiraci. Tajemství se ukáže jednou.

```bash
export ONHOST_TOKEN=onh_live_…
```

## 3. Zeptejte se, kdo klíč je

Odpověď říká, za kterou organizaci klíč jedná. Jiná organizace se v hlavičce `X-Organization` nastavit nedá.

```bash
curl -s "$ONHOST_API/me" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

## 4. Vypište služby a přečtěte si hlavičky

Potřebuje rozsah `services:read`. Celkový počet je v `X-Total-Count`, zbývající limit v `X-RateLimit-Remaining`.

```bash
curl -si "$ONHOST_API/services?limit=5" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"
```

## 5. Napište něco s Idempotency-Key

Potřebuje rozsah `tickets:write`.

```bash
export KEY=$(uuidgen)
curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d '{"subject":"Zkouška API","body":"První volání přes API."}'
```

## 6. Zopakujte ho a pak změňte tělo

Stejný klíč a stejné tělo vrátí původní odpověď s `Idempotent-Replayed: true`. Stejný klíč s jiným tělem je `409 idempotency_key_reused`.

```bash
curl -si -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d '{"subject":"Zkouška API","body":"Jiné tělo."}'
```
