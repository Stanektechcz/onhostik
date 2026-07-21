# OnHost — Kompletní audit 200 bodů (v2, 2026-07-19)

Nástupce `SYSTEM-AUDIT-200.md`, jehož fáze A–P jsou dokončené. Tento dokument je
**dopředný audit aktuálního stavu**: co chybí, co je rozpracované/neověřené a co
by bylo vhodné přidat. Stav kódu: **3194 testů zelených, PHPStan L6 = 0**, vše
běží v mock režimu (`PROVISIONING_MOCK_MODE=true`, `AAPANEL_ALLOW_REAL_WRITES=false`).

Legenda severity: 🔴 blocker go-live · 🟠 vysoké · 🟡 střední · 🟢 rozšíření
Legenda stavu: **[CHYBÍ]** není vůbec · **[NEDOKONČENO]** rozpracované/neověřené · **[PŘIDAT]** vhodné doplnit

---

## A. Produkční připravenost — go-live blockery

1. 🔴 **[NEDOKONČENO]** Reálné platební brány (Comgate/Stripe/GoPay) běží jen proti mocku/`Http::fake()`. Ověřit každou v sandboxu s reálnými credentials: úspěch, storno, timeout, částečná platba, refund. (D51–D53 z v1.)
2. 🔴 **[NEDOKONČENO]** aaPanel produkční zápisy (`AAPANEL_ALLOW_REAL_WRITES`) nikdy trvale zapnuté. Řízený go-live: jeden server, jeden testovací zákazník, verifikace přes `getSiteOverview()`, pak rollout.
3. 🔴 **[NEDOKONČENO]** Proxmox / Pterodactyl reálné E2E (J139) — jen mock. Potvrdit create/suspend/terminate/reinstall proti reálným uzlům mimo produkci.
4. 🔴 **[NEDOKONČENO]** WEDOS WAPI reálná registrace domény a DNS — response tvary následují dokumentaci, NEověřeno proti živému API. Ověřit před cutover.
5. 🔴 **[CHYBÍ]** Sentry / error tracking napojení. `ErrorContext` + `SecretRedactor` jsou připravené (J141), chybí `composer require sentry/sentry-laravel` + DSN + `Integration::handles()` v `bootstrap/app.php`.
6. 🟠 **[NEDOKONČENO]** Ověřit `route:cache`+`config:cache`+`view:cache` v reálném deploy skriptu (guard test existuje, ale běží jen na Linux CI — spustit na produkčním PHP).
7. 🟠 **[CHYBÍ]** Zálohy DB offsite (`spatie/laravel-backup` plán + S3/offsite cíl). Bez toho je jediná kopie na stejném stroji.
8. 🟠 **[NEDOKONČENO]** Redis jako cache + session + queue driver v produkci — ověřit config (dnes testy běží na sync/array).
9. 🟠 **[CHYBÍ]** Horizon supervisord/systemd unit pro trvalý běh workerů (L156 rozdělil fronty, ale běh workerů je deploy záležitost).
10. 🟠 **[CHYBÍ]** Health monitoring `/api/up` napojený na externí uptime službu (Better Uptime / UptimeRobot) s alertem.
11. 🟡 **[CHYBÍ]** CDN + cache statiky (Cache-Control, immutable, verzování assetů) — ověřit hlavičky.
12. 🟡 **[CHYBÍ]** Rate limiting na úrovni reverzní proxy / WAF (nad Laravel throttle) pro DDoS.
13. 🟡 **[NEDOKONČENO]** SMTP / mail deliverability — SPF/DKIM/DMARC pro odesílací doménu, warm-up, bounce handling.
14. 🟡 **[CHYBÍ]** Staging prostředí identické produkci pro release testing.
15. 🟢 **[PŘIDAT]** Blue-green / zero-downtime deploy (dnes `route:cache` během deploye krátce servíruje staré routy).

## B. Ověření mock → real

