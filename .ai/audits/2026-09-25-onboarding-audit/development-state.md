# Development state

Static inventory of `development` @ `2426c17`, with the stack's numbers alongside where they differ. Counts were
taken with `git show` / `git ls-tree` / `git grep`, not from the working tree. **ASSUMED** unless marked otherwise.

## Verdict

**Late beta: a built system with a long tail, not a half-finished one.**

The strongest evidence is what is *absent*. Across ~1 174 tracked PHP files: zero genuine `TODO`, `FIXME`, `HACK` or
`XXX` markers; zero `not implemented`; zero `@phpstan-ignore`; one line of commented-out code, and it is prose.
`AGENTS.md` rule 1 ("no stub that returns fake success in a production path") holds — no violation was found, and two
comments exist purely to *refuse* faking a value:

- `domains/Provisioning/HostPowerReader.php:120` — "a missing secret is a configuration error the doctor reports, not
  a reason to fake a reading"
- `domains/Provisioning/NodeSampler.php:29` — "a stale reading would fake a flat trend"

Every `throw new RuntimeException` (51 across app/domains/platform) is a fail-closed invariant or configuration
error, not an unfinished branch.

## Scale

| Thing | `development` | Stack |
| --- | --- | --- |
| Tracked PHP files | 1 174 (domains 505, app 100, providers 88, platform 64, tests 307) | — |
| HTTP endpoints | 518 (476 API + 42 web) | 543 routes / 493 under `/v1` |
| `Schedule::` entries | 85 | 88 |
| Migrations | 59 | **61** (VERIFIED in the working tree) |
| Test files / cases | 307 / 906 | 337 / ~1 280 |
| Pest suite | 856 @ `8e4614a` (baseline), 921 @ `3fa6b38` | **1 368 / 18 287 assertions — VERIFIED locally** |
| `onhost:doctor` checks | ~94 `add()` call sites | — |
| Runtime composer deps | 13 | — |

Commands and handlers are fully wired: **41 command classes, 41 entries in `DomainServiceProvider::HANDLERS`.**

## Per-domain judgement

23 modules, 505 files, ~61k LOC. The distribution is extremely lopsided.

| Domain | files | tests | Verdict |
| --- | --- | --- | --- |
| Services | 95 | 35 files / 143 cases | COMPLETE |
| Provisioning | 86 | 76 / 216 | COMPLETE |
| Domains | 39 | 9 / 30 | COMPLETE |
| Support | 25 | 9 / 32 | COMPLETE |
| Incidents | 23 | 6 / 14 | COMPLETE code, **thin tests** |
| Orders | 22 | 24 / 62 | COMPLETE |
| WalletLedger | 21 | tests in `Finance/` | COMPLETE, tests misfiled |
| Billing | 20 | 6 / 16 | COMPLETE code, **thin tests** (1 800 LOC / 16 cases) |
| Payments | 17 | tests in `Finance/` | COMPLETE, tests misfiled |
| Catalog | 16 | 4 / 15 | COMPLETE |
| Notifications | 15 | 8 / 12 | COMPLETE (no commands by design) |
| Dns | 14 | 6 / 30 | COMPLETE, 1 dead workflow |
| Invoicing | 13 | tests in `Finance/` | COMPLETE, tests misfiled |
| Organizations | 11 | 2 / 5 | **PARTIAL** — 5 cases for 1 000 LOC incl. membership |
| Identity | 30 | 6 / 23 | COMPLETE |
| Loyalty | 10 | 9 / 11 | COMPLETE |
| Partners | 10 | 4 / 5 | **PARTIAL** — 5 cases for 1 113 LOC |
| Compliance | 9 | 5 / 17 | COMPLETE |
| Integrations | 8 | 2 / 6 | **PARTIAL** — Discord only |
| Tax | 7 | 2 in `Finance/` | **PARTIAL** — see the VAT gap below |
| Marketplace | 6 | 6 / 7 | COMPLETE |
| Content | 6 | 1 / 2 | **SKELETAL** — and it bypasses the bus |
| Risk | 2 | via `Orders/` | PARTIAL by design (shared helpers) |

**Services + Provisioning are 45% of domain code and 50% of feature tests.** `ServiceService.php` alone is ~1 380
lines. Content, Integrations, Tax and Risk together are under 2 000 LOC.

## The functional gap worth fixing first

**Reverse-charge VAT cannot work.** Three facts compose into it:

1. `domains/Tax/ViesClient.php:19` — a complete VIES VAT-validation client with **zero callers** anywhere in code
   (the only reference is `phpstan-baseline.neon`).
2. `domains/Organizations/OrganizationService.php:256` — `vat_status` is only ever *reset* to `'unknown'`. The sole
   writer of any other value in the whole repository is `DevAccountSeeder.php:68`.
3. `domains/Tax/TaxEngine.php:83` — the "intra-EU B2B with validated VAT ID" branch is therefore **unreachable in
   production**.

It fails *safe* — line 86 taxes B2B-without-validated-VAT as B2C, so nobody is under-billed — but no EU B2B customer
can be zero-rated at all. Fix before selling cross-border B2B.

## Rule violations found in the code

