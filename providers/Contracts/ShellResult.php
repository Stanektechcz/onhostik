<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Outcome of one command on a hosting node (NodeShell::run). Output is capped by the shell, never by the caller. */
final class ShellResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr = '',
        public readonly int $durationMs = 0,
        public readonly bool $timedOut = false,
        public readonly bool $truncated = false,
    ) {}

    public function ok(): bool
    {
        return $this->exitCode === 0 && ! $this->timedOut;
    }

    /** stdout and stderr in reading order, trimmed. */
    public function output(): string
    {
        return trim($this->stdout.($this->stderr !== '' ? "\n".$this->stderr : ''));
    }

    /** @return array{exit_code:int, stdout:string, stderr:string, duration_ms:int, timed_out:bool, truncated:bool} */
    public function toArray(): array
    {
        return ['exit_code' => $this->exitCode, 'stdout' => $this->stdout, 'stderr' => $this->stderr, 'duration_ms' => $this->durationMs, 'timed_out' => $this->timedOut, 'truncated' => $this->truncated];
    }
}