16. 🟠 **[NEDOKONČENO]** aaPanel mailboxy (E80) — response tvary z dokumentace, neověřené proti mail_sys pluginu.
17. 🟠 **[NEDOKONČENO]** WEDOS DNSSEC / WHOIS privacy / glue records (F87/F89/F92) — jen mock.
18. 🟠 **[NEDOKONČENO]** Comgate IP whitelist fails-open by design (re-fetch je pojistka) — ověřit na produkci, že re-fetch skutečně chytá forgery.
19. 🟡 **[NEDOKONČENO]** GoPay OAuth token cache + expiry chování proti reálnému API.
20. 🟡 **[NEDOKONČENO]** aaPanel `GetPHPVersion` filtrování `'00'` (Static) — ověřit napříč verzemi panelu.
21. 🟡 **[NEDOKONČENO]** Proxmox async UPID polling (`CheckProxmoxTaskStatusJob`) — ověřit reálné UPID formáty a timeouty.
22. 🟡 **[NEDOKONČENO]** Orphan recovery (`findSiteIdByName`/`findServerIdByName`) proti reálnému panelu při přerušeném create.
23. 🟢 **[PŘIDAT]** Contract testy proti nahraným reálným fixture response (dnes contract test drží tvar, ne reálná data).

## C. Bezpečnost — pokročilé hardening

24. 🟠 **[NEDOKONČENO]** CSP enforce (`SECURITY_CSP_ENFORCE`) — nově zapojeno do configu, ale nikdy nezapnuté na produkci. Projít report-only violations, pak zapnout.
25. 🟠 **[CHYBÍ]** Subresource Integrity (SRI) na CDN skriptech (Swagger UI z unpkg).
26. 🟠 **[CHYBÍ]** Bezpečnostní audit závislostí v CI (`composer audit` / Dependabot).
27. 🟡 **[CHYBÍ]** WAF pravidla / ModSecurity pro webhosting produkty (F/E rozšíření).
28. 🟡 **[NEDOKONČENO]** 2FA je nepovinné (standing rule) — ale zvážit **vynucené 2FA jen pro reseller/partner** účty s výplatami (opt-in per role, ne globální).
29. 🟡 **[CHYBÍ]** Session fixation / concurrent session limit per uživatel.
30. 🟡 **[CHYBÍ]** Detekce anomálií přihlášení (nová země/ASN, ne jen nová IP) nad rámec I125.
31. 🟡 **[CHYBÍ]** Šifrování PII v klidu na úrovni sloupců (nad rámec auth_code/2FA secret) — rodná čísla, adresy.
32. 🟡 **[NEDOKONČENO]** Rate limit na password reset / magic link / topup (ověřit pokrytí).
33. 🟢 **[PŘIDAT]** Bug bounty / security.txt (`/.well-known/security.txt`).
34. 🟢 **[PŘIDAT]** Audit log immutability (append-only + hash chain) pro finanční operace.
35. 🟢 **[PŘIDAT]** Penetrační test před go-live (externí).
36. 🟢 **[PŘIDAT]** Klíčová rotace pro `APP_KEY` / API tokeny (dokumentovaný postup).
37. 🟢 **[PŘIDAT]** CAPTCHA / bot ochrana na registraci a kontaktních formulářích.
38. 🟢 **[PŘIDAT]** Passkeys / WebAuthn jako alternativa k TOTP 2FA.

## D. Platby a fakturace

