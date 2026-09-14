# Domain map

| Area | Modules | Start with |
| --- | --- | --- |
| Accounts and tenancy | `Identity`, `Organizations`, `Risk` | ADR 0001, auth/organization feature tests |
| Catalog and sales | `Catalog`, `Orders`, `Marketplace`, `Partners`, `Loyalty` | pricing runbook, order tests |
| Money | `Billing`, `Invoicing`, `Payments`, `Tax`, `WalletLedger` | ADR 0003, `billing-dunning.md`, `pricing.md` |
| Services | `Services`, `Provisioning`, `Domains`, `Dns` | ADR 0004, provisioning and registrar runbooks |
| Customer operations | `Support`, `Incidents`, `Notifications`, `Compliance` | ADR 0006, incident/compliance runbooks |
| Content and integration | `Content`, `Integrations` | content seed data, provider contracts |

Cross-cutting orchestration lives in `platform`; vendor boundaries live in `providers`; transport and staff APIs live in `app/Http`. Search for the command handler and closest feature test before following references across the repository.
