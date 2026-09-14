<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Reads a host's wall power from its management controller (audit §5n-6): Redfish/IPMI DCMI on the BMC, so the usage
 * watch measures the fleet itself instead of waiting for a separate probe.
 */
interface HostPowerProvider
{
    /**
     * @param  array<string,mixed>  $endpoint  the node's `tags.bmc` (url, chassis, …)
     * @param  array<string,mixed>  $credentials  what the secret store holds for it (username, password | token)
     * @return array{watts:int, at:string, source:string}|null null when the controller did not answer with a reading
     */
    public function read(array $endpoint, array $credentials): ?array;

    /**
     * The controller's health inventory (audit §5o-6): the hottest sensor, failed fans and the power supplies' health.
     *
     * @param  array<string,mixed>  $endpoint
     * @param  array<string,mixed>  $credentials
     * @return array{temp_max_c:?int, fans_failed:int, psus:list<array{name:string, health:string}>, psu_failed:int}|null
     */
    public function inventory(array $endpoint, array $credentials): ?array;
}
