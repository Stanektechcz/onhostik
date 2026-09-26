# Decisions

| Record | Topic |
| --- | --- |
| `docs/adr/0001-control-plane-monolith.md` | Laravel modular monolith as control plane |
| `docs/adr/0002-outbox-idempotency-sagas.md` | outbox, idempotency, sagas |
| `docs/adr/0003-money-ledger-tax.md` | money, ledger, tax |
| `docs/adr/0004-registrar-and-dns.md` | registrars and DNS |
| `docs/adr/0005-surfaces-preserved-with-data-seams.md` | prototype surfaces stay byte-identical; seams |
| `docs/adr/0006-sla-engine-and-incidents.md` | SLA engine and incidents |
| `docs/adr/0007-owner-decisions-2026-09-25.md` | owner decisions of 2026-09-25: backups, price list, metering, four eyes, identity, provider-call audit, withdrawal, capacity, credit orders, postponed products, historical sites, vault card states |
| `docs/adr/0008-audit-high-fixes-2026-09-25.md` | the three HIGH findings of the onboarding audit, fixed (TASK-0029 … TASK-0031): an exhaustive service-action permission map, an explicit API-token scope map with `services:console` and step-up for staff writes outside the bus, VIES-checked reverse charge and partner self-billing VAT (VIES default off; accountant sign-off is a go-live item) |
| `.ai/decisions/AI-0001-orchestration-layer.md` | how the AI team coordinates (state split, worktrees, agents, gate) |

Product and architecture decisions go to `docs/adr/` (next number 0009). Decisions about the AI development process go
to `.ai/decisions/AI-NNNN-*.md`. No record for trivial implementation choices.
