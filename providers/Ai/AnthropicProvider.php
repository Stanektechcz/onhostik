<?php

declare(strict_types=1);

namespace Onhost\Providers\Ai;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AiProvider;

/** Anthropic Messages API behind the same AiProvider contract (system prompt + tool use). */
final class AnthropicProvider implements AiProvider
{
    public function __construct(private readonly HttpFactory $http, private readonly string $baseUrl, private readonly string $apiKey, private readonly string $model, private readonly int $timeout = 30) {}

    public static function providerKey(): string
    {
        return 'anthropic';
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $system = implode("\n\n", array_map(fn ($m) => (string) $m['content'], array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'system')));
        $conversation = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'system') {
                continue;
            }
            if ($role === 'tool') {
                $conversation[] = ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => (string) ($m['tool_call_id'] ?? ''), 'content' => (string) $m['content']]]];

                continue;
            }
            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $blocks = $m['content'] ? [['type' => 'text', 'text' => (string) $m['content']]] : [];
                foreach ($m['tool_calls'] as $call) {
                    $blocks[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['name'], 'input' => (object) $call['arguments']];
                }
                $conversation[] = ['role' => 'assistant', 'content' => $blocks];

                continue;
            }
            $conversation[] = ['role' => $role === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }
        $payload = ['model' => $options['model'] ?? $this->model, 'max_tokens' => $options['max_tokens'] ?? 800, 'temperature' => $options['temperature'] ?? 0.2, 'messages' => $conversation];
        if ($system !== '') {
            $payload['system'] = $system;
        }
        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($t) => ['name' => $t['name'], 'description' => $t['description'], 'input_schema' => $t['parameters']], $tools);
        }
        $response = $this->http->withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'])->acceptJson()->timeout($this->timeout)->post(rtrim($this->baseUrl, '/').'/messages', $payload);
        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderException('ai', ProviderErrorCode::AUTH, 'AI provider rejected the API key', (string) $response->status());
        }
        if ($response->status() === 429 || $response->status() === 529) {
            throw new ProviderException('ai', ProviderErrorCode::RATE_LIMIT, 'AI provider overloaded', (string) $response->status(), retryAfterSeconds: (int) ($response->header('retry-after') ?: 10));
        }
        if ($response->status() >= 500) {
            throw new ProviderException('ai', ProviderErrorCode::TRANSIENT, "AI provider HTTP {$response->status()}", (string) $response->status());
        }
        if (! $response->successful()) {
            throw new ProviderException('ai', ProviderErrorCode::VALIDATION, 'AI provider rejected the request: '.mb_substr((string) $response->json('error.message', $response->body()), 0, 200), (string) $response->status());
        }
        $data = (array) $response->json();
        $text = '';
        $calls = [];
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => (string) $block['id'], 'name' => (string) $block['name'], 'arguments' => (array) ($block['input'] ?? [])];
            }
        }

        return ['content' => $text !== '' ? $text : null, 'tool_calls' => $calls, 'usage' => ['input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0), 'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0)], 'model' => (string) ($data['model'] ?? $this->model), 'finish_reason' => $data['stop_reason'] ?? null];
    }

    public function embeddings(array $inputs, array $options = []): array
    {
        throw new ProviderException('ai', ProviderErrorCode::VALIDATION, 'Anthropic does not provide embeddings; configure an OpenAI-compatible embedding endpoint');
    }

    public function moderate(string $input): array
    {
        $result = $this->chat([['role' => 'system', 'content' => 'You are a content safety classifier. Reply with JSON {"flagged": boolean, "categories": {"harassment": boolean, "self_harm": boolean, "sexual": boolean, "violence": boolean, "illegal": boolean}} and nothing else.'], ['role' => 'user', 'content' => $input]], [], ['max_tokens' => 120, 'temperature' => 0]);
        $json = json_decode((string) ($result['content'] ?? ''), true);

        return ['flagged' => (bool) ($json['flagged'] ?? false), 'categories' => array_map('boolval', (array) ($json['categories'] ?? []))];
    }
}
