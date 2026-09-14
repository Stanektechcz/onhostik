<?php

declare(strict_types=1);

namespace Onhost\Providers\Ai;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AiProvider;

/** OpenAI-compatible chat/embeddings/moderation (OpenAI, vLLM, Mistral, self-hosted gateways). */
final class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(private readonly HttpFactory $http, private readonly string $baseUrl, private readonly string $apiKey, private readonly string $model, private readonly string $embeddingModel = 'text-embedding-3-small', private readonly int $timeout = 30) {}

    public static function providerKey(): string
    {
        return 'openai_compatible';
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $payload = ['model' => $options['model'] ?? $this->model, 'messages' => $messages, 'temperature' => $options['temperature'] ?? 0.2, 'max_tokens' => $options['max_tokens'] ?? 800];
        if ($tools !== []) {
            $payload['tools'] = array_map(fn ($t) => ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['parameters']]], $tools);
            $payload['tool_choice'] = 'auto';
        }
        $data = $this->post('/chat/completions', $payload);
        $choice = $data['choices'][0] ?? [];
        $calls = [];
        foreach ((array) ($choice['message']['tool_calls'] ?? []) as $call) {
            $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
            $calls[] = ['id' => (string) ($call['id'] ?? ''), 'name' => (string) ($call['function']['name'] ?? ''), 'arguments' => is_array($args) ? $args : []];
        }

        return ['content' => $choice['message']['content'] ?? null, 'tool_calls' => $calls, 'usage' => ['input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0), 'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0)], 'model' => (string) ($data['model'] ?? $this->model), 'finish_reason' => $choice['finish_reason'] ?? null];
    }

    public function embeddings(array $inputs, array $options = []): array
    {
        $data = $this->post('/embeddings', ['model' => $options['model'] ?? $this->embeddingModel, 'input' => array_values($inputs)]);

        return array_map(fn ($row) => array_map('floatval', (array) ($row['embedding'] ?? [])), (array) ($data['data'] ?? []));
    }

    public function moderate(string $input): array
    {
        $data = $this->post('/moderations', ['input' => $input]);
        $result = $data['results'][0] ?? [];

        return ['flagged' => (bool) ($result['flagged'] ?? false), 'categories' => array_map('boolval', (array) ($result['categories'] ?? []))];
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->http->withToken($this->apiKey)->acceptJson()->timeout($this->timeout)->post(rtrim($this->baseUrl, '/').$path, $payload);
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProviderException('ai', ProviderErrorCode::AUTH, 'AI provider rejected the API key', (string) $response->status());
        }
        if ($response->status() === 429) {
            throw new ProviderException('ai', ProviderErrorCode::RATE_LIMIT, 'AI provider rate limited', '429', retryAfterSeconds: (int) ($response->header('Retry-After') ?: 10));
        }
        if ($response->status() >= 500) {
            throw new ProviderException('ai', ProviderErrorCode::TRANSIENT, "AI provider HTTP {$response->status()}", (string) $response->status());
        }
        if (! $response->successful()) {
            throw new ProviderException('ai', ProviderErrorCode::VALIDATION, 'AI provider rejected the request: '.mb_substr((string) $response->json('error.message', $response->body()), 0, 200), (string) $response->status());
        }

        return (array) $response->json();
    }
}
