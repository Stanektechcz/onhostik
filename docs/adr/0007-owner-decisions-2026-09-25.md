# ADR-0007 — Owner decisions of 2026-09-25: what is sold, measured, approved and postponed

**Status:** accepted (2026-09-25) · **Decided by:** owner · **Recorded by:** TASK-0026 · **Source:** the owner's answers
to the 25 open questions of 2026-09-25 (vault `Hosting/OWNER-DECISIONS-2026-09-25`)

## Context

On 2026-09-24 the owner set three standing rules, and every decision below is applied within them:

1. **Historical sites are untouchable.** Sites, mail domains and databases on the live ISPConfig and aaPanel that ONhost
   did not create are never changed, bound, suspended or deleted by the platform. On the same panels the platform may
   create, edit and manage its own sites. The guards: `providers/AaPanel/AaPanelWebProvider.php:96-99`,
   `providers/IspConfig/IspConfigWebProvider.php:188-197` and `:585-590` refuse, with `CONFLICT` and before any write,
   a resource the panel does not prove is ours.
2. **The client account survives its services.** Ending every service never closes the ONhost account. The only
   erasure is the owner's own GDPR request (`organization.close`, step-up, a 14-day grace:
   `domains/Compliance/Commands/DataRequestCommand.php`).
3. **Measure everything.** Every limit is measured or enforced, a limit ONhost raises by hand is billed, and where no
   limit exists ONhost still knows the consumption. The phased plan is in the vault (`Hosting/METERING-PLAN-2026-09-24`).
   Phase 1 (plans promise only what is measured or enforced: `domains/Catalog/PlanPromises.php`,
   `domains/Services/Metering/MetricRegistry.php`) is TASK-0017.

Implementation rules that follow from rule 1 and from "never change existing customers en masse":

- A price list changes only as a **new plan version**. Existing subscriptions keep their version, and a plan version
  that is already published is never edited.
- New behaviour that reaches existing services ships behind a `default_off` rule in
  `domains/Provisioning/AutomationLedger.php`, or behind an operator command whose default is `--dry-run`.
- New placement and capacity rules apply to new orders and placements only.

On 2026-09-25 the owner answered the 25 questions that blocked the work after TASK-0017 and TASK-0019. The answers
follow the recommendations, with one refinement for decision 22 (see below).

## Decisions

"Pending" means the implementing task was opened on 2026-09-25 and has not been merged yet. A row describes
behaviour as built only where it cites code.

