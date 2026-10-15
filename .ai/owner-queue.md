# Owner queue (Adrian)

Steps only the owner can do outside the repository. Agents cannot perform them; they only list them in
`/ai-status`. Decisions themselves are not copied here: each row links to its source.
Source: `navrhy/ROZHODNUTI-za-vlastnika-2026-10-15.md`, `navrhy/ROZHODNUTI-vlna2-2026-10-15.md` (N3).

Status values: `OPEN`, `IN_PROGRESS`, `DONE`. Set the date when the status changes (YYYY-MM-DD).

| # | Step | What the owner does | Status | Date | Link |
|---|------|---------------------|--------|------|------|
| 1 | B6 | Switch the GitHub repository to private (most urgent: public runbooks describe open holes). | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika B6 |
| 2 | C1 | Sign contract and DPA with the selected Peppol AP (ecosio, fallback eConnect) and onboard; deadline 2027-01-01; see C1 critical path. | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika C1 |
| 3 | PR-10 | Create the off-site S3 backup bucket with a lifecycle rule (blocks A2 and production doctor A4). | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika A2 |
| 4 | A3 | Set up Healthchecks.io, store the URL in `url_file` on the monitoring server, receive on-call alerts. | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika A3 |
| 5 | B5 | Enable `security` as a required check in branch protection. | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika B5 |
| 6 | D1 | Accept the DPA with processors (Anthropic for the AI assistant). | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika D1 |
| 7 | C3 | Obtain the accountant's signature of D31.9 (VIES stays off until then). | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika C3 |
| 8 | Cutover | DNS and TLS for status.onhost.cz, publish the status snapshot (N14). | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 KROK_VLASTNIKA |
| 9 | SK | One read of the machine-prepared SK texts (hreflang sk N15, C2, landing N16, consent E2 in SK). Owner Adrian. Due before pilot C1 2e (2026-12-10). | OPEN | 2026-10-15 | ROZHODNUTI-za-vlastnika C2/E1, docs/development/sk-locale-inventory.md |

## C1 critical path (e-invoice, deadline 2027-01-01)

Backward schedule for row 2. All dates are estimates (ASSUMED), not commitments. If 2a slips, the buffer 2f
shrinks; the deadline 2027-01-01 does not move. 2e starts only after 2c (signed DPA).

| # | Milestone | Owner | Due | Status | Date | Link |
|---|-----------|-------|-----|--------|------|------|
| 2a | Select the AP against the researcher's criteria (ecosio primary, eConnect fallback). | AI (onhost-researcher) + arbiter | 2026-10-29 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
| 2b | Obtain access to the AP sandbox. | Adrian | 2026-11-12 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
| 2c | Sign the contract and DPA with the AP. | Adrian | 2026-11-19 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
| 2d | Adapter in `providers/*` with a contract test using `Http::fake()`; new event in `docs/architecture/events-catalog.md` and `NotificationRouter`. | AI (onhost-integration) | 2026-11-26 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
| 2e | Pilot document on the AP sandbox (only after 2c, DPA signed). | AI + Adrian | 2026-12-10 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
| 2f | Buffer of 3 weeks before the deadline. | Adrian | 2027-01-01 | OPEN | 2026-10-15 | ROZHODNUTI-vlna2 C1, ZKOUMAL-vlna3 W3-1 |
