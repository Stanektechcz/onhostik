# Dependency map (measured)

Counts of `use Onhost\Domain\<Module>\…` imports, measured 2026-09-23 at `8e4614a` (self-imports excluded).
Regenerate with a read-only scan when module boundaries change; the Brain vault's `Update-CodeMap.ps1` gives a similar view.

## Outbound (module → what it imports)

| Module | Imports (count) |
| --- | --- |
| Provisioning | Services 109, Catalog 10, Dns 8, Identity 6, Organizations 5, Domains 2, Orders 2, WalletLedger 1, Billing 1 |
| Services | Provisioning 55, Identity 20, Organizations 20, Dns 9, Catalog 8, Billing 7, Orders 7, Domains 2, Notifications 1, Invoicing 1, Tax 1, WalletLedger 1 |
| Domains | Provisioning 46, Dns 14, Services 6, Organizations 5, Catalog 5, Identity 4, Orders 4, Invoicing 2, WalletLedger 2, Billing 1, Tax 1 |
| Dns | Provisioning 7, Services 7, Domains 1 |
| Orders | Identity 9, Invoicing 7, Services 7, Catalog 6, WalletLedger 5, Provisioning 5, Organizations 4, Payments 4, Loyalty 2, Risk 2, Domains 1, Billing 1, Tax 1 |
| Billing | Services 15, Invoicing 8, Identity 6, Catalog 6, Incidents 4, Organizations 3, Orders 2, Tax 2, WalletLedger 2, Provisioning 1, Notifications 1, Domains 1 |
| Invoicing | Orders 4, Payments 4, WalletLedger 4, Tax 3, Identity 2, Organizations 2, Billing 2, Provisioning 1 |
| Payments | WalletLedger 3, Organizations 2, Identity 2, Invoicing 1 |
| WalletLedger | Organizations 5, Payments 5, Invoicing 2, Billing 1, Tax 1 |
| Tax | Organizations 1 |
| Catalog | Services 4, Identity 2, Domains 1, Billing 1 |
| Identity | Organizations 2 |
| Organizations | Identity 11, Services 3, Incidents 2, Notifications 2, WalletLedger 2, Billing 1 |
| Support | Services 15, Identity 9, Organizations 7, Invoicing 3, Billing 2, Dns 2, Domains 2, Orders 2, Payments 2, Provisioning 2, WalletLedger 2, Catalog 1, Tax 1 |
| Incidents | Services 5, Compliance 4, Identity 3, Organizations 2, Notifications 1, Provisioning 1, Billing 1, Invoicing 1, Tax 1, WalletLedger 1 |
| Notifications | Services 6, Billing 3, Organizations 3, Provisioning 3, Domains 2, Incidents 2, WalletLedger 2, Orders 2, Invoicing 1, Support 1, Identity 1, Integrations 1 |
| Compliance | Services 6, Identity 3, Organizations 3, Incidents 2, Support 2, Domains 1, Invoicing 1 |
| Integrations | Services 11, Identity 7, Organizations 3, Provisioning 1, Support 1 |
| Partners | Identity 3, Invoicing 3, Organizations 2, Billing 2, Services 2, Provisioning 1, Tax 1, WalletLedger 1 |
| Loyalty | Organizations 6, Identity 4, Invoicing 3, Services 3, Orders 2, WalletLedger 2, Notifications 1, Provisioning 1, Support 1, Billing 1, Risk 1 |
| Marketplace | Identity 6, Partners 3, Organizations 2, Billing 2, Invoicing 2, Services 1, Tax 1, WalletLedger 1 |
| Content | Notifications 1, Organizations 1, Partners 1, Support 1 |
| Risk | — |
| `platform/` | Compliance 1 (exception) |
| `providers/` | Provisioning 15, Payments 2 (exceptions) |
| `app/` | every module; most: Services 99, Provisioning 94, Organizations 55, Identity 52, Orders 37 |

## Inbound (how many modules depend on it) — blast radius

Organizations 20 · Identity 18 · Services 16 · WalletLedger 15 · Billing 15 · Invoicing 14 · Provisioning 14 · Tax 11 ·
Domains 10 · Orders 9 · Catalog 7 · Notifications 7 · Support 6 · Payments 6 · Incidents 5 · Dns 5 · Compliance 3 ·
Partners 3 · Risk 3 · Integrations 2 · Loyalty 2 · Marketplace 1 · Content 1

## What this means for planning

- A change to **Identity, Organizations, Services, WalletLedger, Billing, Invoicing** or **Tax** has a wide blast radius:
  run the full gate, not only the module's tests, and get an architecture review for contract changes.
- **Services ⇄ Provisioning** (109 / 55) and **Domains → Provisioning** (46) are tightly coupled: never split one change
  across parallel tasks in these modules; serialize or give one task ownership of both.
- **Tax, Identity, Payments** are near-leaves (few outbound imports): good candidates for isolated parallel work.