39. 🟠 **[CHYBÍ]** Automatické opakované platby (uložená karta, recurring) — dnes každá obnova = ruční/manuální platba.
40. 🟠 **[NEDOKONČENO]** Refund přes bránu (D56) — dnes `original_method` = operátorský task; napojit reálné refund API bran.
41. 🟡 **[CHYBÍ]** Kreditní/dobírkové limity pro firemní zákazníky (net-30 fakturace).
42. 🟡 **[CHYBÍ]** Přepočet měn v reálném čase (dnes pevné ceny per měna).
43. 🟡 **[CHYBÍ]** Slevové kupóny — procenta vs. fixní, first-order-only, per-produkt (ověřit pokrytí `DiscountCode`).
44. 🟡 **[PŘIDAT]** Automatické upomínky (dunning) eskalace s pozastavením — ověřit napojení na suspension pipeline.
45. 🟡 **[PŘIDAT]** Faktura QR platba (QR Platba CZ / SEPA) na PDF.
46. 🟡 **[PŘIDAT]** Kontrola DIČ přes VIES (dnes OSS VAT logika existuje, ale bez online validace plátce).
47. 🟢 **[PŘIDAT]** Účetní export (Pohoda / Money S3 / ISDOC) nad rámec XLSX.
48. 🟢 **[PŘIDAT]** Vícejazyčné faktury dle `preferred_locale` (O193 — šablona je jen cs).
49. 🟢 **[PŘIDAT]** Zálohové vs. daňové doklady — vícenásobné DPH sazby na jedné faktuře.
50. 🟢 **[PŘIDAT]** Splátkový kalendář UI pro zákazníka (D57 backend existuje).

## E. Provisioning — robustnost

51. 🟠 **[CHYBÍ]** Automatický retry s exponenciálním backoff pro selhané provisiony (dnes ManualReview po max_attempts).
52. 🟠 **[NEDOKONČENO]** Kapacitní plánování serverů — `ServerSelector` počítá load živě, ale chybí alert při docházející kapacitě napříč flotilou.
53. 🟡 **[CHYBÍ]** Migrace služby mezi servery (přesun webu z plného na volný server).
54. 🟡 **[CHYBÍ]** Snapshot/restore jako self-service pro zákazníka (dnes admin approval).
55. 🟡 **[PŘIDAT]** Automatické škálování VPS (upgrade RAM/CPU bez reinstall).
56. 🟡 **[PŘIDAT]** Provisioning dry-run náhled pro admina před reálným zápisem.
57. 🟢 **[PŘIDAT]** Multi-region / multi-datacenter výběr při objednávce.
58. 🟢 **[PŘIDAT]** Provisioning webhook status stránka pro zákazníka (live progress baru).
59. 🟢 **[PŘIDAT]** Terminace s grace period + data retention dle `config/provisioning`.
60. 🟢 **[PŘIDAT]** Bulk provisioning operace pro admina (hromadný suspend/migrate).

## F. Domény / DNS

61. 🟠 **[CHYBÍ]** Domain transfer IN (příchozí transfer s EPP kódem) — dnes jen transfer request tracking.
62. 🟡 **[CHYBÍ]** Automatická obnova domén s předstihem + notifikace expirace (ověřit napojení).
63. 🟡 **[CHYBÍ]** DNS šablony jako produkt (Google Workspace, Microsoft 365 one-click).
64. 🟡 **[PŘIDAT]** DNSSEC UI plný tok (F87 backend existuje, ověřit UI).
65. 🟡 **[PŘIDAT]** Doménový aftermarket / premium domény.
66. 🟢 **[PŘIDAT]** Registrace více TLD najednou (bulk domain search + cart).
67. 🟢 **[PŘIDAT]** WHOIS privacy jako placený add-on.
68. 🟢 **[PŘIDAT]** Reverzní DNS (PTR) pro VPS.
69. 🟢 **[PŘIDAT]** DNS analytics (počet dotazů per záznam).
70. 🟢 **[PŘIDAT]** Sekundární DNS / DNS failover.

## G. Admin / provoz

71. 🟠 **[CHYBÍ]** Admin impersonation audit — kompletní záznam všeho, co admin udělal během „log in as customer" (dnes jen 30min box).
72. 🟡 **[CHYBÍ]** Admin dashboard — real-time provozní přehled (fronty, failed jobs, provisioning queue depth) na jednom místě.
73. 🟡 **[CHYBÍ]** Hromadné e-maily zákazníkům se segmentací + A/B (dnes bulk email existuje, ověřit segmentaci).
74. 🟡 **[PŘIDAT]** Admin approval workflow pro rizikové operace (velké refundy, terminace) — čtyři oči.
75. 🟡 **[PŘIDAT]** Admin poznámky/tagy napříč entitami (customer/service/invoice) — jednotný systém.
76. 🟢 **[PŘIDAT]** Admin mobilní PWA pro on-call.
77. 🟢 **[PŘIDAT]** Konfigurovatelný admin dashboard (widgety drag-drop).
78. 🟢 **[PŘIDAT]** Export libovolného seznamu do CSV/XLSX (jednotné tlačítko).
79. 🟢 **[PŘIDAT]** Admin command palette (Cmd+K globální akce).
80. 🟢 **[PŘIDAT]** Interní znalostní báze / runbooky pro support tým.

