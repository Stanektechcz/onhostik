# Security news for technical support (SN)

Owner request (2026-10-14, project thread "Bezpečnostní novinky"): technical support gets a section with the latest security
advisories for the software we run or host (WordPress core, plugins and themes, the panels, the control plane). The feeds are
checked at least three times a day. After an approval the platform finds the affected places across the systems and fixes them;
where a fix could take a customer's service down, the customer is told at once what, where and why. Once customers were told, a
draft blog post is written for customers, newcomers and readers of the blog.

The full Czech design (classes, packages SN-0…SN-7, questions Q1–Q5) is in the project files,
`security-news/NAVRH-novinky-bezpecnost-2026-10-14.md`. Owner said "Zajisti implementaci" with the recommended answers:
Q1 hourly KEV + Wordfence on top of the three daily runs, Q2 no automatic fix without an approval, Q3 no paid feed,
Q4 one approver with step-up up to 25 sites and four eyes above, Q5 the panel option for automatic updates stays a follow-up.

## What runs

| Piece | Where |
| --- | --- |
| Feeds behind `AdvisoryFeed` / `AdvisoryLookup` | `providers/Advisories`: CISA KEV (exploited now), NVD CVE API 2.0 (CPE-filtered, `nvd_cpe`, plus every plugin or theme whose CPE target software is `wordpress`), the CVE List (cvelistV5 `deltaLog.json`: new CNA records naming a wordpress.org plugin or theme, with their version ranges from the day of publication; at most `cvelist_max_new` a run, whole fetches oldest first), Wordfence Intelligence v3 (WordPress; optional, only with `ONHOST_WORDFENCE_API_KEY`), OSV.dev (the control plane's Composer libraries) |
| Ingest | `AdvisoryIngest`: one row per CVE (or `wordfence:` / `osv:` id), sources merged, the worst severity, KEV or'ed; a feed's cursor in `security_advisory_sources`; a failed feed records a redacted error and the others go on |
| Inventory | `SoftwareInventory`: WordPress core/plugins/themes of active web **services** (`WordPressService::status`, longest unread first, 200 a run), panel versions of provider instances, `composer.lock`. Historical panel sites are never services, so they are never read or touched |
| Matching and plan | `AdvisoryMatcher` + `RemediationPlanner`: **A** plugin fix in the same major line or core fix in the same x.y branch (`wp.plugin update`, `wp.update core staged`); **B** new plugin major, themes, core across branches; **D** no fixed release or a range that could not be compared — the customer is told; **P** the platform's own software — staff only |
| Cases | `AdvisoryCases`: triage (dismissing needs a note), approve (`plan_revision` bound; A, plus B when included), notify only; fixes run in waves (canary 1, then 10 %, then the rest, one operation per service) through `ServiceService::requestAction`; the first failed operation halts the case and the customers left are told to act themselves |
| Decisions | `AdvisoryCommand` (CommandBus): approve and notify are HIGH (step-up); approve takes four eyes over `single_approver_max` sites or with B included, the count is made server-side |
| Customers | `security.advisory.service_affected` / `service_fixed` → in-app notice + mandatory mails `security-advisory` / `security-advisory-fixed` (cs, en) |
| Blog | `BlogDraft` → `ContentCommand post.create` as a **draft** in "Blog a reference"; it names the software and the fix, never a customer or a site; nothing is published by itself |
| Staff | `/sprava/nastaveni/bezpecnost` (nav item `security_news`), `/v1/staff/security/advisories…`, `/v1/staff/security/advisory-cases…`; permissions `security.advisory.read` / `.manage` / `.remediate` (security_soc all, sre read+manage, hosting admins read) |
| Schedule | `onhost:advisories:sync` 05:30, 12:30, 19:30 Europe/Prague; `onhost:advisories:quick` hourly (KEV + CVE List + Wordfence); `onhost:advisories:advance` every 5 minutes; switches `advisories.sync` / `advisories.remediate` in the automation ledger |

## VERIFIED / ASSUMED / NOT DONE

- VERIFIED (tests): feed parsing against the documented shapes (the CVE List included: slug from wordpress.org and trac references, `lessThan` / `lessThanOrEqual`, links outside the repository never followed, backlog over several runs) (`tests/Contract/AdvisoryFeedsContractTest.php`), version ranges and
  the planner (`tests/Unit/AdvisoryVersionTest.php`), the whole case flow with step-up, four eyes, waves, halt, notices, mails and the
  blog draft (`tests/Feature/Advisories/SecurityAdvisoriesTest.php`).
- ASSUMED: the feed formats follow the vendors' public documentation; SN-0 records one real answer of each feed on staging (no
  production). Wordfence's v3 production feed is large (hundreds of MB); it is read with a 256 MB cap and 120 s timeout.
- `wp.plugin update` updates to the newest release, which may be newer than the fixed one; class A is decided by the fixed version.
- NOT DONE (follow-ups): Debian/npm packages of nodes (needs the node agent to list them), PHP-level per-site scans, EPSS, an AI
  summary of advisories, the panel's own automatic-update option (Q5), a doctor row for feed freshness (the page shows it).

## Owner inputs

Owner decision 2026-10-14 ("nemáme wordfence key, pokračuj"): the platform runs **without a Wordfence key**. WordPress plugins and
themes then come from NVD: a CVE whose CPE names the target software `wordpress` is kept, and the CPE product is read as the slug
(`contact_form_7` → `contact-form-7`; "theme for WordPress" in the description makes it a theme). The gap: NVD analyses CVEs with a
delay, and a CVE without a CPE yet is not matched until it has one. A key set later (`ONHOST_WORDFENCE_API_KEY`) closes the gap
without a code change. Owner decision the same day ("Přidat"): the CVE List (cvelistV5) is read as well, keyless; its CNA records
carry the affected versions from the day the CVE is published, so a plugin CVE no longer waits for NVD's analysis. A record whose
references name no wordpress.org plugin or theme is skipped (NVD still covers it once analysed). `ONHOST_NVD_API_KEY` stays optional (higher NVD rate). The first run on staging needs the owner's
step-specific "ano".
