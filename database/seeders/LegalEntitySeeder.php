<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\Models\ConsentDocument;

/**
 * Legal entity + versioned consent documents. LEGAL GATE: company identifiers,
 * bank details and document texts are placeholders to be replaced by the real
 * ONhost entity before production (they are configuration, not code).
 */
final class LegalEntitySeeder extends Seeder
{
    public function run(): void
    {
        LegalEntity::query()->updateOrCreate(['key' => 'onhost-cz'], [
            'name' => env('ONHOST_LEGAL_NAME', 'ONhost s.r.o.'),
            'ico' => env('ONHOST_ICO', '00000000'),
            'dic' => env('ONHOST_DIC', 'CZ00000000'),
            'vat_id' => env('ONHOST_VAT_ID', 'CZ00000000'),
            'address' => ['street' => env('ONHOST_STREET', 'Datacentrum 1'), 'city' => env('ONHOST_CITY', 'Praha'), 'postal_code' => env('ONHOST_ZIP', '110 00')],
            'country' => 'CZ',
            'iban' => env('ONHOST_BANK_IBAN', 'CZ0000000000000000000000'),
            'bic' => env('ONHOST_BANK_BIC', 'XXXXCZPP'),
            'bank_account' => env('ONHOST_BANK_ACCOUNT', '000000-0000000000/0000'),
            'series' => ['invoice' => 'FV', 'credit_note' => 'DK', 'proforma' => 'PF', 'receipt' => 'PP', 'correction' => 'OD', 'statement' => 'VY'],
            'vat_payer' => true,
            'meta' => ['registry' => 'Městský soud v Praze, oddíl C', 'oss' => true],
        ]);

        foreach ([
            ['terms', '2026-09', ['cs' => 'Všeobecné obchodní podmínky', 'en' => 'Terms of Service'], '/dokumenty/vop', true],
            ['privacy', '2026-09', ['cs' => 'Zásady ochrany osobních údajů', 'en' => 'Privacy Policy'], '/dokumenty/ochrana-osobnich-udaju', true],
            ['dpa', '2026-09', ['cs' => 'Smlouva o zpracování osobních údajů (čl. 28 GDPR)', 'en' => 'Data Processing Agreement'], '/dokumenty/dpa', false],
            ['sla', '2026-09', ['cs' => 'Smlouva o úrovni služeb (SLA)', 'en' => 'Service Level Agreement'], '/sla', false],
            ['withdrawal_waiver', '2026-09', ['cs' => 'Žádost o okamžité zahájení plnění a poučení o právu na odstoupení', 'en' => 'Request for immediate performance and withdrawal notice'], '/dokumenty/odstoupeni', false],
            ['registrar_terms', '2026-09', ['cs' => 'Podmínky registrace a správy domén ONhost', 'en' => 'ONhost domain registration terms'], (string) config('onhost.domains.terms_url', '/dokumenty/podminky-registrace-domen'), false],
            ['registry_terms_cz', '2026-09', ['cs' => 'Pravidla registrace domén .CZ (CZ.NIC)', 'en' => '.CZ registration rules (CZ.NIC)'], 'https://www.nic.cz/page/314/', false],
            ['registry_terms_sk', '2026-09', ['cs' => 'Pravidlá registrácie domén .SK (SK-NIC)', 'en' => '.SK registration rules (SK-NIC)'], 'https://sk-nic.sk/pravidla/', false],
            ['registry_terms_eu', '2026-09', ['cs' => 'Pravidla registrace domén .EU (EURid)', 'en' => '.EU registration rules (EURid)'], 'https://eurid.eu/en/other-infomation/document-repository/', false],
            ['auto_renew', '2026-09', ['cs' => 'Podmínky automatického obnovování', 'en' => 'Auto-renewal terms'], '/dokumenty/obnovovani', false],
        ] as [$key, $version, $title, $url, $required]) {
            ConsentDocument::query()->updateOrCreate(['key' => $key, 'version' => $version], [
                'title' => $title, 'url' => $url, 'hash' => hash('sha256', $key.'|'.$version), 'effective_from' => '2026-09-01 00:00:00', 'required_for_checkout' => $required,
            ]);
        }
    }
}
