# Comgate Cutover — Sandbox → Production

## Přehled

Comgate je platební brána pro platby kartou a bankovním převodem.
Bez Comgate credentials fungují pouze mock platby a kredit platby.

---

## Krok 1: Získání credentials

1. Zaregistrujte se na https://portal.comgate.cz/
2. Ověřte podnikatelský účet (požadují IČO, DIČ, výpis z živnostenského rejstříku)
3. Po schválení obdržíte:
   - `COMGATE_MERCHANT_ID` (číslo obchodníka)
   - `COMGATE_SECRET` (tajný klíč — NIKDY logovat!)

---

## Krok 2: Sandbox test

```bash
# Vyplňte credentials v .env:
COMGATE_MERCHANT_ID=<váš merchant ID>
COMGATE_SECRET=<váš secret>
COMGATE_TEST_MODE=true              # sandbox!

# Obnovte config cache:
php artisan config:cache
```

### Ověření platebního flow:

1. Přihlaste se jako zákazník (`admin@onhost.cz` / `password`)
2. Vytvořte testovací objednávku
3. Otevřete fakturu → klikněte "Zaplatit kartou / bankou"
4. Budete přesměrováni na Comgate sandbox platební stránku
5. Použijte sandbox testovací kartu: `4111 1111 1111 1111` (nebo dle Comgate docs)

---

## Krok 3: Ověření return URL

Po platbě musí browser skončit na:
```
https://onhost.cz/panel/fakturace/faktury/{uuid}/zaplatit/comgate/navrat
```
→ Faktura musí zobrazit stav "Comgate pending" nebo "Zaplaceno"

---

## Krok 4: Ověření webhook

Comgate pošle webhook na:
```
https://onhost.cz/api/webhooks/comgate
```

V Comgate portálu nastavte tuto URL jako notification URL.

**Ověření po platbě:**
```bash
# Na serveru zkontrolujte logy:
tail -f storage/logs/laravel.log | grep -i "comgate\|webhook\|payment"

# Nebo v adminu:
# /admin/platby → WebhookLog sekce
```

Očekávaný flow po webhookvání:
1. ComgateGateway zpracuje webhook
2. Payment se označí jako completed
3. InvoicePaid event → HandleInvoicePaid (service provisioning)
4. Tax document (pokud zákazník má billing adresu)

---

## Krok 5: Přepnutí na produkci

**Pouze po úspěšném sandbox smoke testu:**

```bash
# V .env:
COMGATE_TEST_MODE=false

# Obnovte config cache:
php artisan config:cache

# Ověřte:
php artisan onhost:doctor
```

---

## Krok 6: První reálná platba

1. Proveďte první reálnou platbu minimální testovací částkou (1 Kč nebo 10 Kč)
2. Ověřte v Comgate portálu, že platba přišla
3. Ověřte v adminu `/admin/platby`, že payment záznam existuje
4. Ověřte, že zákazník dostal e-mail s potvrzením
5. Ověřte, že vznikl daňový doklad (pokud customer má billing adresu)
6. Ověřte audit log `/admin/audit`

---

## Důležité bezpečnostní poznámky

- `COMGATE_SECRET` NIKDY nelogovat, neserializovat, necommitovat do gitu
- Webhook URL musí být HTTPS (HTTP platby Comgate odmítne)
- Optional: přidat `COMGATE_IP_WHITELIST=<comgate-IPs>` pro extra bezpečnost (viz Comgate docs)
- Pokud platba selže: zákazník je přesměrován zpět, payment zůstane pending, faktura není zaplacena

---

## Comgate bez credentials (mock mode)

Bez vyplněných credentials:
- Pokus o Comgate platbu vrátí chybu "platební bránu se nepodařilo kontaktovat"
- ❌ NESMÍ padnout na HTTP 500
- ✅ Mock platba (dev only) stále funguje
- ✅ Kredit platba stále funguje
