<?php

declare(strict_types=1);

namespace App\Domains\Ai\Providers;

/**
 * OpenAI — PLACEHOLDER slot (no HTTP client yet).
 */
final class OpenAiProvider extends GatedRealAiProvider
{
    public function name(): string
    {
        return 'openai';
    }
}
