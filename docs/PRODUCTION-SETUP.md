# OnHost — Nasazení do produkce: co nastavit a kde

Průvodce pro uvedení do ostrého provozu. Řazeno podle závislostí — kroky 1–5 jsou
**nutné**, 6–9 zapínají reálné integrace, 10+ je provozní tvrdnutí.

Legenda: 🖥 = na serveru · 🔐 = v administraci (`/admin/integrace`) · 📄 = v `.env`

---

## 1. Server a runtime 🖥

| Položka | Požadavek | Poznámka |
|---|---|---|
| PHP | **8.2+** (ověřeno na 8.4) | rozšíření: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `bcmath`, `intl` |
| Databáze | MySQL 8 / **MariaDB 10.11+** | aktuálně `s2.onhost.cz` / `OH_10_OHnew` |
| Redis | doporučeno | cache + session + fronty |
| Node | jen pro build assetů | assety jsou v repu, build není nutný |
| Supervisor | pro Horizon | `deploy/supervisor/onhost-horizon.conf` |

**GD je povinné** — generuje QR platby a obrázky. Bez něj padají části fakturace.

```bash
php -m | grep -E "gd|pdo_mysql|mbstring|openssl|zip|bcmath|intl"
```

## 2. Aplikační klíče a základ 📄

| Proměnná | Hodnota | Kde získat |
|---|---|---|
| `APP_ENV` | `production` | — |
| `APP_DEBUG` | `false` | **Nikdy `true` v produkci** — health check to hlídá |
| `APP_KEY` | `base64:…` | `php artisan key:generate` |
| `APP_URL` | `https://vase-domena.cz` | musí být https, jinak se rozbijí odkazy v e-mailech |
| `DB_*` | připojení | viz krok 1 |

> ⚠️ **Rotace `APP_KEY` znehodnotí všechna šifrovaná data** (telefony zákazníků,
> credentials integrací). Při rotaci nejdřív dešifrovat, pak znovu zašifrovat.

