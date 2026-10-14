# Outbox dead letters

Every event of the platform is written to `outbox_messages` in the same transaction as the change and relayed to its
listeners afterwards (`onhost:outbox:relay`, every minute). A listener that throws makes the relay retry the message with
backoff (30 s, 60 s, … up to an hour). After **10 failed attempts** the relay stops trying: the message is a **dead letter**
(`published_at` empty, `attempts >= 10`, `Onhost\Domain\Platform\OutboxDeadLetters`). What the event should have started —
a notification, a webhook delivery, a follow-up such as fulfilling a paid order — has not happened and will not happen by
itself.

## How you learn about it

| Signal | Where |
| --- | --- |
| `onhost:doctor` row *observability · no outbox dead letters* | count, age of the oldest, the first event names, the remedy (WARN, not blocking) |
| `onhost_outbox_dead_letters`, `onhost_outbox_dead_letter_oldest_seconds` | `/metrics` (HealthController) |
| `OnhostOutboxDeadLetters` (ticket, after 10 min) · `OnhostOutboxDeadLettersOld` (page, older than a day) | `infra/monitoring/slo-alerts.yml` → Alertmanager → on-call |

Dead letters are **not** outbox lag any more: `onhost_outbox_pending_oldest_seconds`, the `OnhostOutboxLag` alert and the
`/healthz` outbox check count only messages the relay is still trying. Before G7 one dead letter kept the lag alert paging
and the readiness check red for ever, for a reason the relay could not fix.

## What to do

1. `php artisan onhost:outbox:dead-letters` — id, event name, organization, attempts, when it was written and the last
   error (redacted by the relay; the payload is never printed). `--name=<event>` and `--json` narrow and script it.
2. Find the listener that fails (the error names it; the trace id is in the error tracker) and fix the cause — a bug, a
   missing configuration, a provider that refused.
3. `php artisan onhost:outbox:dead-letters --requeue` (or `--requeue --id=<id>` for one) — the attempts start again from
   zero and the next relay run delivers them. Consumers dedupe on the message id; a listener that had already acted on
   the message before another one failed must itself be idempotent (the platform's listeners are).
4. The doctor row and the alert clear by themselves once the relay has published the messages.

Never delete a dead letter to make the alert go away: the event is the only record that the follow-up is owed.
