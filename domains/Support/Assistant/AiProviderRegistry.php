<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Ai\AnthropicProvider;
use Onhost\Providers\Ai\OpenAiCompatibleProvider;
use Onhost\Providers\Contracts\AiProvider;

/** Resolves the configured LLM provider (blueprint §70.1); `null` when AI is disabled — the assistant then runs on rules. */
final class AiProviderRegistry
{
    private ?AiProvider $resolved = null;

    private bool $attempted = false;

    public function __construct(private readonly HttpFactory $http, private readonly SecretStore $secrets) {}

    public function provider(): ?AiProvider
    {
        if ($this->attempted) {
            return $this->resolved;
        }
        $this->attempted = true;
        if (! config('onhost.ai.enabled', false)) {
            return null;
        }
        $driver = (string) config('onhost.ai.driver', 'openai_compatible');
        $cfg = (array) config("onhost.ai.{$driver}", []);
        $credentials = $this->secrets->read(SecretRef::parse((string) ($cfg['secret_ref'] ?? 'env://AI_'.strtoupper($driver))));
        $key = (string) ($credentials['api_key'] ?? $credentials['key'] ?? '');
        if ($key === '') {
            return null;
        }
        $this->resolved = match ($driver) {
            'anthropic' => new AnthropicProvider($this->http, (string) $cfg['base_url'], $key, (string) $cfg['model'], (int) config('onhost.ai.timeout_seconds', 30)),
            default => new OpenAiCompatibleProvider($this->http, (string) $cfg['base_url'], $key, (string) $cfg['model'], (string) ($cfg['embedding_model'] ?? 'text-embedding-3-small'), (int) config('onhost.ai.timeout_seconds', 30)),
        };

        return $this->resolved;
    }

    public function override(?AiProvider $provider): void
    {
        $this->resolved = $provider;
        $this->attempted = true;
    }
}