| Rule | Violation |
| --- | --- |
| `AGENTS.md` #2 (writes go through the CommandBus) | `app/Http/Controllers/Api/V1/Staff/ContentController.php:56,69,80,91` — staff publishing of `Post`, `KnowledgeArticle`, `ChangelogEntry`, `StockItem` writes **raw Eloquent from the controller**: no command, no handler, no risk level. `CLAUDE.md` says publishing to customers is HIGH/CRITICAL. It *is* audited. |
| `AGENTS.md` #7 (`tests/Feature/<Domain>`) | `tests/Feature/{Invoicing,Payments,Tax,WalletLedger,Risk}` do not exist; coverage lives in `tests/Feature/Finance/` and `tests/Feature/Orders/`. Not missing coverage — missing convention. |
| `AGENTS.md` provider registration | The doc says adapters are registered in `PlatformServiceProvider::ADAPTERS`. That map has **10 keys**; the other 10 adapters are wired through `PaymentProviderRegistry`, `AiProviderRegistry`, `OnCallService:271`, `NodeOrders:27`, `HostPowerReader:31`, or a container binding. `CdnService` injects the concrete `CloudflareCdnProvider`, not the `CdnProvider` interface. All are wired; **the doc is wrong**. |

## Dead and unwired code

Only 4 of 484 non-model classes have no code caller — a remarkably low rate.

1. `domains/Dns/Workflows/ApplyDnsChangesWorkflow.php` — referenced nowhere at all.
2. `domains/Provisioning/Workflows/DeployAppWorkflow.php` — a 137-line Kubernetes git-push → BuildKit → rolling
   deploy saga with no caller.
3. `domains/Tax/ViesClient.php` + `domains/Tax/Models/VatValidation.php` — see the VAT gap.
4. `providers/Contracts/EInvoiceProvider.php` — contract for SK eFaktúra 2027 / Peppol; no implementation, no caller.

**Root cause for 1 and 2: workflows have no registry.** All 22 `implements Workflow` classes are resolved by
hard-coded class reference in `ServiceService.php:191,622-628` and `ServiceMigrationService.php:31`. Write a workflow,
forget that line, and it is dead code silently. **Adding a registry is a cheap, high-value refactor.**

## Provider adapter coverage

`providers/Contracts/` holds 44 interfaces/DTOs; `tests/Contract/` has 18 files / 85 cases.

**Adapters with a real contract test:** IspConfig (4 tests), AaPanel (2), Proxmox, Pterodactyl (3), Wedos (2),
Subreg, Pbs, PowerDns, GoPay + Stripe (shared `GatewayRecurringContractTest`).

**Adapters with no `tests/Contract` file:**

| Adapter | Note |
| --- | --- |
| **Hetzner** (`NodeOrderProvider`) | **Buys real servers for real money. No test at all** — the only file naming it is `VendorNeutralityTest`. |
| **Payments/Bank** | No test anywhere; settlement depends entirely on `BankStatementImporter` + the `billing.*` schedule. |
| Payments/Comgate | The **default gateway**; only faked inside `Feature/Orders/CheckoutTest`. |
| Kubernetes, Cloudflare | None. |
| Ai (Anthropic, OpenAiCompatible) | None at adapter level; Support tests fake higher up. |
| Redfish, OnCall (×3), IpGeo, Shell | Exercised indirectly via Feature tests with `Http::fake()`. |

## Feature gating — the real system is not config

`config/onhost.php` (649 lines) has only **6** env-driven booleans defaulting to false: `demo_mode`, `ui.demo`,
`identity.oidc.enabled`, `ai.enabled`, `queue.autoscale.enabled`, `provisioning.drift.auto_repair`.

The actual flag system is **`domains/Provisioning/AutomationLedger::RULES`** — 30 operator-switchable scheduled
automations on `development` (38 on the stack), gated at runtime by `AutomationLedger::enabled($key)` and toggled via
`automation.toggle` with a step-up. Only `capacity.auto_order` is `default_off` on `development`; the stack raises
that to **7** (`backups.compute`, `backups.as_sold`, `mail.backup_retention`, `capacity.auto_order`, `usage.rotation`,
`services.reinstate`, `billing.withdrawal`) — i.e. the newest features ship dark, exactly as rule 1 prescribes.

**Two config traps for go-live:**

- `COMGATE_TEST=true` and `WEDOS_TEST_MODE=true` are sandbox-by-default. Fail-safe, but easy to forget.
- `config/onhost.php:593` — `clamav.enforce=true` while `host` defaults to `''`. Upload virus scanning is
  **effectively off until an operator sets a host**, while the config reads as enforcing.

Fail-closed defaults that are correct: `ONHOST_STAFF_MFA_REQUIRED`, `ONHOST_FOUR_EYES`, `ONHOST_CAPACITY_GATE`,
`ONHOST_ORDER_RISK`, `ONHOST_TURNSTILE_ENFORCE_*` all default to `true`.

## Known partial features (documented, not defects)

`domains/Provisioning/ServiceMigrationService.php:71` — web/mail/managed services have **no migration saga**; only
`game` and `cloud` are automated. `ProviderInstanceService.php:278` — node discovery exists for
Proxmox/ISPConfig/aaPanel/game panels only. `RegistrarConnectionService.php:64` — bring-your-own-registrar is
WEDOS-only although Subreg implements the same contract. `AaPanelTools.php:697` — no temporary logins (ISPConfig
has them). `IspConfigTools.php:292` — no per-cron-job log. `ServiceService.php:1354,1377` —
`console_unsupported` / `usage_unsupported` per family.

## Static analysis is weaker than it looks

Larastan **level 5** (not max), `reportUnmatchedIgnoredErrors: false`, and **641 baseline entries suppressing 1 013
occurrences** of typing debt. The rule is "never add entries; remove when touched" — worth enforcing in review,
since the baseline file is not protected by anything (see `security-posture.md`).
