# Response to the onboarding audit — verified against the stack tip

- **Audit under review:** `.ai/audits/2026-09-25-onboarding-audit/README.md` and `development-state.md`. The audit analysed the stack at `36bf495` and `development` at `2426c17`.
- **Verified against:** the TASK-0027 worktree, branch `fix/TASK-0027-stack-coherence-and-the-docs-that-descri`, at HEAD `980a078` as the verifiers reported it. PR #24 says the stack is 107 commits over `2426c17`; one verifier counted 108. The stack tip moved after the audit was taken, and that difference was not reconciled.
- **How this was checked:** every claim was checked read-only. Verifiers ran `git grep` / `git show` / `git diff` / `git ls-tree`, `php artisan route:list --json`, `php artisan list onhost --raw`, `vendor/bin/pest --list-tests`, and Larastan with a scratch config that had `tmpDir` and `reportUnmatchedIgnoredErrors` set. They also ran three reflection scripts in the session scratchpad (`actions_matrix.php`, `ops_matrix.php`, `destructive.php`) that load only the autoloader, with no database and no writes. After that, adversarial challengers re-read the findings rated HIGH or disputed. `git status` stayed clean. Nothing was executed against a database, a panel or a vendor.
- **Evidence tags follow `.ai/DEVELOPMENT_RULES.md` §9.** Every verdict below comes from static reads or the read-only commands above. None of it is proven at runtime. The Pest tests proposed in the recommendations are the runtime proof still owed, especially for C13-H2c and C13-H1c.
- **Pages not reviewed:** `production-readiness.md`, `security-posture.md`, `ai-docs-and-tooling.md` and `needs-verification.md` were never pushed. This response does not guess at what they contain.

---

## 1. Summary

### Counts

There are 72 rows in total: 59 claims taken from the audit, plus 13 rows the verifiers added (hypotheses, scope extensions and new checks).

| Verdict | All 72 rows | The audit's own 59 claims |
| --- | --- | --- |
| CONFIRMED | 45 | 36 |
| RESOLVED_BY_STACK | 1 | 0 |
| PARTLY | 21 | 20 |
| WRONG | 5 | 3 |
| UNVERIFIABLE | 0 as a whole row | 0 as a whole row |

- **Unverifiable part:** one sub-claim cannot be checked. It is the audit's "second HIGH" in C13, which lives in the unpushed `security-posture.md`.
- **What the stack fixed:** TASK-0017..0027 fixed none of the audit's substantive findings. What it did resolve:
  - `panel.password` left the permission default arm (TASK-0021, `634b11f`).
  - TASK-0021/0022/0025 added 88 Orders cases and 64 Billing cases.
  - The command/handler count rose from 41 to 46, and the two lists still match.
- **Where the stack made things worse:** it widened C12. `Doctor.php:237` now promises that a doctor FAIL "fails a production deploy", but no deploy path obeys that.

### The five things that matter most

1. **Service actions skip their own permission and risk model (HIGH).** 121 of 132 service actions fall through `ServiceActionCommand::permissionFor()` to `service.manage` at NORMAL risk.
   - Anyone holding `service.manage` can call `backup.delete`, `gbackup.delete` and `snapshot.delete` with no step-up and no four-eyes. That includes a `svc_manage` guest, an org `developer`, and a `services:power` API token. The catalogue itself rates `backup.delete` CRITICAL.
   - A `svc_manage` guest, a role documented as "without a shell", can reach root or console through `access.reset`, `rescue.start`, `subuser.create` and command schedules.
   - `archive.restore` overwrites a live site without a step-up.
   - Findings: C13, C13-FM, C13-H1, C13-H1b, C13-H1c.
2. **A read-only API token opens an interactive console (HIGH).** `ApiContext::assertTokenScope` maps every `service.*` permission to `services:read` by prefix. So `GET /v1/services/{id}/console-token` hands a noVNC or Wings console to a `services:read` token. This is the HIGH of the audit's "default arm" shape, found outside the Command classes. It also contradicts the headline "no step-up bypass". Finding: C13-H2c.
3. **Reverse-charge VAT is unreachable, and customers are told the opposite (HIGH if EU B2B sales are in scope).**
   - `ViesClient` has no caller, so `vat_status` never becomes `valid`. Every EU B2B customer outside CZ is charged OSS destination VAT, with no review flag.
   - Seeded public content promises automatic VIES checks and reverse charge.
   - A sibling bug makes every production partner's self-billing document carry 0 % VAT.
   - Findings: C1a–C1d, plus section 4.
4. **The deploy script discards the readiness gate (MEDIUM now, HIGH at the first production deploy).**
   - `deploy.sh:52` and `install.sh:85` run `onhost:doctor || true`, and only after migrations and restarts.
   - The doctor is the only guard against sandbox payment/registrar modes in production, unscanned uploads and unconfigured Turnstile.
   - No release tag exists and rollback by tag is impossible.
   - Findings: C12, C14, C7a, C7b, F5.
5. **Hetzner real-money node ordering is weakly gated and has no contract test (MEDIUM now, HIGH once any instance gets `options.node_order`).**
   - There is no four-eyes step, and the budget defaults to "no cap" and fails open.
   - When nothing fits, the adapter buys the largest type.
   - The vendor call runs inside the bus transaction, and a timeout after the server was created leaves a billed server nobody tracks.
   - Findings: C6-hetzner-b, C6-hetzner-gating.

---

## 2. Every claim

**Audit tag:** the audit's own tag. `development-state.md` marks everything ASSUMED unless stated otherwise. "— (new)" marks a row the verifiers added.

**Severity:** the severity after the adversarial challenge, for what is still true.

