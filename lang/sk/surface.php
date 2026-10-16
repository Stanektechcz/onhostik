<?php

declare(strict_types=1);

// Slovak (W5-5): schváleno vlastníkem 2026-10-16 (W4-K3), aktivní až s ONHOST_LOCALE_SK=true. Only the keys the sk branches of
// docs/development/sk-locale-inventory.md use; read only while config('onhost.locale_sk') is on. No legal texts here.

return [
    'most_popular' => 'Najčastejšia voľba',
    'stock_unit' => 'prenájom / mesiac',
    'stock_units' => '{1} :count kus na sklade|[2,4] :count kusy na sklade|[5,*] :count kusov na sklade',
    'stock_lead' => '{1} dodanie do :days dňa|[2,*] dodanie do :days dní',
    'stock_on_order' => 'na objednávku',
    'metric' => [
        'disk' => 'priestor',
        'traffic' => 'prenos dát',
        'memory' => 'pamäť',
        'mail' => 'poštové schránky',
        'inodes' => 'počet súborov',
        'transfer' => 'mesačný prenos dát',
        'web_disk_total' => 'priestor tarifu celkom (súbory, databázy, pošta)',
    ],
];
