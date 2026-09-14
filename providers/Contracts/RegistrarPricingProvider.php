<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Registrars that publish their wholesale (cost) prices through their API. The
 * platform compares these prices across registrars and registers each domain
 * with the cheapest one (`RegistrarSelector`); registrars without a price API
 * keep a manually maintained cost list.
 */
interface RegistrarPricingProvider
{
    /**
     * @param  list<string>  $tlds  bare TLDs (`cz`, `com`)
     * @return array<string, array{currency:string, register:?string, renew:?string, transfer:?string, restore:?string}> decimal amounts per TLD; TLDs the registrar does not sell are omitted
     */
    public function costPrices(array $tlds): array;
}
