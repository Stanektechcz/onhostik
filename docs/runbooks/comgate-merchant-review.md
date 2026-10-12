# Comgate merchant review: what the public site must show (I-R10, TASK-0141)

**Owner statement of 2026-10-08 (decision I-R10):** Comgate issues the **test merchant account only after its support has manually
reviewed a fully functional, deployed website**. So the order of I-R3 changes: first the public site is deployed and complete, then
Comgate reviews it, then the test account exists, then package I2 (check, 1 CZK test payment, recorded fixtures, manual card test on
staging; see `comgate.md`). Until the account exists card payments stay unverified (go-live B5).

Nothing here was verified with Comgate: the owner's statement is the only source. The checklist below is built from what a payment
gateway review of an online shop usually asks for and from Czech consumer-sales rules (Act 89/2012 §1811-1820, Act 634/1992,
Act 480/2004 for e-commerce information). **Before the review the owner asks Comgate support for their current list and this page is
corrected to it.** The lawyer (B4) should read the rows marked *lawyer*.

Status: **HAVE** = exists in the repository, **PARTIAL** = exists but incomplete, **MISSING** = nothing exists, **VERIFY** = cannot be
judged from the repository, look at the deployed site. "Repo" names where the thing lives; the prototype pages in `apps/surfaces` stay
byte-identical, so any change to what a visitor sees goes through the seams (`apps/surfaces/api/*`, `SurfaceRenderer`).

## 0. State of rows 1–17 after phase J (J4, TASK-0155, 2026-10-11)

Read from the repository and the tests; **not yet looked at in a browser** (package J7) and **not on a deployed site** (decision 2).
The table in section 1 keeps the reasoning; this one is the current answer.

