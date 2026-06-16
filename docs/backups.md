# Backups

Mock-only in Phase 3 — no archives are actually written.

## Data model

- `backup_policies` — one per service (daily, retention 14 days,
  provider `local_mock`), created automatically on service activation.
- `backup_jobs` — pending/running/success/failed, manual or scheduled.
- `backup_files` — simulated archive rows (path, size, checksum, expiry).
- `backup_restores` — restore requests (placeholder flow).

## Behaviour

- Customer "Zazálohovat nyní (mock)" → creates a pending `BackupJob` and
  dispatches `RunBackupJob` (queue `provisioning`). Double-clicks are
  throttled (one pending/running job per service).
- `LocalMockBackupProvider` simulates the archive and fills the file row.
- Failures mark the job failed with `error_message`
  (audit `backup.completed` / `backup.failed`).
- `S3CompatibleBackupProvider` is a refusing placeholder; Backblaze B2 has
  a vault row only.

## Visibility

- Customer: service detail — last backups table + request button.
- Admin: `/admin/zalohy` — jobs, failed count, policies.

## Later (real)

Implement the S3 provider (S3 SDK, lifecycle rules per retention_days),
wire scheduled jobs per policy frequency, and implement restore execution
with an explicit admin approval step.