## H. Observabilita / monitoring

81. 🟠 **[CHYBÍ]** APM (application performance monitoring) — pomalé dotazy, N+1 v runtime, response time percentily.
82. 🟠 **[CHYBÍ]** Centralizované logy (log aggregator — Loki/ELK) — `LogContext` je připravený (M166), chybí sběr.
83. 🟡 **[CHYBÍ]** Business metriky dashboard (MRR, churn, LTV, CAC) — reseller MRR existuje (K149), chybí platform-wide.
84. 🟡 **[CHYBÍ]** Alerting na anomálie (náhlý pokles objednávek, spike v failed platbách).
85. 🟡 **[PŘIDAT]** Queue metriky / Horizon dashboard přístupný adminovi (Horizon je nainstalovaný).
86. 🟡 **[PŘIDAT]** Synthetic monitoring kritických toků (login → objednávka → platba) v produkci.
87. 🟢 **[PŘIDAT]** Real user monitoring (RUM) — frontend výkon.
88. 🟢 **[PŘIDAT]** Cost tracking per server / per zákazník (unit economics).
89. 🟢 **[PŘIDAT]** SLA reporting per zákazník (uptime %, incidenty).
90. 🟢 **[PŘIDAT]** Distributed tracing (request_id už existuje, propojit napříč službami).

## I. Notifikace / komunikace

91. 🟡 **[NEDOKONČENO]** I126 kritické alerty přes SMS — Slack hotový, SMS kanál (`CRITICAL_ALERT_SMS_TO`) potřebuje SMS bránu (Twilio/SMSbrana).
92. 🟡 **[CHYBÍ]** Push notifikace (web push / mobilní) nad rámec in-app zvonku.
93. 🟡 **[PŘIDAT]** Notifikační digest granularita (denní/týdenní/nikdy) per typ, ne globální (I127 je globální digest).
94. 🟢 **[PŘIDAT]** Notifikace do Discord/Teams (nad Slack).
95. 🟢 **[PŘIDAT]** Šablony notifikací editovatelné adminem.
96. 🟢 **[PŘIDAT]** Odhlašovací link v každém marketingovém e-mailu (compliance).
97. 🟢 **[PŘIDAT]** Notifikační historie / audit (co bylo komu posláno).
98. 🟢 **[PŘIDAT]** In-app changelog notifikace badge (I130 stránka existuje, přidat počet neviděných do zvonku).

## J. API / integrace

99. 🟠 **[CHYBÍ]** OAuth2 authorization flow pro dev portal (dnes OAuth aplikace existují, ale bez plného auth flow).
100. 🟡 **[CHYBÍ]** API SDK / klientské knihovny (PHP/JS/Python).
101. 🟡 **[CHYBÍ]** Webhook management UI pro zákazníka (v2/webhooks existuje jako API, chybí UI).
102. 🟡 **[PŘIDAT]** GraphQL endpoint jako alternativa REST.
103. 🟡 **[PŘIDAT]** API changelog + deprecation policy (versioning existuje).
104. 🟢 **[PŘIDAT]** Terraform provider / Infrastructure-as-Code integrace.
105. 🟢 **[PŘIDAT]** Zapier / Make konektor.
106. 🟢 **[PŘIDAT]** WHMCS import/export pro migrující zákazníky.
107. 🟢 **[PŘIDAT]** API usage analytics dashboard pro zákazníka (backend existuje).
108. 🟢 **[PŘIDAT]** API sandbox prostředí.

