# AI providers (support assistant)

**Files:** `providers/Ai/OpenAiCompatibleProvider.php`, `providers/Ai/AnthropicProvider.php`,
`domains/Support/Assistant/AiProviderRegistry.php`, `domains/Support/Assistant/AssistantService.php`

The assistant (`POST /v1/assistant/chat`) is rule-first: `Triage` classifies the question, a rule bank answers
known topics, and only then an LLM is called — with READ-only tools (`get_account_facts`,
`search_knowledge_base`) and SAFE_WRITE proposals that the customer must confirm (`confirm: true`) before the
command runs through the bus with the AI actor (`CommandContext::ai(runId, org, confirmedByUserId)`; AI actors
can never run HIGH/CRITICAL commands).

| Provider | Endpoint | Auth | Notes |
| --- | --- | --- | --- |
| OpenAI-compatible | `POST {base}/v1/chat/completions` (tools, JSON mode) | `Authorization: Bearer` from `env://AI_OPENAI` | works with OpenAI, Azure OpenAI, local vLLM/Ollama gateways (EU hosting preferred) |
| Anthropic | `POST https://api.anthropic.com/v1/messages` | `x-api-key`, `anthropic-version` | tool use via `tools` + `tool_result` blocks |

Every run is stored in `ai_runs` (provider, model, prompt version, tool calls, tokens, latency, redacted
transcript) for evaluation; PII is minimised before the prompt (`Redactor`). Handoff to a human ticket happens
on keywords, security/legal topics, repeated failure or low confidence, with the transcript and an AI summary
attached (`ticket.handoff`). Provider failures fall back to the rule bank and are attributed as `provider: rules`.

Configuration: `config/onhost.php` → `ai` (`default`, `models`, `timeout_seconds`, `max_tokens`,
`tool_policy`). Tests override the registry (`AiProviderRegistry::override`) — no network calls in CI.