| # | Decision | Existing customers | Implemented by | State on 2026-09-25 |
| --- | --- | --- | --- | --- |
| 1 | Automatic backups of managed databases and of VPS with a backup add-on are switched on, but only after `onhost:backups:compute-plan` has listed the affected services and the Proxmox instance has `backup_storage` set (PBS has room). | Nothing changes until an operator switches on the rule `backups.compute` (`default_off`, `domains/Provisioning/AutomationLedger.php:48`). | TASK-0019 (rule, read-only list); TASK-0024 (doctor rows) | Built in TASK-0019, not merged. Switching it on is an operator action after deploy (`docs/runbooks/backups.md`). |
| 2 | Point-in-time recovery (`pitr_days` 7/14 on db-s/db-m) leaves the price list in a new plan version until WAL archiving exists. | Existing contracts keep their plan version. | TASK-0022 | Pending. `pitr_days` is a known gap (`domains/Catalog/PlanPromises.php`, `KNOWN_GAPS`). |
| 3 | Mail backups: the mailbox backup retention in ISPConfig is set from the plan's `backup_days`, verified on staging first. | Behind a switch or an operator command with `--dry-run`, never applied to every mailbox at deploy. | TASK-0024 | Pending. `backup_days` on mail plans is a known gap (`KNOWN_GAPS`). |
| 4 | `dedicated_outbound_ip` (Mail Enterprise) and `dedicated_db` (managed-woo, shop-peak) stay listed as gaps and leave the price list in the next plan version, until they are built. | Existing contracts keep their plan version. | TASK-0022 | Pending. Both are in `KNOWN_GAPS`. |
| 5 | `products` (e-shop) becomes fair use: "doporučeno do N produktů". | Wording only; nothing is enforced on existing stores. | TASK-0022 | Pending. `products` is in `KNOWN_GAPS`. |
| 6 | `connections` (managed database) leaves the price list until the DB image sets `max_connections`. | New plan version; existing contracts keep theirs. | TASK-0022 | Pending. |
| 7 | `php_workers` on aaPanel is described as "sdílené PHP workery"; managed plans are placed on ISPConfig only. | Wording in a new plan version; the placement rule applies to new placements only. | TASK-0022 (wording), TASK-0023 (placement) | Pending. `php_workers` and `php_workers_dedicated` are in `KNOWN_GAPS`. |
| 8 | A manual limit raise costs the product option's price; a zero price needs four eyes. | No manual raise exists today, so nothing changes for anybody. | TASK-0022 | Pending. |
| 9 | Overage of a soft limit only notifies: no billing, no throttling. | Nothing is billed or throttled. | TASK-0023 | Pending. |
| 10 | The web disk counts files + databases + mail. It is shown now and enforced only after a dated notice to customers. | Display only until the notice; enforcement then sits behind a switch. | TASK-0023 | Pending. |
| 11 | Cron on Start keeps its value and is renamed "N naplánovaných úloh". | Wording only. | TASK-0022 | Pending. |
| 12 | Usage samples are kept raw for 45 days, as daily rollups for 400 days and as monthly rollups for ever. | New storage; nothing existing is deleted. | TASK-0023 | Pending (migration `0001_01_01_000860_*`). |
| 13 | HIGH = a fresh step-up. Four eyes only for CRITICAL and for price and plan changes in the admin configuration. | Staff rule only. With `ONHOST_FOUR_EYES=false` (single operator, `docs/runbooks/approvals.md`) the second person is waived and audited. | TASK-0022 | Step-up for HIGH and four eyes for CRITICAL are built (`domains/Identity/Authorization/IdentityCommandAuthorizer.php:68-69`). Price and plan changes run under `catalog.manage`, which is HIGH (`domains/Identity/Authorization/PermissionCatalog.php:151`), so four eyes for them is pending. TASK-0022 also corrects `docs/product/ADMIN_CONFIGURATION_ARCHITECTURE.md:122`, which asked for a second person on HIGH. |
| 14 | A password change revokes every personal API token except the session that changes the password; service and integration tokens stay. | Takes effect at a customer's next password change; no token is revoked at deploy. | TASK-0021 | Pending. |
| 15 | `panel.password` is for the service owner only, with step-up, and never part of a shared role. | Shared users lose the action. That is the point of the decision. | TASK-0021 | The action is HIGH already (`domains/Services/Commands/ServiceActionCommand.php:56-58`); the owner-only rule is pending. |
| 16 | A read-only check of `provider_calls` over the last 90 days, on staging or a production copy, with the results in a report. | Read-only. | TASK-0020 | Pending. |
| 17 | 14-day consumer withdrawal: "cancel within 14 days" with a prorated refund to credit, for consumers only (not businesses). The terms go to legal review before release. | Consumer orders from the release on. | TASK-0025 | Pending, and gated on legal review. |
| 18 | Backup frequency is what the price list sells: Start daily, Managed 6h/1h, Shop down to 15 minutes. No silent platform default. | Correcting the frequency raises existing backup load, so it goes through an operator command with `--dry-run`. | TASK-0024 | Gap: `managed-woo` and `shop-growth` sell `1h` (`database/seeders/CatalogSeeder.php:38`, `:44`), which `BackupScheduler::FREQUENCIES` (`domains/Services/Web/BackupScheduler.php:37`) does not know, so they run daily (`:265`). |
| 19 | `capacity_basis: sold` for disk; CPU and RAM are measured. | New placements only. | TASK-0023 | Pending. |
| 20 | Orders paid from credit: the owner and the billing role directly, everybody else only with the owner's approval (permission `billing.wallet.spend`). | The role change reaches every organization at deploy; state it in the release notes. | TASK-0021 | Pending. |
| 21 | Student and freelance benefits (vault H30, H428, H429) are postponed until after launch. | Nothing is sold or promised. | TASK-0022 (hides the public page `sol-edu`, which still advertises them, through a surface seam) | Postponed. No such code exists in `domains/`. |
| 22 | Taking over a historical site (vault H304) happens only at the explicit request of the site's owner, after an ownership check, as a manual operator step, never automatically. It is done as a customer-run import into a NEW site the platform creates, with an operator's help. The historical resource is never bound to a service and never modified. | The historical site stays as it is. The rule above protects it. | TASK-0026 (`docs/runbooks/historical-site-import.md`); the guards are code (rule 1) | The procedure uses existing tools only (`import.run`). No staff path runs an import for a customer. |
| 23 | Pay-and-restore after a subscription ends: yes, within the grace period before purge. | Only for services still inside their grace; nothing after purge. | TASK-0025 | Pending. |
| 24 | OV certificates and the anti-DDoS add-on are postponed. | Nothing on sale changes. | none needed (recorded by TASK-0026) | Already off sale: `ssl` and `anti-ddos-pro` are `draft` (`database/seeders/CatalogSeeder.php:156-159`, `:165-167`), and `Addons::handled()` excludes them (`domains/Services/Addons.php:38-41`). The seeder is not proof of the production database; staging and production are checked with `onhost:doctor`. |
| 25 | The 16 P0 cards of the vault that the assessment compared with code move from `proposed` to `assessed`. | Vault only. | TASK-0026 (vault) | Done in the vault on 2026-09-25 (H01, H02, H03, H06, H07, H08, H09, H11, H12, H13, H14, H16, H17, H18, H23, H29). |

