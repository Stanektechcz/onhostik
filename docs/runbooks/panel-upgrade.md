# Upgrading a panel

aaPanel, ISPConfig, Pterodactyl (and Wings), Proxmox VE, Proxmox Backup Server, PowerDNS, RKE2 — the software the
platform runs itself. Brain cards H511–H530.

## What the platform does by itself

* The health probe (`onhost:integrations:health`, every minute) reads the version each panel reports and writes it to
  `provider_instances.vendor_version`.
* `Domain\Provisioning\PanelVersionGate` compares it with the version it last knew (`provider_instances.version_gate`):
  * **Changed** (an upgrade, a rollback) **or a new instance**: *verified* when the version is one the adapter declares
    (`supportedVendorVersions()`, matched by `VendorVersion`) **and** the checks pass on the panel as it is now
    (`NodePrerequisites::check()`: the calls the adapter relies on — `SelfProbing` —, the API user's rights, the cron API,
    the game panel's client API). Event `integration.version.verified`.
  * Otherwise **held**: `ProviderInstance::isUsable()` is false, so **no new orders or placements go there**; the services
    already on it are still managed. Event `integration.version.held` (hot) with the reason; doctor row "no panel is
    held on an unverified version".
  * A held version the adapter declares, whose checks failed (a plugin missing, rights reset by the upgrade), is checked
    again every 15 minutes and released by itself once they pass.
  * A held version the adapter does **not** declare waits for an operator:
    `php artisan onhost:integrations:versions --accept=<instance key> --reason="what was checked and where"`.
    Only a held version, only with a reason of ten characters or more, and only while the checks pass; who, when and why
    stay on the gate record and in the audit (`provider.instance.version.accepted`).
* The first look at a panel that already carries customers takes the version it runs as its **baseline** — nothing stops
  at deploy. The doctor row "every panel runs a version its adapter was verified on" names a baseline the adapter was
  never verified against: check it, then accept it or upgrade.
* Which panels report a version: Proxmox VE, PBS, PowerDNS, RKE2, aaPanel, and ISPConfig (`server_get_app_version`, the
  remote user needs "Server functions"). **Pterodactyl's API has no version**: it is not gated — read the version in the
  panel's admin area before and after, and the Wings version of each node. The doctor row "panels report their version"
  lists panels that should report one and do not. Registrars (WEDOS, Subreg) are hosted APIs and are not gated.

`php artisan onhost:integrations:versions` lists every panel: what it runs, what its adapter was verified on, the gate's
state and why.

## Before the upgrade (H511, H512, H519)

1. `php artisan onhost:integrations:versions` — is the target version one the adapter declares? If not, check it on a lab
   instance first and write down what was checked; the acceptance asks for it.
2. Operations still running on the instance: `GET /v1/staff/provisioning/jobs?state=RUNNING` and `?state=WAITING` — let
   them finish; do not start the upgrade in the middle of a backup or a migration.
3. Put **only this instance** into maintenance for the window: `POST /v1/staff/integrations/{instance}/state` with
   `state=maintenance`, `maintenance_until`, `reason` (H518). New work for it waits, customers of its services see
   `control_plane_maintenance` with the planned end; every other panel keeps working.
4. Take the panel's own backup (its database and configuration) the way the vendor documents it.

## After the upgrade

1. End the maintenance (`state=active`) — or let the health probe lift it after the planned end once the panel answers.
2. Within a minute the probe sees the new version: verified, or held with the reason (`onhost:integrations:versions`).
3. Held, version not declared: try the operations the platform uses on a test service (create, restart, backup, delete),
   then accept it with a reason. Held, checks failing: fix the cause the reason names; the gate looks again every
   15 minutes.
4. `php artisan onhost:doctor` — the providers rows.

A rollback is a version change like any other: the gate verifies it the same way (H527).

Tests: `tests/Feature/Provisioning/PanelVersionGateTest.php`.
