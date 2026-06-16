<?php

declare(strict_types=1);

namespace App\Domains\Ai\Providers;

/**
 * Anthropic Claude — PLACEHOLDER slot (no HTTP client yet).
 * Future implementation note: use claude-fable-5 / claude-haiku-4-5 via the
 * official SDK, with prompt templates from ai_prompt_templates.
 */
final class ClaudeProvider extends GatedRealAiProvider
{
    public function name(): string
    {
        return 'claude';
    }
}
