# Integrations & Credential Vault

## Model

Every external provider has one row in `integration_settings`
(`App\Domains\Integrations\Models\IntegrationSetting`):

- `credentials` — encrypted at rest (Laravel Crypt/AES-256), `$hidden`,
  displayed only masked (`maskedCredentials()`, last 4 chars). Values are
  never echoed back and never written to the audit log (key names only).
- `is_active`, `mock_mode`, `dry_run` — every provider seeds as
  inactive + mock + dry-run. Internal mocks (`internal_monitoring`,
  `ai_mock`) seed active.
- `last_success_at` / `last_error_at` / `last_error_message` — health,
  shown in Admin → Integrace and Admin → Stav systému.

The catalog (labels, credential fields, categories) lives in
`config/integrations.php`. Admin screens: list, edit (flags + credentials,
empty inputs keep stored values), connection test.

## Five-layer refusal gate for real calls

A REAL write call only executes when **all** of these hold
(`GuardsRealCalls::assertRealCallAllowed()`):

1. provider row `is_active`,
2. `mock_mode = false`,
3. `dry_run = false`,
4. env approval gate open — `config('integrations.real_write_gates.*')`:
   `AAPANEL_ALLOW_REAL_WRITES`, `WAPI_ALLOW_REAL_WRITES`, `AI_ALLOW_REAL_CALLS`
   (all default **false**),
5. required credentials present.

Anything else → simulated dry-run payload (logged) or a hard
`ProvisioningException`. In Phase 3 no real HTTP is possible anywhere.

## Provider status (Phase 3)

| Provider | Status |
| --- | --- |
| aapanel | real-ready client (`AapanelClient`), dry-run only |
| wedos | real-ready client (`WedosWapiClient`), dry-run only |
| comgate | gateway + idempotent webhook wired, test mode, no real payments |
| gopay, stripe | placeholders (vault rows only) |
| internal_monitoring | ACTIVE mock |
| uptime_kuma | placeholder provider class |
| s3_backups, backblaze_b2 | placeholders (local mock does backups) |
| smtp | vault row only (mail is `log` locally) |
| ai_mock | ACTIVE deterministic mock |
| claude, openai | gated placeholder classes, refuse all calls |
| n8n, cloudflare, sitepro, softaculous | vault rows reserved |

## Activating a real provider later

1. Implement/finish the real client (aaPanel/WEDOS clients already exist).
2. Enter credentials in Admin → Integrace (stored encrypted).
3. Flip `is_active`, then disable `mock_mode`, then `dry_run` — in that order.
4. Open the env gate (e.g. `AAPANEL_ALLOW_REAL_WRITES=true`) on the server.
5. Run the connection test; watch `last_success_at` and the audit log.

Never enable real gates in development. See docs/production-checklist.md.
