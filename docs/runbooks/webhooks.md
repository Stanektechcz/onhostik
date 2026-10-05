# Customer webhooks: queue lane and secret rotation

The customer-facing contract (endpoints, headers, body, retries) is `docs/api/README.md` → *Webhooks*. This page is what an
operator needs to run them.

## The `webhooks` queue lane

Every attempt is one queued job (`DeliverWebhook`, at most 8 s on the customer's endpoint). The jobs go to the lane named by
`ONHOST_WEBHOOK_QUEUE` (`webhooks` by default) **while a worker loops on that lane** — the same per-lane heartbeat the doctor
reads (`QueueLaneHeartbeat`, a lane is alive when its worker looped within 20 minutes). Otherwise they go to the default
queue. So:

| Installation | What happens |
| --- | --- |
| `onhost-queue@webhooks` enabled (production) | deliveries run apart; a burst of slow customer endpoints does not hold `default` |
| no webhooks worker (staging runs `default` + `mails` only) | deliveries run on `default`, as before G7; nothing piles up on a lane nobody works |
| the webhooks worker stopped | after 20 minutes new deliveries go to `default`; jobs already queued on `webhooks` wait for the worker (the doctor row *queue worker alive (webhooks)* is red) |
| `ONHOST_WEBHOOK_QUEUE=` (empty) or `default` | always the default queue |

Enable the lane: `systemctl enable --now onhost-queue@webhooks` (unit `infra/systemd/onhost-queue@.service`; Docker:
the `queue` service already lists `webhooks`). Once it has looped, the lane is expected by the doctor; retire it on purpose
with `php artisan onhost:queue:lanes --forget=webhooks`. Retries are the platform's own (`onhost:webhooks:retry`, every
minute) and follow the same lane choice.

## Rotating a signing secret

`POST /v1/webhooks/{id}/rotate-secret` (HIGH, step-up) answers the new `secret` once. The secret it replaces keeps signing
for `ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES` (60 by default, 0 = no overlap, at most 1440), in a header of its own:

```
X-ONhost-Signature:          v1=<hex HMAC-SHA256(new secret, "<timestamp>.<raw body>")>
X-ONhost-Signature-Previous: v1=<hex HMAC-SHA256(replaced secret, "<timestamp>.<raw body>")>   (only during the window)
```

**Choice and why:** `X-ONhost-Signature` keeps exactly one value, always signed with the current secret. A list inside it
(`v1=new,v1=old`) would have broken every receiver that does the documented whole-header constant-time compare. With a
separate header a receiver that already holds the new secret keeps working unchanged, and one that still holds the old
secret verifies `X-ONhost-Signature-Previous`. Receivers should accept a delivery when **either** signature verifies with the
secret they hold (`WebhookSigner::verifyAny` is the reference).

The answer's `previous_secret_valid_until` says when the window ends. A second rotation inside the window keeps only the
secret it replaced. The replaced secret is stored encrypted like the secret itself, never shown again, and cleared by
`onhost:webhooks:retry` once the window is over. A suspected leak of the secret: rotate with `{"overlap": false}` in the body — the replaced
secret stops signing at once (an overlap would keep a leaked secret valid for its window).

## Suspended endpoints

After 20 failed attempts in a row an endpoint is suspended and the organization is told (`webhook.endpoint.suspended`).
The customer fixes the receiver and calls `POST /v1/webhooks/{id}/enable`; deliveries that died meanwhile can be redelivered
(`…/deliveries/{delivery}/redeliver`, at most 10 attempts per delivery).
