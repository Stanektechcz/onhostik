<?php

declare(strict_types=1);

namespace App\Domains\Ai\DTOs;

use Spatie\LaravelData\Data;

final class AiResponse extends Data
{
    /** @param array<string, mixed>|null $toolCall */
    public function __construct(
        public readonly string $content,
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
        public readonly ?array $toolCall = null,
    ) {}
}
