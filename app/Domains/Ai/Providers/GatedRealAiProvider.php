<?php

declare(strict_types=1);

namespace App\Domains\Ai\Providers;

use App\Domains\Ai\Contracts\AiProviderInterface;
use App\Domains\Ai\DTOs\AiResponse;
use RuntimeException;

/**
 * Shared refusal behaviour for the real-provider PLACEHOLDERS
 * (Claude / OpenAI). Every method throws until ALL gates open:
 * AI_ALLOW_REAL_CALLS=true + active vault row + API key configured —
 * and the actual HTTP implementation lands in a later phase.
 */
abstract class GatedRealAiProvider implements AiProviderInterface
{
    /** @param array<string, mixed> $context */
    public function chat(string $prompt, array $context = []): AiResponse
    {
        throw $this->refused();
    }

    /** @param list<string> $labels */
    public function classify(string $text, array $labels): AiResponse
    {
        throw $this->refused();
    }

    public function summarize(string $text): AiResponse
    {
        throw $this->refused();
    }

    /** @param array<string, mixed> $requirements */
    public function recommendPlan(array $requirements): AiResponse
    {
        throw $this->refused();
    }

    /** @param array<string, mixed> $context */
    public function explainError(string $error, array $context = []): AiResponse
    {
        throw $this->refused();
    }

    /** @param array<string, mixed> $context */
    public function draftSupportReply(string $question, array $context = []): AiResponse
    {
        throw $this->refused();
    }

    /** @param array<string, mixed> $payload */
    public function requestToolAction(string $action, array $payload): AiResponse
    {
        throw $this->refused();
    }

    private function refused(): RuntimeException
    {
        return new RuntimeException(sprintf(
            'AI provider [%s] is a gated placeholder — real calls require AI_ALLOW_REAL_CALLS=true, '
            . 'an active integration row with an API key, and the real client implementation (later phase).',
            $this->name(),
        ));
    }
}
