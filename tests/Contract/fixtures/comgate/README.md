# Comgate recorded responses

Replayed through `ComgatePaymentProvider` by `tests/Contract/ComgateRecordedResponsesTest.php` (no network).

Provenance: these files are **sanitized and shaped from the Comgate Payments API v2.0 documentation**, not yet captured
from the Comgate test account (the owner's account is the open step, audit P1-14). All ids, e-mails and secrets are made up.
To replace them with real sandbox exchanges, run `php artisan onhost:fixtures:record comgate` on a development machine with
the test credentials (it redacts like the provider call log and writes `recorded_create.json` / `recorded_status.json`),
review the output for secrets, and copy the payloads over the matching file here. The test asserts on the fields the adapter
reads, so a real capture that changes a field name fails loudly.