## 3. Migrace a první data 🖥

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=ProductCatalogSeeder --force
php artisan db:seed --class=IntegrationSeeder --force
```

`IntegrationSeeder` založí všech 21 poskytovatelů **vypnutých, v mock režimu** —
nic nemůže volat ven, dokud to výslovně nepovolíte (krok 6).

## 4. Optimalizace a oprávnění 🖥

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

> Po **každém** deployi znovu `config:cache` — jinak se změny v `.env` neprojeví.

## 5. Cron a fronty 🖥

```cron
* * * * * cd /cesta/k/onhost && php artisan schedule:run >> /dev/null 2>&1
```

Plánovač pohání **39 úloh**: obnovy, upomínky, provisioning, zálohy, auto-healing,
rollup tržeb. Jeho výpadek hlídá heartbeat na `/admin/system` (stav „scheduler").

Fronty přes Horizon (vyžaduje `pcntl`/`posix`):

```bash
supervisorctl reread && supervisorctl update && supervisorctl start onhost-horizon:*
```

---

## 6. Integrace — credentials 🔐

Vše se zadává v **administraci → Integrace** (`/admin/integrace`), ne do `.env`.
Údaje jsou šifrované v DB, v UI maskované a nikdy se nelogují.
Každý poskytovatel má tlačítko **Test připojení**.

| Poskytovatel | Pole | Kde údaje získáte |
|---|---|---|
| **Comgate** (platby) | `merchant_id`, `secret` | [Comgate portál](https://portal.comgate.cz) → Obchod → Napojení |
| **Stripe** | `secret_key`, `webhook_secret` | [Stripe Dashboard](https://dashboard.stripe.com/apikeys) |
| **GoPay** | `goid`, `client_id`, `client_secret` | [GoPay účet](https://account.gopay.com) |
| **aaPanel** (webhosting) | `base_url`, `api_key` | aaPanel → Nastavení → API rozhraní (+ povolit IP serveru) |
| **WEDOS WAPI** (domény) | `user`, `password` | [WEDOS klientská zóna](https://client.wedos.com) → WAPI (+ povolit IP) |
| **Proxmox** (VPS) | endpoint, token | Proxmox → Datacenter → Permissions → API Tokens |
| **Pterodactyl** (game) | endpoint, token | Pterodactyl → Admin → Application API |
| **Cloudflare** (DNS) | `api_token`, `zone_id` | [API Tokens](https://dash.cloudflare.com/profile/api-tokens) — oprávnění *Zone:DNS:Edit* |
| **SMTP** | `host`, `port`, `username`, `password` | u poskytovatele e-mailu |
| **S3 zálohy** | `endpoint`, `bucket`, `access_key`, `secret_key` | Hetzner / Backblaze / AWS |
| **Claude / OpenAI** | `api_key` | [console.anthropic.com](https://console.anthropic.com) / [platform.openai.com](https://platform.openai.com/api-keys) |

> **Backblaze B2** nepotřebuje vlastní driver — je S3-kompatibilní, použijte
> položku *S3 zálohy* a nastavte B2 endpoint.

## 7. Otevření reálných zápisů 📄

Integrace zůstávají v **mock/dry-run**, dokud neotevřete bránu. Toto je poslední
pojistka proti nechtěnému zápisu do cizího systému — otevírejte až po úspěšném
testu připojení.

```env
PROVISIONING_MOCK_MODE=false
AAPANEL_ALLOW_REAL_WRITES=true
WAPI_ALLOW_REAL_WRITES=true
CLOUDFLARE_ALLOW_REAL_WRITES=true
AI_ALLOW_REAL_CALLS=true
```

Reálné volání projde **jen když platí všech pět** podmínek: poskytovatel aktivní
+ mock vypnutý + dry-run vypnutý + brána v `.env` + vyplněné údaje.

## 8. Web push notifikace 🖥

```bash
php artisan webpush:vapid
```

Vygeneruje VAPID pár a uloží ho do administrace (privátní klíč šifrovaně, nikdy
se nevypisuje). Push funguje **jen přes HTTPS**.

## 9. Monitoring a chybovost 📄

| Proměnná | Účel | Kde získat |
|---|---|---|
| `SENTRY_LARAVEL_DSN` | sledování chyb | [sentry.io](https://sentry.io) → Project Settings → Client Keys |
| `METRICS_TOKEN` | Prometheus scrape | libovolný náhodný řetězec (`openssl rand -hex 32`) |
| `SECURITY_DISCLOSURE_EMAIL` | kontakt v `security.txt` | vaše bezpečnostní adresa |

Endpointy pro monitoring:

| URL | Účel | Očekávaný stav |
|---|---|---|
| `/api/up` | liveness (aplikace + DB) | 200 / 503 |
| `/api/ready` | readiness (DB, cache, fronta, úložiště) | 200 / 503 |
| `/api/metrics` | Prometheus (vyžaduje token) | 200 |
| `/admin/system` | přehled pro člověka | — |

Load balancer směrujte na **`/api/ready`**, ne na `/api/up`.

---

## 10. HTTPS, DNS a bezpečnost 🖥

- TLS certifikát + **HSTS** (aplikace posílá bezpečnostní hlavičky sama)
- DNS `A`/`AAAA` na server, `MX` pro e-mail
- **SPF, DKIM, DMARC** — bez nich končí faktury ve spamu
- Firewall: povolit jen 80/443/SSH; MySQL a Redis nikdy veřejně
- Admin panel volitelně omezit na IP: `/admin/nastaveni` → IP allowlist

## 11. Zálohy 🖥

```bash
php artisan db:backup               # dump + upload na offsite disk, ověřte že projde
php artisan retention:apply --dry-run
```

- Zálohy **offsite a šifrované** (krok 6, S3)
- **Vyzkoušejte obnovu** — nevyzkoušená záloha není záloha
- Stáří poslední zálohy hlídá `/admin/system`

## 12. Fakturační údaje firmy 🔐

`/admin/nastaveni` — IČO, DIČ, adresa, bankovní spojení. Tiskne se na faktury,
takže špatný údaj znamená neplatný daňový doklad.

## 13. Ověření po nasazení

```bash
curl -sS https://vase-domena.cz/api/up      # → {"status":"ok"}
curl -sS https://vase-domena.cz/api/ready   # → {"status":"ready"}
```

Ruční průchod:

1. `/` — načte se homepage, ceny odpovídají katalogu
2. Registrace → přihlášení
3. **Objednávka**: tarif → košík → souhlas s podmínkami → platba
   → vznikne objednávka, **zálohová faktura i daňový doklad**, služba jde do zřizování
4. `/admin` — objednávka je vidět, provisioning task běží
5. `/admin/system` — vše zelené (scheduler, fronta, zálohy, úložiště)
6. Instalace PWA — v prohlížeči se nabídne „Nainstalovat"

---

## Přehled: co je kde

| Nastavení | Umístění | Proč tam |
|---|---|---|
| Credentials integrací | **administrace** `/admin/integrace` | šifrované v DB, měnitelné bez deploye |
| Firemní/fakturační údaje | **administrace** `/admin/nastaveni` | provozní data, ne konfigurace |
| Odpovědi AI chatu | **administrace** `/admin/odpovedi-chatu` | podpora je upravuje sama |
| Feature flags | **administrace** `/admin/feature-flags` | zapínání funkcí bez deploye |
| Věrnostní odměny | **administrace** `/admin/vernostni-odmeny` | katalog, ne kód |
| Klíče aplikace, brány, DSN | **`.env`** | infrastruktura, mění se s deployem |
| Cron, Horizon, TLS, firewall | **server** | mimo aplikaci |

## Známá omezení

- **aaPanel endpointy** (58 operací) jsou psané podle API v4. Před otevřením
  `AAPANEL_ALLOW_REAL_WRITES` ověřte cesty proti své instanci — zejména plugin
  `mail_sys` (forwardy, auto-reply), jehož API se mezi verzemi liší.
- **Stripe/GoPay** mají hotové napojení na credentials, ale plné produkční
  3-D Secure návratové toky jsou vedle Comgate na placeholder úrovni.
- **Uptime Kuma** nemá REST API (socket.io) — dedikovaný klient neexistuje;
  napojte přes `/api/metrics` nebo `/api/ready`.
- **n8n** je pokryté obecným webhook systémem, samostatný klient není potřeba.
