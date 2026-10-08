<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\LegalDocuments;
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
        $legal = fn (string $key, string $placeholder) => (string) config("onhost.legal_entity.{$key}", '') !== '' ? (string) config("onhost.legal_entity.{$key}") : $placeholder; // config survives config:cache, env() does not
        $entity = LegalEntity::query()->updateOrCreate(['key' => 'onhost-cz'], [
            'name' => $legal('name', 'ONhost s.r.o.'),
            'ico' => $legal('ico', '00000000'),
            'dic' => $legal('dic', 'CZ00000000'),
            'vat_id' => $legal('vat_id', 'CZ00000000'),
            'address' => ['street' => $legal('street', 'Datacentrum 1'), 'city' => $legal('city', 'Praha'), 'postal_code' => $legal('zip', '110 00')],
            'country' => 'CZ',
            'iban' => $legal('iban', 'CZ0000000000000000000000'),
            'bic' => $legal('bic', 'XXXXCZPP'),
            'bank_account' => $legal('bank_account', '000000-0000000000/0000'),
            'series' => ['invoice' => 'FV', 'credit_note' => 'DK', 'proforma' => 'PF', 'receipt' => 'PP', 'correction' => 'OD', 'statement' => 'VY'],
            'meta' => ['registry' => $legal('registry', 'Městský soud v Praze, oddíl C'), 'oss' => true],
        ]);
        // G2: the declared VAT mode (ONHOST_VAT_PAYER) only for a legal entity created now; an existing one keeps its mode — it is
        // switched by finance with a step-up and a second person (POST /v1/staff/tax/vat-payer-mode), never by a seeder run
        // H0: whether the row was created by THIS run is read before the row is fetched again — the fresh copy never was "recently
        // created", so the declared mode was never written and a new legal entity kept the column default (payer)
        $created = $entity->wasRecentlyCreated;
        $entity = LegalEntity::query()->findOrFail('onhost-cz');
        if ($created || $entity->getAttribute('vat_payer') === null) {
            $entity->forceFill(['vat_payer' => (bool) config('vat.payer', false)])->save(); // H-R0: not a VAT payer unless declared
        }

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
        $this->drafts();
    }

    /**
     * TASK-0142: version 2026-10 of the customer documents, prepared as DRAFTS (owner decision I-R4/4A: the 2026-09 texts stay in
     * force until an attorney confirms these). A draft is created once and its metadata kept in step while it is a draft; a version
     * the owner has published (`onhost:legal:publish 2026-10 --apply`) is never touched by a seeder run again.
     */
    private function drafts(): void
    {
        $version = '2026-10';
        foreach ([
            ['terms', ['cs' => 'Všeobecné obchodní podmínky', 'en' => 'Terms of Service'], '/dokumenty/vop', true],
            ['privacy', ['cs' => 'Zásady ochrany osobních údajů', 'en' => 'Privacy Policy'], '/dokumenty/ochrana-osobnich-udaju', true],
            ['dpa', ['cs' => 'Smlouva o zpracování osobních údajů (čl. 28 GDPR)', 'en' => 'Data Processing Agreement'], '/dokumenty/dpa', false],
            ['sla', ['cs' => 'Smlouva o úrovni služeb (SLA)', 'en' => 'Service Level Agreement'], '/sla', false],
            ['withdrawal_waiver', ['cs' => 'Poučení o právu na odstoupení a žádost o okamžité zahájení plnění', 'en' => 'Withdrawal notice and request for immediate performance'], '/dokumenty/odstoupeni', false],
            ['registrar_terms', ['cs' => 'Podmínky registrace a správy domén ONhost', 'en' => 'ONhost domain registration terms'], (string) config('onhost.domains.terms_url', '/dokumenty/podminky-registrace-domen'), false],
            ['auto_renew', ['cs' => 'Podmínky automatického obnovování', 'en' => 'Auto-renewal terms'], '/dokumenty/obnovovani', false],
            ['complaints', ['cs' => 'Reklamační řád', 'en' => 'Complaints procedure'], '/dokumenty/reklamacni-rad', false],
            ['aup', ['cs' => 'Zásady přijatelného užívání služeb', 'en' => 'Acceptable Use Policy'], '/dokumenty/zasady-uzivani', false],
        ] as [$key, $title, $url, $required]) {
            $meta = ['title' => $title, 'url' => $url, 'required_for_checkout' => $required, 'hash' => LegalDocuments::hash($key, $version)];
            $row = ConsentDocument::query()->where('key', $key)->where('version', $version)->first();
            if ($row === null) {
                ConsentDocument::query()->create(['key' => $key, 'version' => $version, 'state' => ConsentDocument::DRAFT, 'effective_from' => ConsentDocument::DRAFT_EFFECTIVE_FROM] + $meta);
            } elseif ($row->state === ConsentDocument::DRAFT) {
                ConsentDocument::query()->where('key', $key)->where('version', $version)->update(['title' => json_encode($title, JSON_UNESCAPED_UNICODE)] + array_diff_key($meta, ['title' => true]));
            }
        }
    }
}
