<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ShellResult;

/**
 * Deterministic shell for tests and dry runs: commands are matched against regular expressions in order and answered
 * from the script; everything run is recorded. Unmatched commands succeed with empty output unless `strict`.
 */
final class ScriptedShell implements NodeShell
{
    /** @var list<array{command:string, options:array<string,mixed>}> */
    public array $calls = [];

    /** @param array<string, ShellResult|array{0:int,1:string,2?:string}|string> $script regex => result */
    public function __construct(private array $script = [], private readonly bool $strict = false, private readonly bool $available = true) {}

    public function on(string $pattern, ShellResult|array|string $result): self
    {
        $this->script[$pattern] = $result;

        return $this;
    }

    public function run(string $command, array $options = []): ShellResult
    {
        $this->calls[] = ['command' => $command, 'options' => $options];
        foreach ($this->script as $pattern => $result) {
            if (preg_match($pattern, $command)) {
                if ($result instanceof ShellResult) {
                    return $result;
                }
                if (is_string($result)) {
                    return new ShellResult(0, $result);
                }

                return new ShellResult((int) $result[0], (string) ($result[1] ?? ''), (string) ($result[2] ?? ''));
            }
        }
        if ($this->strict) {
            return new ShellResult(127, '', "scripted shell: no answer for `{$command}`");
        }

        return new ShellResult(0, '');
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function describe(): string
    {
        return 'scripted shell';
    }

    /** @return list<string> */
    public function commands(): array
    {
        return array_map(fn (array $c) => $c['command'], $this->calls);
    }

    public function ran(string $needle): bool
    {
        foreach ($this->calls as $call) {
            if (str_contains($call['command'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
