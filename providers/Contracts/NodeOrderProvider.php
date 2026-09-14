<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Orders a new host from an infrastructure vendor (audit §5n-7): the capacity planner calls it when a pool runs short and
 * the pool's provider instance says how nodes are bought (`options.node_order`). The adapter returns the vendor's id and
 * the name the node will carry; provisioning of the hypervisor on it stays with the operator's playbooks.
 */
interface NodeOrderProvider
{
    /**
     * @param  array{name:string, ram_mb:int, cpu_cores:int, disk_gb:int, region:?string, options:array<string,mixed>}  $spec
     * @return array{remote_id:string, name:string, ip:?string, type:?string, cost_minor?:?int, currency?:?string} cost = the vendor's monthly price of the type (audit §5q-5)
     */
    public function order(array $spec): array;

    /** @return list<array{type:string, ram_mb:int, cpu_cores:int, disk_gb:int, price_monthly_minor?:?int, currency?:?string}> what can be ordered, smallest first (empty when unknown); the price feeds the monthly budget cap (audit §5q-5) */
    public function catalogue(array $options): array;
}
