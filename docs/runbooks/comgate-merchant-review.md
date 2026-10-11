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
