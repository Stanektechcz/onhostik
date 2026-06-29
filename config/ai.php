<?php

declare(strict_types=1);

return [
    /*
     * Set AI_ALLOW_REAL_CALLS=true in .env AND configure the 'claude'
     * integration row to enable live Claude API calls.
     * Defaults to false — all calls go to MockAiProvider.
     *
     * Security: api_key is NEVER logged; only 'api_key_set: true' is safe.
     */
    'allow_real_calls' => (bool) env('AI_ALLOW_REAL_CALLS', false),

    /*
     * Anthropic model to use. Defaults to Haiku (cost-efficient).
     * Options: claude-haiku-4-5-20251001, claude-sonnet-4-6, claude-opus-4-8
     */
    'claude_model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
];
