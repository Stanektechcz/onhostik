<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A pager behind the on-call escalation (audit §5q-1): trigger / acknowledge / resolve one alert, identified on the
 * vendor's side by the platform's dedup key. Implementations never throw on vendor trouble: they answer null and the
 * service keeps the alert open in the console (the internal inbox stays the ground truth).
 */
interface OnCallProvider
{
    public function name(): string;

    /**
     * @param  array{dedup_key:string, title:string, body:?string, severity:string, event:string, surface:?string, escalation:int}  $alert
     * @return string|null the vendor's reference (incident id, alert alias), null when the vendor did not accept it
     */
    public function trigger(array $alert): ?string;

    public function acknowledge(string $dedupKey, ?string $providerRef, string $by): bool;

    public function resolve(string $dedupKey, ?string $providerRef, string $by): bool;
}
