# Support operations

* **Tickets** — `TK-YYYY-NNNN`, states NEW → TRIAGED → OPEN → WAITING_CUSTOMER / WAITING_INTERNAL →
  ESCALATED → RESOLVED → CLOSED (UI keys `otevreny | ceka | vyreseny`); priorities p1–p4 (UI
  `nizka | stredni | vysoka`). Deterministic `Triage` assigns a topic, queue and skills from the subject/body;
  contractual customers (business/ha/critical) get the higher SLA policy automatically.
* **SLA** — policies `standard | business | ha` with ack / first response / next response / resolve targets;
  clocks pause in WAITING_CUSTOMER; a breach escalates to the queue's `escalates_to` and emits
  `ticket.sla_breached`; resolved tickets auto-close after 7 days; CSAT after resolution.
* **Macros and clusters** — `GET /v1/staff/tickets/macros`, `GET /v1/staff/tickets/clusters` (topic clusters
  for the admin `#/podpora` view and the store's `clusters()`).
* **Assistant** — `POST /v1/assistant/chat`: rule bank → LLM with read-only tools → SAFE_WRITE proposals
  confirmed by the customer; handoff creates a ticket with the transcript and an AI summary. Every run is in
  `ai_runs` for evaluation; provider outages fall back to rules.
* **Abuse and legal** — DSA cases reach the customer as tickets on the `abuse` channel; security topics route
  to the `security` queue (`Triage::TOPICS['bezpecnost']`).
* **Partner-fronted support** — partners with `own_support` enabled receive their clients' tickets first;
  escalation to ONhost follows the same SLA clocks.

Notification templates for tickets (`ticket-ack`, `ticket-reply`, `ticket-solved`, …) are versioned in
`NotificationTemplateSeeder`; customers can mute non-mandatory kinds in `/v1/notifications/preferences`.
