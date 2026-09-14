<?php

declare(strict_types=1);

namespace Onhost\Platform\StateMachine;

/**
 * Minimal explicit state machine. Transitions are data (the same map is exposed to
 * the UI as `flows`), guards run before and side effects after the state is set.
 * Services own the transition; controllers never mutate state fields directly.
 */
final class StateMachine
{
    /** @param array<string, array{label:string,next:list<string>,tone?:string,internal?:bool}> $definition */
    public function __construct(
        public readonly string $name,
        public readonly array $definition,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        return isset($this->definition[$from]) && in_array($to, $this->definition[$from]['next'], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidTransitionException(sprintf('%s: transition %s -> %s is not allowed', $this->name, $from, $to));
        }
    }

    /** @return list<string> */
    public function nextStates(string $from): array
    {
        return $this->definition[$from]['next'] ?? [];
    }

    public function label(string $state): string
    {
        return $this->definition[$state]['label'] ?? $state;
    }

    public function has(string $state): bool
    {
        return isset($this->definition[$state]);
    }

    public function isTerminal(string $state): bool
    {
        return isset($this->definition[$state]) && $this->definition[$state]['next'] === [];
    }

    /** @return list<string> */
    public function states(): array
    {
        return array_keys($this->definition);
    }

    /** UI dictionary: state => label/next/tone (blueprint: "Slovník stavů posílá server"). */
    public function toArray(): array
    {
        return $this->definition;
    }
}