## K. Reseller / partner / affiliate

109. 🟡 **[CHYBÍ]** K144 sub-zákazník limity — reseller nemá strop počtu sub-zákazníků ani fakturační limit.
110. 🟡 **[CHYBÍ]** White-label kompletní — reseller custom doména + branding + panel_title existují, ověřit plný izolovaný panel.
111. 🟡 **[PŘIDAT]** Reseller fakturace vůči koncovým zákazníkům (reseller jako fakturující subjekt).
112. 🟡 **[PŘIDAT]** Partner marketingové materiály / bannery generátor (ověřit stav).
113. 🟢 **[PŘIDAT]** Reseller API pro automatizaci sub-zákazníků.
114. 🟢 **[PŘIDAT]** Vícestupňový affiliate (tier 2 provize z doporučených partnerů).
115. 🟢 **[PŘIDAT]** Reseller onboarding flow + smlouva (DocuSign-like).
116. 🟢 **[PŘIDAT]** Partner leaderboard / gamifikace.

## L. Výkon / škálovatelnost

117. 🟠 **[CHYBÍ]** Load test kritických endpointů (login, objednávka, webhook, chat poll) — L160 nikdy neproveden.
118. 🟡 **[CHYBÍ]** Tailwind purge — `style.css` má 200k+ řádků, zmenšit na použité třídy (L155).
119. 🟡 **[PŘIDAT]** Read replica pro DB (analytics/reporting mimo primární).
120. 🟡 **[PŘIDAT]** Fronta pro těžké exporty (XLSX/PDF velkých datasetů) — dnes synchronní.
121. 🟡 **[PŘIDAT]** Cache warming po deploy (dashboard, katalog).
122. 🟢 **[PŘIDAT]** Database connection pooling (PgBouncer-like pro MySQL).
123. 🟢 **[PŘIDAT]** Lazy-load / pagination u dlouhých activity logů.
124. 🟢 **[PŘIDAT]** Image optimization / WebP pro uploady.
125. 🟢 **[PŘIDAT]** HTTP/2 push / preload kritických assetů.
126. 🟢 **[PŘIDAT]** Archivace starých dat (faktury, logy) do cold storage.

## M. Testy / QA

127. 🟡 **[CHYBÍ]** Coverage report + práh v CI (M163) — kolik % kódu je pokryto.
128. 🟡 **[CHYBÍ]** Mutační testování (Infection) — kvalita testů, ne jen pokrytí.
129. 🟡 **[CHYBÍ]** Browser/Dusk E2E testy JS-kritických toků (košík, wizard, chat, compare) — M162.
130. 🟡 **[CHYBÍ]** Accessibility testy (axe-core) v CI (M164).
131. 🟡 **[PŘIDAT]** Visual regression testy (Percy-like) pro Cuba konformitu.
132. 🟢 **[PŘIDAT]** Contract testy proti reálným API fixture (nad tvar).
133. 🟢 **[PŘIDAT]** Performance regression testy (query count guard existuje, přidat čas).
134. 🟢 **[PŘIDAT]** Chaos testing (výpadek Redis/fronty/brány).
135. 🟢 **[PŘIDAT]** Seed data pro reprodukovatelné demo/staging.
136. 🟢 **[PŘIDAT]** Test flakiness monitoring v CI.

## N. UX / přístupnost / i18n / mobil