## Consequences

- The price-list decisions (2, 4, 5, 6, 7, 11) reach customers only through new plan versions. Anyone who bought an
  older version keeps it.
- Decisions that touch existing services (1, 3, 10, 18) do nothing until an operator acts: a `default_off` rule, a
  dated notice or a command whose default is `--dry-run`.
- Four eyes on prices (13) blocks a solo operator unless `ONHOST_FOUR_EYES=false` is set on the server before that
  change is deployed.
- A historical site enters ONhost only as a copy in a new site (22). The platform keeps no path that finds a panel
  resource by name and starts managing it.

## Not decided here

- The TASK-0019 note "Every service, every tick — OWNER DECISION before merge" (`docs/runbooks/backups.md:354`) is
  not one of the 25 questions and stays open.
- The per-operation NORMAL downgrades listed in the readiness audit (§7 item 6) stay open. Decision 13 sets the rule,
  not the exemptions.
- A staff path that runs `import.run` for a customer. Today the customer runs it, or shares the new service with the
  helper (`svc_manage` covers it). Building a staff path needs its own task and a security review.
- Import of mailboxes from a historical mail domain: no tool exists.

## Implementation state after the stack (2026-09-25, TASK-0027)

The decision table above records the state of the day the ADR was written and is kept as it was. Every row that said
"Pending" is now built on the stack branch `fix/TASK-0027-stack-coherence-and-the-docs-that-descri` (TASK-0017 …
TASK-0027, one pull request into `development`, not merged yet). Audit rows are in
`docs/runbooks/production-readiness-audit.md` §7.

| # | Built by | How it reaches existing customers | Audit row |
| --- | --- | --- | --- |
| 1 | TASK-0019, TASK-0024 | rule `backups.compute` (default off) after `onhost:backups:compute-plan` | 105, 113 |
| 2, 4, 5, 6, 11 | TASK-0022 | revision `2026-09-honest-promises` through `onhost:catalog:revise --apply`; held versions untouched | 108 |
| 3 | TASK-0024 | rule `mail.backup_retention` (default off), `onhost:mail:backup-retention` (dry run) | 114 |
| 7 | TASK-0023 (placement), TASK-0027 C4 (wording) | new placements only; `eshop/shop-peak` loses `php_workers_dedicated` in revision `2026-09-shared-php-workers` | 110, 118 |
| 8 | TASK-0022 | product `limit-raise` (revision `2026-09-limit-raise`); customer orders behind `ONHOST_LIMIT_RAISE_CUSTOMER_ORDERS` | 109 |
| 9, 12 | TASK-0023 | new storage; rule `usage.rotation` (default off); new metrics behind `ONHOST_METERING_ENFORCE_NEW_METRICS` | 111 |
| 10 | TASK-0023 | shown at once; counted only from `ONHOST_WEB_DISK_TOTAL_ENFORCE_FROM` for noticed services | 112 |
| 13 | TASK-0022 | staff only; `ONHOST_FOUR_EYES=false` for a solo owner **before** deploy | 6 |
| 14, 15 | TASK-0021 | at a customer's next password change (switch default on); the role change with `AuthorizationSeeder` | 28, 106 |
| 16 | TASK-0020 | read-only `onhost:audit:provider-calls` | 92 |
| 17 | TASK-0025 | rule `billing.withdrawal` (default off) after the legal review | 16, 116 |
| 18 | TASK-0024 | rule `backups.as_sold` (default off) after `onhost:backups:frequency-plan` | 113 |
| 19 | TASK-0023 | `ONHOST_CAPACITY_DISK_BASIS=sold` after `onhost:capacity:basis`; new placements only | 110 |
| 20 | TASK-0021, TASK-0027 C1 | `billing.wallet.spend` with the deploy; approvals behind `ONHOST_ORDER_CREDIT_APPROVAL` (default off); one credit gate for pay-and-restore | 107, 118 |
| 23 | TASK-0025, TASK-0027 C1/C2 | rule `services.reinstate` (default off) after `onhost:billing:reinstatement-audit`; a restore never switches auto-renew on | 115, 118 |
| 21, 22, 24, 25 | TASK-0022 (21: `sol-edu` withdrawn), TASK-0026 | as recorded above | 117 |

Still not decided: the every-service backup tick (above), the per-operation exemptions of decision 13, a staff path for
`import.run`, mailbox import from a historical mail domain.
