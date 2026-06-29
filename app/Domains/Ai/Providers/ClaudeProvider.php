<?php

declare(strict_types=1);

namespace App\Domains\Ai\Providers;

use App\Domains\Ai\Contracts\AiProviderInterface;
use App\Domains\Ai\DTOs\AiResponse;
use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Anthropic Claude API provider.
 *
 * Activated when:
 *   AI_ALLOW_REAL_CALLS=true
 *   + integrations row (provider=claude, is_active=true, credentials.api_key set)
 *
 * Security: the API key is NEVER logged; only 'api_key_set: true' appears in logs.
 * Uses claude-haiku-4-5 by default (cost-efficient); override via CLAUDE_MODEL env.
 */
final class ClaudeProvider implements AiProviderInterface
{
    private const ANTHROPIC_API = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->model  = config('ai.claude_model', 'claude-haiku-4-5-20251001');
    }

    public function name(): string
    {
        return 'claude';
    }

    /** @param array<string, mixed> $context */
    public function chat(string $prompt, array $context = []): AiResponse
    {
        $systemPrompt = $this->buildSystemPrompt($context['topic'] ?? 'general');
        return $this->call($systemPrompt, $prompt);
    }

    /** @param list<string> $labels */
    public function classify(string $text, array $labels): AiResponse
    {
        $labelList = implode(', ', $labels);
        $system = "Classify the input into one of: {$labelList}. Reply with only the label name.";
        return $this->call($system, $text);
    }

    public function summarize(string $text): AiResponse
    {
        $system = 'Summarize the following text concisely in 2-3 sentences. Reply in Czech if the input is Czech.';
        return $this->call($system, $text);
    }

    /** @param array<string, mixed> $requirements */
    public function recommendPlan(array $requirements): AiResponse
    {
        $reqText = collect($requirements)
            ->map(fn ($v, $k) => "{$k}: {$v}")
            ->implode(', ');
        $system = 'You are an OnHost hosting advisor. Recommend the best hosting plan based on the customer requirements. Be concise and mention the plan name. Reply in Czech.';
        return $this->call($system, "Customer requirements: {$reqText}");
    }

    /** @param array<string, mixed> $context */
    public function explainError(string $error, array $context = []): AiResponse
    {
        $system = 'You are an OnHost technical support specialist. Explain this hosting/provisioning error in plain Czech to help a customer or admin understand and resolve it. Be concise.';
        return $this->call($system, $error);
    }

    /** @param array<string, mixed> $context */
    public function draftSupportReply(string $question, array $context = []): AiResponse
    {
        $system = 'You are an OnHost support agent. Draft a helpful, professional reply to the customer question in Czech. Be concise and friendly.';
        return $this->call($system, $question);
    }

    /** @param array<string, mixed> $payload */
    public function requestToolAction(string $action, array $payload): AiResponse
    {
        // Tool actions are never auto-executed — describe intent only.
        $payloadText = json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
        $system = 'Describe what the following hosting management action would do, in plain Czech. Do NOT execute anything.';
        return $this->call($system, "Action: {$action}\nPayload: {$payloadText}");
    }

    // ---------------------------------------------------------------- internals

    private function buildSystemPrompt(string $topic): string
    {
        return match ($topic) {
            'dns'           => 'You are an OnHost DNS expert. Explain DNS concepts clearly in Czech for customers.',
            'invoice'       => 'You are an OnHost billing assistant. Explain invoices and payments in plain Czech.',
            'website_brief' => 'You are an OnHost web advisor. Help customers plan their website hosting needs in Czech.',
            default         => 'You are an OnHost hosting assistant. Answer questions about web hosting, domains, and server management in Czech. Be concise and professional.',
        };
    }

    private function call(string $systemPrompt, string $userMessage): AiResponse
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type'      => 'application/json',
            ])
            ->timeout(30)
            ->post(self::ANTHROPIC_API, [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if (!$response->successful()) {
                $status = $response->status();
                Log::warning('Claude API error', [
                    'status'       => $status,
                    'api_key_set'  => true,
                    'model'        => $this->model,
                ]);
                throw new RuntimeException("Claude API returned HTTP {$status}.");
            }

            $body     = $response->json();
            $content  = $body['content'][0]['text'] ?? '';
            $tokensIn = $body['usage']['input_tokens']  ?? 0;
            $tokensOut= $body['usage']['output_tokens'] ?? 0;

            Log::info('Claude API call completed', [
                'model'       => $this->model,
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
                'api_key_set' => true,
            ]);

            return new AiResponse(
                content:   $content,
                tokensIn:  $tokensIn,
                tokensOut: $tokensOut,
            );
        } catch (RequestException $e) {
            Log::error('Claude API request exception', [
                'message'     => $e->getMessage(),
                'api_key_set' => true,
            ]);
            throw new RuntimeException('Claude API call failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Factory: create from IntegrationSetting. Returns null if not configured. */
    public static function fromIntegration(?IntegrationSetting $integration): ?self
    {
        if ($integration === null || !$integration->is_active) {
            return null;
        }

        $apiKey = $integration->credentials['api_key'] ?? null;
        if (empty($apiKey)) {
            return null;
        }

        return new self((string) $apiKey);
    }
}