137. 🟡 **[CHYBÍ]** N170 focus-trap v modálech (fokus dovnitř, Esc, návrat).
138. 🟡 **[CHYBÍ]** N175 klávesové zkratky („/" hledání, „?" nápověda).
139. 🟡 **[CHYBÍ]** N180 onboarding tour pro nové zákazníky.
140. 🟡 **[PŘIDAT]** N178 skeleton/loading stavy u pomalých AJAX sekcí.
141. 🟡 **[PŘIDAT]** Kompletní i18n — ještě zbývají natvrdo psané cs řetězce v blade (nad `lang/`) — N172 částečně.
142. 🟡 **[PŘIDAT]** WCAG AA kontrast audit badge/light variant (N177).
143. 🟢 **[PŘIDAT]** Mobilní tabulky → karty (< 768px) místo horizontálního scrollu.
144. 🟢 **[PŘIDAT]** Další jazyky (SK, DE, PL) — infra pro en/cs hotová.
145. 🟢 **[PŘIDAT]** Dark mode i pro Antler frontend (dnes jen panel).
146. 🟢 **[PŘIDAT]** Empty-state ilustrace místo jen ikon.
147. 🟢 **[PŘIDAT]** Inline nápověda / tooltips u složitých polí.
148. 🟢 **[PŘIDAT]** Uložené filtry / pohledy v seznamech.
149. 🟢 **[PŘIDAT]** Bulk akce v zákaznickém panelu (hromadné platby faktur).
150. 🟢 **[PŘIDAT]** Reduced-motion / high-contrast režim.

## O. Produktové kategorie (nové)

151. 🟠 **[CHYBÍ]** E-mailový hosting jako produkt (O181) — velká chybějící kategorie; potřebuje mail API v aaPanel klientovi.
152. 🟡 **[CHYBÍ]** SSL certifikáty jako samostatný produkt (placené, wildcard, EV) — O182.
153. 🟡 **[CHYBÍ]** Object storage / S3-kompatibilní produkt — O183.
154. 🟡 **[CHYBÍ]** Managed WordPress tarif s jednoklik migrací — O184.
155. 🟡 **[CHYBÍ]** Migrační nástroj z konkurence (cPanel/Plesk import) — O191.
156. 🟡 **[PŘIDAT]** Marketplace aplikací — rozšířit katalog + instalace (O185, `Marketplace*` existuje).
157. 🟢 **[PŘIDAT]** Statusová stránka (status.onhost.cz) napojená na monitoring (O187).
158. 🟢 **[PŘIDAT]** Usage dashboard s grafy CPU/RAM/disk/traffic (O194) — potřebuje sběr metrik.
159. 🟢 **[PŘIDAT]** Automatické doporučení tarifu (AI recommendPlan → objednávka) — O192.
160. 🟢 **[PŘIDAT]** Backup jako placený add-on s retencí.
161. 🟢 **[PŘIDAT]** CDN produkt.
162. 🟢 **[PŘIDAT]** Dedikované servery / colocation.
163. 🟢 **[PŘIDAT]** Kubernetes / kontejnerový hosting.
164. 🟢 **[PŘIDAT]** Load balancer jako produkt.
165. 🟢 **[PŘIDAT]** Managed databáze (MySQL/PostgreSQL/Redis) jako produkt.
166. 🟢 **[PŘIDAT]** Věrnostní program / kredit za doporučení (O186).

## P. Datový model / architektura / tech-dluh

167. 🟡 **[NEDOKONČENO]** P197 UUID vs id v URL — Order/Service/Invoice bindují UUID, Domain id; sjednotit + zdokumentovat.
168. 🟡 **[NEDOKONČENO]** P198 soft-delete politika — ověřit konzistenci u zákazníků/služeb (GDPR mazání vs. audit retence).
169. 🟡 **[PŘIDAT]** P200 dokumentace doménových služeb (`docs/`) — chat, admin ops, order lifecycle, provisioning pipeline.
170. 🟡 **[PŘIDAT]** Event sourcing / doménové eventy pro auditovatelnost finančních toků.
171. 🟢 **[PŘIDAT]** Sjednotit money handling do jednoho value objectu napříč (dnes Brick\Money + minor units konvence).
172. 🟢 **[PŘIDAT]** Repository/query object pattern pro složité dotazy (dnes v controllerech).
173. 🟢 **[PŘIDAT]** Feature flags systém (dnes config env).
174. 🟢 **[PŘIDAT]** Migrace na read/write model separation u reportingu.
175. 🟢 **[PŘIDAT]** Konzistentní naming domén (`App\Domains\*`) — pár modelů žije v `App\Models`.
176. 🟢 **[PŘIDAT]** Automatická dokumentace enumů/stavových strojů.

