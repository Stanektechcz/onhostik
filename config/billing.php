<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    */
    'default_currency'     => env('BILLING_CURRENCY_DEFAULT', 'CZK'),
    'supported_currencies' => ['CZK', 'EUR', 'USD'],

    /*
    |--------------------------------------------------------------------------
    | Credit (zálohový účet)
    |--------------------------------------------------------------------------
    | Threshold in minor units of the customer's ledger currency below which
    | the CreditBalanceLow event fires.
    */
    'credit_low_threshold_minor' => env('BILLING_CREDIT_LOW_THRESHOLD', 10000),

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    */
    'invoice_due_days'        => env('BILLING_INVOICE_DUE_DAYS', 14),
    'proforma_validity_days'  => env('BILLING_PROFORMA_VALIDITY_DAYS', 10),

    'supplier' => [
        'name'    => env('BILLING_COMPANY_NAME', 'Onhost.cz s.r.o.'),
        'ic'      => env('BILLING_COMPANY_IC'),
        'dic'     => env('BILLING_COMPANY_DIC'),
        'street'  => env('BILLING_COMPANY_STREET'),
        'city'    => env('BILLING_COMPANY_CITY'),
        'zip'     => env('BILLING_COMPANY_ZIP'),
        'country' => env('BILLING_COMPANY_COUNTRY', 'CZ'),
        'bank_account_czk' => env('BILLING_BANK_CZK'),
        'bank_account_eur' => env('BILLING_BANK_EUR'),  // IBAN
    ],

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    | cz_rate: standard Czech VAT rate.
    | oss_rates: standard rates of EU member states for OSS (B2C digital
    | services). REVIEW QUARTERLY — member states change rates.
    | Source of truth: https://taxation-customs.ec.europa.eu/
    */
    'vat' => [
        'cz_rate' => 21.0,

        'oss_rates' => [
            'AT' => 20.0, 'BE' => 21.0, 'BG' => 20.0, 'HR' => 25.0,
            'CY' => 19.0, 'CZ' => 21.0, 'DK' => 25.0, 'EE' => 22.0,
            'FI' => 25.5, 'FR' => 20.0, 'DE' => 19.0, 'GR' => 24.0,
            'HU' => 27.0, 'IE' => 23.0, 'IT' => 22.0, 'LV' => 21.0,
            'LT' => 21.0, 'LU' => 17.0, 'MT' => 18.0, 'NL' => 21.0,
            'PL' => 23.0, 'PT' => 23.0, 'RO' => 19.0, 'SK' => 23.0,
            'SI' => 22.0, 'ES' => 21.0, 'SE' => 25.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dunning / suspension lifecycle (days relative to due date)
    |--------------------------------------------------------------------------
    */
    'lifecycle' => [
        'reminder_days_before'   => 14,
        'suspension_warning_after' => 3,
        'suspend_after'          => 7,
        'terminate_after'        => 30,
    ],
];
