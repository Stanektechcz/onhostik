# 09 Ruční testy podle rolí

> Tento soubor je **generovaný z kódu** příkazem `php artisan onhost:docs:roles` (zdroje: `RoleCatalog`, `StaffNavigation`, `PanelNavigation`, `SurfaceDataController::NAV_REQUIRES`). Ručně se needituje; CI hlídá shodu příkazem `php artisan onhost:docs:roles --check`.

Automatický protějšek bez prohlížeče (sdílení služby a role): `tests/Feature/E2E/SharingFlowTest.php`.

## Jak testovat

1. Přihlaste se jako uživatel dané role. Demo účty a jejich hesla si nastavuje vlastník platformy sám, tady žádná hesla nejsou.
2. Porovnejte postranní menu s očekávaným seznamem u role.
3. Provedte povolené akce, každá musí uspět (u rizika HIGH po čerstvém ověření step-up, u CRITICAL se čtyřma očima).
4. Provedte odmítnuté akce, každá musí skončit uvedeným stavem. Člen organizace bez oprávnění dostane **403**, cizí uživatel mimo organizaci **404**.
5. Testujte jen na testovacím nebo lokálním prostředí, nikdy zápisem proti živým panelům.

## Obsah

- [Role zaměstnanců (staff)](#role-zaměstnanců-staff)
- [Role zákazníka v organizaci](#role-zákazníka-v-organizaci)
- [Přístup ke sdílené službě (svc_*)](#přístup-ke-sdílené-službě-svc_)

## Role zaměstnanců (staff)

Postranní menu konzole vzniká z `StaffNavigation`: položka se zobrazí, když role drží její oprávnění v globálním rozsahu. Položka „Schvalování“ je vidět všem zaměstnancům.

| Role (klíč) | Název | Počet oprávnění | Položek menu |
|---|---|---|---|
| `platform_owner` | PlatformOwner / SuperAdmin | 114 | 46 |
| `iam_admin` | IAMAdmin | 9 | 4 |
| `infrastructure_admin` | InfrastructureAdmin | 15 | 18 |
| `network_admin` | NetworkAdmin | 6 | 17 |
| `domain_dns_admin` | DomainDNSAdmin | 7 | 10 |
| `shared_hosting_admin` | SharedHostingAdmin | 8 | 17 |
| `managed_hosting_admin` | ManagedHostingAdmin | 8 | 17 |
| `apps_platform_admin` | AppsPlatformAdmin | 8 | 18 |
| `cloud_vps_admin` | CloudVPSAdmin | 9 | 18 |
| `game_admin` | GameAdmin | 8 | 18 |
| `mail_admin` | MailAdmin | 6 | 17 |
| `database_admin` | DatabaseAdmin | 6 | 10 |
| `ai_platform_admin` | AIPlatformAdmin | 5 | 4 |
| `backup_dr_admin` | BackupDRAdmin | 9 | 16 |
| `sre` | SRE | 12 | 24 |
| `incident_commander` | IncidentCommander | 8 | 14 |
| `security_soc` | SecuritySOC | 7 | 5 |
| `abuse_trust_safety` | AbuseTrustSafety | 4 | 5 |
| `compliance_legal` | ComplianceLegal | 6 | 8 |
| `billing_finance_admin` | BillingFinanceAdmin | 19 | 18 |
| `billing_operator` | BillingOperator | 8 | 8 |
| `support_manager` | SupportManager | 14 | 20 |
| `support_l1` | SupportL1 | 6 | 11 |
| `support_l2` | SupportL2 | 13 | 14 |
| `support_l3` | SupportL3 | 15 | 24 |
| `sales` | Sales | 3 | 7 |
| `marketing_content` | MarketingContent | 2 | 3 |
| `product_manager` | ProductManager | 6 | 11 |
| `auditor_read_only` | AuditorReadOnly | 6 | 11 |

### `platform_owner` PlatformOwner / SuperAdmin

Break-glass only; never a daily account.

**Očekávané menu konzole** (položek: 46):

- **Přehled**: Přehled
- **Podpora**: Fronta tiketů, Detail tiketu
- **Zákazníci**: Zákazníci, Poptávky
- **Provoz**: Incidenty, Kalendář odstávek, Pohotovost (on-call), Sondy a komponenty, SLO a dostupnost
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Obnovy a expirace, Kredity a platby, Věrnost a kampaně, Marketplace
- **Finance**: Bankovní platby, Upomínky a pohledávky, Odstoupení od smlouvy, SLA kredity, Partneři, Výplaty partnerům, Reporty a výhled
- **Produkt**: Ceník, slevy a doplňky, Tarify a verze, Navigace klientského panelu, Životní cyklus služeb, Šablony zpráv a odchozí pošta, Obsah webu
- **Bezpečnost a compliance**: Bezpečnostní incidenty a lhůty, Zneužití (DSA), Žádosti o data
- **Identita a schvalování**: Schvalování, Reset MFA

**Oprávnění** (114): `abuse.case.manage`, `ai.ops.read`, `ai.policy.manage`, `api_token.manage`, `apps.deploy`, `audit.read`, `audit.read.global`, `backup.delete`, `backup.download`, `backup.policy.manage`, `backup.read`, `backup.restore`, `billing.budget.manage`, `billing.credit.adjust`, `billing.credit.adjust_mass`, `billing.credit_line.manage`, `billing.dunning.manage`, `billing.invoice.manage`, `billing.invoice.read`, `billing.limit_raise.waive`, `billing.payment_method.manage`, `billing.reconcile`, `billing.refund.execute`, `billing.refund.execute_large`, `billing.tax_rule.manage`, `billing.wallet.read`, `billing.wallet.spend`, `billing.wallet.topup`, `capacity.manage`, `capacity.read`, `catalog.manage`, `catalog.order.create`, `compliance.case.manage`, `compliance.legal_hold.manage`, `compute.vm.delete`, `compute.vm.manage`, `content.manage`, `data_export.request`, `database.manage`, `dns.dnssec.manage`, `dns.global.write`, `dns.zone.read`, `dns.zone.write`, `domain.critical.manage`, `domain.manage`, `domain.read`, `domain.registrant.change`, `domain.registrar.manage`, `domain.transfer_out.execute`, `feature_flag.manage`, `game.manage`, `iam.access_review.manage`, `iam.approval.decide`, `iam.break_glass`, `iam.jit.approve`, `iam.jit.request`, `iam.mfa.reset`, `iam.role.manage`, `iam.user.manage`, `incident.manage`, `incident.publish`, `ipam.manage`, `mail.manage`, `maintenance.manage`, `node.manage`, `notification.mass.send`, `notification.template.manage`, `organization.close`, `organization.manage`, `organization.members.manage`, `organization.read`, `partner.manage`, `partner.portal.read`, `project.manage`, `provider.instance.manage`, `provider.instance.read`, `provider.secret.view`, `provisioning.drift.resolve`, `provisioning.freeze`, `provisioning.operation.cancel`, `provisioning.operation.read`, `provisioning.operation.retry`, `report.read`, `secret.rotate`, `security.event.read`, `security.incident.manage`, `security.settings.manage`, `service.console`, `service.credentials.rotate`, `service.data.delete`, `service.delete`, `service.manage`, `service.operate`, `service.read`, `sla.credit.manage`, `staff.backup.read`, `staff.billing.read`, `staff.chargeback.decide`, `staff.console`, `staff.customer.manage`, `staff.customer.read`, `staff.inbox.read`, `staff.order.manage`, `staff.service.delete`, `staff.service.manage`, `staff.support.ticket.read`, `support.chat.use`, `support.customer_impersonate`, `support.kb.manage`, `support.queue.manage`, `support.ticket.assign`, `support.ticket.manage`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, `staff.support.ticket.read`): úspěch.
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, `support.ticket.manage`): úspěch.
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, `support.ticket.assign`): úspěch.
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, `support.queue.manage`): úspěch.
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Nastavit heslo účtu herního panelu zákazníka (jen vlastník organizace) (`POST /v1/services/{service}/actions (akce panel.password)`, chybí `service.panel_account.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `iam_admin` IAMAdmin

Users, roles, SSO, JIT approvals; no refunds.

**Očekávané menu konzole** (položek: 4):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Identita a schvalování**: Schvalování, Reset MFA

**Oprávnění** (9): `audit.read.global`, `iam.access_review.manage`, `iam.approval.decide`, `iam.jit.approve`, `iam.mfa.reset`, `iam.role.manage`, `iam.user.manage`, `security.event.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Rozhodnout čtyři oči (schválit žádost) (`POST /v1/staff/approvals/{approval}/decision`, `iam.approval.decide`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Resetovat MFA jiného uživatele (`POST /v1/staff/users/{user}/mfa-reset`, `iam.mfa.reset`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `infrastructure_admin` InfrastructureAdmin

Proxmox, resources, capacity, operations queue.

**Očekávané menu konzole** (položek: 18):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (15): `backup.policy.manage`, `capacity.manage`, `capacity.read`, `iam.jit.request`, `node.manage`, `provider.instance.manage`, `provider.instance.read`, `provisioning.drift.resolve`, `provisioning.freeze`, `provisioning.operation.cancel`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `network_admin` NetworkAdmin

IPAM/BGP/VLAN/firewall/rDNS.

**Očekávané menu konzole** (položek: 17):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `capacity.read`, `iam.jit.request`, `ipam.manage`, `provider.instance.read`, `provisioning.operation.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `domain_dns_admin` DomainDNSAdmin

WAPI, PowerDNS, DNSSEC.

**Očekávané menu konzole** (položek: 10):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Registrátoři domén, Provoz a operace, Hromadné akce
- **Identita a schvalování**: Schvalování

**Oprávnění** (7): `dns.global.write`, `domain.critical.manage`, `domain.registrar.manage`, `iam.jit.request`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `shared_hosting_admin` SharedHostingAdmin

ISPConfig / web / db.

**Očekávané menu konzole** (položek: 17):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `iam.jit.request`, `provider.instance.read`, `provisioning.drift.resolve`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `managed_hosting_admin` ManagedHostingAdmin

aaPanel managed pool.

**Očekávané menu konzole** (položek: 17):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `iam.jit.request`, `provider.instance.read`, `provisioning.drift.resolve`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `apps_platform_admin` AppsPlatformAdmin

RKE2/build/registry.

**Očekávané menu konzole** (položek: 18):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `capacity.read`, `iam.jit.request`, `provider.instance.read`, `provisioning.drift.resolve`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `cloud_vps_admin` CloudVPSAdmin

VPS/VDS lifecycle.

**Očekávané menu konzole** (položek: 18):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (9): `capacity.read`, `iam.jit.request`, `provider.instance.read`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.delete`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `game_admin` GameAdmin

Pterodactyl/Wings.

**Očekávané menu konzole** (položek: 18):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `capacity.read`, `iam.jit.request`, `provider.instance.read`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `mail_admin` MailAdmin

Mailboxes/relay/reputation.

**Očekávané menu konzole** (položek: 17):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `iam.jit.request`, `provider.instance.read`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `database_admin` DatabaseAdmin

DBaaS.

**Očekávané menu konzole** (položek: 10):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Provoz a operace, Hromadné akce
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `iam.jit.request`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.console`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, `staff.console`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `ai_platform_admin` AIPlatformAdmin

Models, gateway, GPU policies.

**Očekávané menu konzole** (položek: 4):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Obchod**: Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (5): `ai.ops.read`, `ai.policy.manage`, `iam.jit.request`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `backup_dr_admin` BackupDRAdmin

PBS/restore/retention.

**Očekávané menu konzole** (položek: 16):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Identita a schvalování**: Schvalování

**Oprávnění** (9): `backup.delete`, `backup.policy.manage`, `backup.read`, `backup.restore`, `iam.jit.request`, `provider.instance.read`, `provisioning.operation.read`, `staff.backup.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `sre` SRE

Metrics/incidents/maintenance.

**Očekávané menu konzole** (položek: 24):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: Incidenty, Kalendář odstávek, Pohotovost (on-call), Sondy a komponenty, SLO a dostupnost
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Kapacita a nákup uzlů, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Finance**: SLA kredity, Reporty a výhled
- **Identita a schvalování**: Schvalování

**Oprávnění** (12): `capacity.read`, `iam.jit.request`, `incident.manage`, `incident.publish`, `maintenance.manage`, `provider.instance.read`, `provisioning.freeze`, `provisioning.operation.read`, `provisioning.operation.retry`, `report.read`, `sla.credit.manage`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, `provisioning.operation.retry`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.
- [ ] Zobrazit incidenty a pohotovost (`GET /v1/staff/incidents`, `incident.manage`): úspěch.
- [ ] Přečíst reporty (MRR, churn) (`GET /v1/staff/reports/mrr`, `report.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `incident_commander` IncidentCommander

Temporary incident authority.

**Očekávané menu konzole** (položek: 14):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: Incidenty, Pohotovost (on-call), Sondy a komponenty
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Kapacita a nákup uzlů, Provoz a operace, Hromadné akce
- **Bezpečnost a compliance**: Bezpečnostní incidenty a lhůty
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `capacity.read`, `incident.manage`, `incident.publish`, `notification.mass.send`, `provisioning.freeze`, `provisioning.operation.read`, `security.incident.manage`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.
- [ ] Zobrazit incidenty a pohotovost (`GET /v1/staff/incidents`, `incident.manage`): úspěch.
- [ ] Vést bezpečnostní incident (`GET /v1/staff/security/incidents`, `security.incident.manage`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `security_soc` SecuritySOC

Detections/quarantine/forensics.

**Očekávané menu konzole** (položek: 5):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Obchod**: Kredity a platby
- **Bezpečnost a compliance**: Bezpečnostní incidenty a lhůty
- **Identita a schvalování**: Schvalování

**Oprávnění** (7): `audit.read.global`, `iam.jit.request`, `provisioning.freeze`, `security.event.read`, `security.incident.manage`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Vést bezpečnostní incident (`GET /v1/staff/security/incidents`, `security.incident.manage`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `abuse_trust_safety` AbuseTrustSafety

DSA/abuse cases.

**Očekávané menu konzole** (položek: 5):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Obchod**: Kredity a platby
- **Bezpečnost a compliance**: Zneužití (DSA)
- **Identita a schvalování**: Schvalování

**Oprávnění** (4): `abuse.case.manage`, `security.event.read`, `staff.customer.read`, `staff.service.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, `staff.service.manage`): úspěch.
- [ ] Zpracovat případ zneužití (DSA) (`GET /v1/staff/abuse-cases`, `abuse.case.manage`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `compliance_legal` ComplianceLegal

Regulatory cases/evidence.

**Očekávané menu konzole** (položek: 8):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: SLO a dostupnost
- **Finance**: Reporty a výhled
- **Bezpečnost a compliance**: Bezpečnostní incidenty a lhůty, Zneužití (DSA), Žádosti o data
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `abuse.case.manage`, `audit.read.global`, `compliance.case.manage`, `compliance.legal_hold.manage`, `report.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Přečíst reporty (MRR, churn) (`GET /v1/staff/reports/mrr`, `report.read`): úspěch.
- [ ] Zpracovat případ zneužití (DSA) (`GET /v1/staff/abuse-cases`, `abuse.case.manage`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `billing_finance_admin` BillingFinanceAdmin

Invoice config/tax/reconciliation.

**Očekávané menu konzole** (položek: 18):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: SLO a dostupnost
- **Obchod**: Kredity a platby, Věrnost a kampaně, Marketplace
- **Finance**: Bankovní platby, Upomínky a pohledávky, Odstoupení od smlouvy, SLA kredity, Partneři, Výplaty partnerům, Reporty a výhled
- **Produkt**: Ceník, slevy a doplňky, Tarify a verze, Navigace klientského panelu, Životní cyklus služeb
- **Identita a schvalování**: Schvalování

**Oprávnění** (19): `billing.credit.adjust`, `billing.credit.adjust_mass`, `billing.credit_line.manage`, `billing.dunning.manage`, `billing.invoice.manage`, `billing.invoice.read`, `billing.limit_raise.waive`, `billing.reconcile`, `billing.refund.execute`, `billing.refund.execute_large`, `billing.tax_rule.manage`, `catalog.manage`, `iam.approval.decide`, `partner.manage`, `report.read`, `sla.credit.manage`, `staff.billing.read`, `staff.customer.manage`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Připsat kredit zákazníkovi (`POST /v1/staff/customers/{organization}/wallet/credit`, `billing.credit.adjust`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Spustit upomínky (`POST /v1/staff/dunning/run`, `billing.dunning.manage`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zobrazit bankovní platby k párování (`GET /v1/staff/payments/bank`, `billing.reconcile`): úspěch.
- [ ] Vrátit platbu objednávky na kartu nebo účet při odstoupení (G6) (`POST /v1/staff/payments/{payment}/refund`, `billing.refund.execute`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `billing_operator` BillingOperator

Invoice ops; limited refunds.

**Očekávané menu konzole** (položek: 8):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: SLO a dostupnost
- **Finance**: Bankovní platby, Upomínky a pohledávky, Odstoupení od smlouvy, Reporty a výhled
- **Identita a schvalování**: Schvalování

**Oprávnění** (8): `billing.dunning.manage`, `billing.invoice.manage`, `billing.invoice.read`, `billing.reconcile`, `billing.refund.execute`, `report.read`, `staff.billing.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Spustit upomínky (`POST /v1/staff/dunning/run`, `billing.dunning.manage`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Zobrazit bankovní platby k párování (`GET /v1/staff/payments/bank`, `billing.reconcile`): úspěch.
- [ ] Vrátit platbu objednávky na kartu nebo účet při odstoupení (G6) (`POST /v1/staff/payments/{payment}/refund`, `billing.refund.execute`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Potvrdit odeslání bankovní vratky (G6) (`POST /v1/staff/payments/refunds/{refund}/confirm`, `billing.refund.execute`, riziko HIGH: čerstvý step-up): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `support_manager` SupportManager

Queues/SLA/escalations.

**Očekávané menu konzole** (položek: 20):

- **Přehled**: Přehled
- **Podpora**: Fronta tiketů, Detail tiketu
- **Zákazníci**: Zákazníci, Poptávky
- **Provoz**: Incidenty, Pohotovost (on-call), Sondy a komponenty, SLO a dostupnost
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Provoz a operace, Hromadné akce
- **Obchod**: Obnovy a expirace, Kredity a platby
- **Finance**: Reporty a výhled
- **Produkt**: Obsah webu
- **Identita a schvalování**: Schvalování

**Oprávnění** (14): `ai.ops.read`, `iam.jit.request`, `incident.manage`, `provisioning.operation.read`, `report.read`, `staff.chargeback.decide`, `staff.customer.read`, `staff.order.manage`, `staff.support.ticket.read`, `support.kb.manage`, `support.queue.manage`, `support.ticket.assign`, `support.ticket.manage`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, `staff.support.ticket.read`): úspěch.
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, `support.ticket.manage`): úspěch.
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, `support.ticket.assign`): úspěch.
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, `support.queue.manage`): úspěch.
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, chybí `staff.service.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, chybí `staff.console`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Zopakovat operaci z fronty provisioningu (`POST /v1/staff/provisioning/jobs/{operation}/retry`, chybí `provisioning.operation.retry`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Připsat kredit zákazníkovi (`POST /v1/staff/customers/{organization}/wallet/credit`, chybí `billing.credit.adjust`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Spustit upomínky (`POST /v1/staff/dunning/run`, chybí `billing.dunning.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `support_l1` SupportL1

Read basics + safe actions.

**Očekávané menu konzole** (položek: 11):

- **Přehled**: Přehled
- **Podpora**: Fronta tiketů, Detail tiketu
- **Zákazníci**: Zákazníci
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Provoz a operace, Hromadné akce
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `provisioning.operation.read`, `staff.customer.read`, `staff.support.ticket.read`, `support.chat.use`, `support.ticket.manage`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, `staff.support.ticket.read`): úspěch.
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, `support.ticket.manage`): úspěch.
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, chybí `staff.service.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Otevřít serverovou konzoli zákaznické služby (`GET /sprava/konzole/{service}`, chybí `staff.console`): **403** (Člen zaměstnanců bez oprávnění).

### `support_l2` SupportL2

Deeper diagnostics + service actions.

**Očekávané menu konzole** (položek: 14):

- **Přehled**: Přehled
- **Podpora**: Fronta tiketů, Detail tiketu
- **Zákazníci**: Zákazníci, Poptávky
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Provoz a operace, Hromadné akce
- **Obchod**: Obnovy a expirace, Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (13): `iam.jit.request`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.chargeback.decide`, `staff.console`, `staff.customer.read`, `staff.order.manage`, `staff.service.manage`, `staff.support.ticket.read`, `support.chat.use`, `support.ticket.assign`, `support.ticket.manage`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, `staff.support.ticket.read`): úspěch.
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, `support.ticket.manage`): úspěch.
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, `support.ticket.assign`): úspěch.
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, `staff.order.manage`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Zobrazit incidenty a pohotovost (`GET /v1/staff/incidents`, chybí `incident.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Připsat kredit zákazníkovi (`POST /v1/staff/customers/{organization}/wallet/credit`, chybí `billing.credit.adjust`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Spustit upomínky (`POST /v1/staff/dunning/run`, chybí `billing.dunning.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Zobrazit bankovní platby k párování (`GET /v1/staff/payments/bank`, chybí `billing.reconcile`): **403** (Člen zaměstnanců bez oprávnění).

### `support_l3` SupportL3

Engineering escalation.

**Očekávané menu konzole** (položek: 24):

- **Přehled**: Přehled
- **Podpora**: Fronta tiketů, Detail tiketu
- **Zákazníci**: Zákazníci, Poptávky
- **Provoz**: Incidenty, Pohotovost (on-call), Sondy a komponenty
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Herní uzly, Šablony her, Alokace a porty, Provisioning fronta, Integrace providerů, Umístění tarifů, Registrátoři domén, Provoz a operace, Hromadné akce
- **Obchod**: Obnovy a expirace, Kredity a platby
- **Identita a schvalování**: Schvalování

**Oprávnění** (15): `iam.jit.request`, `incident.manage`, `provider.instance.read`, `provisioning.drift.resolve`, `provisioning.operation.read`, `provisioning.operation.retry`, `staff.chargeback.decide`, `staff.console`, `staff.customer.read`, `staff.order.manage`, `staff.service.manage`, `staff.support.ticket.read`, `support.ticket.assign`, `support.ticket.manage`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, `staff.support.ticket.read`): úspěch.
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, `support.ticket.manage`): úspěch.
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, `support.ticket.assign`): úspěch.
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, `staff.order.manage`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Připsat kredit zákazníkovi (`POST /v1/staff/customers/{organization}/wallet/credit`, chybí `billing.credit.adjust`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Spustit upomínky (`POST /v1/staff/dunning/run`, chybí `billing.dunning.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Zobrazit bankovní platby k párování (`GET /v1/staff/payments/bank`, chybí `billing.reconcile`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Vrátit platbu objednávky na kartu nebo účet při odstoupení (G6) (`POST /v1/staff/payments/{payment}/refund`, chybí `billing.refund.execute`): **403** (Člen zaměstnanců bez oprávnění).

### `sales` Sales

Quotes/CRM without infra admin.

**Očekávané menu konzole** (položek: 7):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci, Poptávky
- **Provoz**: SLO a dostupnost
- **Obchod**: Obnovy a expirace
- **Finance**: Reporty a výhled
- **Identita a schvalování**: Schvalování

**Oprávnění** (3): `report.read`, `staff.customer.read`, `staff.order.manage`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, `staff.order.manage`): úspěch.
- [ ] Přečíst reporty (MRR, churn) (`GET /v1/staff/reports/mrr`, `report.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Pozastavit nebo obnovit zákaznickou službu (`POST /v1/staff/services/{service}/actions`, chybí `staff.service.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `marketing_content` MarketingContent

Public content only.

**Očekávané menu konzole** (položek: 3):

- **Přehled**: Přehled
- **Produkt**: Obsah webu
- **Identita a schvalování**: Schvalování

**Oprávnění** (2): `content.manage`, `staff.inbox.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Upravit obsah webu (příspěvky, changelog) (`PUT /v1/staff/content/posts`, `content.manage`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, chybí `staff.customer.read`): **403** (Člen zaměstnanců bez oprávnění).

### `product_manager` ProductManager

Catalogue, plan versions and prices, notification templates, feature flags; no customer data beyond the overview.

**Očekávané menu konzole** (položek: 11):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: SLO a dostupnost
- **Finance**: Reporty a výhled
- **Produkt**: Ceník, slevy a doplňky, Tarify a verze, Navigace klientského panelu, Životní cyklus služeb, Šablony zpráv a odchozí pošta, Obsah webu
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `catalog.manage`, `content.manage`, `feature_flag.manage`, `notification.template.manage`, `report.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Přečíst reporty (MRR, churn) (`GET /v1/staff/reports/mrr`, `report.read`): úspěch.
- [ ] Zobrazit ceník a verze tarifů (`GET /v1/staff/pricing`, `catalog.manage`, riziko HIGH: čerstvý step-up): úspěch.
- [ ] Upravit šablony zpráv (`GET /v1/staff/templates`, `notification.template.manage`): úspěch.
- [ ] Upravit obsah webu (příspěvky, changelog) (`PUT /v1/staff/content/posts`, `content.manage`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

### `auditor_read_only` AuditorReadOnly

Immutable audit/evidence read.

**Očekávané menu konzole** (položek: 11):

- **Přehled**: Přehled
- **Zákazníci**: Zákazníci
- **Provoz**: SLO a dostupnost
- **Infrastruktura**: Infrastruktura, Běhové úlohy, Automatizace, Odchylky a úklid, Provoz a operace, Hromadné akce
- **Finance**: Reporty a výhled
- **Identita a schvalování**: Schvalování

**Oprávnění** (6): `ai.ops.read`, `audit.read.global`, `provisioning.operation.read`, `report.read`, `security.event.read`, `staff.customer.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Otevřít přehled zákazníků (Customer 360) (`GET /v1/staff/customers`, `staff.customer.read`): úspěch.
- [ ] Zobrazit přehled infrastruktury (`GET /v1/staff/provisioning/board`, `provisioning.operation.read`): úspěch.
- [ ] Přečíst reporty (MRR, churn) (`GET /v1/staff/reports/mrr`, `report.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít frontu tiketů (`GET /v1/staff/tickets`, chybí `staff.support.ticket.read`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Odpovědět na tiket zákazníka (`POST /v1/staff/tickets/{ticket}/messages`, chybí `support.ticket.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přiřadit tiket kolegovi (`POST /v1/staff/tickets/{ticket}/assign`, chybí `support.ticket.assign`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Upravit fronty, makra a SLA politiky podpory (`POST /v1/staff/support/queues`, chybí `support.queue.manage`): **403** (Člen zaměstnanců bez oprávnění).
- [ ] Přejít objednávku do dalšího stavu (`POST /v1/staff/orders/{order}/transition`, chybí `staff.order.manage`): **403** (Člen zaměstnanců bez oprávnění).

## Role zákazníka v organizaci

Postranní menu klientského panelu vzniká z `PanelNavigation` (kategorie služeb a volitelné odkazy, které personál zapíná v nastavení `panel.nav`) a z `SurfaceDataController::NAV_REQUIRES` (oprávnění, která potřebují koncové body daného pohledu). Odkaz, který by člen otevřel jen do odmítnutí, se nenabízí.

| Role (klíč) | Název | Počet oprávnění | Oblastí menu |
|---|---|---|---|
| `owner` | Owner | 46 | 21 |
| `org_admin` | Organization admin | 42 | 21 |
| `billing_admin` | Billing admin | 15 | 20 |
| `domain_manager` | Domain manager | 13 | 18 |
| `dns_manager` | DNS manager | 11 | 18 |
| `developer` | Developer | 19 | 18 |
| `cloud_operator` | Cloud operator | 19 | 18 |
| `game_operator` | Game operator | 18 | 18 |
| `mail_manager` | Mail manager | 13 | 18 |
| `security_auditor` | Security auditor | 9 | 18 |
| `support_contact` | Support contact | 5 | 12 |
| `viewer` | Viewer | 9 | 18 |
| `guest` | Guest | 0 | 6 |
| `partner` | Partner (commission only) | 13 | 19 |

### `owner` Owner

Full control of the organization.

**Očekávané menu panelu** (oblastí: 21): Přehled, Služby, Nastavení účtu, Objednat, Tikety, Fakturace, Dobití peněženky, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, API klíče a webhooky, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (46): `api_token.manage`, `apps.deploy`, `audit.read`, `backup.delete`, `backup.download`, `backup.read`, `backup.restore`, `billing.budget.manage`, `billing.invoice.read`, `billing.payment_method.manage`, `billing.wallet.read`, `billing.wallet.spend`, `billing.wallet.topup`, `catalog.order.create`, `compute.vm.delete`, `compute.vm.manage`, `data_export.request`, `database.manage`, `dns.dnssec.manage`, `dns.zone.read`, `dns.zone.write`, `domain.manage`, `domain.read`, `domain.registrant.change`, `domain.transfer_out.execute`, `game.manage`, `mail.manage`, `organization.close`, `organization.manage`, `organization.members.manage`, `organization.read`, `partner.payout_account.manage`, `partner.portal.read`, `project.manage`, `security.settings.manage`, `service.console`, `service.credentials.rotate`, `service.data.delete`, `service.delete`, `service.manage`, `service.operate`, `service.panel_account.manage`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- _Role smí vše z tohoto seznamu._

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `org_admin` Organization admin

Everything except what the owner alone may do (closing the organization, the panel account password).

**Očekávané menu panelu** (oblastí: 21): Přehled, Služby, Nastavení účtu, Objednat, Tikety, Fakturace, Dobití peněženky, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, API klíče a webhooky, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (42): `api_token.manage`, `apps.deploy`, `audit.read`, `backup.delete`, `backup.download`, `backup.read`, `backup.restore`, `billing.budget.manage`, `billing.invoice.read`, `billing.payment_method.manage`, `billing.wallet.read`, `billing.wallet.topup`, `catalog.order.create`, `compute.vm.delete`, `compute.vm.manage`, `data_export.request`, `database.manage`, `dns.dnssec.manage`, `dns.zone.read`, `dns.zone.write`, `domain.manage`, `domain.read`, `domain.registrant.change`, `domain.transfer_out.execute`, `game.manage`, `mail.manage`, `organization.manage`, `organization.members.manage`, `organization.read`, `partner.portal.read`, `project.manage`, `security.settings.manage`, `service.console`, `service.credentials.rotate`, `service.data.delete`, `service.delete`, `service.manage`, `service.operate`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Předat vlastnictví organizace (uzavření a předání jsou jen vlastníka) (`POST /v1/organizations/{organization}/ownership-transfer`, chybí `organization.close`): **403** (Člen organizace bez oprávnění).
- [ ] Nastavit výplatní účet partnera (jen vlastník) (`PUT /v1/partner/payout-account`, chybí `partner.payout_account.manage`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `billing_admin` Billing admin

Wallet, invoices, payment methods, budgets.

**Očekávané menu panelu** (oblastí: 20): Přehled, Služby, Nastavení účtu, Objednat, Tikety, Fakturace, Dobití peněženky, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (15): `audit.read`, `backup.read`, `billing.budget.manage`, `billing.invoice.read`, `billing.payment_method.manage`, `billing.wallet.read`, `billing.wallet.spend`, `billing.wallet.topup`, `catalog.order.create`, `dns.zone.read`, `domain.read`, `organization.read`, `partner.portal.read`, `service.read`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, chybí `support.ticket.write`): **403** (Člen organizace bez oprávnění).
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Restartovat službu (`POST /v1/services/{service}/actions`, chybí `service.operate`): **403** (Člen organizace bez oprávnění).
- [ ] Měnit nastavení služby (PHP, cron, databáze) (`POST /v1/services/{service}/actions`, chybí `service.manage`): **403** (Člen organizace bez oprávnění).
- [ ] Otevřít konzoli nebo shell služby (`POST /v1/services/{service}/actions`, chybí `service.console`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `domain_manager` Domain manager

Domains, contacts, renewals, transfers.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (13): `audit.read`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `dns.zone.read`, `dns.zone.write`, `domain.manage`, `domain.read`, `domain.registrant.change`, `domain.transfer_out.execute`, `organization.read`, `service.read`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, chybí `support.ticket.write`): **403** (Člen organizace bez oprávnění).
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `dns_manager` DNS manager

DNS zones and DNSSEC.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (11): `audit.read`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `dns.dnssec.manage`, `dns.zone.read`, `dns.zone.write`, `domain.read`, `organization.read`, `service.read`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, chybí `support.ticket.write`): **403** (Člen organizace bez oprávnění).
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `developer` Developer

Apps, deploys, databases, consoles.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (19): `apps.deploy`, `audit.read`, `backup.download`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `database.manage`, `dns.zone.read`, `dns.zone.write`, `domain.read`, `organization.read`, `service.console`, `service.data.delete`, `service.manage`, `service.operate`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Spravovat platební metody (`DELETE /v1/payment-methods/{method}`, chybí `billing.payment_method.manage`): **403** (Člen organizace bez oprávnění).
- [ ] Obnovit službu ze zálohy (`POST /v1/services/{service}/restore`, chybí `backup.restore`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `cloud_operator` Cloud operator

VPS/VDS lifecycle, snapshots, firewall, backups.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (19): `audit.read`, `backup.download`, `backup.read`, `backup.restore`, `billing.invoice.read`, `billing.wallet.read`, `compute.vm.delete`, `compute.vm.manage`, `dns.zone.read`, `domain.read`, `organization.read`, `service.console`, `service.data.delete`, `service.manage`, `service.operate`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Spravovat platební metody (`DELETE /v1/payment-methods/{method}`, chybí `billing.payment_method.manage`): **403** (Člen organizace bez oprávnění).
- [ ] Upravit DNS záznam (`POST /v1/dns/zones/{zone}/changes`, chybí `dns.zone.write`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `game_operator` Game operator

Game servers, console, mods, backups.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (18): `audit.read`, `backup.download`, `backup.read`, `backup.restore`, `billing.invoice.read`, `billing.wallet.read`, `dns.zone.read`, `domain.read`, `game.manage`, `organization.read`, `service.console`, `service.data.delete`, `service.manage`, `service.operate`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Spravovat platební metody (`DELETE /v1/payment-methods/{method}`, chybí `billing.payment_method.manage`): **403** (Člen organizace bez oprávnění).
- [ ] Upravit DNS záznam (`POST /v1/dns/zones/{zone}/changes`, chybí `dns.zone.write`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `mail_manager` Mail manager

Mail domains, mailboxes, DKIM/SPF/DMARC.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (13): `audit.read`, `backup.download`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `dns.zone.read`, `dns.zone.write`, `domain.read`, `mail.manage`, `organization.read`, `service.read`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Spravovat platební metody (`DELETE /v1/payment-methods/{method}`, chybí `billing.payment_method.manage`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `security_auditor` Security auditor

Read-only, including the audit log.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (9): `audit.read`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `dns.zone.read`, `domain.read`, `organization.read`, `service.read`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, chybí `support.ticket.write`): **403** (Člen organizace bez oprávnění).
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `support_contact` Support contact

Open and read tickets, use chat.

**Očekávané menu panelu** (oblastí: 12): Přehled, Služby, Nastavení účtu, Tikety, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Servisní okna, Osobní údaje a odchod, Monitoring.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (5): `organization.read`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.
- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, `support.ticket.write`): úspěch.
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, `support.chat.use`): úspěch.
- [ ] Zobrazit kartu Penpotu (adresa, přihlašovací e-mail, limity) (`GET /v1/services/{service}/penpot`, `service.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, chybí `domain.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit faktury (`GET /v1/invoices`, chybí `billing.invoice.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, chybí `billing.wallet.read`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `viewer` Viewer

Read-only.

**Očekávané menu panelu** (oblastí: 18): Přehled, Služby, Nastavení účtu, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (9): `audit.read`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `dns.zone.read`, `domain.read`, `organization.read`, `service.read`, `support.ticket.read`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Otevřít tiket a odpovídat (`POST /v1/tickets`, chybí `support.ticket.write`): **403** (Člen organizace bez oprávnění).
- [ ] Použít AI asistenta nebo živý chat (`POST /v1/assistant/chat`, chybí `support.chat.use`): **403** (Člen organizace bez oprávnění).
- [ ] Objednat službu (`POST /v1/orders`, chybí `catalog.order.create`): **403** (Člen organizace bez oprávnění).
- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `guest` Guest

Sees only the services shared with them.

**Očekávané menu panelu:** jen přehled, služby sdílené s hostem a nastavení vlastního účtu. Žádné faktury, tým, domény ani objednávky organizace.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí jen služby sdílené s ní nebo z projektů, kde má roli).

**Oprávnění** (0): _žádné na úrovni organizace; co smí, dávají sdílení služeb (svc_*)_

**Kontrolní seznam**

Povolené akce (musí uspět):

- _Žádná z akcí tohoto seznamu; role nic z nich nesmí._

Odmítnuté akce (musí skončit chybou):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, chybí `service.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, chybí `domain.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit faktury (`GET /v1/invoices`, chybí `billing.invoice.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, chybí `billing.wallet.read`): **403** (Člen organizace bez oprávnění).
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, chybí `support.ticket.read`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

### `partner` Partner (commission only)

Read access, orders and support tickets; earns commission only.

**Očekávané menu panelu** (oblastí: 19): Přehled, Služby, Nastavení účtu, Objednat, Tikety, Fakturace, Domény, Kalendář, Znalostní báze, Stav služeb, Tým a práva, Projekty, Připojené registrátory (WEDOS API), Oznámení a audit, Servisní okna, Náklady, Osobní údaje a odchod, Monitoring, Zálohy.

**Kategorie služeb v menu:** Domény a DNS, Webhosting, Herní servery, Servery a VPS, Emailing, Objektové úložiště, Housing a racky (zobrazí se ty, které personál zapnul a katalog prodává, a vždy ty, kde organizace už službu má; role vidí všechny služby organizace).

**Oprávnění** (13): `audit.read`, `backup.read`, `billing.invoice.read`, `billing.wallet.read`, `catalog.order.create`, `dns.zone.read`, `domain.read`, `organization.read`, `partner.portal.read`, `service.read`, `support.chat.use`, `support.ticket.read`, `support.ticket.write`

**Kontrolní seznam**

Povolené akce (musí uspět):

- [ ] Zobrazit seznam služeb (`GET /v1/services`, `service.read`): úspěch.
- [ ] Zobrazit domény a DNS zóny (`GET /v1/domains`, `domain.read`): úspěch.
- [ ] Zobrazit faktury (`GET /v1/invoices`, `billing.invoice.read`): úspěch.
- [ ] Zobrazit zůstatek peněženky (`GET /v1/wallet`, `billing.wallet.read`): úspěch.
- [ ] Zobrazit tikety organizace (`GET /v1/tickets`, `support.ticket.read`): úspěch.

Odmítnuté akce (musí skončit chybou):

- [ ] Dobít peněženku (`POST /v1/wallet/topup`, chybí `billing.wallet.topup`): **403** (Člen organizace bez oprávnění).
- [ ] Zaplatit z kreditu organizace (`POST /v1/orders (platba z kreditu)`, chybí `billing.wallet.spend`): **403** (Člen organizace bez oprávnění).
- [ ] Spravovat platební metody (`DELETE /v1/payment-methods/{method}`, chybí `billing.payment_method.manage`): **403** (Člen organizace bez oprávnění).
- [ ] Restartovat službu (`POST /v1/services/{service}/actions`, chybí `service.operate`): **403** (Člen organizace bez oprávnění).
- [ ] Měnit nastavení služby (PHP, cron, databáze) (`POST /v1/services/{service}/actions`, chybí `service.manage`): **403** (Člen organizace bez oprávnění).

- Cizí uživatel mimo organizaci otevře `GET /v1/organizations/{organization}` a dostane **404**; kdo organizaci nezná, nesmí poznat, že existuje.

## Přístup ke sdílené službě (svc_*)

Schopnosti na jedné službě (`scope: resource`) se nepřidělují jako role organizace; dává je pozvánka ke sdílení služby (`ServiceAccessService`). Host (`guest`) nevidí nic z organizace, jen sdílené služby. Cizí služba, která mu nebyla sdílena, vrací **404**; sdílená služba s chybějící schopností **403**.

### `svc_view` Service: view

State, metrics, logs, backups list.

**Oprávnění** (2): `backup.read`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Otevřít detail sdílené služby, metriky a seznam záloh; restart je odmítnut (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_operate` Service: operate

Restart, PHP version, caches, certificates — no files, cron, databases, logins or shell.

**Oprávnění** (2): `service.operate`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Restartovat službu, změnit verzi PHP, smazat cache; nahrát soubor nebo upravit cron je odmítnuto (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_manage` Service: manage

Actions and settings: restart, PHP, databases, cron, files, deploys, mailboxes, deleting them too — this runs code on the service; without backup deletion and without logins that open a shell.

**Oprávnění** (4): `service.data.delete`, `service.manage`, `service.operate`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Měnit nastavení, databáze, cron a soubory služby; otevřít konzoli je odmítnuto (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_console` Service: console

Terminal, SSH keys and root access, rescue mode, VNC, game console and its sub-users and console schedules.

**Oprávnění** (5): `service.console`, `service.data.delete`, `service.manage`, `service.operate`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Otevřít konzoli, nastavit SSH klíče; smazat zálohu je odmítnuto (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_data_delete` Service: delete data

Delete sites, databases, files, mailboxes and game data inside the service — never its backups.

**Oprávnění** (2): `service.data.delete`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Smazat web, databázi nebo schránku uvnitř služby; restart je odmítnut (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_backups` Service: backups

Download backup archives.

**Oprávnění** (3): `backup.download`, `backup.read`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Stáhnout zálohu sdílené služby; obnova ze zálohy je odmítnuta (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_restore` Service: restore

Restore the service from a backup.

**Oprávnění** (3): `backup.read`, `backup.restore`, `service.read`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Obnovit službu ze zálohy; stažení zálohy je odmítnuto (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.

### `svc_assistant` Service: assistant

Use the AI assistant for the shared service.

**Oprávnění** (2): `service.read`, `support.chat.use`

- [ ] Přihlaste se jako host, kterému byla sdílena jedna služba s touto schopností.
- [ ] Použít AI asistenta pro sdílenou službu; restart je odmítnut (403)
- [ ] Otevřete jinou službu téže organizace, která hostu sdílena nebyla: **404**.
- [ ] Otevřete faktury nebo tým organizace: **403**.