| # | Status now | What changed in J4 / what is left |
| --- | --- | --- |
| 1 | PARTIAL | Footer: identity line (`LegalIdentitySeam`, TASK-0147) + the new legal block (`PublicLegalLinks`). Left: the entity row in each database (`onhost:production:prepare --legal`, B2) |
| 2 | PARTIAL | Phone and complaints e-mail shown (I-R11). Left: support hours — owner input (decision 7) |
| 3 | HAVE | VOP linked from the footer of every page (only the version in force); `#/sluzba/legal` lists the same documents in force with version and date, each row opening `/dokumenty/<slug>` (M1, TASK-0168, PR #165) |
| 4 | PARTIAL | The complaints rules exist as the `2026-10` draft (`complaints`, slug `reklamacni-rad`); the footer links them **automatically once published** — after the lawyer (decision 6, C-R10b) |
| 5 | HAVE / lawyer | Linked from the footer; naming Comgate as processor is for the lawyer (draft texts) |
| 6 | PARTIAL | Local browser listing (K7, TASK-0161): only `XSRF-TOKEN` and the session cookie (2 h, SameSite=Lax, the session HttpOnly), no other cookie and no banner — consistent with privacy section 4. Left: the `Secure` flag and the cookie names on the deployed site (staging after section C) |
| 7 | HAVE | Withdrawal document linked from the footer |
| 8 | HAVE | Unchanged (VOP 2.3, 8.1; statutory refunds to the original method, L-09) |
| 9 | HAVE | Prices in Kč; the footer of every page says *Nejsme plátci DPH, ceny jsou konečné.* (L-10, TASK-0146). Left: the accountant's confirmation of the wording (decision 7) |
| 10 | PARTIAL | The checkout says when the service runs (`OnhostCart.eta`); the product cards do not repeat it |
| 11 | HAVE (logos: owner) | Only what is delivered: card through the gateway **only while the gateway has its credentials** (`ONHOST_DATA.cardPayments()`; without them the cart offers and starts on the bank transfer) and the bank transfer with a pro forma invoice. Left: official Comgate and card-scheme logos (decision 7, after the account) |
| 12 | PARTIAL | A card order without gateway credentials is refused up front with a plain Czech sentence that names the bank transfer, before a guest account is created and without any request to the gateway (`CardPayments`, `payment_card_unavailable` 409; it used to create the account and come back as a provider error). K7 (TASK-0161, local browser): step 3 offers only the bank transfer, a guest order ends with the order number, account, IBAN and VS; the pricing page's plan cards did not reach an order ("VPS Standard" unmatched) — fixed by TASK-0162. Left: the walk-through as a stranger on the deployed site, the reviewer's demo account (owner). M2 (TASK-0171/0172, PR #167/#168): the checkout names the card only with a working gateway, no VAT words for a non-payer, no top-up bonus, the pay button by the cart content, the CDN add-on only with a CDN account |
| 13 | MISSING | Which environment Comgate reviews — decision 2 (recommended: the pre-launch variant of `production-plan-2026-10.md` §5) |
| 14 | HAVE | Unchanged |
| 15 | HAVE | Unchanged |
| 16 | HAVE | The footer links the Czech Trade Inspection's out-of-court dispute resolution (`https://adr.coi.cz/cs`). No EU ODR link: the platform was shut down on 20 July 2025 (Regulation (EU) 2024/3228, L-13) |
| 17 | PARTIAL | Unchanged; the new footer block is Czech and English |

Engineering packages of section 3: **C-R10a done** (J4), **C-R10b** waits for the lawyer (the link appears by itself on publication),
**C-R10c done** (tile list follows the gateway), **C-R10d done** (footer VAT line since TASK-0146, checkout activation time),
**C-R10e done** (`CardPayments`). No request was made to Comgate (tests use `Http::preventStrayRequests()`).

## 1. Checklist

| # | What the reviewer expects on the public site | Repo state | Status | What to do |
| --- | --- | --- | --- | --- |
| 1 | Operator identity: legal name, IČO, DIČ (or the statement that the operator is not a VAT payer), registered seat, commercial/trade register entry, bank account | `LegalEntity` model and `LegalEntitySeeder`; the VOP and the privacy text are templates with `{{entity_name}}`, `{{entity_ico}}`, `{{entity_dic}}`, `{{entity_address}}`, `{{entity_email}}`; env `ONHOST_LEGAL_NAME`, `ONHOST_ICO`, `ONHOST_DIC`, `ONHOST_BANK_*` in `config/onhost.php`. Real data not entered (go-live **B2**). A public footer/imprint showing them was not found in the prototype pages. **TASK-0147 (I-R11): the footer now names the operator (name, IČO, seat, register entry) from the legal entity through `LegalIdentitySeam`; the defaults are the ARES data of IČO 08094616; the entity row in each database still has to be written (`onhost:production:prepare --legal`, needs the bank details)** | PARTIAL | Enter the real entity (`onhost:production:prepare --legal`, step 11); add an imprint line to the public footer through the content seam (engineering item); register entry text is owner input |
| 2 | Contact: e-mail, phone, postal address, support hours, how to reach a complaint handler | `{{entity_email}}` in the legal texts; a support/contact page exists in the prototype (`svc:support`); support hours and phone are plan promises in the tariff tables. **TASK-0147 (I-R11): phone +420 736 741 902 and complaints e-mail reklamace@onhost.cz are in the footer, the support rows and the legal texts; the invented "24/7" phone promise is gone** | PARTIAL | Owner supplies the support hours; the contact page shows the same entity data as #1 (VERIFY on the deployed site) |
| 3 | Terms of sale (VOP) in force, dated and versioned, linked from the footer and the checkout | `resources/legal/terms.md`, public at `/dokumenty/vop` (`LegalDocumentController`), consent document `terms` version `2026-09`, required at checkout | HAVE | Lawyer review pending (B4, accepted risk). *Lawyer:* VOP art. 2.1 says prices are "bez DPH", while ONhost is not a VAT payer (I-R5, VAT 0): the wording misleads a consumer; a new version goes through `consent_documents` after the lawyer's answer, not before |
| 4 | Complaints procedure (reklamační řád): where, how, deadlines (30 days), what the customer gets | Only art. 8.2 of the VOP (ČOI, EU ODR platform) and the SLA credit rules; no complaints document, no `ConsentDocument` key, no `/dokumenty/reklamace` slug | **MISSING** | *Lawyer:* write `reklamacni-rad` (service defects, SLA credit vs. complaint, 30-day decision, e-mail/panel channel); add as consent document + slug + footer link in one package; owner decides who handles complaints |
| 5 | Personal data: privacy policy (GDPR art. 13), controller identity, processors, rights, retention | `resources/legal/privacy.md` at `/dokumenty/ochrana-osobnich-udaju`, `dpa.md`, compliance requests (`compliance-requests.md`) | HAVE | Name Comgate (payment processor) in the processor list: privacy.md and terms.md contain no mention of Comgate (checked by search) — *lawyer* |
| 6 | Cookies: what is used, consent banner if anything beyond the necessary | privacy.md section 4 says only technically necessary cookies; the prototype legal route has a `cookies` page id; no consent document key and no banner found | PARTIAL | VERIFY the deployed page; if only necessary cookies are set, the statement suffices — confirm with a browser cookie listing; otherwise add the banner |
| 7 | Right of withdrawal (14 days), the exceptions, the model withdrawal form | `resources/legal/withdrawal_waiver.md` at `/dokumenty/odstoupeni` incl. section 3 *Vzorový formulář*; VOP art. 8.1; `billing.withdrawal` mechanism off until the legal review (`ONHOST_WITHDRAWAL_LEGAL_REVIEWED`) | HAVE | B4 (lawyer's answer on credit top-up and forfeited credit) is an accepted risk for launch, but Comgate may ask about it: answer is VOP art. 2.3-2.5 and 8.1 |
| 8 | Refund policy: how and when money comes back, to which instrument | VOP art. 2.3 (no cash refund of credit; refunds go to credit), art. 8.1 (unused part of a service returns to credit); `money-corrections` code paths | HAVE | Likely reviewer question: a card payment is returned to credit, not to the card. State it on the public pricing/checkout page in one sentence (engineering item) and be ready to justify it to Comgate (owner decision of 2026-10-06: credit forfeits, no cash refund) |
| 9 | Prices: every product with its price, **currency** (CZK), the VAT statement, billing period | Catalogue price lists (`CatalogController`, `SurfacePricing`: CZK always, other currencies by ČNB rate); not-a-VAT-payer mode (H-R0, `vat-payer-mode.md`); penpot add-on price in `penpot.md` | PARTIAL | VERIFY every listed price shows `Kč` and the period; add one visible line "ONhost není plátce DPH, ceny jsou konečné" (content seam) — owner confirms the wording with the accountant (I-R5) |
| 10 | Delivery / activation terms for a digital service: when it starts, what the customer receives | VOP art. 3.1 (set up without undue delay after payment, usually minutes); order confirmation and provisioning notifications; domain registration terms (`registrar_terms`) | PARTIAL | Put the activation time on each product card and in the checkout summary (VERIFY); note that a failed delivery is refunded (order settlement) |
| 11 | Payment methods offered, with the logos of the methods and of the gateway | Platform delivers: card through Comgate (`PAYMENT_GATEWAY=comgate`), bank transfer by proforma, credit. The prototype checkout list (`Onhost.dc.html`, `payOpts`) names **Visa/Mastercard · Stripe, Apple Pay/Google Pay, PayPal, crypto, SEPA** — methods the platform does not deliver through Comgate | **MISSING / wrong** | VERIFY the deployed public cart. Show only what works: card, bank transfer, credit (the panel order seam `onhost-panel-order.api.js` already words it as "platební brána"); remove Stripe/PayPal/crypto/SEPA wording through the cart seam; get the official Comgate and card-scheme logo files and the usage rules from Comgate (nothing is in the repository) |
| 12 | A working path from catalogue to order: product, cart, checkout, consent to the VOP, summary, payment redirect — testable by a reviewer without help | Guest checkout (`POST /v1/checkout/guest`), cart (`CartController`), quote, order, proforma/bank path; manual tests `docs/manual-tests/01-registrace-objednavka.md` | PARTIAL | Rehearse it on the deployed site as a stranger. Open question: what a card order does while no Comgate credentials exist (`comgate_credentials_missing`): it must fail with a clear message, not a stack trace; give the reviewer the bank-transfer/credit path and a demo customer login (credentials sent to Comgate support by the owner, never stored in the repository) |
| 13 | Site is live, on HTTPS, not in maintenance or test mode, no placeholder text, no "demo" banner, services really orderable | Deploy path: `deploy-aapanel.md`, `staging-launch.md`, `go-live-checklist.md`; staging rehearsal R0-R24 not done (B1) | MISSING | Owner decision below: which environment Comgate reviews. Prices, products and texts must be the real ones |
| 14 | Terms for special products: domain registration terms and registry rules, SLA, auto-renewal | `registrar_terms`, `registry_terms_cz/sk/eu`, `sla`, `auto_renew` documents, all in `/dokumenty` | HAVE | Links from the checkout where the product is chosen (VERIFY) |
| 15 | Age / business-only restrictions, prohibited content (gateway rules for hosting providers) | VOP art. 4 (prohibited content, DSA notice handling) | HAVE | Comgate may ask for the abuse process: `incident-response.md` / abuse handling; owner answers in writing |
| 16 | Dispute resolution: ČOI and the EU ODR link | VOP art. 8.2 names ČOI and the EU platform (no link text) | PARTIAL | Add the two addresses as visible links in the footer legal block with row 4 |
| 17 | Language: Czech site for a Czech operator (and English if offered) | CZ/EN prototype and Lexicon; legal texts are Czech, an English title exists per document | PARTIAL | The review is in Czech: make sure every legal page and the checkout are complete in Czech |

## 2. Owner decisions and inputs this needs

1. **Which site does Comgate review?** The staging site is behind the maintenance/staging gate; the production site is blocked by B1, B2 and
   others. Recommended: a public pre-launch deployment of the production codebase on the real domain, real company data, no test banner,
   ordering open only for the reviewer's demo account or by bank transfer. Needs the owner's yes (a deploy is a production action).
2. Real company data and bank account (B2); the register entry line; phone and support hours.
3. The lawyer for rows 3, 4, 5 and 7 (documents are versioned: new text means a new version `2026-10`, not an edit).
4. The accountant's wording for the "not a VAT payer" line (I-R5).
5. Comgate's own list, logos and brand rules (ask support when applying).

## 3. Engineering work this list implies (none done here)

| Package | Content | Rows |
| --- | --- | --- |
| C-R10a | Public legal footer through the content seam: imprint (entity data), links to VOP, privacy, withdrawal, complaints, ČOI/ODR | 1, 2, 4, 16 |
| C-R10b | Complaints document (`reklamace`) as a consent document + slug + footer link, after the lawyer's text | 4 |
| C-R10c | Public cart payment methods restricted to what is delivered (card via Comgate, bank, credit) and the refund-to-credit sentence | 8, 11 |
| C-R10d | Price lines: currency and VAT statement on every price; activation time on product cards | 9, 10 |
| C-R10e | Card order without gateway credentials answers cleanly in the cart (test with `Http::fake`/no credentials) | 12 |

## 4. After the review

Comgate issues the test merchant account. The owner stores its credentials on the server himself (`php artisan onhost:secrets:set`; never in
a chat, the repository or a log), then package I2 as in `comgate.md` (*Not verified live*): *Ověřit spojení*, *Testovací platba 1 Kč*,
`php artisan onhost:fixtures:record comgate`, the manual card payment on staging. A rejection by Comgate is recorded here with the reason
and the rows it concerns.
