<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Result of reconcile(desired, actual): the list of drifts with ownership and the
 * classification the reconciler applies (blueprint §60.4, §75.2).
 */
final class ActionPlan
{
    /** @param list<array{field:string,expected:mixed,actual:mixed,ownership:string,classification:string}> $drifts */
    public function __construct(public readonly array $drifts = []) {}

    public static function inSync(): self
    {
        return new self([]);
    }

    public function hasDrift(): bool
    {
        return $this->drifts !== [];
    }

    /** @return list<array{field:string,expected:mixed,actual:mixed,ownership:string,classification:string}> */
    public function autoRepairable(): array
    {
        return array_values(array_filter($this->drifts, fn ($d) => $d['classification'] === 'AUTO_REPAIRABLE'));
    }

    public static function drift(string $field, mixed $expected, mixed $actual, string $ownership = 'ONHOST_MANAGED', string $classification = 'REQUIRES_APPROVAL'): array
    {
        return compact('field', 'expected', 'actual', 'ownership', 'classification');
    }
}
