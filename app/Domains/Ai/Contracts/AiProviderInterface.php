<?php

declare(strict_types=1);

namespace App\Domains\Ai\Contracts;

use App\Domains\Ai\DTOs\AiResponse;

/**
 * AI provider contract. Phase 3: only the deterministic MockAiProvider is
 * callable; Claude/OpenAI are gated placeholders. requestToolAction() NEVER
 * executes anything — it only describes the action so the assistant service
 * can park it as an AiActionApproval for an admin decision.
 */
interface AiProviderInterface
{
    public function name(): string;

    /** @param array<string, mixed> $context */
    public function chat(string $prompt, array $context = []): AiResponse;

    /** @param list<string> $labels */
    public function classify(string $text, array $labels): AiResponse;

    public function summarize(string $text): AiResponse;

    /** @param array<string, mixed> $requirements */
    public function recommendPlan(array $requirements): AiResponse;

    /** @param array<string, mixed> $context */
    public function explainError(string $error, array $context = []): AiResponse;

    /** @param array<string, mixed> $context */
    public function draftSupportReply(string $question, array $context = []): AiResponse;

    /** @param array<string, mixed> $payload */
    public function requestToolAction(string $action, array $payload): AiResponse;
}
