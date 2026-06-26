# OnHost.cz — Production Blockers

**Stav:** Systém lokálně schválen (Phase 21). Tento dokument sleduje zbývající blokery před produkčním deployem.

---

## 🔴 CRITICAL — Deployment nelze provést bez těchto hodnot

| # | Blocker | Akce | Kdo zajistí | Ověření |
|---|---------|------|-------------|---------|
| C1 | **APP_KEY** — musí být nastavený před prvním startem | `php artisan key:generate --show` → vložit do `.env` | DevOps | `php artisan onhost:doctor --production` |
| C2 | **Comgate credentials** — COMGATE_MERCHANT_ID + COMGATE_SECRET | Aktivovat merchant účet na https://portal.comgate.cz/ | Business | Test sandbox platby → přepnout COMGATE_TEST_MODE=false |
| C3 | **MySQL přepnutí** — DB_CONNECTION=mysql | Vyplnit DB_* do `.env` na serveru | DevOps | `php artisan migrate:status` na serveru |
| C4 | **APP_KEY NIKDY neměnit po spuštění** | Uložit APP_KEY do password manageru | DevOps | Encrypted payout details + sessions by se zneplatily |

---

## 🟡 HIGH — Systém funguje, ale nedokonale bez těchto hodnot

| # | Blocker | Akce | Kdo zajistí | Ověření |
|---|---------|---------|-------------|---------|
| H1 | **Billing company address** — BILLING_COMPANY_STREET/CITY/ZIP/IC | Doplnit do `.env` nebo Admin → Nastavení | Majitel firmy | Daňový doklad se vytvoří po platbě |
| H2 | **Právní kontrola** — obchodní podmínky, GDPR, SLA, cookies, reklamace | Právní zástupce schválí obsah | Právník | Viz `docs/LEGAL-REVIEW-CHECKLIST.md` |
| H3 | **SMTP credentials a port** — MAIL_PORT NESMÍ být 3306 | Ověřit port 587/465 na s2.onhost.cz, doplnit heslo | DevOps/ISP | `telnet s2.onhost.cz 587` + testovací e-mail |
| H4 | **SESSION_SECURE_COOKIE=true** | Přidat do `.env` | DevOps | HTTPS musí fungovat před tímto krokem |
| H5 | **SESSION_DOMAIN=.onhost.cz** | Přidat do `.env` | DevOps | admin.onhost.cz a onhost.cz sdílí session |
| H6 | **MAIL_FROM_ADDRESS=info@onhost.cz** | Doplnit do `.env` (ne stanektech.cz) | Majitel | Zákazníci obdrží e-mail z správné domény |

---

## 🟢 MANAGED — Záměrná omezení (NE blokery)

| # | Položka | Stav | Důvod |
|---|---------|------|-------|
| M1 | PROVISIONING_MOCK_MODE=true | Záměrné pro první deploy | Staged cutover aaPanel/Proxmox |
| M2 | WAPI_ALLOW_REAL_WRITES=false | Záměrné | Staged cutover WEDOS domén |
| M3 | AAPANEL_ALLOW_REAL_WRITES=false | Záměrné | Staged cutover aaPanel |
| M4 | Gamehosting COMING SOON | Záměrné | Pterodactyl driver není hotový |
| M5 | Partner payouts — MANUAL | Záměrné | Automatický výplatní systém naplánovaný |
| M6 | AI/Monitoring/Backups MOCK | Záměrné | Real provider se aktivuje postupně |

---

## Ověřovací příkazy

```bash
# Na serveru po nasazení:
php artisan onhost:doctor --production

# Musí být 0 critical errors před prvním prodejem
```

---

## Staged cutover postup

Po go-live s mock provisioning:
1. Přidat aaPanel server v `/admin/servery` → test connection → `AAPANEL_ALLOW_REAL_WRITES=true`
2. `PROVISIONING_MOCK_MODE=false`
3. Ověřit WEDOS availability check → `WAPI_ALLOW_REAL_WRITES=true`
4. Comgate sandbox → smoke test → `COMGATE_TEST_MODE=false`
