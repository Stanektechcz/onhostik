<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Blueprint §70.1. Business logic never depends on a vendor SDK: OpenAI-compatible
 * (OpenAI, vLLM, Mistral), Anthropic Messages, or future providers implement this.
 */
interface AiProvider
{
    public static function providerKey(): string;

    /**
     * @param  list<array{role:string,content:string}>  $messages
     * @param  list<array{name:string,description:string,parameters:array<string,mixed>}>  $tools
     * @return array{content:string|null,tool_calls:list<array{id:string,name:string,arguments:array<string,mixed>}>,usage:array{input_tokens:int,output_tokens:int},model:string,finish_reason:string|null}
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;

    /** @param list<string> $inputs @return list<list<float>> */
    public function embeddings(array $inputs, array $options = []): array;

    /** @return array{flagged:bool,categories:array<string,bool>} */
    public function moderate(string $input): array;
}
