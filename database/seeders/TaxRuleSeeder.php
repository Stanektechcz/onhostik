<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Tax\Models\TaxRuleVersion;

/**
 * Tax rule version 1 — Czech legal entity, VAT payer, OSS registered for B2C ESD
 * across the EU. LEGAL GATE: rates and the OSS flag must be confirmed by the tax
 * advisor before launch (blueprint §23.8); changes create a new version, never edit.
 */
final class TaxRuleSeeder extends Seeder
{
    public function run(): void
    {
        TaxRuleVersion::query()->firstOrCreate(['version' => 1], [
            'effective_from' => '2026-01-01 00:00:00',
            'state' => 'active',
            'note' => 'Initial rule set (CZ supplier, OSS). Verify standard rates annually.',
            'rules' => [
                'supplier' => ['country' => 'CZ', 'vat_payer' => true, 'legal_entity' => 'onhost-cz'],
                'eu_members' => ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'],
                'standard_rates' => [
                    'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21, 'DK' => 25, 'EE' => 24, 'FI' => 25.5, 'FR' => 20,
                    'DE' => 19, 'GR' => 24, 'HU' => 27, 'IE' => 23, 'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17, 'MT' => 18, 'NL' => 21,
                    'PL' => 23, 'PT' => 23, 'RO' => 21, 'SK' => 23, 'SI' => 22, 'ES' => 21, 'SE' => 25,
                ],
                'oss' => ['registered' => true, 'from' => '2026-01-01'],
                'product_classes' => [
                    'esd' => ['description' => 'Electronically supplied services (hosting, cloud, domains, apps)'],
                    'domain' => ['description' => 'Domain registration — treated as ESD'],
                    'support' => ['description' => 'Human consulting/support hours — general B2B/B2C rules'],
                ],
                'evidence' => ['require_two_pieces' => true, 'strict' => false],
            ],
        ]);
    }
}
