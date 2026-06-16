# Website builder & app installer (PREP ONLY)

Nothing executes for real in Phase 3.

## What exists

- Public teaser page `/website-builder`.
- Customer service detail: **Install WordPress (mock)** — records a
  successful `install_wordpress` ProvisioningTask exactly once (audit
  `provisioning.wordpress_mock_installed`); "Choose starter template" is a
  disabled placeholder button.
- Integrations vault rows reserved: `sitepro` (Site.pro builder) and
  `softaculous` (Softaculous/Installatron) with credential fields defined
  in `config/integrations.php`.

## What arrives later

- Real WordPress install through the aaPanel driver (create DB + deploy
  WP + configure SSL) executed as a queued provisioning operation.
- Site.pro SSO embed for the builder.
- Softaculous/Installatron one-click catalog.
- Admin screens for app templates.

All of it must follow the established pattern: queued job + ProvisioningTask
+ idempotency + audit + refusal gates.
