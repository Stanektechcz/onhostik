# AI assistant & automation

MOCK ONLY in Phase 3 — `MockAiProvider` produces deterministic responses;
no request ever leaves the machine.

## Data model

- `ai_prompt_templates` — seeded templates (5 customer + 5 admin features).
- `ai_runs` + `ai_messages` — every run persists input (sanitized),
  user/assistant messages, token estimates, duration, provider.
- `ai_action_approvals` — high-risk tool actions are PARKED here
  (pending/approved/rejected). Approval **never executes anything** in
  this phase; execution wiring arrives with real providers.
- `ai_usage_logs` — per-run usage rows (cost tracking ready).

Deviation from the original model list (documented intentionally):
`AiProvider` rows live in the integrations vault (`ai_mock`, `claude`,
`openai`) and tool calls are a JSON column on `ai_runs` — two fewer tables
with no loss of function at this stage.

## Features

Customer (`/panel/ai`): plan_recommendation, dns_explanation,
invoice_explanation, support_draft, website_brief.
Admin (`/admin/ai`): incident_summary, provisioning_failure_explanation,
ticket_summary, reply_draft, audit_summary + approvals queue + templates
+ usage log.

Everything flows through `AiAssistantService` (audit `ai.run_completed`,
`ai.approval_requested`, `ai.approval_reviewed`).

## Real providers later

`ClaudeProvider` / `OpenAiProvider` extend `GatedRealAiProvider` and refuse
every call until: `AI_ALLOW_REAL_CALLS=true` + active vault row + API key
+ a real HTTP implementation (recommended: claude-fable-5 for quality,
claude-haiku-4-5 for cheap classification). Keep approvals mandatory for
any tool execution.