## Q. Compliance / právní

177. 🟠 **[NEDOKONČENO]** GDPR export/mazání — ověřit úplnost (všechny tabulky s PII) a 30denní lhůtu automatické anonymizace.
178. 🟡 **[CHYBÍ]** DPA (zpracovatelská smlouva) generování pro firemní zákazníky.
179. 🟡 **[CHYBÍ]** Cookie consent — granularita kategorií (nezbytné/analytické/marketing) + evidence verzí.
180. 🟡 **[PŘIDAT]** Retenční politika dat (jak dlouho držet faktury/logy/PII) — zdokumentovat + vynutit.
181. 🟡 **[PŘIDAT]** Právo na přenositelnost dat (strojově čitelný export).
182. 🟢 **[PŘIDAT]** Souhlas s marketingem — double opt-in.
183. 🟢 **[PŘIDAT]** Audit přístupů k PII (kdo se koho podíval).
184. 🟢 **[PŘIDAT]** Age verification / KYC pro rizikové produkty.

## R. Business / growth

185. 🟡 **[CHYBÍ]** Churn prevence — automatické win-back kampaně (WinbackCampaign existuje, ověřit trigger).
186. 🟡 **[CHYBÍ]** Upsell/cross-sell engine (doporučení add-onů k běžící službě).
187. 🟡 **[PŘIDAT]** Zákaznický NPS trend + akční follow-up (NPS survey existuje).
188. 🟡 **[PŘIDAT]** Referral program s tracking dashboardem pro zákazníka.
189. 🟢 **[PŘIDAT]** Slevové akce / kampaně s časovým oknem (Black Friday).
190. 🟢 **[PŘIDAT]** Věrnostní slevy dle délky zákaznictví.
191. 🟢 **[PŘIDAT]** Affiliate landing page builder.
192. 🟢 **[PŘIDAT]** Zákaznické recenze / testimonials sběr.
193. 🟢 **[PŘIDAT]** Onboarding e-mailová sekvence (drip existuje, napojit na signup).
194. 🟢 **[PŘIDAT]** Cenový konfigurátor / kalkulačka na webu.

## S. Provozní zralost

195. 🟠 **[CHYBÍ]** Runbooky pro incidenty (výpadek brány, panelu, DB) — dokumentované postupy.
196. 🟡 **[CHYBÍ]** On-call rotace + eskalační matice (I126 alerting je připravený).
197. 🟡 **[PŘIDAT]** Disaster recovery plán + pravidelný restore test záloh.
198. 🟡 **[PŘIDAT]** Capacity planning proces (kdy přidat server) — data existují (ServerSelector).
199. 🟢 **[PŘIDAT]** Postmortem šablona + blameless kultura.
200. 🟢 **[PŘIDAT]** SLA definice + interní SLO/error budget.

---

## Doporučené pořadí (priorita)

| # | Priorita | Body |
|---|---|---|
| 1 | 🔴 go-live blockery | 1–5 (reálné brány, aaPanel/Proxmox/WEDOS E2E, Sentry) |
| 2 | 🟠 produkční zralost | 6–15 zálohy/redis/horizon/monitoring, 24 CSP enforce, 39 recurring platby, 51 provisioning retry, 71 impersonation audit, 81–82 APM/logy, 117 load test, 177 GDPR úplnost, 195 runbooky |
| 3 | 🟡 rozšíření hodnoty | produktové kategorie 151–155, i18n 141/144, testy 127–130, reseller 109–110 |
| 4 | 🟢 nice-to-have | zbytek — dle produktové strategie |

**Poznámka ke stavu:** vše z původního auditu (fáze A–P) je hotové a otestované
(3194 testů, PHPStan 0). Tento dokument je čistě dopředný — žádný z bodů není
regrese, jde o zralostní a růstové kroky nad hotovým MVP. Blockery kategorie A
jsou většinou **externě blokované** (potřebují reálné credentials / produkční
prostředí), ne chybějící kód.