| id | Claim (short) | Audit tag | Verdict now | Evidence (stack unless stated) | Severity | Recommendation | Effort |
| --- | --- | --- | --- | --- | --- | --- | --- |
| C1a | `ViesClient` has zero callers | ASSUMED | CONFIRMED | `git grep -n ViesClient` finds only `domains/Tax/ViesClient.php:18` and `phpstan-baseline.neon:3565`. `git diff --stat 2426c17..HEAD -- domains/Tax` is empty | HIGH (MEDIUM if sales are CZ-only at launch) | Wire it through a bus command, queued after commit when `vat_id` changes. Re-check at checkout and snapshot the consultation number. Add a scheduled re-check in AutomationLedger, a staff override (step-up + four-eyes), and `Http::fake` tests | M |
| C1b | `vat_status` is only ever reset to `unknown`; the only other writer is `DevAccountSeeder` | ASSUMED | CONFIRMED (line drift) | The reset is now at `OrganizationService.php:261-262`; TASK-0021 `f90bcfe` shifted it from `:256`. `vat_status` is not in the allow-list at `:232`. Other writers are the dead `ViesClient.php:69` and the `'payer'` value in `DevAccountSeeder.php:68` and `PartnersTest.php:65` | HIGH | Adopt one vocabulary (`valid/invalid/unknown/not_registered`). Fix the seeder, the test and `PartnerService.php:757` | S |
| C1c | The reverse-charge branch is unreachable in production | ASSUMED | CONFIRMED | `TaxEngine.php:81-83` needs `b2b && 'valid'`. Every caller passes the organisation's own status. A request cannot claim it (`QuoteService.php:123-127`, `CartController.php:69-70`, pinned by `PaidForIsWhatYouGetTest.php:97-113`). Only `TaxEngineTest.php:28` reaches the branch | HIGH | Same fix as C1a, plus the review flag from C1d | M |
| C1d | It fails safe: nobody is under-billed, but no EU B2B customer can be zero-rated | ASSUMED | PARTLY | Nothing is under-collected (`TaxEngine.php:85-95`; OSS registered at `TaxRuleSeeder.php:31`). Not safe: the review flag fires only for `invalid` (`TaxEngine.php:87`); B2B supplies go through OSS; public copy promises VIES (`prototype-content.json:533,1285`, `Onhost-app.dc.html:11704`); `go-live-checklist.md:61` cannot pass | HIGH | S: set review for b2b + EU + non-supplier country + not `valid`, add a doctor row, and qualify the content (via a SurfaceRenderer seam; surfaces stay byte-identical). M: wire VIES and have an accountant review invoices already issued | M |
| C1e | (Hypothesis) TASK-0025 withdrawal misclassifies an IČO holder with `vat_status` unknown as a consumer | — (new) | WRONG | `WithdrawalPolicy.php:189-219` never reads `vat_status`. The class comes from `order.meta.customer_class`, written at `CheckoutService.php:141`. Tested at `WithdrawalTest.php:280-292` | INFO | Keep `vat_status` out of withdrawal eligibility | — |
| C1f | Where the consumer/business class really can be wrong | — (new) | CONFIRMED (static) | `OrganizationService.php:259` uses `isset`, so clearing the IČO leaves the organisation b2b. `AuthController.php:46` registers `type=company` without an IČO as b2c. So does `CheckoutController.php:90`. Setting only a DIČ via update is ignored | MEDIUM | Use `array_key_exists`. Derive the class from type, IČO or VAT ID. Put the question to the lawyer (`LEGAL_REVIEW_withdrawal.md:23-37`) | S |
| C2 | Staff `ContentController` writes raw Eloquent outside the bus | ASSUMED | CONFIRMED | `ContentController.php:56,69,80,91`; audit calls at `:57,70,82,92`; no Content commands. Permissions are NORMAL (`PermissionCatalog.php:136,162`). Tables default to `published` (`…000440:82,93`, `…000400:145`) | MEDIUM | Add a `ContentCommand` that is HIGH when publishing, defaults to draft, and has a rule for stock prices. Extend `ContentTest` | M |
| C2-sweep | Other controllers that bypass the bus | — (new) | CONFIRMED | route:list plus reflection: 281 mutating actions; 211 reach the bus, 20 write raw, 50 call a service. HIGH permissions run without step-up at `Staff/ReportController.php:58-62` and `ProvisioningController.php:302-307`. `SupportController.php:116-139` has no audit record | MEDIUM | Add an arch test for raw writes with an allow-list. Enforce step-up in `ApiContext::authorize`. Add `TicketService::reprioritise()` | M |
| C3 | `tests/Feature/{Invoicing,Payments,Tax,WalletLedger,Risk}` do not exist | ASSUMED | CONFIRMED | `git ls-tree` gives the same list on both revisions. Coverage lives in `Finance/`, `Orders/` and also `Billing/` | LOW | Document the grouping in `AGENTS.md` #7 and `.ai/TESTING.md:28`. Do not move files; runbooks cite the paths | S |
| C4 | `ADAPTERS` has 10 keys; the doc is wrong | ASSUMED | CONFIRMED (count corrected) | `PlatformServiceProvider.php:51-62`, `AGENTS.md:42`, `docs/provider-adapters/README.md:5-6`. The rest are 13 classes in 7 families plus 2 scrapers, not "10" | LOW | Correct the docs; do not force everything into `ADAPTERS` | S |
| C4-cdn | `CdnService` injects the concrete `CloudflareCdnProvider` | ASSUMED | CONFIRMED | `CdnService.php:36`; no container binding for `CdnProvider` | MEDIUM | Bind the interface and add a contract test | M |
| C5.1 | `ApplyDnsChangesWorkflow` is dead | ASSUMED | CONFIRMED | Only its own declaration at `:17`. All callers use `DnsService::commit` (`:158`) | LOW | Delete it | S |
| C5.2 | `DeployAppWorkflow` has no caller | ASSUMED | CONFIRMED, and broken | The `Deployment` model maps to `git_deployments` (`Deployment.php:13`). The baseline hides the resulting error (`phpstan-baseline.neon:2871-2875`) | LOW | Delete it together with `Build`, the baseline entries and the `events-catalog.md:65` row | S |
| C5.3 | `ViesClient` and `VatValidation` are unwired | ASSUMED | CONFIRMED | As C1a. The baseline entry at `:3562-3565` is stale | MEDIUM as dead code (HIGH as C1) | See TASK-0033 | M |
| C5.4 | `EInvoiceProvider` has no implementation | ASSUMED | CONFIRMED (deliberate) | The interface at `:8`. Its orphan siblings: `EInvoiceDelivery`, `invoices.einvoice_state`, `config/onhost.php:209-214` | INFO | Mark it as reserved, pending the owner's decision | S |
| C5.5 | No workflow registry; hard-coded references | ASSUMED | PARTLY | Still 22 workflows. The lines moved to `ServiceService.php:204-208,652-659`. The audit's list misses the starters at `DomainService.php:226,314,418,465` and `Reconciler.php:131` | LOW | Add a reachability and unique-kind test instead of a registry | S |
| C5.6 | Only 4 of 484 non-model classes are dead | ASSUMED | PARTLY | The four are confirmed. There are also 5 orphan models (`AccessReview`, `MfaChallenge`, `TrustedDevice`, `EInvoiceDelivery`, `CapacitySnapshot`), and `Build` is used only by dead code. The figure 484 was not reproduced | LOW | Delete each one or mark it reserved; ask the Identity owner about the MFA and trusted-device models | S |
| C5.7 | Did the stack add dead classes? | — (new) | CONFIRMED: none | All 71 added PHP files are wired. Two methods have no caller: `UsageRecorder.php:53` and `UsageReading.php:66` (`dad369e`) | INFO | Delete them or add the test they were meant for | S |
| C6-hetzner-a | Hetzner has "no test at all" | ASSUMED | WRONG | `CapacityPlannerTest.php:80-92`, `CapacityBudgetTest.php:55-95` and `NodeBootstrapTest.php:49-60` all run under `preventStrayRequests`. They predate `2426c17` | INFO | Correct the audit row | S |
| C6-hetzner-b | Hetzner has no `tests/Contract` coverage | ASSUMED | CONFIRMED | No Contract file. Untested: the largest-type fallback (`HetznerCloudNodeOrderProvider.php:81-95`), the location fallback (`:34`), the timeout path (`:40-44`). It uses raw `Http` (`:111`) and throws `DomainError` (`:43,46,86`) | MEDIUM (HIGH before any `node_order` is configured) | Add a contract test with a real catalogue fixture. Refuse when nothing fits. Adopt an existing server after a timeout or 409. Move to `ProviderHttpClient`/`ProviderException` and change the planner's catch in the same commit | M |
| C6-hetzner-gating | Real-money orders are gated (default_off, plus four-eyes and budget as the verifier framed it) | ASSUMED (default_off) | PARTLY | default_off holds (`AutomationLedger.php:62`); approve is HIGH + step-up (`CapacityCommand.php:33-41`). No four-eyes (`:43-46`). The budget of 0 means no cap (`config/onhost.php:295`) and fails open (`CapacityBudget.php:36-44`). The `budget` op is NORMAL. The vendor call runs inside the transaction (`CommandBus.php:88`) | MEDIUM (HIGH once a token is configured) | Make the budget op HIGH. Fail closed without a cap or a price. Require approval for approve/retry with a vendor. Show a dry-run estimate. Call the vendor outside the transaction | M |
| C6-bank | Payments/Bank has no test anywhere | ASSUMED | WRONG | `BankImportTest.php:26-88`, `WalletTopUpReferenceTest.php:14-21`, `InvoiceBankPaymentTest.php:21-44`. The provider makes no HTTP call | INFO | Correct the audit; optionally unit-test the VS fallback (`:33`) | S |
| C6-comgate | Comgate is faked only inside `CheckoutTest` | ASSUMED | PARTLY | Also `StoredPaymentMethodTest.php:44-61`, `StoredMethodProvidersTest` and `RecordFixturesTest`. True that there is no Contract file and no recorded fixtures, refund and settlement are never faked, and `CheckoutTest` lacks `preventStrayRequests` | MEDIUM | Record sandbox fixtures and add `ComgateContractTest` under `preventStrayRequests` | M |
| C6-kubernetes | Kubernetes has no test | ASSUMED | CONFIRMED | No test references it. Both products are drafts (`CatalogSeeder.php:78-88`) | LOW | Make a contract test a precondition for un-drafting | M |
| C6-cloudflare | Cloudflare has no test | ASSUMED | CONFIRMED | Only `WebToolsUnitTest.php:147`. The adapter deletes edge DNS (`CloudflareCdnProvider.php:92-95`) | MEDIUM | Add `CloudflareContractTest` | M |
| C6-ai | AI adapters have no adapter-level test | ASSUMED | CONFIRMED | Every Support test overrides the registry. A latent `tool_calls` shape defect is traced statically, not executed: `AssistantService.php:742` vs `OpenAiCompatibleProvider.php:24` | MEDIUM (HIGH before enabling AI) | Add a contract test for a two-round tool loop. Convert `tool_calls` to the OpenAI shape. Catch `ConnectionException` | M |
| C6-redfish | Redfish is tested only indirectly via Feature tests | ASSUMED | CONFIRMED | `HostPowerReaderTest.php:38-55`, `BmcInventoryTest.php:33-39` | LOW | Settle the convention (C6-rule) | S |
| C6-oncall | OnCall (x3) is tested only indirectly | ASSUMED | PARTLY | Only the PagerDuty outbound call is faked (`OnCallEscalationTest.php:30-43`). The Opsgenie and Webhook outbound calls never run | LOW | Add two cases | S |
| C6-ipgeo | IpGeo is tested only indirectly | ASSUMED | CONFIRMED (adapter-level) | `OrderRiskFeedbackTest.php:124-141` | INFO | — | S |
| C6-shell | Shell is tested only indirectly | ASSUMED | PARTLY | It speaks SSH, not HTTP. Unit tests exist at `WebToolsUnitTest.php:77-79,150-156`. `ScriptedShell` sits in the production namespace | LOW | Amend AGENTS #4 for non-HTTP transports; move `ScriptedShell` | S |
| C6-gopay-stripe | GoPay and Stripe have a real contract test | ASSUMED | PARTLY | `GatewayRecurringContractTest.php:41,89` covers recurring payments only. Create and status are covered only by Feature tests | INFO | Extend it when refunds are wired | S |
| C6-rule | Which adapters break AGENTS #4 | — (new) | CONFIRMED | No HTTP test at all: Cloudflare, Kubernetes, Anthropic, OpenAiCompatible, Opsgenie, Webhook. `tests/Contract` went from 18 files / 85 cases to 20 / 101 | MEDIUM | Work in priority order, and add an arch test with an allow-list | M |
| C7a | `COMGATE_TEST` / `WEDOS_TEST_MODE` sandbox defaults are "fail-safe but easy to forget" | ASSUMED | PARTLY | The defaults are at `config/onhost.php:184,373`, and the doctor has rows at `Doctor.php:409,453`. Not fail-safe for WEDOS: `WapiGateway.php:82` sends `test=1` while `DomainService.php:228-245` still bills. Comgate's handling of the test flag is UNVERIFIED | MEDIUM (HIGH until C12 is fixed) | Derive the defaults from `APP_ENV`, or refuse in production without an explicit override | S |
| C7b | ClamAV `enforce=true` with an empty host | ASSUMED | CONFIRMED | Now at `config/onhost.php:614` (was `:593`). `VirusScanner.php:28-46`; pinned by `AuditBlock5rTest.php:113-117`. The doctor has a row at `Doctor.php:499-502`. The metric is omitted at `HealthController.php:130-131` | MEDIUM | Fail closed in production; emit `onhost_virus_scanner_up=0` | S |
| C11 | The pre-commit hook is inert until `hooksPath` is set; nothing stops a `.env` | VERIFIED (Fikoun's clone) | PARTLY | Nothing enforces the setting (composer and npm scripts). The Windows clone has it set. `.env` and `.env.production` are gitignored, but `.env.local`, `.env.staging` and `.env.testing` are not. The CI gitleaks job (`security.yml:5-27`) runs only on `main`/`development` pushes and on PRs | MEDIUM | Ignore `.env.*` except `!.env.example`. Set `hooksPath` automatically in composer scripts. Widen the gitleaks trigger | S |
| C12 | `deploy.sh` runs `onhost:doctor \|\| true` | ASSUMED | CONFIRMED | `deploy.sh:51-52` and `install.sh:85`. The doctor runs after migrations and restarts. The stack adds the promise at `Doctor.php:237` | MEDIUM (HIGH at the first production deploy) | Drop `\|\| true`. Gate before the restarts. Print a rollback hint. Allow a logged `ALLOW_DOCTOR_FAIL=1`. Long-term, switch to atomic release directories | S (L for atomic releases) |
| C14 | No release has ever been cut | ASSUMED | CONFIRMED | `git tag -l` returns 0; `git ls-remote --tags origin` is empty; `.ai/releases` is absent. `deploy.sh:24-26` has no REF | MEDIUM (HIGH before go-live) | After the PR #24 merge, run `/ai-release-check` and create an annotated tag. Add a `REF` parameter to `deploy.sh` | S |
| F1 | `ONHOST_STAFF_MFA_REQUIRED` defaults to true | ASSUMED | CONFIRMED | `config/onhost.php:109`, `StepUpService.php:59`, `Doctor.php:473` | INFO | Keep it | — |
| F2 | `ONHOST_FOUR_EYES` defaults to true | ASSUMED | CONFIRMED | `config/onhost.php:117`. ADR `0007:73,104` notes that a solo owner must switch it off before TASK-0022 | LOW | Put it in the release notes. Make the doctor row blocking when there are fewer than 2 deciders | S |
| F3 | `ONHOST_CAPACITY_GATE` defaults to true | ASSUMED | CONFIRMED | `config/onhost.php:318`, `QuoteService.php:405`; no doctor row | INFO | Optionally add a non-blocking row | S |
| F4 | `ONHOST_ORDER_RISK` defaults to true | ASSUMED | CONFIRMED | `config/onhost.php:349`, `OrderRiskService.php:60` | INFO | — | — |
| F5 | `ONHOST_TURNSTILE_ENFORCE_*` are fail-closed | ASSUMED | PARTLY | The flags are true (`config/onhost.php:621-622`), but the keys default to empty, which means OFF (`Turnstile.php:36-47,90-104`). A verifier outage counts as PASS (`:65-67`). No doctor row exists | MEDIUM | Add a doctor row, blocking in production | S |
| C13 | "Two HIGH findings, both about a default arm" | ASSUMED | PARTLY | One is confirmed (`ServiceActionCommand.php:50`). The second is UNVERIFIABLE because `security-posture.md` was not pushed. The nearest HIGH of the same shape is `ApiContext.php:121` (C13-H2c) | HIGH | Push the page; track H1 and H2c | S |
| C13-FM | First move 3: an S-sized fix closes a four-eyes hole | ASSUMED | PARTLY | The hole is real: `backup.delete` is CRITICAL (`PermissionCatalog.php:75`) and never checked. Remapping alone does nothing: `IdentityCommandAuthorizer.php:61` takes the command's own risk, and `:65-67` forces CRITICAL for staff only. Approvers are staff-only (`ApprovalService.php:38`) | HIGH | S interim: map plus step-up. M: the full fix. Needs an owner decision on customer four-eyes | S / M |
| C13-H1 | Which actions reach the default arm | — (new) | CONFIRMED | 121 of 132 reach it. 7 of the 13 `DestructivePreview` actions have no step-up. A `services:power` token passes (`ApiContext.php:120`). `panel.password` is RESOLVED_BY_STACK (`634b11f`) | HIGH | An exhaustive map that throws on unknown actions. Risk and step-up lists. A two-way map test and a role-matrix test | M |
| C13-H1b | A `svc_manage` guest reaches root or console | — (new) | CONFIRMED (rescue.start PARTLY) | `RoleCatalog.php:41`. `access.reset` (`ServiceService.php:786-800`); `rescue.start` (`RescueMode.php:34`); `subuser.create` (`:1318-1327`), which survives revocation when a different e-mail is used; command schedules (`:817-836`) | HIGH | Map these to `service.console`. Make the schedule permission payload-aware, including spec apply | S–M |
| C13-H1c | `archive.restore` runs without step-up | — (new) | CONFIRMED | Absent from `ServiceActionCommand.php:61,66`. `backup.restore` is HIGH (`PermissionCatalog.php:74`). No test covers it | MEDIUM | Take the risk floor from the catalogue, or drop the action from `/actions` | S |
| C13-H2a | `ProvisioningCommand` defaults to a read permission | — (new) | PARTLY | `ProvisioningCommand.php:48` cannot be reached today: 36 of 36 ops are mapped and the handler throws (`:270`). `OPS` lists only 19 of 36 | LOW | Throw on an unknown op; make `OPS` the source of truth | S |
| C13-H2b | A `riskLevel()` default of NORMAL downgrades HIGH permissions | — (new) | CONFIRMED | 23 Provisioning ops, plus Compliance, Incident, RegistrarConnection and `DomainCommand` `publish_ds`. All staff-only except `publish_ds` | MEDIUM | Use `max(declared, catalogue)` unless an op is listed in `LOWERED_RISK`; add an arch test | M |
| C13-H2c | The token-scope prefix arm lets a read token open a console | — (new) | CONFIRMED | `ApiContext.php:120-121`, `routes/api.php:305-306`, `TokenRouteScope.php:29,55`, `IssueConsoleTokenCommand.php:12-15`. No test | HIGH | An explicit permission-to-scope map. Console returns null or needs a new scope. Tests | S |
| C13-H2d | The other permission default arms are HIGH holes | — (new) | WRONG | Every op that reaches those defaults is meant to get that permission. The handlers throw on an unknown op | INFO | A shared fail-closed arch test | S |
| C8.1 | Larastan runs at level 5 | ASSUMED | CONFIRMED | `phpstan.neon:7`; unchanged by the stack | LOW | Clean the baseline first, then try level 6 | M |
| C8.2 | `reportUnmatchedIgnoredErrors` is false | ASSUMED | CONFIRMED | `phpstan.neon:15`. A scratch run found 183 `ignore.unmatched` and 22 `ignore.count` errors across 70 files | MEDIUM | Regenerate the baseline, then set the flag to true | S |
| C8.3 | The baseline has 641 entries / 1013 occurrences | ASSUMED | PARTLY | The counts are exact, but only 458 entries / 655 occurrences are live. `PROJECT_STATE.md:56` overstates the debt by about 35 % | LOW | Update the numbers after regenerating | S |
| C8.4 | Nothing protects the baseline | ASSUMED | CONFIRMED | `tests.yml:35-36`; no `CODEOWNERS`; the stale assertion at `Config.Tests.ps1:67-68` | MEDIUM | A CI check against baseline growth; `CODEOWNERS` | S |
| C9.1 | Routes: 518 on development, 543/493 on the stack | ASSUMED | PARTLY | The stack figure is confirmed by `route:list`. The development figure is a static grep; statically, HEAD is 486+42=528. The table compares two different methods | INFO | Count both revisions the same way | S |
| C9.2 | `Schedule::` entries: 85 → 88 | ASSUMED | CONFIRMED | Added: `metering:rollup`, `metering:prune`, `withdrawals:finish` | INFO | — | — |
| C9.3 | Migrations: 59 → 61 | VERIFIED (stack) | CONFIRMED | `000860`, `000870` | INFO | — | — |
| C9.4 | Tests 307/906 → 337/~1280; suite 1368 | VERIFIED (Pest) | CONFIRMED | `pest --list-tests` lists 1368 | INFO | — | — |
| C9.5 | 41 commands match 41 handlers | ASSUMED | PARTLY | True on development. On the stack both are 46 (`DomainServiceProvider.php:115-163`). No test checks it | INFO | Add an arch test | S |
| C9.6 | Automation rules 30 → 38, with 7 `default_off` | ASSUMED | CONFIRMED | `AutomationLedger.php:48-82`. There are also 2 new default-on rules (`:76-77`). Go-live §6 omits `capacity.auto_order` | LOW | Update the checklist | S |
| C10.1 | No migration saga for web, mail or managed | ASSUMED | WRONG | `ServiceMigrationService.php:31` maps web and managed to `WebMigrationWorkflow` (`85e9cbf`, before `2426c17`), covered by `WebMigrationTest` with 4 cases. The stale text at `:22-27` and `:71` misled the audit | LOW | Fix the message and docblock. Mail is the only real gap | S |
| C10.2 | Node discovery covers only 4 families | ASSUMED | CONFIRMED | `ProviderInstanceService.php:268-278` | INFO | By design | — |
| C10.3 | BYO registrar is WEDOS-only although Subreg has the same contract | ASSUMED | PARTLY | WEDOS-only is confirmed (`RegistrarConnectionService.php:63-64`). Subreg has no DNS-zone adapter, so it does not have the same contract | INFO | M, if Subreg BYO is wanted | M |
| C10.4 | aaPanel has no temporary logins | ASSUMED | CONFIRMED | `AaPanelTools.php:695-698` | INFO | — | — |
| C10.5 | ISPConfig has no per-cron-job log | ASSUMED | CONFIRMED | `IspConfigTools.php:290-293` | INFO | — | — |
| C10.6 | `console_unsupported` / `usage_unsupported` per family | ASSUMED | CONFIRMED | Lines are now `ServiceService.php:1384,1407` | INFO | Update the line references | S |
| C15.1 | Billing tests are thin (16 cases) | ASSUMED | PARTLY | True on development. The stack has 80 cases (TASK-0025 `b62ab51`, `9e99f1b`), but the older billing code is still covered by the same 16 | LOW | Add targeted cases and measure with pcov | M |
| C15.2 | Organizations tests are partial | ASSUMED | PARTLY | 5 cases but 79 assertions. Membership flows are covered in other folders | LOW | Add one membership-lifecycle test in its own folder | S |
| C15.3 | Partners tests are partial | ASSUMED | PARTLY | 5 cases with 132 assertions. Payout rejection and `recomputeAllTiers` are untested | LOW | Add those two cases | S |
| C15.4 | Incidents tests are thin | ASSUMED | CONFIRMED (count) | 6 files / 14 dense cases. Coverage cannot be measured: no pcov, and CI sets `coverage: none` | LOW | Run one pcov pass first | M |
| C15.5 | TASK-0021/0025 added Billing and Orders tests | — (new) | RESOLVED_BY_STACK | Orders went from 62 to 150 cases, Billing gained 64 | INFO | — | — |

---

## 3. Findings that survived the adversarial challenge

Two independent challengers re-examined each of the 12 findings below, trying to refute them. All 12 survived. Three were downgraded from HIGH to MEDIUM, both challengers agreeing each time. None was refuted.

| id | Result | Challengers' notes |
| --- | --- | --- |
| C1a | Stands HIGH (MEDIUM if CZ-only) | No other writer of `valid` exists. The request-side spoofing path is deliberately closed (`QuoteService.php:121-127`, `CartController.php:69-70`). The harm is overcharged EU B2B customers, invoices that are legally wrong, and a false public promise, not lost revenue. `ViesClient` calls HTTP from `domains/`, which breaks CLAUDE.md, so the fix should move the call behind a `providers/*` contract. The requester VAT ID must be the real one, or VIES answers `unknown`. Unverified: whether production already has EU B2B invoices. A read-only count would answer that. |
| C1b | Stands HIGH | Confirmed, with the line drift explained. Both challengers independently traced the partner consequence: `PartnerService.php:757` checks `'payer'` and reads `rates.<CC>` instead of `standard_rates`, so every production self-billing document is at 0 % VAT (see section 4). |
| C1c | Stands HIGH | Found three more callers that pass the organisation's own status: `CheckoutController.php:106`, `OrdersCommandHandler.php:120` and `Staff/CustomerController.php:193`. Correction to the recommendation: "do not sell as reverse charge" does not fit, because the system never issues a reverse-charge invoice. What it issues are VAT invoices that should not carry VAT. |
| C1d | Stands HIGH | Nuance from Art. 18 of Implementing Regulation 282/2011: B2C treatment of a customer who gave no VAT number is defensible. The defect here is that a VAT number that *was* given is never checked. An accountant must confirm. Any fix to the surface text must go through a SurfaceRenderer seam, because `Onhost-app.dc.html` must stay byte-identical. |
| C6-hetzner-b | Downgraded to MEDIUM (HIGH once `node_order` is configured) | The path does nothing until an instance has `options.node_order`. The test fixture's cx42 at 16/64/1000 is not a real size (ASSUMED from vendor knowledge), so the "largest type" fallback is likely on a real catalogue. `pick()` does not filter by CPU architecture. `NodeBootstrap.php:99` later corrects the node's recorded capacity. Important: `ProviderException` extends `RuntimeException`, while `CapacityPlanner.php:141` catches only `DomainError`. The adapter and the planner must change in the same commit, or `decide()` leaves requests stuck in APPROVED. |
| C6-hetzner-gating | Downgraded to MEDIUM (HIGH once a token is configured) | One `infrastructure_admin` holder can do the whole chain alone: add a vendor token (`instance.upsert`), remove the cap (the `budget` op is NORMAL), then approve with step-up. The `forecast/run` path is only LOW; it can order only what the automatic rule already allows. The real integrity hole is the vendor call inside `DB::transaction`, where a later exception rolls back local state while the server stays bought. If the owner runs solo with `ONHOST_FOUR_EYES=false`, adding approval helps little, so failing the budget closed is the most valuable fix. |
| C12 | Downgraded to MEDIUM (HIGH at the first production deploy) | Deploys are run by hand and print the FAIL table. `onhost:production:prepare` does gate the first go-live, and staging always exits 0. Correction to the recommendation: moving the doctor "before queue:restart" cannot prevent a bad release. After `git reset --hard` (`:26`) PHP-FPM already serves the new code, and `migrate --force` (`:33`) has already run. Real prevention needs release directories switched by a symlink (L). The S fix makes the failure visible and scriptable. |
| C13 | Stands HIGH (PARTLY) | H1 is confirmed. The second HIGH stays unverifiable until `security-posture.md` is pushed. |
| C13-FM | Stands HIGH | An S interim fix closes the step-up and role hole. The full fix is M. Correction to point (3): `ActionHookService` and `DiscordService` cannot issue `backup.delete` today, because their allow-lists and deny-lists exclude it. So the three hard-coded `service.manage` gates are latent consistency debt, not a live bypass. `snapshot.delete` likewise skips `compute.vm.delete`. |
| C13-H1 | Stands HIGH | The concrete harm path: `file.delete`, then `database.delete`, then `backup.delete` on every generation that is not protected. Each step is NORMAL, with no step-up and no confirmation (`confirm` is optional at `ServiceController.php:114`). That is permanent loss inside one organisation. Corrections to the fix: (a) the risk and step-up lists must change with the permission map, or a CRITICAL `backup.delete` would silently demand staff four-eyes; (b) map `snapshot.delete` to `compute.vm.delete`, and decide `gbackup.delete` with the owner, so `cloud_operator` and `game_operator` keep their documented abilities; (c) `gbackup.delete` has no protected-backup check (`ServiceActionWorkflow.php:620-625`). |
| C13-H1b | Stands HIGH | `rescue.start` alone is PARTLY: the rescue system is only usable with console or VNC access, so for `svc_manage` it is mainly forced downtime, unless the operator's ISO exposes SSH (UNVERIFIABLE statically). The two challengers disagree on subuser cleanup: one found none; the other found `RevokeDelegatedAccess.php:93-98` removing only sub-users with the guest's own e-mail. Either way, a sub-user created with another address survives revocation. Keys set via `access.reset` are probably not tracked by `SshKeyLedger` (ASSUMED). `svc_manage` on game servers already has near-console control through file and variable actions, so schedules add the least harm. Follow-up: check the staff roles that have `staff.service.manage` but no `staff.console`. |
| C13-H2c | Stands HIGH | No second token check exists anywhere. The console relay (`ConsoleRelayController.php:28-45`) does not re-check who issued the `con_` token or with which scope. Same catch-all, second gap: `command.run`, `shell.*` and `command.send` resolve to `services:read`, so the portal's "operate services" preset (`services:read` + `services:power`, `onhost-panel-account.api.js:72`) can run commands and add SSH keys. The proposed test must use a read+power token, because a power-only token is refused today by accident. Exploiting it end to end needs the external relay to be deployed (UNVERIFIABLE in this repo). |

---

## 4. New findings the audit did not include

### Interactions with TASK-0017..0027

- **VAT status vs consumer withdrawal (TASK-0025): no defect.** C1e is WRONG. `WithdrawalPolicy` decides from `order.meta.customer_class`, which is set from IČO or VAT ID, never from `vat_status`.
- **The consumer/business class can still be wrong (C1f, MEDIUM, S).** Found while checking the withdrawal question:
  - Clearing a mistyped IČO or VAT ID leaves the organisation `b2b` (`isset` at `OrganizationService.php:259`). The customer then gets the DPA instead of the withdrawal waiver, and is refused the 14-day withdrawal.
  - `type=company` without an IČO registers as `b2c`, both at `AuthController.php:46` and in guest checkout (`CheckoutController.php:90`).
  - A DIČ set through the update path does not count.
- **TASK-0017 widened C12.** `Doctor.php:237` and `.ai/tasks/TASK-0017.md:88` say a metering gap "fails a production deploy", but nothing obeys the exit code.
- **TASK-0021 resolved `panel.password`** (`634b11f`: `service.panel_account.manage` plus `OwnerOnlyActions`). No other default-arm action was touched.
- **TASK-0024 `mailbox.backup_retention`** reaches the default arm, but `CustomerActionParams.php:48-50` refuses it with `operator_only` for non-staff callers. Map it explicitly to a staff permission.
- **TASK-0025 resume after cancellation.** `ServiceReinstatement.php:291-308` guards it only while the default-off rule `services.reinstate` is on. With the rule off nothing bills again (INFO), but check this before toggling the rule.
- **TASK-0022 creates a deploy-order dependency:** a solo owner must set `ONHOST_FOUR_EYES=false` before deploying (`docs/adr/0007…:73,104`). Put it in the release notes (F2).
- **Not everything ships dark.** `metering.rollup` and `metering.prune` are default-on (`AutomationLedger.php:76-77`), and the TASK-0019 backup tick has no switch (`.ai/PROJECT_STATE.md:68`).
- **Stack code health:** the stack touched 20 of the 70 files that hold stale baseline entries without pruning them. It also added two unused public methods (`UsageRecorder::hasMeasured`, `UsageReading::hasValue`).

### Security and authorization

- **Step-up is not enforced on non-bus staff triggers that check a HIGH permission (MEDIUM, S).** `ApiContext::authorize` (`:100-106`) never checks risk. The affected endpoints are `POST /v1/staff/dunning/run` (it can suspend and terminate services) and `POST /v1/staff/capacity/forecast/run`.
- **Several permissions are never checked (MEDIUM, M).** They are `compute.vm.manage`, `compute.vm.delete`, `game.manage`, `mail.manage`, `database.manage`, `apps.deploy`, `service.credentials.rotate`, and `backup.delete` as a permission. As a result, `mail_manager` (`RoleCatalog.php:31`) cannot run any mailbox or alias action, and `cloud_operator`'s `compute.vm.delete` does nothing.
- **Three secondary gates hard-code `service.manage`** and would bypass a finer map: `ServiceSpecService::apply:170`, `ActionHookService.php:55,100` and `DiscordService.php:353-357`. They are latent today; fix them together with TASK-0029.
- **Scope bug in `IssueConsoleTokenCommand`.** It uses the organisation scope, so a `svc_console` guest is refused the console that role promises. This fails closed (LOW, S).
- **The destructive confirmation is optional for API clients** (`ServiceController.php:103-114`; INFO, owner decision).
- **`Staff/SupportController@assign`** changes priority and queue without an audit record and without recomputing the SLA (LOW-MEDIUM, S).

### Money and tax

- **Partner self-billing VAT is always 0 % in production (HIGH, S-M; both C1b challengers rate it HIGH).**
  - `PartnerService.php:757` checks `vat_status === 'payer'`, which no production code writes.
  - The same line reads `rates.<CC>`, but the rule set uses `standard_rates` (`TaxRuleSeeder.php:26`), so the rate always falls back to 21.
  - `PartnersTest.php:65` hides this by setting `'payer'` directly.
  - Reachable through `POST /v1/partner/payouts` (`routes/api.php:144`).
- **Customers are told the opposite of what the system does** (truthfulness; see C1d). The false statements are in `prototype-content.json:533,1285`, `Onhost-app.dc.html:11704,11646` and `onhost-content.js:243`.
- **Hetzner orphan servers.**
  - A timeout after the server was created makes the request FAILED. No node row is written and no budget is counted.
  - Name reuse then makes Hetzner refuse every retry, while the untracked server keeps billing.
  - Separately, the approve path holds the DB transaction open across up to three 15 s HTTP calls.
- **`PaymentService::refund` and `::reconcile` have no production caller.** `wallet.refund.requested` has no consumer. Confirm with the finance runbook whether card refunds and reconciliation are meant to be manual.
- **`docs/runbooks/web-tools.md:1194`** leaves out the largest-type fallback.

### Integrations

- **AI:** a 30 s adapter timeout reaches the customer chat as HTTP 500. `AssistantService.php:221` catches only `ProviderException`.
- **Eight adapters bypass `ProviderHttpClient`:** Hetzner, Redfish, HttpIpGeo, the three OnCall adapters, Anthropic and OpenAiCompatible. They write no `provider_calls` row and get no breaker or rate limit, so an audit of provider calls cannot see a paid Hetzner order.
- **The Fio bank API is called from `domains/Payments/BankStatementImporter.php`**, which breaks CLAUDE.md's rule against vendor calls from domains. It is tested.
- **`CheckoutTest` and `RegistrarPriceScrapeTest` lack `Http::preventStrayRequests()`**, so an unmatched URL would reach the real network.
- **There are no `docs/provider-adapters` pages** for Hetzner, Payments, Cloudflare, Redfish, OnCall or IpGeo.

### Operations

- **Secret hygiene:** `.gitignore` does not cover `.env.local`, `.env.staging` or `.env.testing`. `security.yml` scans pushes only on `main` and `development`.
- **In WEDOS test mode**, `RegistrarSelector.php:43-44` sends every registration to WEDOS dry runs.
- **`deploy.sh` takes a backup but never runs `onhost:platform:backup:verify`**, although `release-and-rollback.md` requires a verified backup.
- **The ClamAV metric is omitted when no host is set**, so an alert cannot tell "not configured" from "fine".

### Static analysis and tests

- **The baseline has 358 suppression slots that match no current error**, so a new error with the same message in those 70 files passes CI silently. `Config.Tests.ps1:68` expects 15 entries, while the real number is 641.
- **Coverage is measured nowhere** (`tests.yml:21 coverage: none`; no pcov or xdebug locally). The user's 80 % coverage rule and every "thin tests" judgement therefore cannot be verified.
- **No arch test guards AGENTS rule 2, `HANDLERS` completeness or workflow reachability.**

---

## 5. Corrections to the audit

The audit is careful and mostly right. 36 of its 59 claims hold unchanged on the stack tip, and its evidence discipline made re-checking easy. The corrections below are factual only. Several come from reading `development` while the stack moved underneath.

1. **Hetzner "no test at all" (`development-state.md:117`) is not accurate.** Three Feature tests exercise the adapter with `Http::fake` and `preventStrayRequests`, and they predate `2426c17`. The real gap is the missing Contract test and the untested branches (C6-hetzner-b).
2. **Payments/Bank "no test anywhere" (`:118`) is not accurate.** `BankImportTest`, `WalletTopUpReferenceTest` and `InvoiceBankPaymentTest` cover it, and the provider makes no HTTP call.
3. **"Web/mail/managed have no migration saga" (`:146`) is not accurate for web and managed.** They have used `WebMigrationWorkflow` since `85e9cbf`. The refusal message at `ServiceMigrationService.php:71` is stale and probably caused the misreading. Only mail is a gap.
4. **Comgate is "only faked inside CheckoutTest":** it is also faked in `StoredPaymentMethodTest`, `StoredMethodProvidersTest` and `RecordFixturesTest`. The underlying gap (no Contract file, no recorded fixtures) stands.
5. **Headline 4, "no step-up bypass", no longer holds.**
   - `archive.restore` reaches a HIGH permission without step-up (C13-H1c).
   - A `services:read` token obtains a console token (C13-H2c).
   - `dunning/run` and `forecast/run` check HIGH permissions without step-up.
   - Of the "two HIGH, both default arm", one is confirmed. The other cannot be matched until `security-posture.md` is pushed; the HIGH of the same shape we found lies outside the Command classes.
6. **First move 3 ("S-sized change") understates the work.** Remapping the permission alone restores neither step-up nor four-eyes, because `IdentityCommandAuthorizer.php:61` trusts the command's own `riskLevel()`. An S interim fix exists; the full fix is M and needs an owner decision.
7. **First move 1 ("VERIFIED inert")** is true for the audit's macOS clone. The Windows clone has `core.hooksPath` set. "Nothing stops a `.env`" is overstated: `.env` is gitignored. It is true for `.env.local`, `.env.staging` and `.env.testing`.
8. **"41 command classes / 41 handlers"** is true on `development`; the stack has 46/46.
9. **"4 dead classes out of 484"** leaves out 5 orphan models and `Build`. The figure 484 could not be reproduced without the counting rule.
10. **"The other 10 adapters"** are 13 vendor classes in 7 families plus 2 scrapers.
11. **The ADR citation:** the HIGH/CRITICAL-for-publishing rule is in `AGENTS.md:13-14`, not `CLAUDE.md`.
12. **"Fail-safe" sandbox flags:** WEDOS test mode in production bills customers for domains the registry never created. The Comgate test flag handling is UNVERIFIED.
13. **"`ONHOST_TURNSTILE_ENFORCE_*` fail-closed":** the gate is off without keys, and there is no doctor row.
14. **"The newest features ship dark"** holds for 7 rules. Two new metering rules are on by default, and the TASK-0019 tick has no switch.
15. **The baseline debt figure (641/1013)** is counted correctly, but about 35 % of it is stale; the live figure is 458/655.
16. **The scale table** compares a static grep for `development` (476+42) with a runtime `route:list` for the stack (543/493).
17. **Thin or partial tests:** Billing is no longer 16 cases on the stack (80). The Organizations and Partners counts are right, but their cases are dense (79 and 132 assertions).
18. **Line drift, all caused by stack commits:**

    | Item | Audit's line | Line now |
    | --- | --- | --- |
    | `ViesClient` class | `:19` | `:18` |
    | `OrganizationService` reset | `:256` | `:261` |
    | ClamAV config | `config/onhost.php:593` | `:614` |
    | `ServiceService` unsupported arms | `:1354/1377` | `:1384/1407` |
    | `ServiceService` workflow references | `:191,622-628` | `:204-208,652-659`, plus `DomainService.php:226,314,418,465` and `Reconciler.php:131` |

**Not re-checked in this pass:** the tracked-file counts (1 174 PHP files), the zero TODO/FIXME claim, the ~94 doctor `add()` sites, the 13 runtime composer dependencies, and the per-domain verdicts "Integrations: Discord only" and "Content: skeletal".

---

## 6. Proposed follow-up tasks (priority order)

- **Numbering:** TASK-0028 is taken by Fikoun's unpushed `chore/TASK-0028-portable-toolchain-and-ai-docs`, so new ids start at TASK-0029.
- **Coordination:** TASK-0041 (docs) touches `AGENTS.md` and should be sequenced after or merged with TASK-0028 to avoid conflicting edits.
- **Base branch:** all tasks assume PR #24 is merged first.

| # | Title | Why | Owner agent | Risk | Size |
| --- | --- | --- | --- | --- | --- |
| TASK-0029 | Close the service-action permission and risk default arms | C13-H1, H1b, H1c, FM. Includes: an exhaustive action→permission map that throws on unknown actions; risk and step-up lists for every `DestructivePreview` action; `access.reset`, `rescue.start` and `subuser.create` to `service.console`; payload-aware schedule permission; `snapshot.delete` → `compute.vm.delete`; the three secondary gates switched to `permissionFor()`; a two-way map test and a role-matrix test. Owner decisions needed: customer four-eyes vs HIGH for `backup.delete`, and who may delete `gbackup`/`snapshot` | onhost-security | HIGH: changes authorization for every service action. Needs a review chain and the full suite | M |
| TASK-0030 | API token scope: explicit map, and no console for tokens | C13-H2c. Replace the prefix arm with a map that denies by default. Console gets null or a new `services:console` scope. Tests with read and read+power tokens. Also enforce step-up in `ApiContext::authorize` for HIGH permissions (dunning/run, forecast/run). Regenerate OpenAPI | onhost-security | MEDIUM: may break existing CI tokens that rely on the gap. Announce it in the release notes | S |
| TASK-0031 | Deploy gate and first release | C12, C14. Drop `\|\| true`; gate before the restarts; rollback hint; logged `ALLOW_DOCTOR_FAIL`; `REF` parameter; backup verify; make `Doctor.php:237` and `release-and-rollback.md:29` true. After PR #24: `/ai-release-check`, `.ai/releases/<date>-<sha>.md`, annotated tag | onhost-infra (tag: onhost-release) | MEDIUM: touches the deploy path. Test on staging only; no production actions without the owner | S (atomic release directories later: L) |
| TASK-0032 | Production fail-closed configuration | C7a, C7b, F5, F2. Derive sandbox defaults from `APP_ENV` or refuse sandbox in production; ClamAV treats an empty host as UNAVAILABLE in production; emit the metric as 0; Turnstile doctor row; four-eyes decider row | onhost-backend | MEDIUM: can block uploads and checkout on a misconfigured production, which is intended | S |
| TASK-0033 | Wire VIES and fix VAT vocabulary, partner self-billing and public copy | C1a–C1d plus section 4. Bus command, queued validation, re-check at checkout, consultation number on order and invoice, scheduled re-check, staff override (step-up + four-eyes), review flag for unknown status, doctor row, `standard_rates` fix, adapter moved behind `providers/*`, end-to-end SK/DE test. Inputs needed: the accountant on invoices already issued, and the owner on content wording | onhost-billing | HIGH: changes tax documents. Needs the accountant's sign-off | M |
| TASK-0034 | Customer class correctness | C1f. `array_key_exists` in update; derive the class from type, IČO or VAT ID; count DIČ; add the lawyer question | onhost-backend | MEDIUM: affects the withdrawal right and the DPA/waiver choice | S |
| TASK-0035 | Harden Hetzner node ordering | C6-hetzner-b and gating. Contract test with a real catalogue fixture; refuse when nothing fits; filter by architecture; require a location; adopt an existing server after a timeout or 409; vendor call outside the transaction; `ProviderHttpClient` and `ProviderException` together with the planner catch; budget op HIGH; budget fails closed; approval; dry-run estimate; `forecast/run` through the bus; adapter doc page. **Must land before any instance gets `node_order`** | onhost-provisioning | MEDIUM: real money. Tests only; no live Hetzner calls | M |
| TASK-0036 | Contract-test backlog and arch coverage test | C6-rule, C4-cdn, C6-comgate, C6-ai, C6-cloudflare, C6-oncall. Comgate sandbox fixtures; Cloudflare contract test and `CdnProvider` binding; AI two-round tool loop and `tool_calls` conversion plus `ConnectionException` handling; OnCall Opsgenie and Webhook cases; `preventStrayRequests` in `CheckoutTest` and `RegistrarPriceScrapeTest`; an arch test that every adapter has a contract test (with an allow-list) | onhost-integration | LOW-MEDIUM: recording Comgate fixtures uses the sandbox only (`COMGATE_TEST=true`) | M |
| TASK-0037 | Content and support writes through the bus | C2, C2-sweep. `ContentCommand` (HIGH when publishing), draft default in the command, a `published_at` fix, a decision on the stock price rule, `TicketService::reprioritise()` with audit and SLA recompute, an arch test for raw controller writes with an allow-list | onhost-backend | LOW-MEDIUM: staff publishing gains a step-up | M |
| TASK-0038 | Fail-closed op commands and architecture guards | C13-H2a, H2b, H2d, C9.5, C5.5. `max(declared, catalogue)` risk unless an op is listed in `LOWERED_RISK`; throw on unknown ops; `OPS` as the single source; tests for `HANDLERS` completeness, workflow reachability and unique `kind()` values | onhost-architect | MEDIUM: staff ops gain step-ups | M |
| TASK-0039 | Static-analysis hygiene | C8.2–C8.4. Regenerate the baseline in its own commit; set `reportUnmatchedIgnoredErrors: true`; a CI check against baseline growth; `CODEOWNERS`; fix or delete the `Config.Tests.ps1` assertion; update `PROJECT_STATE.md` and `baseline.json` | onhost-qa | LOW | S |
| TASK-0040 | Secret hygiene | C11. `.env.*` except `!.env.example` in `.gitignore`; `hooksPath` set in composer `post-install-cmd` and `setup`; gitleaks on every branch push | onhost-infra | LOW | S |
| TASK-0041 | Documentation corrections | C4, C3, C10.1, C9.6, C5.2, extras. `AGENTS.md:42` registration line; `docs/provider-adapters/README.md` (contracts, class names, table); test grouping in AGENTS #7; `ServiceMigrationService` message and docblock; go-live checklist (`capacity.auto_order`, the default-on rules, the `ONHOST_FOUR_EYES` decision); `events-catalog.md:65`; `QuoteService.php:122-127` comment; `web-tools.md:1194`. Coordinate with TASK-0028 | onhost-docs | LOW | S |
| TASK-0042 | Dead-code cleanup and coverage measurement | C5.1, C5.2, C5.6, C5.7, C15. Delete `ApplyDnsChangesWorkflow`, `DeployAppWorkflow` + `Build`, the two unused methods; decide the orphan models with the Identity owner; mark the e-invoice placeholder reserved; add pcov to one CI job and write tests only where coverage reports gaps (Billing legacy, Partners payout rejection) | onhost-qa (deletions: onhost-architect) | LOW; dropping tables needs new migrations and a PostgreSQL CI run | M |

---

## 7. What depends on Fikoun's unpushed pages

These pages are linked from the README but absent from the repository (`git log --all -- .ai/audits/…/security-posture.md` is empty). Until they are pushed, the following cannot be closed:

- **`security-posture.md`:**
  - The second HIGH ("both about a permission falling through a default arm"). We cannot tell whether it is C13-H2c, one of the default arms ruled out in C13-H2d, or something else.
  - The evidence behind headline 4 ("no unauthenticated path, no cross-tenant path, no step-up bypass, no secret in tracked files"). The step-up part is contradicted above; the other three parts were not re-verified in this pass.
  - The "baseline not protected" discussion that `development-state.md` refers to (C8.4 was verified independently).
- **`production-readiness.md`:** the full list behind headline 3 ("the operational perimeter is not" ready). C12 and C14 are confirmed here. Anything else it lists (for example backups, monitoring or DNS for go-live) is unknown to us.
- **`ai-docs-and-tooling.md`:** which prompt and doc files it considers stale or wrong, and the design of the portable `./brain`. TASK-0041 must not duplicate or contradict it. The pages should be pushed or merged before TASK-0041 starts.
- **`needs-verification.md`:** the exact commands and tests the audit wanted run. Several can now be answered on the Windows toolchain: `route:list`, `pest --list-tests`, the Larastan scratch runs above. Others need the evidence listed as unresolved:
  - A read-only production count of b2b organisations in other EU countries with `vat_status='unknown'`, and of existing EU B2B invoices.
  - One Comgate sandbox `/payment/transId` status response, to see whether a test PAID could settle a live order.
  - A read-only Hetzner `GET /v1/server_types` against a test project.
  - Whether the console relay is deployed.
- **The audited revision:** the README says the audit analysed the stack at `36bf495`. This response verified `980a078`. Pushing the pages with their revision notes would let us mark precisely which differences come from the stack moving, rather than from the audit.