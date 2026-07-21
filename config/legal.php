<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Legal document versions (audit H123)
    |--------------------------------------------------------------------------
    |
    | Stamped onto every consent record so it is always clear WHICH wording a
    | customer agreed to. Consent to the 2026-01 terms is not consent to a
    | later revision.
    |
    | Bump the version here whenever the corresponding document changes
    | materially — existing consents then visibly refer to the older text,
    | which is exactly what you want to be able to show.
    |
    */
    'document_versions' => [
        'terms'     => env('LEGAL_TERMS_VERSION', '2026-01'),
        'privacy'   => env('LEGAL_PRIVACY_VERSION', '2026-01'),
        'marketing' => env('LEGAL_MARKETING_VERSION', '2026-01'),
        'cookies'   => env('LEGAL_COOKIES_VERSION', '2026-01'),
        'dpa'       => env('LEGAL_DPA_VERSION', '2026-01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Processing Agreement (audit 178)
    |--------------------------------------------------------------------------
    |
    | GDPR art. 28(3) requires the processor–controller relationship to be set
    | out in a contract covering a fixed list of points. A hosting customer is
    | the CONTROLLER of the personal data in their sites/databases; we are the
    | PROCESSOR. Business customers routinely need this on file for their own
    | compliance, and "email us for a DPA" is friction that generating it on
    | demand removes.
    |
    | These are the standing art. 28(3) particulars; the customer's own
    | identity is filled in per document at render time.
    |
    */
    'dpa' => [
        'subject_matter' => 'Poskytování webhostingu, serverových a doménových služeb objednaných zákazníkem.',
        'duration'       => 'Po dobu trvání smlouvy o poskytování služeb mezi stranami.',
        'nature_purpose' => 'Ukládání, zálohování a zpřístupňování dat v rozsahu nezbytném pro provoz objednaných služeb.',

        // art. 28(3)(a)–(h) — categories, subjects, measures, sub-processors.
        'data_categories' => [
            'Identifikační a kontaktní údaje koncových uživatelů zákazníka',
            'Přístupové údaje a obsah uložený v rámci služby',
            'Provozní a logová data (IP adresy, časy přístupů)',
        ],
        'data_subjects' => [
            'Koncoví uživatelé a zákazníci správce (zákazníka)',
            'Zaměstnanci a kontaktní osoby správce',
        ],
        'security_measures' => [
            'Šifrování přenosu (TLS) a citlivých údajů v úložišti',
            'Řízení přístupu na základě rolí a víceúrovňové ověření pro administrátory',
            'Pravidelné zálohování a monitoring dostupnosti',
            'Vedení auditního záznamu administrátorských operací',
        ],
        // Named sub-processors (art. 28(2)/(4)). Keep this list truthful — it is
        // a contractual representation, not marketing copy.
        'subprocessors' => [
            ['name' => 'Poskytovatel datacentra (EU)', 'purpose' => 'Fyzická infrastruktura serverů', 'location' => 'EU'],
        ],
    ],

];
