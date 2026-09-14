# ADR-0001 — Laravel control plane as the single source of truth

**Status:** accepted (2026-09) · **Blueprint:** §5, §17, §20

## Context

ONhost orchestrates five vendor systems (Proxmox VE/PBS, ISPConfig, aaPanel, Pterodactyl, PowerDNS) and one
registrar (WEDOS WAPI). Each keeps its own state and identifiers. Customers, staff and partners need one
consistent view, one audit trail and one billing truth.

## Decision

One Laravel 13 monolith (`onhost-platform`) is the control plane. Modules live in `domains/*`
(business), `providers/*` (adapters) and `platform/*` (cross-cutting). Provider systems are executors:
they never own business identity, pricing, entitlements or customer state. Every mutation goes through the
CommandBus pipeline (authorization → step-up/approval → idempotency → transaction → hash-chained audit →
outbox). Provider identifiers are stored only in `provider_bindings`; public identifiers are prefixed ULIDs.

## Consequences

* Drift between the control plane and providers is detected by the reconciler and classified
  (ONHOST_MANAGED / PROVIDER_MANAGED / SHARED) instead of being silently overwritten.
* Adding an executor means implementing the capability contracts in `Onhost\Providers\Contracts` plus contract
  tests; no domain code changes.
* The monolith can later be split along `domains/*` boundaries because modules communicate through commands,
  events and services, never through each other's tables.
