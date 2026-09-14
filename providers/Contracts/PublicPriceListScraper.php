<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Registrars publish a public (retail) domain price list on their website. Where no
 * wholesale price API exists, the platform scrapes that list into the registrar price
 * book (`registrar_tld_costs`, source `scrape`) so the cheapest-registrar selection and
 * the margin checks always have a current, if conservative, cost figure.
 */
interface PublicPriceListScraper
{
    public static function registrarKey(): string;

    public function url(): string;

    /**
     * @return array<string, array{currency:string, register:?string, renew:?string, transfer:?string, promo?:bool, min_years?:int}> decimal amounts per bare TLD
     */
    public function parse(string $html): array;
}
