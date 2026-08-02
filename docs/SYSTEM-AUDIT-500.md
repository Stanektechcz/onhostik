# OnHost — Hloubkový audit 500+ bodů (2026-08-02)

Komplexní backlog pro **maximalizaci výkonu, efektivity, funkčnosti a rozšířenosti**.
Na rozdíl od `SYSTEM-AUDIT-200-FINAL.md` (stav 200 funkčních bodů) je tohle
**dopředný improvement backlog**: co dodělat, rozšířit, přidat, zajistit a jaké
údaje doplnit. Body jsou konkrétní a akční.

## Legenda stavů

- ✅ **hotovo** — implementováno a ověřeno (kontext)
- 🟡 **částečně** — existuje, ale je vhodné dokončit/rozšířit
- 🔴 **chybí** — není v kódu, doporučeno doplnit
- ➕ **přidat** — nová funkce/vylepšení pro vyšší hodnotu
- 🔑 **údaje** — čeká na reálné credentials/konfiguraci
- 🏗 **infra** — serverové/provozní zajištění, ne kód

---

## A. Výkon — databáze a dotazy (1–32)

1. 🟡 Zapnout `Model::preventLazyLoading()` mimo produkci pro odhalení N+1 v testech/dev.
2. ➕ Zavést `Model::preventSilentlyDiscardingAttributes()` a `preventAccessingMissingAttributes()`.
3. 🟡 N+1: většina výpisů už `->with()`; systematické dořešení = `preventLazyLoading` (větší úklid, viz #1).
4. ➕ Kompozitní indexy pro časté filtry: `services(customer_id,status)`, `invoices(customer_id,status,due_date)`.
5. ➕ Index na `activity_log(subject_type,subject_id,created_at)` pro timeline.
6. ➕ Index na `api_usage_logs(token_id,created_at)` a `(user_id,created_at)` pro analytiku.
7. 🟡 Ověřit indexy na všech FK sloupcích (Laravel je automaticky nevytváří u všech).
8. ➕ Přidat `chunkById`/`lazy()` do všech dávkových příkazů místo `get()` (paměť).
9. ➕ Kurzorová paginace (`cursorPaginate`) pro velké seznamy (audit log, api usage, faktury).
10. ➕ Cache agregací dashboardu (tržby, počty) s krátkou TTL místo dotazu na každý request.
11. ✅ Denní revenue rollup (`revenue_daily` + `reporting:rollup-revenue`, idempotentní, scheduled).
12. ➕ `EXPLAIN` profiling nejpomalejších dotazů z `configureSlowQueryLogging`.
13. ➕ Read-replica pro reporting/analytiku (`DB::connection('read')`).
14. ➕ Připravit connection pooling / persistent connections (PgBouncer ekvivalent) na infra.
15. 🟡 Zkrátit `select *` na konkrétní sloupce v horkých cestách (velké modely, encrypted).
16. ➕ Zvážit přechod encrypted PII na deterministické šifrování jen kde je nutné hledat.
17. ➕ Přidat `->remember()`/cache na číselníky (produkty, ceny, země, měny).
18. 🟡 Batch-insert: migrace používají `insertOrIgnore` dávky a `Notification::send` batchuje — bez jednoho hotspotu.
19. ➕ Fulltextový index pro globální vyhledávání (dnes LIKE) — MySQL FULLTEXT / Meilisearch.
20. ➕ Meilisearch/Typesense pro škálovatelné vyhledávání zákazníků/služeb/KB.
21. 🟡 Optimalizovat `whereHas` s poddotazy → `whereExists`/joins kde je to horké.
22. ➕ Databázové transakce kolem multi-write akcí (objednávka→služba→faktura) auditovat.
23. ➕ Soft-delete cleanup příkaz (pruning starých soft-deleted řádků).
24. ✅ Pruning: `retention:apply` pokrývá activity_log/login_history/incidents/export_jobs + nově `api_usage_logs`.
25. ➕ `MassPrunable` na log modely (Laravel prune scheduling).
26. 🏗 Partitioning velkých tabulek (api_usage_logs, activity_log) — DB-engine DDL, serverová úloha.
27. ➕ Query result cache invalidace přes model events (observery).
28. ➕ Kontrola `updated_at` touchování v horkých relacích (zbytečné zápisy).
29. ➕ Deferred/queued zápis analytiky (api usage) místo synchronního insertu v requestu.
30. 🟡 Ověřit, že `LogApiUsage` insert neblokuje API latenci (přesunout na `terminating`/queue).
31. ➕ Databázové zdraví: alert na velikost tabulek a růst (metrics gauge).
32. ➕ Připravit migraci na UUID/ULID primární klíče kde je to relevantní (distribuce).

## B. Výkon — cache a fronty (33–57)

33. 🔑 Nasadit Redis pro cache + session + queue (dnes default; produkce chce Redis).
34. ➕ Cache tagging pro cílenou invalidaci (product catalog, settings).
35. ➕ `config:cache`, `route:cache`, `event:cache`, `view:cache` v deploy pipeline.
36. 🏗 Horizon pro fronty (pcntl/posix) — supervisor unit už existuje, nasadit.
37. ➕ Oddělené fronty dle priority: `high` (platby/provisioning), `default`, `low` (maily, analytika).
38. ➕ `WithoutOverlapping` + `onOneServer` na všech relevantních scheduled úlohách.
39. 🟡 Rate-limit náročných jobů (`RateLimited` middleware) vůči externím API.
40. ➕ Job batching (`Bus::batch`) pro hromadné notifikace/reporty s progress.
41. ➕ Unique jobs (`ShouldBeUnique`) pro provisioning/sync aby se neduplikovaly.
42. ➕ Backoff + `retryUntil` konzistentně na všech externích jobech.
43. ➕ Dead-letter handling: přesun trvale selhaných jobů + admin akce retry/zahodit.
44. ✅ Queue health alert (`queue:health-check`) — pending+failed prahy, notifikuje adminy (scheduled).
45. ➕ Cache warming po deploy (číselníky, homepage, ceny).
46. ➕ Response cache pro veřejné stránky (homepage, ceník, KB) s ETag/Cache-Control.
47. ➕ HTTP cache hlavičky (`Cache-Control`, `ETag`, `Last-Modified`) na read API.
48. ➕ Conditional GET (304) pro read endpointy (šetří přenos).
49. ➕ Cache invalidace při změně cen/produktů (observer → forget tagů).
50. ✅ Distribuovaný lock (`Cache::lock`) pro výběr serveru — `ServerSelector::pickAndReserve` (select+reserve atomicky).
51. ✅ Číselná řada faktur — `lockForUpdate` v transakci + `unique(series,year)` uzavírá i insert-race.
52. ➕ Queue metriky do Prometheus (throughput, wait time, failure rate).
53. ➕ Scheduled task duration tracking + alert na pomalé/dlouhé běhy.
54. ➕ Memoizace opakovaných výpočtů v rámci requestu (currency, settings).
55. ➕ Octane (Swoole/RoadRunner) pro dramatické zrychlení (vyžaduje audit stavovosti).
56. ➕ Lazy collections pro exporty (XLSX/CSV) aby nešplhala paměť.
57. 🟡 Ověřit, že PDF generování (dompdf) běží ve frontě, ne v requestu (paměť/latence).

## C. Výkon — HTTP, assety, frontend (58–82)

58. 🔑 Obnovit smazané frontend assety (`public/panel/*`, `public/front/*`) — akce uživatele (restore / `npm build`).
59. ➕ Vite build pipeline + hashované assety + long-term cache.
60. ➕ Code-splitting a lazy-load JS panelu (per-page bundly).
61. ➕ Odstranit jQuery závislost kde možné (moderní vanilla/Alpine).
62. ➕ Kritické CSS inline + defer zbytku.
63. ➕ Preload/preconnect klíčových zdrojů a fontů.
64. ➕ WebP/AVIF obrázky + `loading="lazy"` + `srcset`.
65. ➕ Brotli/gzip komprese na serveru (infra).
66. 🏗 HTTP/2 nebo HTTP/3 na reverzní proxy.
67. 🏗 CDN pro statické assety + edge cache.
68. ➕ Subresource Integrity (SRI) na externí skripty (CSP už je).
69. ➕ Bundle-size rozpočet + kontrola v CI.
70. ➕ Lighthouse/Core Web Vitals měření v CI.
71. ➕ PWA: manifest + instalovatelnost (service worker už existuje pro push).
72. ➕ Offline fallback stránka přes service worker.
73. ➕ Skeleton loadery rozšířit na všechny async sekce (komponenta existuje).
74. ➕ Optimistic UI u rychlých akcí (toggle, mazání) s rollbackem.
75. ➕ Debounce/throttle realtime hledání (služby, zákazníci).
76. ➕ Virtuální scrollování dlouhých tabulek.
77. ➕ Prefetch odkazů při hoveru (instant navigace).
78. ➕ Tmavý režim s persistentním přepínačem.
79. ➕ Responzivita audit na mobilu (admin tabulky, checkout).
80. ➕ `prefers-reduced-motion` respektovat u animací (skeleton už ano).
81. ➕ Font-display swap + self-host fontů.
82. 🏗 Asset verzování a cache-busting v deploy.

## D. Škálování a infrastruktura (83–112)

83. 🏗 Stateless app servery za load balancerem (session v Redis).
84. 🏗 Horizontální škálování workerů (Horizon na více uzlech).
85. 🏗 Autoscaling app/worker dle metrik (CPU/queue depth).
86. 🏗 Zero-downtime deploy (atomic symlink / rolling).
87. 🏗 Health-based rotace přes `/api/ready` (hotovo v kódu).
88. 🏗 Oddělené prostředí: dev/staging/production s paritou.
89. 🏗 Infrastructure as Code (Terraform/Ansible) pro reprodukovatelnost.
90. 🏗 Kontejnerizace (Docker) app + work+ scheduler + Redis + DB.
91. 🏗 Secrets management (Vault/SOPS) místo .env na serveru.
92. 🏗 Centralizované logy (Loki/ELK) + korelace přes X-Request-Id (hlavička už je).
93. ➕ Distributed tracing (OpenTelemetry) přes requesty/joby.
94. ➕ APM (application performance monitoring) integrace.
95. 🏗 Rate-limit na edge/WAF (kromě app-level throttle).
96. 🏗 DDoS ochrana na infra (Cloudflare/anti-DDoS).
97. 🔑 TLS certifikát + HSTS preload + OCSP stapling.
98. 🏗 Automatická obnova TLS (ACME) — příkaz existuje, nasadit cron.
99. 🏗 Cron pro `schedule:run` každou minutu (heartbeat už hlídá).
100. 🏗 Zálohy DB offsite + šifrované + otestovaná obnova (restore drill).
101. 🏗 Point-in-time recovery (binlog) pro DB.
102. 🏗 Geografická redundance / multi-AZ.
103. ➕ Read/write splitting na DB úrovni.
104. 🏗 Object storage (S3/B2) pro uživatelské soubory + zálohy.
105. 🏗 Monitorování diskového místa + alert.
106. 🏗 Log rotace + retence na serveru.
107. 🏗 NTP/časová synchronizace (kritické pro tokeny/2FA/faktury).
108. 🏗 Firewall + fail2ban + SSH hardening.
109. 🏗 Oddělení sítě: app ↔ DB ↔ Redis privátní.
110. ➕ Feature flags systém pro postupné rollouty bez deployů.
111. ➕ Blue-green / canary deploy strategie.
112. 🏗 Runbook pro incidenty + eskalace (částečně v docs).

## E. Observabilita a monitoring (113–137)

113. ✅ Readiness probe `/api/ready` (DB/cache/queue/storage).
114. ✅ Prometheus `/metrics` (token-gated).
115. ✅ Scheduler heartbeat (dead-cron detekce).
116. ✅ Backup-freshness na system-health stránce.
117. ➕ Rozšířit `/metrics` o business gauge (aktivní služby, MRR) — samostatný gated endpoint.
118. ➕ Histogramy latence requestů/jobů do metrik.
119. ➕ Alerting pravidla (Alertmanager) pro klíčové gauge.
120. ➕ Uptime monitoring externí (StatusCake/Kuma) na `/up` a `/ready`.
121. ➕ Veřejná/interní status page napojená na reálné checky (dnes `/stav`).
122. 🔑 Sentry DSN pro error tracking (scaffold `ErrorContext` hotový).
123. ➕ Front-end error tracking (JS chyby) do Sentry.
124. ➕ Structured JSON logy s kontextem (request_id, user, tenant).
125. ➕ Audit „kdo viděl co" pro citlivá data (GDPR access log).
126. ➕ Business KPI dashboard realtime (MRR, churn, konverze) — část existuje.
127. ➕ Anomaly detection na platby/objednávky (fraud signály).
128. ➕ SLA monitoring dashboard (dostupnost, response time) — incidenty existují.
129. ➕ Synthetic monitoring klíčových toků (objednávka, platba).
130. ➕ Real user monitoring (RUM) pro frontend.
131. ➕ Queue lag alert (jobs čekají > threshold).
132. ➕ Certificate expiry monitoring (TLS + DNSSEC klíče) — SSL monitoring existuje.
133. ➕ Deadletter/failed-job dashboard s trendy.
134. ➕ Log sampling/rate-limit aby se nezahltily logy.
135. ➕ Trace ID propagace do externích API volání.
136. ➕ Dashboard integrací health (existuje list) → historie + trend.
137. ➕ Notifikace adminovi při degradaci `/ready` (přes externí monitor).

## F. Bezpečnost — aplikace (138–170)

138. ✅ CSP s nonce + enforce, bezpečnostní hlavičky, HMAC webhooky, šifrování PII.
139. ➕ Rotace `APP_KEY` postup + re-encrypt příkaz pro PII (reindex existuje).
140. ➕ Rotace API tokenů: expirace + upozornění před vypršením.
141. ➕ Scoped tokeny per-IP allowlist (kromě abilities).
142. ➕ Detekce a blokace credential stuffing (login) — throttle existuje, přidat device/geo.
143. ➕ Passkeys / WebAuthn jako 2FA metoda (dnes TOTP).
144. ➕ Vynucené 2FA volitelně per-role politikou (2FA je nepovinné dle pravidla).
145. ➕ Session anomaly (nová země/zařízení) → step-up auth.
146. ➕ Bezpečné cookies audit (SameSite, Secure, HttpOnly, __Host- prefix).
147. ➕ Subdomain/tenant isolation audit (reseller domény).
148. ➕ Mass-assignment audit napříč modely (`$fillable` vs request).
149. ➕ Authorization audit: policy/gate na každý admin/panel endpoint.
150. ➕ IDOR audit: ownership check u všech `{model}` route bindingů.
151. ➕ Rate-limit na citlivé akce (změna e-mailu, hesla, plateb).
152. ➕ CAPTCHA/turnstile na registraci a kontaktní formuláře (proti botům).
153. ➕ Content scanning nahraných souborů (antivir) + typová validace.
154. ➕ Signed URLs pro dočasné odkazy (zálohy, exporty) — GDPR export už má token.
155. ➕ Secrets scanning v CI (gitleaks) — commit hygiena.
156. ➕ Dependency scanning (composer audit v CI existuje) + Dependabot/Renovate.
157. ➕ SAST (statická bezpečnostní analýza) v CI.
158. ➕ DAST/penetrační test před go-live.
159. ➕ `security.txt` (RFC 9116) + kontakt pro disclosure.
160. ➕ Bug bounty / responsible disclosure proces.
161. ➕ Audit log neměnnost (append-only, hash chain) pro forenziku.
162. ➕ Ochrana proti replay útokům na webhooky (nonce/timestamp) — HMAC existuje.
163. ➕ Idempotence i pro GraphQL mutace (REST má EnforceIdempotency).
164. ➕ GraphQL: disable introspection v produkci + persisted-only režim (APQ existuje).
165. ➕ Omezení velikosti requestů/uploadů + timeouts.
166. ➕ Ochrana admin panelu: IP allowlist (existuje) + VPN-only doporučení.
167. ➕ Šifrování dalších PII sloupců (adresy, rodná čísla nejsou-li) + blind index.
168. ➕ PII maskování v logu/exportech konzistentně (SecretsNeverLeak test existuje).
169. ➕ Ochrana proti enumeraci (registrace, reset hesla — jednotné odpovědi).
170. ➕ Bezpečnostní review CSP report endpointu (throttle existuje) + analýza reportů.

## G. Bezpečnost — provoz a compliance kontrola (171–195)

171. 🏗 WAF pravidla na edge (aplikační WAF doména existuje).
172. 🏗 Pravidelný patch management OS/PHP/knihoven.
173. 🏗 Nejmenší oprávnění DB uživatele (ne root).
174. 🏗 Šifrování at-rest na DB/disku.
175. 🏗 Šifrování záloh + oddělené klíče.
176. 🏗 Přístup k produkci přes bastion + audit SSH.
177. 🏗 MFA na cloud/hosting účty (infra).
178. 🏗 Rotace přístupových klíčů k S3/DB pravidelně.
179. ➕ Incident response plán + tabletop cvičení.
180. ➕ Data breach notifikační proces (GDPR 72h).
181. ➕ Klasifikace dat (PII/tajné/veřejné) + politiky.
182. ➕ Retenční politiky per-datový-typ (retention příkaz existuje) rozšířit.
183. ➕ Right-to-be-forgotten úplnost (erasure příkaz existuje) — pokrýt všechny tabulky.
184. ➕ DPA se subdodavateli (generování DPA existuje pro zákazníky).
185. ➕ Cookie consent granularita + logování souhlasů (existuje) rozšířit.
186. ➕ Pravidelný přístupový review (kdo má admin/reseller práva).
187. ➕ Segregace povinností (four-eyes existuje) rozšířit na další rizikové akce.
188. ➕ Audit exportů dat (kdo co exportoval).
189. ➕ Ochrana proti hromadnému stažení dat (rate-limit exportů).
190. ➕ Právní stránky verzování + akceptace verze uživatelem.
191. ➕ Accessibility statement (a11y prohlášení) — právní požadavek.
192. ➕ Souhlas s marketingem oddělený od nutných notifikací (preferences existují).
193. 🔑 Reálné údaje pro fakturační/účetní compliance (DIČ, banka) doplnit.
194. ➕ Archivace faktur dle zákona (10 let) + neměnnost.
195. ➕ EET/účtenky pokud relevantní pro trh (zvážit).

## H. API — REST, GraphQL, SDK (196–228)

196. ✅ REST v1/v2, OpenAPI, idempotence, per-token limity, GraphQL (read+mutace+APQ), OAuth2.
197. ➕ GraphQL: rozšířit o `orders`, `paymentMethods`, `domains` detaily, `monitors`.
198. ➕ GraphQL mutace: `topUpCredit`, `updateProfile`, `createOrder` (s idempotencí).
199. ➕ GraphQL: DataLoader pro N+1 v resolverech (batch).
200. ➕ GraphQL: query cost analýza dle polí (dnes depth+complexity).
201. ➕ GraphQL: subscriptions (realtime) přes websockety — pokročilé.
202. ➕ REST: kurzorová paginace + `Link` hlavičky.
203. ➕ REST: filtrování/řazení/sparse-fieldsets (JSON:API styl) konzistentně.
204. ➕ REST: PATCH s částečnou aktualizací kde chybí.
205. ➕ REST: `Retry-After` hlavička u 429 + 503.
206. ➕ REST: konzistentní chybový formát RFC 7807 (problem+json).
207. ➕ API: verzování přes hlavičku i URL (URL existuje) + sunset policy (changelog existuje).
208. ➕ Webhooky odchozí: retry s backoff + podpisové rotace + dashboard doručení (existuje).
209. ➕ Webhooky: uživatelský výběr událostí (event catalog) rozšířit.
210. ➕ API: sandbox/test režim s testovacími daty pro vývojáře.
211. ➕ API: klíče s prostředím (test/live) rozlišené.
212. ➕ SDK PHP/JS: doplnit nové endpointy (v2, webhooky, OAuth flow helper).
213. ➕ SDK: publikovat na Packagist / npm + verzování.
214. ➕ SDK: Python/Go klienti (rozšíření dosahu).
215. ➕ SDK: automatická generace z OpenAPI.
216. ➕ Developer portál: interaktivní „try it" konzole (Swagger UI existuje).
217. ➕ Developer portál: usage analytics per-token grafy (existuje) rozšířit.
218. ➕ API: mTLS pro nejcitlivější integrace (volitelně).
219. ➕ API: GraphQL introspection dokumentace generovaná do portálu.
220. ➕ API: bulk endpointy (hromadné operace) pro efektivitu.
221. ➕ API: pole `expand`/`include` pro vnořené zdroje.
222. ➕ API: konzistentní timestamp formát (ISO 8601 UTC) audit.
223. ➕ API: locale/currency negotiation přes hlavičky.
224. ➕ API: deprecation warnings v odpovědích (lifecycle middleware existuje).
225. ➕ API: rate-limit dle plánu zákazníka (tiered).
226. ➕ API: idempotency-key i pro GraphQL a webhook re-delivery.
227. ➕ API: OpenAPI examples/schémata doplnit u všech operací (coverage test existuje).
228. ➕ API: GraphQL error kódy standardizovat (extensions.code).

## I. Integrace a poskytovatelé (229–260)

229. ✅ Credential vault v administraci (19 poskytovatelů), test připojení, mock/dry-run gaty.
230. 🔑 aaPanel: reálný base_url + api_key + otevřít `AAPANEL_ALLOW_REAL_WRITES`.
231. 🔑 WEDOS WAPI: user/heslo + `WAPI_ALLOW_REAL_WRITES`.
232. 🔑 Proxmox/Pterodactyl: endpoint + token.
233. 🔑 Comgate/Stripe/GoPay: reálné klíče v administraci.
234. 🔑 AI (Claude/OpenAI): klíč + `AI_ALLOW_REAL_CALLS`.
235. 🔑 VAPID klíče (příkaz `webpush:vapid` nebo administrace).
236. 🔑 SMTP: produkční credentials (MailConfigurator čte vault).
237. 🔑 S3/B2 zálohy: credentials.
238. 🔑 Cloudflare: api_token + zone_id (klient hotový, mock-gated).
239. 🔴 Uptime Kuma klient — socket.io, zvážit status/metrics endpoint místo REST.
240. 🔴 n8n: pokryto generickým webhook systémem — případně dedikovaný trigger UI.
241. 🟡 Stripe/GoPay: dokončit produkční 3-D Secure návratové toky (vedle Comgate).
242. 🟡 aaPanel: mailbox forwardy/autorespondéry (dnes create/delete/quota).
243. ➕ Registrátoři domén: další (Namecheap, OVH) za jednotné rozhraní.
244. ➕ DNS provideři: abstrakce (dnes WEDOS napevno) + Cloudflare jako provider.
245. ➕ Platební brány: PayU, Apple/Google Pay, SEPA, bankovní převod s párováním.
246. ➕ Fakturační/účetní integrace (Fakturoid, Money S3, Pohoda) — export existuje.
247. ➕ Účetní webhooky/synchronizace do ERP.
248. ➕ SMS brána pro 2FA/notifikace (dnes e-mail/push).
249. ➕ Slack/Teams/Discord notifikace pro admin/ops.
250. ➕ Monitoring integrace (Grafana Cloud, Datadog) přes `/metrics`.
251. ➕ CDN/WAF integrace (Cloudflare, Bunny) management z panelu.
252. ➕ Object storage jako produkt (S3-kompatibilní pro zákazníky).
253. ➕ Let's Encrypt/ACME plná automatizace v provisioningu (příkaz existuje).
254. ➕ Migrace z konkurence (import z cPanel/Plesk/aaPanel).
255. ➕ Marketplace integrací (třetí strany) s OAuth (OAuth server existuje).
256. ➕ Circuit breaker + health tracking per-integrace (last_error/success existuje).
257. ➕ Retry queue pro selhané externí volání s idempotencí.
258. ➕ Sandbox režim pro každou integraci (test bez reálných dopadů) — mock existuje.
259. ➕ Konzistentní timeouts + retry napříč všemi klienty.
260. ➕ Verzování externích API + detekce breaking changes.

## J. Fakturace a platby (261–292)

261. ✅ Faktury+PDF, číselné řady, dobropisy, DPH/OSS, dunning, splátky, pro-rata, kredit.
262. ➕ Opakované platby uložené kartou (tokenizace) end-to-end (recurring existuje).
263. ➕ Automatické strhávání z karty při obnově (dnes kredit/manuál).
264. ➕ Wallet/kredit: převod mezi účty, dárkové kredity.
265. ➕ Fakturace po užití (metered billing) pro VPS/traffic.
266. ➕ Víceúrovňové ceníky + množstevní slevy + tier pricing.
267. ➕ Promo/kupóny rozšířit: první měsíc zdarma, BOGO, cílené segmenty.
268. ➕ Předplatné management: pause/resume/upgrade s pro-rata (plán change existuje).
269. ➕ Roční platba se slevou + toggle měsíční/roční.
270. ➕ Faktury v cizí měně + kurzové zajištění (přepočet existuje).
271. ➕ Automatické upomínky eskalace + odpojení + reaktivace flow (dunning existuje).
272. ➕ Chargeback/dispute workflow rozšířit (evidence existuje).
273. ➕ Refundace částečné + storno faktury audit.
274. ➕ Zaokrouhlení dle měny/legislativy konzistentně.
275. ➕ Reverse charge / VIES ověření (existuje) → automatické DPH pravidlo.
276. ➕ Účtenky/proforma před platbou.
277. ➕ Kreditní limit + fakturace na splatnost pro firmy.
278. ➕ Automatické párování bankovních plateb (VS) — QR platba existuje.
279. ➕ Přehled cash-flow/revenue forecast (forecast existuje) rozšířit.
280. ➕ Daňové reporty (DPH přiznání, kontrolní hlášení) export.
281. ➕ Nákladové středisko/účtování per-produkt marže.
282. ➕ Slevové kódy pro resellery/partnery.
283. ➕ Fakturační profily (více adres/subjektů pod účtem) — sub-účty existují.
284. ➕ Automatické dobropisy při downgrade/zrušení.
285. ➕ Platební plán/kalendář viditelný zákazníkovi.
286. ➕ Webhook `invoice.paid/overdue/refunded` do účetnictví.
287. ➕ Idempotence plateb (dvojitá platba) audit + ochrana.
288. ➕ Anti-fraud skóre u plateb (geo, BIN, velocity).
289. ➕ Platby v kryptu (volitelně) — pouze pokud trh chce.
290. ➕ Faktury e-mailem + do datové schránky (CZ) volitelně.
291. ➕ Připomínka expirace karty + self-service aktualizace.
292. ➕ Historie všech finančních transakcí exportovatelná (XLSX existuje).

## K. Provisioning a služby (293–324)

293. ✅ aaPanel/Proxmox/Pterodactyl/WEDOS drivery, retry, kapacita, orphan recovery, drain, auto-heal, autoscale.
294. ➕ Provisioning stavový automat (state machine) explicitní + vizualizace.
295. ➕ Idempotentní provisioning kroky + rollback při částečném selhání.
296. ➕ Provisioning preview/dry-run i pro zákazníka (existuje admin) .
297. ➕ Fronta provisioningu s prioritou + odhad času.
298. ➕ Automatické přeřazení na jiný server při selhání (drain existuje) rozšířit.
299. ➕ Kapacitní plánování dashboard (kolik místa/serverů zbývá).
300. ➕ Server pools/regiony výběr zákazníkem (lokalita).
301. ➕ Živé metriky služby (CPU/RAM/disk/traffic) v panelu (live status existuje).
302. ➕ Grafy využití zdrojů v čase (per-služba).
303. ➕ Automatické upgrady zdrojů při překročení (nebo alert) — quota breach existuje.
304. ➕ Snapshoty on-demand + plánované + restore (approval existuje).
305. ➕ Klonování/šablony služeb (rychlé nasazení).
306. ➕ Staging→produkce push pro webhosting.
307. ➕ Git deploy / CI hooky pro webhosting.
308. ➕ One-click aplikace rozšířit (marketplace existuje) + verze/aktualizace.
309. ➕ Automatické aktualizace CMS + bezpečnostní patche (marketplace).
310. ➕ WordPress toolkit (staging, clone, updates, security scan).
311. ➕ SSH/SFTP/DB přístupy management z panelu.
312. ➕ Cron management pro webhosting z panelu.
313. ➕ File manager v panelu (nebo odkaz do aaPanel).
314. ➕ DNS/e-mail/DB rychlé odkazy v service detailu (aaPanel parita existuje).
315. ➕ Migrace mezi tarify bez výpadku (plán change existuje) rozšířit.
316. ➕ Bulk operace nad službami (admin) rozšířit.
317. ➕ Automatické testy dostupnosti služby po provisioningu.
318. ➕ Rollback provisioningu na tlačítko.
319. ➕ Rezervace zdrojů před platbou (hold kapacity).
320. ➕ Overselling politika + monitoring (kapacita alert existuje).
321. ➕ Service dependency graph (doména↔hosting↔SSL).
322. ➕ Automatické vystavení SSL po přiřazení domény (ACME).
323. ➕ Suspend/unsuspend s grace period + notifikace (suspend existuje).
324. ➕ Reinstall/rebuild s výběrem OS/šablony (game reinstall existuje) rozšířit.

## L. Domény a DNS (325–344)

325. ✅ Registrace, DNS CRUD, DNSSEC, transfer, WHOIS privacy, bulk NS, šablony, glue.
326. ➕ DNS provider abstrakce (WEDOS napevno) + Cloudflare/Route53 drivery.
327. ➕ DNS propagace check + vizualizace stavu.
328. ➕ DNS health check (existuje) rozšířit o SPF/DKIM/DMARC validaci.
329. ➕ Automatické DKIM/SPF/DMARC pro e-mailový hosting.
330. ➕ DNSSEC automatizace end-to-end + rotace klíčů.
331. ➕ Doménový aukční/backorder systém.
332. ➕ Bulk registrace/transfer domén.
333. ➕ Doménové portfolio dashboard (expirace, auto-renew).
334. ➕ Automatické prodloužení + upomínky (existuje) + grace/redemption stavy.
335. ➕ Premium domény + prémiové ceny.
336. ➕ IDN/národní znaky podpora.
337. ➕ WHOIS/RDAP lookup nástroj v panelu.
338. ➕ Doménové tipy/generátor jmen (AI) při objednávce.
339. ➕ TLD katalog s cenami + politikami (min/max roky).
340. ➕ Registrant kontakty management + GDPR.
341. ➕ Transfer lock/EPP kód management.
342. ➕ DNS import/export (zone file BIND).
343. ➕ Anycast DNS nabídka (infra/provider).
344. ➕ URL forwarding/redirect služba.

## M. Zákaznický panel a UX (345–376)

345. ✅ Cuba design, skeleton, onboarding, sub-účty (role, pozvánky, přepínač), loyalty, marketplace.
346. ➕ Globální hledání v panelu (služby, faktury, domény, tikety).
347. ➕ Přizpůsobitelný dashboard (widgety, pořadí).
348. ➕ Rychlé akce/command palette (Cmd+K).
349. ➕ Notifikační centrum rozšířit (filtry, kategorie) — existuje.
350. ➕ Onboarding checklist/průvodce pro nové zákazníky (tour existuje).
351. ➕ In-app product tour pro nové funkce.
352. ➕ Kontextová nápověda + tooltips + odkazy do KB.
353. ➕ Prázdné stavy s CTA (komponenta existuje) všude.
354. ➕ Bulk akce v seznamech zákazníka.
355. ➕ Export dat zákazníka (faktury, služby) self-service (GDPR export existuje).
356. ➕ Aktivita účtu/feed (existuje) rozšířit o filtr a export.
357. ➕ Vícejazyčnost panelu (i18n ratchet hlídá) — doplnit EN překlady.
358. ➕ Přepínač měny + jazyka v UI.
359. ➕ Uživatelské preference (téma, hustota, výchozí stránka).
360. ➕ Mobilní aplikace / responzivní PWA.
361. ➕ Přístupnost WCAG 2.1 AA audit + opravy.
362. ➕ Klávesová navigace + focus management (focus-trap existuje).
363. ➕ Realtime aktualizace (websockety) pro stav služeb/tiketů.
364. ➕ Chat/podpora widget (DB chat existuje) rozšířit o proaktivní.
365. ➕ Knowledge base vyhledávání + AI asistent (existuje).
366. ➕ Referral program UI (existuje) gamifikace.
367. ➕ Loyalty: vizualizace bodů/postupu + katalog (existuje) rozšířit.
368. ➕ Doporučení/upsell na dashboardu (personalizace).
369. ➕ Health score zákazníka viditelný + doporučené akce (existuje interně).
370. ➕ Faktury/platby přehled s grafy.
371. ➕ Self-service downgrade/cancel s retencí nabídkou (cancel survey existuje).
372. ➕ Plánované úlohy/automatizace pro zákazníka.
373. ➕ Dvoufázové potvrzení nebezpečných akcí (mazání) — data-confirm existuje.
374. ➕ Undo pro nedávné akce.
375. ➕ Tmavý režim + kontrast (a11y).
376. ➕ Uživatelské API klíče self-service (existuje) + scoping UI.

## N. Admin a backoffice (377–401)

377. ✅ 360° zákazník/služba, role/oprávnění, fronta, hledání, metriky, BI, KPI alerty, SLA.
378. ➕ Admin command palette + rychlé přepnutí kontextu.
379. ➕ Bulk operace napříč entitami rozšířit.
380. ➕ Admin audit log filtrování/export (existuje) rozšířit.
381. ➕ Impersonace (existuje) s vodoznakem + úplný audit akcí.
382. ➕ Konfigurace systému UI (existuje) rozšířit o všechny .env přepínače.
383. ➕ Feature flags admin UI.
384. ➕ Cache management (clear) z admin UI (opatrně).
385. ➕ Queue/Horizon dashboard odkaz + failed job akce (existuje).
386. ➕ Data opravné nástroje (reindex, recompute) — pii:reindex existuje.
387. ➕ Reporty stavitel (custom reports) + plánované e-maily.
388. ➕ Cohort/retention analytika (existuje) rozšířit.
389. ➕ Revenue/churn/LTV dashboardy.
390. ➕ Fraud/risk dashboard.
391. ➕ Ticket management SLA + makra + kategorizace AI (existuje).
392. ➕ Knowledge base editor + verzování článků.
393. ➕ Announcement/broadcast (existuje) segmentace rozšířit.
394. ➕ Price change management + notifikace (existuje).
395. ➕ Server inventory + kapacita dashboard.
396. ➕ Provisioning fronta + ruční intervence (existuje).
397. ➕ Refund/credit note admin flow (existuje) rozšířit.
398. ➕ Customer notes/tags (existuje) rozšířit filtry.
399. ➕ Admin notifikace preference + digest (existuje).
400. ➕ Multi-admin kolaborace (kdo edituje) lock/presence.
401. ➕ Admin mobilní přístup (responzivita).

## O. Notifikace a komunikace (402–421)

402. ✅ 52 e-mail notifikací, web push (+kampaně přes announcement), preference, digest, changelog.
403. ➕ SMS kanál pro kritické (2FA, výpadky).
404. ➕ Notifikační kanály rozšířit (Slack/Teams/Discord/webhook per-uživatel).
405. ➕ Šablony notifikací editovatelné adminem + verzování.
406. ➕ Lokalizace všech notifikací (EN).
407. ➕ Notification batching/digest granularita per-typ.
408. ➕ Transakční vs marketing oddělení + opt-out compliance.
409. ➕ Doručitelnost e-mailů: SPF/DKIM/DMARC + monitoring bounce/complaint.
410. ➕ E-mail warm-up/reputace monitoring.
411. ➕ Push kampaně cílené segmentem (dnes všem přes announcement).
412. ➕ In-app oznámení (banner) cílená (existuje) rozšířit.
413. ➕ Preferenční centrum granularita per-kanál per-typ (existuje) doladit.
414. ➕ Notifikace o údržbě (maintenance windows existuje) auto z incidentů.
415. ➕ Eskalace notifikací (nezaplaceno → SMS → telefon).
416. ➕ Notifikace o bezpečnostních událostech (nové přihlášení existuje) rozšířit.
417. ➕ Webhook retry + dead-letter pro notifikace.
418. ➕ Notifikační analytika (open/click) pro maily.
419. ➕ Rich e-maily (HTML šablony) + plain fallback konzistentně.
420. ➕ Rate-limit notifikací aby nespamovaly.
421. ➕ Uživatelské „tiché hodiny" pro notifikace.

## P. Přístupnost (a11y) a i18n (422–441)

422. ➕ WCAG 2.1 AA audit celého panelu + frontendu.
423. ➕ Sémantické HTML + ARIA role/labels audit.
424. ➕ Kontrast barev audit (light+dark).
425. ➕ Klávesová ovladatelnost všech interakcí.
426. ➕ Focus indikátory viditelné + logické pořadí.
427. ➕ Screen-reader testování klíčových toků.
428. ➕ Formuláře: labely, chyby, aria-describedby.
429. ➕ Skip-to-content odkazy.
430. ➕ `prefers-reduced-motion` + `prefers-color-scheme`.
431. ➕ Alt texty u obrázků/ikon.
432. ➕ Přístupné modály (focus-trap existuje) + tabulky.
433. ➕ Accessibility statement stránka.
434. ➕ i18n: dokončit EN překlady (ratchet 4251 hardcoded CZ) — postupně extrahovat.
435. ➕ i18n: pluralizace + formátování čísel/měn/datumů per-locale.
436. ➕ i18n: RTL připravenost (pokud cílit trhy).
437. ➕ i18n: překlady notifikací a e-mailů.
438. ➕ i18n: překlady chybových hlášek a validace.
439. ➕ i18n: locale detekce + přepínač (SetLocale existuje).
440. ➕ i18n: časové zóny per-uživatel.
441. ➕ i18n: měnové zóny + zobrazení.

## Q. Testování a QA (442–466)

442. ✅ 3501 testů zelených, PHPStan L6=0, Cuba/i18n/secrets ratchety.
443. ➕ Zvýšit pokrytí kritických cest (platby, provisioning) k 90%+ + coverage report v CI.
444. ➕ Mutation testing (Infection) pro kvalitu testů.
445. ➕ Architektura testy (Pest arch) — hranice domén, žádné cross-domain závislosti.
446. ➕ Contract testy pro externí integrace (aaPanel/WEDOS/Comgate).
447. ➕ Load/stress testy (k6/Gatling) na klíčové endpointy.
448. ➕ Soak/spike testy pro škálování.
449. ➕ E2E testy prohlížečem (Dusk/Playwright) klíčových toků.
450. ➕ Visual regression testy UI.
451. ➕ Accessibility automat testy (axe) v CI.
452. ➕ Security testy (auth bypass, IDOR) automatizované.
453. ➕ Property-based testy pro fakturační výpočty.
454. ➕ Seed/factory pro realistická demo data.
455. ➕ Test data builders/fixtures konzistentní.
456. ➕ Flaky test detekce + karanténa.
457. ➕ Paralelní běh testů (rychlost CI).
458. ➕ Snapshot testy pro OpenAPI spec + GraphQL schéma.
459. ➕ Regresní testy pro každý opravený bug.
460. ➕ Performance regression testy (dotazy/latence).
461. ➕ Test databáze paritní s produkcí (MySQL, ne SQLite) v CI.
462. ➕ Migrace up/down testy + rollback bezpečnost.
463. ➕ Seeder idempotence testy (existuje částečně).
464. ➕ Static analysis rozšířit (L7/L8 postupně) — dnes L6.
465. ➕ Coding standard (Pint) enforce v CI + pre-commit.
466. ➕ Dokumentační testy (příklady v docs běží).

## R. CI/CD a DevOps (467–486)

467. 🏗 CI pipeline (existuje `.github/workflows/ci.yml`) rozšířit o coverage/mutation/lint.
468. 🏗 CD: automatický deploy na staging po merge.
469. 🏗 Deploy approval gate pro produkci.
470. 🏗 Migrace v deploy s `--force` + zero-downtime strategie.
471. 🏗 Rollback deploy jedním příkazem.
472. 🏗 Build artefakty verzované + reprodukovatelné.
473. 🏗 Dependency caching v CI (composer/npm).
474. 🏗 Matrix testy (PHP 8.2/8.3/8.4).
475. 🏗 Container image build + scan v CI.
476. 🏗 Secrets injection v CI/CD (ne v repu).
477. 🏗 Smoke testy po deployi (`/up`, `/ready`, klíčové stránky).
478. 🏗 Feature branch preview prostředí.
479. 🏗 Automatické changelogy + verze (semver).
480. 🏗 Release notes generování.
481. 🏗 Database seeding pro staging (anonymizovaná produkční data).
482. 🏗 Monitorování deploy → automatický rollback při chybovosti.
483. 🏗 Blue-green/canary v CD.
484. 🏗 `.env.production.example` (existuje) → validace úplnosti v deployi.
485. 🏗 Config drift detekce mezi prostředími.
486. 🏗 Scheduled security scan (composer audit) noční.

## S. Data, migrace, seeding (487–504)

487. ➕ Migrační verifikátor (existuje) rozšířit na FK/typy/nullable kontroly.
488. ➕ Anonymizace produkčních dat pro staging/dev.
489. ➕ Data seeding pro demo/onboarding tenant.
490. ➕ Bulk import zákazníků/služeb (migrace z konkurence).
491. ➕ Export/import konfigurace (produkty, ceníky) mezi prostředími.
492. ➕ Data validace/integrita checky (orphan záznamy) periodicky.
493. ➕ Referenční integrita audit (FK bez indexů, cascade politiky).
494. ➕ Archivace starých dat (faktury >X let) do cold storage.
495. ➕ Soft-delete konzistence + restore UI.
496. ➕ Enum/číselník management (země, měny, TLD) z administrace.
497. ➕ Datové migrace odděleně od schema migrací (backfilly).
498. ➕ Idempotentní backfill příkazy (pii:reindex existuje) rozšířit.
499. ➕ Kontrola šifrovaných sloupců po rotaci APP_KEY.
500. ➕ Data retention automat per-tabulka (retention příkaz existuje).
501. ➕ Duplicitní detekce (zákazníci, kontakty) + merge.
502. ➕ Timezone/UTC konzistence napříč sloupci.
503. ➕ Money jako minor-units (haléře) audit napříč (většina ano).
504. ➕ Datové slovníky/dokumentace schématu.

## T. Reseller, partner, monetizace (505–524)

505. ✅ Reseller portál, cenové přepisy, limity, KPI, branding, affiliate, bannery, výplaty.
506. ➕ Reseller white-label plná (vlastní doména, e-maily, logo) — branding existuje.
507. ➕ Reseller vlastní ceníky + marže per-produkt (přepisy existují).
508. ➕ Reseller fakturace sub-zákazníkům (číselná řada existuje).
509. ➕ Reseller dashboard: provize, MRR, churn.
510. ➕ Multi-tier reseller (reseller pod resellerem).
511. ➕ Affiliate: cookie window, multi-touch atribuce (cookie existuje).
512. ➕ Affiliate: výplaty automatizace + prahy (existuje).
513. ➕ Partner marketplace/adresář.
514. ➕ Loyalty: úrovně/tiers + benefity (body+katalog existuje).
515. ➕ Loyalty: referral bonusy propojit s body.
516. ➕ Gamifikace (odznaky, milníky) — milníky existují.
517. ➕ Upsell/cross-sell engine (doporučení).
518. ➕ A/B testování cen/nabídek.
519. ➕ Dynamické ceny/akce (Black Friday) — pattern existuje.
520. ➕ Marketplace doplňků: platby (hotovo) + revenue share s vývojáři.
521. ➕ Předplatné add-onů (dnes jednorázové marketplace).
522. ➕ Bundle nabídky (hosting+doména+SSL).
523. ➕ Win-back kampaně pro churnnuté (churn detekce existuje).
524. ➕ NPS/CSAT (NPS existuje) → akční follow-up.

## U. Dokumentace a DevEx (525–540)

525. ✅ Rozsáhlé docs/ (architektura, billing, aapanel, go-live, audity).
526. ➕ Veřejná developer dokumentace (portál + guides).
527. ➕ API changelog veřejný (existuje endpoint) + RSS.
528. ➕ Onboarding docs pro nové vývojáře (setup, konvence).
529. ➕ ADR (architecture decision records).
530. ➕ Runbooky pro incidenty/ops.
531. ➕ Diagramy: doménová mapa, data flow, deployment.
532. ➕ Postman/Insomnia kolekce z OpenAPI.
533. ➕ Příklady kódu pro SDK v docs.
534. ➕ Kontribuční guide + PR šablony.
535. ➕ Uživatelská nápověda/KB provázaná s UI.
536. ➕ Video návody pro klíčové funkce.
537. ➕ Status/changelog stránka pro zákazníky.
538. ➕ Interní wiki znalostí (provozní).
539. ➕ Generovaná dokumentace schématu DB.
540. ➕ Glosář domény (termíny, zkratky).

## V. Produkční credentials a konfigurace k doplnění (541–556)

541. 🔑 `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false` (health check hlídá).
542. 🔑 Databáze produkční (host/user/heslo) + zálohovací DB uživatel.
543. 🔑 Redis (cache/session/queue) připojení.
544. 🔑 Mail (SMTP) credentials + from adresa + DKIM.
545. 🔑 Comgate merchant_id + secret (+ test/live).
546. 🔑 Stripe/GoPay klíče (pokud aktivní).
547. 🔑 aaPanel/WEDOS/Proxmox/Pterodactyl endpointy + tokeny.
548. 🔑 AI klíč (Claude/OpenAI) + `AI_ALLOW_REAL_CALLS`.
549. 🔑 VAPID klíče (web push).
550. 🔑 S3/B2 zálohovací credentials + bucket.
551. 🔑 Sentry DSN.
552. 🔑 `METRICS_TOKEN` pro Prometheus scrape.
553. 🔑 Cloudflare api_token + zone_id (pokud DNS/CDN).
554. 🔑 Real-write gaty otevřít po ověření (AAPANEL/WAPI/AI/CLOUDFLARE_ALLOW_REAL_WRITES, PROVISIONING_MOCK_MODE=false).
555. 🔑 Doména + TLS + DNS produkční.
556. 🔑 Firemní fakturační údaje (IČO/DIČ/banka) do nastavení.

## W. Resilience a disaster recovery (557–576)

557. 🏗 RPO/RTO cíle definovat + testovat.
558. 🏗 Automatické zálohy DB + soubory + offsite + šifrování.
559. 🏗 Restore drill (pravidelně ověřit obnovu) — freshness check v kódu.
560. 🏗 Point-in-time recovery.
561. 🏗 Failover DB (replika promote).
562. 🏗 Multi-region/AZ redundance.
563. ➕ Circuit breakery na všechny externí závislosti.
564. ➕ Graceful degradation (číst z cache když DB pomalá).
565. ➕ Bulkhead izolace (fronty per-integrace).
566. ➕ Timeout + retry + jitter na všech síťových voláních.
567. ➕ Idempotence napříč re-tries (klíče).
568. ➕ Chaos testing (výpadek Redis/DB/integrace).
569. 🏗 Maintenance mode s allowlistem (Laravel down) + admin toggle.
570. ➕ Read-only režim při degradaci (nákupy pozastavit, čtení jede).
571. ➕ Queue draining při deployi (dokončit joby).
572. ➕ Rate-limit a load-shedding při přetížení.
573. 🏗 Backup encryption key escrow.
574. 🏗 Runbook: DB down, Redis down, integrace down, cron down (heartbeat existuje).
575. 🏗 On-call rotace + eskalace + paging.
576. 🏗 Post-mortem proces + akční položky.

---

## Souhrn a priorizace

| Blok | Rozsah bodů | Těžiště |
|---|---|---|
| A–C Výkon | 1–82 | DB/dotazy, cache/fronty, frontend/HTTP |
| D–E Infra/observabilita | 83–137 | škálování, monitoring |
| F–G Bezpečnost | 138–195 | app + provoz + compliance |
| H–I API/integrace | 196–260 | REST/GraphQL/SDK, providers |
| J–L Billing/provisioning/DNS | 261–344 | jádro produktu |
| M–O Panel/admin/notifikace | 345–421 | UX |
| P–Q a11y/i18n/QA | 422–466 | kvalita |
| R–S DevOps/data | 467–504 | dodávka |
| T–U Monetizace/docs | 505–540 | růst |
| V–W Credentials/DR | 541–576 | go-live + odolnost |

**Celkem 576 bodů.**

### Doporučené pořadí (dopad × úsilí)

1. **Go-live nezbytné** (🔑 credentials + 🏗 infra: Redis, Horizon, zálohy+restore drill, TLS, cron, Sentry, credentials bran/panelů). Bez těchto nelze do produkce.
2. **Výkon quick-wins** (indexy, N+1 audit, eager loading, cache číselníků, `preventLazyLoading`, log pruning).
3. **Bezpečnostní hardening** (authorization/IDOR audit, rate-limit citlivých akcí, RFC 7807, secrets/SAST v CI, security.txt).
4. **Observabilita dokončit** (Sentry, alerting pravidla, tracing, uptime monitor) — základ hotový.
5. **Frontend obnovit + modernizovat** (assety, Vite, PWA, dark mode, a11y) — po obnově smazaných assetů.
6. **Funkční rozšíření dle poptávky** (recurring karty, metered billing, DNS provider abstrakce, GraphQL rozšíření).

*Poznámka: body ✅ jsou kontext (implementováno), 🟡/🔴/➕ jsou práce v kódu,
🔑 čeká na údaje, 🏗 je serverové zajištění. Většina bodů je dopředné vylepšení pro
maximalizaci výkonu/efektivity/funkčnosti/rozšířenosti, ne defekty.*
