# SYSTEM-AUDIT-200-EXPANSION (v3)
### Nedokončené · rozpracované · rozšíření & automatizace

> **Kontext.** Základní platforma je funkčně hotová (3337 testů, PHPStan L6 = 0).
> Předchozí audity (`SYSTEM-AUDIT-200-VERIFIED.md`) řešily *co chybí do MVP*.
> **Tento audit je dopředný**: čím systém rozšířit, co dodělat a hlavně **co
> zautomatizovat**, aby platforma škálovala bez lineárního růstu obsluhy.
>
> Není to seznam bugů — je to roadmapa. Body nejsou ověřené 1:1 proti kódu
> (na rozdíl od VERIFIED); ber je jako návrhy k rozhodnutí, ne jako fakta.

**Legenda priority:** 🔴 vysoká hodnota / near-term · 🟠 střední · 🟢 budoucí
**Typ:** `[NEDOK]` nedokončené · `[ROZPR]` rozpracované · `[AUTO]` automatizace · `[ROZŠ]` rozšíření

---

## A. Nedokončené / rozpracované funkce (deklarované, ale nehotové)

| # | P | Typ | Bod |
|---|---|---|---|
| 1 | 🔴 | NEDOK | **E-mailový hosting jako produkt** — `admin/mailbox` je placeholder; schránky, kvóty, webmail, MX/DKIM automatika |
| 2 | 🔴 | NEDOK | **Zákaznické sub-účty (team members)** — FAQ slibuje; pozvánky, role, scoped přístup ke službám |
| 3 | 🟠 | ROZPR | **Web push notifikace** (audit 92) — potřebuje `minishlink/web-push` + VAPID; model `PushSubscription` + service worker |
| 4 | 🟠 | ROZPR | **Samostatná číselná řada faktur na resellera** (audit 111) — branding hotový, číslování ne |
| 5 | 🟠 | NEDOK | **Šifrování dalších PII sloupců** (audit 31) — s deterministickým blind-indexem pro zachování hledání/VIES |
| 6 | 🟠 | NEDOK | **OAuth2 authorization flow** (audit 99) — aplikace existují, flow ne (Passport) |
| 7 | 🟢 | NEDOK | **GraphQL endpoint** (audit 102) — vedle REST |
| 8 | 🟢 | NEDOK | **Oficiální SDK** (PHP/JS/Python, audit 100) — generované z OpenAPI |
| 9 | 🟠 | ROZPR | **Onboarding tour** — checklist hotový; průvodce UI (spotlight/tooltipy) chybí |
| 10 | 🟠 | NEDOK | **Reálný connection test** neznámých providerů (`ConnectionTester` fallback) |
| 11 | 🟢 | ROZŠ | **Skeleton/loading stavy** (audit 140) — pokud Cuba doplní vlastní komponentu |
| 12 | 🟢 | NEDOK | **WCAG AA kontrast audit** (audit 142) — guard test na vlastní barvy v `onhost.css` |

---

## B. Automatizace provisioningu a provozu

