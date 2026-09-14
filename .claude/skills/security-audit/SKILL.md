---
name: security-audit
description: Use for an ONHOST security review, threat assessment, secret scan, dependency audit, authorization review, or pre-release security gate.
---

# Security audit

1. Set the audit boundary: Git range, domain, endpoint, workflow, or release.
2. Read `AGENTS.md`, relevant ADR/runbooks, and `docs/generated/SECURITY_STATUS.md`.
3. Run `brain.ps1 security`; use the security reviewer for code-level trust boundaries.
4. Check authentication, tenant isolation, risk level, audit, validation/encoding, SSRF/path/command injection, secret flow, dependencies, and provider data.
5. Never print secret values. Report filenames and remediation classes only.

Output findings as Critical/High/Medium/Low with evidence, exploit condition, effect, and smallest safe fix. List clean checks separately.