| # | P | Typ | Bod |
|---|---|---|---|
| 13 | 🔴 | AUTO | **Auto-healing služeb** — periodická detekce `MissingRemote` → automatické re-provision (dnes ruční tlačítko) |
| 14 | 🔴 | AUTO | **Automatické draining plného serveru** — když `max_services` dosaženo, `MigrateServiceToServerAction` přesune nové/vybrané služby |
| 15 | 🔴 | AUTO | **Auto-scaling upozornění** — když klesne volná kapacita flotily pod práh → task na přidání serveru |
| 16 | 🟠 | AUTO | **Automatický retry provisioningu s exponenciálním backoffem** — sjednotit napříč všemi drivery |
| 17 | 🟠 | AUTO | **Orphan reconciliation scheduler** — periodicky porovnat panel vs DB a hlásit rozdíly (dnes jen při create) |
| 18 | 🟠 | AUTO | **Automatická rotace SSH/API klíčů serverů** — plánovaná obměna credentials |
| 19 | 🟠 | AUTO | **Self-service migrace webu mezi tarify/servery** pro zákazníka (dnes admin-only) |
| 20 | 🟠 | AUTO | **Automatické cert obnovení (Let's Encrypt)** hook do aaPanel + alert před expirací |
| 21 | 🟠 | AUTO | **Automatické suspend→terminate po grace period** s eskalací notifikací |
| 22 | 🟢 | AUTO | **Predikce vytížení serveru** z `capacity_meta` → doporučení rebalance |
| 23 | 🟢 | AUTO | **Automatické snapshot před rizikovými akcemi** (reinstall, plan change) |
| 24 | 🟠 | AUTO | **Bulk provisioning fronta** s rate-limitem na panel (ochrana před přetížením aaPanelu) |
| 25 | 🟢 | ROZŠ | **Blue/green provisioning** — nová verze služby vedle staré, přepnutí DNS |

---

## C. Automatizace fakturace, plateb a revenue recovery

| # | P | Typ | Bod |
|---|---|---|---|
| 26 | 🔴 | AUTO | **Smart dunning** — adaptivní kadence upomínek dle platební historie zákazníka |
| 27 | 🔴 | AUTO | **Automatický retry selhané karty** (dunning s opakovaným pokusem D+1/D+3/D+7) |
| 28 | 🔴 | AUTO | **Auto-collections z kreditu** — když je kredit, automaticky zaplatit obnovu (částečně `AutoPayInvoicesFromCredit`) → rozšířit |
| 29 | 🟠 | AUTO | **Pre-dunning „karta brzy vyprší"** — detekce expirace uložené karty + výzva k aktualizaci |
| 30 | 🟠 | AUTO | **Automatické OSS VAT přiznání export** — měsíční/kvartální dávka pro účetní |
| 31 | 🟠 | AUTO | **Auto-generování dobropisu** při schválené reklamaci/refundu |
| 32 | 🟠 | AUTO | **Revenue recognition report** — automatický rozpad předplatného na období |
| 33 | 🟠 | AUTO | **Automatická detekce a storno duplicitních plateb** |
| 34 | 🟠 | ROZŠ | **Wallet / předplacený kredit s auto-topup** při poklesu pod práh (kód auto-topup existuje → dokončit UI a triggery) |
| 35 | 🟠 | AUTO | **Automatické párování bankovních výpisů** (import CSV/API → match na variabilní symbol) |
| 36 | 🟢 | ROZŠ | **Splátkové kalendáře s automatickým inkasem** (installments existují → automatizovat) |
| 37 | 🟢 | AUTO | **Churn-based rabaty** — automatická retention nabídka při signálu odchodu |
| 38 | 🟠 | AUTO | **Automatické late fee** dle pravidel (kód existuje → napojit na scheduler pravidelně) |
| 39 | 🟢 | ROZŠ | **Usage-based billing** (metered) — účtování dle spotřeby (traffic, storage) |
| 40 | 🟢 | ROZŠ | **Multi-currency automatický přepočet** kurzem ČNB s denní aktualizací |

---

## D. Zákaznická automatizace & self-service

| # | P | Typ | Bod |
|---|---|---|---|
| 41 | 🔴 | AUTO | **Automatizovaný onboarding flow** — po objednávce sekvence: welcome mail → DNS návod → první přihlášení do panelu |
| 42 | 🔴 | ROZŠ | **Self-service povýšení/snížení tarifu** s okamžitým pro-rata (backend hotový → plný self-service UI) |
| 43 | 🟠 | AUTO | **Automatické „jak na to" e-maily** dle stavu služby (nezřízeno X dní, nevyužito, atd.) |
| 44 | 🟠 | ROZŠ | **Self-service žádost o zálohu + obnovu** z panelu (approval workflow existuje → rozšířit) |
| 45 | 🟠 | ROZŠ | **In-panel knowledge base search** s návrhy dle kontextu služby |
| 46 | 🟠 | AUTO | **Automatický re-engagement** neaktivních zákazníků (health score → kampaň) |
| 47 | 🟢 | ROZŠ | **Customer portal mobile app / PWA** |
| 48 | 🟢 | ROZŠ | **Self-service export všech dat** (GDPR portabilita — základ hotový, rozšířit formáty) |
| 49 | 🟠 | AUTO | **Automatické připomínky obnovy** s 1-click renew odkazem |
| 50 | 🟢 | ROZŠ | **Zákaznický status page** per služba (uptime, incidenty) veřejně sdílitelný |

---

## E. AI / ML automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 51 | 🔴 | AUTO | **Churn prediction model** — ML nad health-score featurami (dnes heuristika) → skóre + doporučená akce |
| 52 | 🔴 | AUTO | **AI triage ticketů** — automatická kategorizace, priorita, návrh odpovědi (chatbot základ existuje → rozšířit na tickety) |
| 53 | 🟠 | AUTO | **AI-assist konceptů odpovědí** pro adminy (draft z historie + KB) |
| 54 | 🟠 | AUTO | **Anomaly detection provozu** — neobvyklý traffic/spotřeba → alert (základ pro fraud existuje) |
| 55 | 🟠 | AUTO | **Smart upsell doporučení** — na základě vzorců využití nabídnout vyšší tarif |
| 56 | 🟠 | AUTO | **AI shrnutí incidentů** — z logů/tasků vygenerovat post-mortem draft |
| 57 | 🟢 | ROZŠ | **AI asistent v panelu** — přirozený jazyk → akce ("ukaž mi faktury po splatnosti") |
| 58 | 🟢 | AUTO | **Prediktivní kapacita** — forecast spotřeby serverů (ML nad snapshoty) |
| 59 | 🟢 | AUTO | **Automatická detekce spam/abuse** obsahu na hostingu |
| 60 | 🟢 | ROZŠ | **AI content moderation** recenzí (nový modul recenzí → auto-flag) |

---

## F. Monitoring, observability & alerting automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 61 | 🔴 | AUTO | **Synthetic monitoring** — periodické e2e checky klíčových flow (objednávka, platba, login) |
| 62 | 🔴 | AUTO | **Napojení `/api/up` + `onhost:doctor` na externí uptime** s eskalací |
| 63 | 🔴 | AUTO | **Automatická eskalace incidentů** (P1 → PagerDuty/on-call) — `CriticalAlertDispatcher` základ |
| 64 | 🟠 | AUTO | **SLA breach auto-detekce** + automatický kredit/kompenzace |
| 65 | 🟠 | AUTO | **Automatické health checky služeb** s auto-remediation runbooky |
| 66 | 🟠 | ROZŠ | **Centralizované logy** (Loki/ELK) — `LogContext` připraven, chybí sink |
| 67 | 🟠 | ROZŠ | **APM** (slow-query logging hotový → napojit tracing) |
| 68 | 🟠 | AUTO | **Automatický capacity alert** flotily (`FleetCapacityReport` → scheduler + eskalace) |
| 69 | 🟢 | AUTO | **Automatické restart-on-crash** s circuit breakerem pro provisioning joby |
| 70 | 🟢 | ROZŠ | **Real-user monitoring (RUM)** frontend výkonu |
| 71 | 🟠 | AUTO | **Dead-letter queue processing** — automatická analýza a retry failed jobů |
| 72 | 🟢 | AUTO | **Automatický backup verification** — restore test do sandboxu (rozšíření `db:backup`) |

---

## G. Notifikace & komunikace automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 73 | 🟠 | AUTO | **Multi-kanál orchestrace** — jedna událost → e-mail + in-app + (push/SMS) dle preferencí |
| 74 | 🟠 | ROZŠ | **SMS kanál** pro kritické alerty (2FA, výpadek) |
| 75 | 🟠 | AUTO | **Digest personalizace** dle chování (dnes fixní týdenní) |
| 76 | 🟠 | ROZŠ | **Webhooks pro zákazníky** — outbound eventy (služba zřízena, faktura vystavena) |
| 77 | 🟠 | ROZŠ | **Slack/Discord/Teams integrace** pro admin alerty |
| 78 | 🟢 | AUTO | **Transakční e-mail templating engine** s A/B testem |
| 79 | 🟢 | AUTO | **Automatické „NPS follow-up"** dle skóre (detraktor → ticket, promotér → recenze/referral) |
| 80 | 🟢 | ROZŠ | **Maintenance status broadcast** na status page + odběr |
| 81 | 🟠 | AUTO | **Bounce/complaint handling** — auto-suppression neplatných adres |
| 82 | 🟢 | ROZŠ | **In-app changelog / product updates feed** (model existuje → widget) |

---

## H. Bezpečnostní automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 83 | 🔴 | AUTO | **Automatická detekce & blokace brute-force** napříč IP (dnes per-account throttle) |
| 84 | 🔴 | AUTO | **Login anomaly dle země/ASN** (audit 30) — GeoIP + risk skóre + step-up auth |
| 85 | 🟠 | AUTO | **Automatický vendor dependency audit** (`composer audit`) v CI + alert na CVE |
| 86 | 🟠 | AUTO | **Automatická rotace API tokenů** po X dnech s upozorněním |
| 87 | 🟠 | AUTO | **Impersonation session recording** + auto-expire (základ existuje → rozšířit audit) |
| 88 | 🟠 | AUTO | **Automatická revokace kompromitovaných credentials** (HIBP monitoring účtů) |
| 89 | 🟠 | ROZŠ | **WAF pravidla managed z aplikace** (per-service firewall existuje → centralizovat) |
| 90 | 🟢 | AUTO | **Automatický security header scan** vlastního webu v CI |
| 91 | 🟢 | AUTO | **Secrets scanning** v CI (gitleaks) — doplněk k `SecretsNeverLeakTest` |
| 92 | 🟠 | AUTO | **Automatické CSP report triage** — agregace violations → návrh policy |
| 93 | 🟢 | ROZŠ | **Hardware/WebAuthn 2FA** vedle TOTP |
| 94 | 🟢 | AUTO | **Automatická detekce sdílených účtů** (concurrent session limit → eskalace) |

---

## I. Reseller / partner automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 95 | 🔴 | AUTO | **Automatické výplaty provizí** — nad prahem, s four-eyes (základ existuje), přes platební API |
| 96 | 🟠 | AUTO | **Automatický přepočet a schválení provizí** po grace period (`ApproveEligibleCommissions` → rozšířit) |
| 97 | 🟠 | ROZŠ | **White-label plná customizace** resellera (doména, e-maily, branding) |
| 98 | 🟠 | ROZŠ | **Reseller API** pro programatické zakládání zákazníků |
| 99 | 🟠 | AUTO | **Tier-based automatické marže** dle objemu resellera |
| 100 | 🟢 | ROZŠ | **Partner marketing kit generator** (bannery hotové → landing pages, e-mail šablony) |
| 101 | 🟢 | AUTO | **Automatický fraud check** nových partnerů (self-referral už blokován) |
| 102 | 🟢 | ROZŠ | **Sub-reseller hierarchie** (multi-level) |
| 103 | 🟠 | AUTO | **Reseller onboarding automation** — schválení → aktivace → welcome |
| 104 | 🟢 | ROZŠ | **Reseller performance dashboard** s benchmarky |

---

## J. Domény & DNS automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 105 | 🔴 | AUTO | **Automatická obnova domén** s dostatečným kreditem/kartou (dnes manuální flag) |
| 106 | 🟠 | AUTO | **Auto-DNSSEC rollover** klíčů (UI hotové → automatika rotace) |
| 107 | 🟠 | AUTO | **Automatický transfer-in progress tracking** s notifikacemi na každý krok |
| 108 | 🟠 | ROZŠ | **Bulk domain operations** (hromadná obnova, NS změna — základ existuje → rozšířit) |
| 109 | 🟠 | AUTO | **DNS health monitoring** — detekce nesprávné delegace/propagace |
| 110 | 🟢 | ROZŠ | **Doménový marketplace** (aftermarket, aukce) |
| 111 | 🟢 | AUTO | **Automatické doporučení volných domén** při registraci (typo/alt TLD) |
| 112 | 🟢 | ROZŠ | **Registrar failover** — druhý registrátor jako záloha WEDOS |
| 113 | 🟠 | AUTO | **Expiry sweep** — automatická detekce blížící se expirace + kampaň |
| 114 | 🟢 | ROZŠ | **Premium DNS** jako placený produkt (anycast, DDoS) |

---

## K. Reporting, BI & business automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 115 | 🔴 | AUTO | **Automatický měsíční business report** (MRR, churn, cohort, ARPU) na e-mail vedení |
| 116 | 🟠 | ROZŠ | **Read replica pro reporting** (audit 119) — oddělit analytiku od produkce |
| 117 | 🟠 | AUTO | **Revenue forecast** automatizace (základ existuje → ML + scénáře) |
| 118 | 🟠 | ROZŠ | **Cohort retention dashboard** (data existují → vizualizace) |
| 119 | 🟠 | AUTO | **Automatické KPI alerty** (existuje → rozšířit thresholdy + anomálie) |
| 120 | 🟠 | ROZŠ | **Customizovatelné admin dashboardy** (widgety per admin) |
| 121 | 🟢 | ROZŠ | **Data warehouse export** (BigQuery/Snowflake connector) |
| 122 | 🟢 | AUTO | **Automatické finanční uzávěrky** — měsíční export pro účetnictví |
| 123 | 🟢 | ROZŠ | **Product analytics** — funnel objednávky, drop-off analýza |
| 124 | 🟢 | AUTO | **Automatický anomaly report** tržeb (náhlý pokles → alert) |

---

## L. API, integrace & ekosystém

| # | P | Typ | Bod |
|---|---|---|---|
| 125 | 🔴 | ROZŠ | **Zákaznické outbound webhooks** — event subscriptions (v2 základ → rozšířit typy) |
| 126 | 🟠 | ROZŠ | **API rate-limit tiers** dle plánu (základ per-token → produktizovat) |
| 127 | 🟠 | AUTO | **Automatické API changelog + deprecation** (hlavičky hotové → workflow oznámení) |
| 128 | 🟠 | ROZŠ | **Zapier/Make konektor** (přes webhooks + REST) |
| 129 | 🟠 | ROZŠ | **Terraform provider** pro IaC zákazníky |
| 130 | 🟢 | ROZŠ | **WHMCS/Blesta migrace importér** (pro příchozí resellery) |
| 131 | 🟢 | ROZŠ | **Accounting integrace** (Fakturoid/Money S3/Pohoda) |
| 132 | 🟢 | ROZŠ | **CRM integrace** (HubSpot/Pipedrive) pro sales |
| 133 | 🟠 | AUTO | **Idempotency + retry SDK** helpery (idempotency hotová → dokumentovat vzory) |
| 134 | 🟢 | ROZŠ | **GraphQL + subscriptions** pro real-time |
| 135 | 🟢 | ROZŠ | **Public developer portal** rozšíření (dev portal existuje → sandbox, API keys self-service) |
| 136 | 🟢 | ROZŠ | **Marketplace add-onů** (třetí strany) |

---

## M. Produktová rozšíření (nové produktové linie)

| # | P | Typ | Bod |
|---|---|---|---|
| 137 | 🟠 | ROZŠ | **SSL certifikáty jako produkt** (DV/OV/EV, wildcard) — monitoring existuje |
| 138 | 🟠 | ROZŠ | **Object storage** (S3-kompatibilní) jako produkt |
| 139 | 🟠 | ROZŠ | **Managed WordPress** (auto-update, staging, cache) |
| 140 | 🟢 | ROZŠ | **Website builder** (`front/builder` placeholder) |
| 141 | 🟢 | ROZŠ | **Migrace z cPanel/Plesk** jako služba (auto-import) |
| 142 | 🟢 | ROZŠ | **Managed databáze** (MySQL/PostgreSQL jako služba) |
| 143 | 🟢 | ROZŠ | **CDN produkt** (edge cache) |
| 144 | 🟢 | ROZŠ | **Zálohy jako placený add-on** (offsite, retence, restore SLA) |
| 145 | 🟢 | ROZŠ | **Load balancer / HA hosting** |
| 146 | 🟢 | ROZŠ | **Kubernetes / container hosting** |
| 147 | 🟢 | ROZŠ | **E-mail marketing** jako add-on (dedikované IP, deliverability) |
| 148 | 🟢 | ROZŠ | **Monitoring jako produkt** (uptime/SSL/DNS pro externí weby) |

---

## N. Compliance & governance automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 149 | 🟠 | AUTO | **Automatická retenční politika** — hotovo (`retention:apply`); rozšířit o další tabulky + reporting |
| 150 | 🟠 | AUTO | **Automatizované GDPR DSAR** — export/výmaz na žádost s workflow (základ existuje) |
| 151 | 🟠 | ROZŠ | **Automatické generování DPA/smluv** (DPA hotové → rozšířit o SLA, VOP verze) |
| 152 | 🟠 | AUTO | **Consent lifecycle automation** — expirace, re-consent při změně verze dokumentu |
| 153 | 🟢 | AUTO | **Automatický compliance dashboard** (2FA adopce, konsenty, retence) |
| 154 | 🟢 | ROZŠ | **Audit trail export** pro auditory (immutable log) |
| 155 | 🟢 | AUTO | **Automatická data residency** kontrola (EU-only ukládání) |
| 156 | 🟢 | ROZŠ | **SOC2 / ISO evidence collection** automatizace |
| 157 | 🟠 | AUTO | **Automatické oznámení o změně VOP** dotčeným zákazníkům + re-consent |
| 158 | 🟢 | ROZŠ | **Right-to-portability** rozšíření (strojově čitelné formáty) |

---

## O. Výkon, škálování & infrastruktura

| # | P | Typ | Bod |
|---|---|---|---|
| 159 | 🔴 | ROZŠ | **Redis** pro cache/session/queue (produkce) |
| 160 | 🔴 | AUTO | **Cache warming po deploy** (audit 121) — přednahřát hot dotazy |
| 161 | 🟠 | ROZŠ | **Tailwind purge / CSS build pipeline** (`style.css` 243k řádků) |
| 162 | 🟠 | ROZŠ | **Horizontální škálování** app serverů (stateless ověřit) |
| 163 | 🟠 | AUTO | **Automatické DB index advisories** ze slow-query logu |
| 164 | 🟠 | ROZŠ | **Connection pooling** (PgBouncer/ProxySQL) |
| 165 | 🟢 | ROZŠ | **HTTP/2 push / preload** kritických assetů |
| 166 | 🟢 | ROZŠ | **Edge caching** statiky přes CDN |
| 167 | 🟢 | AUTO | **Automatická archivace starých dat** do cold storage |
| 168 | 🟢 | ROZŠ | **Query result caching** hot reportů |
| 169 | 🟠 | AUTO | **Automatické load testy** v CI (regrese výkonu) |
| 170 | 🟢 | ROZŠ | **Async everything** — přesunout těžké operace do fronty (částečně hotovo) |

---

## P. DevOps & release automatizace

| # | P | Typ | Bod |
|---|---|---|---|
| 171 | 🔴 | AUTO | **CI pipeline** — testy + PHPStan + `composer audit` na každý PR |
| 172 | 🔴 | AUTO | **Automatizovaný deploy** (zero-downtime, migrace, cache rebuild, health check) |
| 173 | 🟠 | AUTO | **Automatický rollback** při failed health checku po deployi |
| 174 | 🟠 | AUTO | **Staging → prod promotion** workflow |
| 175 | 🟠 | AUTO | **Mutation testing** (Infection) v CI |
| 176 | 🟠 | AUTO | **Browser E2E** (Dusk/Playwright) klíčových flow v CI |
| 177 | 🟢 | AUTO | **Visual regression** testy v CI |
| 178 | 🟢 | AUTO | **Accessibility testy** (axe) v CI |
| 179 | 🟠 | AUTO | **Automatický DB restore drill** (rozšíření `db:backup` — obnovit do sandboxu a ověřit) |
| 180 | 🟢 | AUTO | **Automatická changelog generace** z konvenčních commitů |

---

## Q. Zákaznická zkušenost & UX rozšíření

| # | P | Typ | Bod |
|---|---|---|---|
| 181 | 🟠 | ROZŠ | **Globální command palette** (⌘K) v panelu i adminu (klávesové zkratky základ) |
| 182 | 🟠 | ROZŠ | **Dark mode** doladění napříč všemi stránkami (základ hotový) |
| 183 | 🟠 | ROZŠ | **Real-time dashboard** (WebSocket/Reverb — základ existuje) |
| 184 | 🟢 | ROZŠ | **Přizpůsobitelný dashboard** widgety (drag & drop) |
| 185 | 🟢 | ROZŠ | **Vícejazyčnost** — dokončit i18n extrakci (ráčna drží dluh) + EN/DE/SK |
| 186 | 🟢 | ROZŠ | **In-app produktové tipy** dle využití |
| 187 | 🟢 | ROZŠ | **Guided troubleshooting wizardy** (DNS nefunguje, e-mail nechodí) |
| 188 | 🟢 | ROZŠ | **Referral program gamifikace** (odznaky, žebříčky) |
| 189 | 🟢 | ROZŠ | **Mobilní PWA** panelu |
| 190 | 🟢 | ROZŠ | **Přístupnost — plný WCAG 2.1 AA** audit + fixy |

---

## R. Datový model, tech-debt & kvalita

| # | P | Typ | Bod |
|---|---|---|---|
| 191 | 🟠 | ROZŠ | **Dokončit i18n extrakci** blade → `lang/` (ráčna měří ~4193; splácet po obrazovkách) |
| 192 | 🟠 | ROZŠ | **Unifikovat poznámky** — migrovat customer/service bespoke notes na `HasEntityNotes` |
| 193 | 🟠 | AUTO | **Rozšířit `retention:apply`** o soft-deleted purge a další telemetrii |
| 194 | 🟠 | ROZŠ | **Event sourcing** pro finanční operace (audit-grade immutability nad ledgerem) |
| 195 | 🟢 | ROZŠ | **Sjednotit status enumy** napříč doménami (konzistence) |
| 196 | 🟢 | AUTO | **Automatický dead-code / unused-route detektor** v CI |
| 197 | 🟢 | ROZŠ | **Feature flags systém** pro postupné rollouty |
| 198 | 🟢 | ROZŠ | **Multi-tenancy izolace** (pokud white-label poroste) |
| 199 | 🟢 | AUTO | **Automatický migration column verifier** v CI (`tools/verify-migration-columns.php` už existuje → zapojit) |
| 200 | 🟢 | AUTO | **Automatický OpenAPI drift check** — spec vs. skutečné routy v CI (guard existuje → rozšířit) |

---

## Doporučené první vlny (co dělat dřív)

**Vlna 1 — automatizace, která nejvíc šetří obsluhu (🔴):**
13 auto-healing · 26–28 smart dunning + auto-collections · 41 onboarding flow ·
51–52 churn prediction + AI ticket triage · 61–63 synthetic monitoring + eskalace ·
95 auto-výplaty provizí · 105 auto-obnova domén · 115 měsíční business report ·
171–172 CI + automatizovaný deploy.

**Vlna 2 — dokončení rozpracovaného + near-term rozšíření (🟠).**

**Vlna 3 — nové produktové linie (🟢)** dle obchodní priority.

> **Pozn.:** tento audit je *dopředný a nezávazný* — návrhy, ne verifikovaný
> backlog. Před implementací každý bod ověřit proti aktuálnímu kódu (stejná
> disciplína jako u `SYSTEM-AUDIT-200-VERIFIED.md`).
