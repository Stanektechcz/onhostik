<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API version lifecycle (audit 103)
    |--------------------------------------------------------------------------
    |
    | Declares each REST API version's status so the platform can WARN clients
    | before a version disappears instead of breaking them on the day it does.
    | A version marked 'deprecated' still works, but every response carries the
    | RFC 8594 `Sunset` header and a `Deprecation` header pointing integrators
    | at the changelog — a machine-readable "move off this, and by when".
    |
    | status:  'active' | 'deprecated'
    | sunset:  ISO-8601 date the version stops being served (null = no date yet)
    |
    | Nothing is deprecated today; the machinery exists so that when v1 is one
    | day retired, the deprecation is announced for months rather than sprung.
    |
    */
    'versions' => [
        'v1' => [
            'status' => env('API_V1_STATUS', 'active'),
            'sunset' => env('API_V1_SUNSET'), // e.g. '2027-01-01'
        ],
        'v2' => [
            'status' => env('API_V2_STATUS', 'active'),
            'sunset' => env('API_V2_SUNSET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | Notable, client-visible API changes, newest first. Served publicly at
    | GET /api/changelog so an integrator can see what changed without an
    | account. Keep entries terse and behavioural — what a client must do,
    | not internal refactors.
    |
    */
    'changelog' => [
        [
            'date'    => '2026-07-20',
            'version' => 'v1,v2',
            'type'    => 'added',
            'summary' => 'Version lifecycle: responses now carry Deprecation/Sunset headers; this changelog is published at GET /api/changelog.',
        ],
        [
            'date'    => '2026-07-01',
            'version' => 'v2',
            'type'    => 'added',
            'summary' => 'v2 introduced: 2× rate limit, services embed monitor data, customer-managed webhook subscriptions.',
        ],
        [
            'date'    => '2026-06-15',
            'version' => 'v1',
            'type'    => 'added',
            'summary' => 'Idempotency-Key support on all write endpoints; per-token rate limits configurable in the panel.',
        ],
        [
            'date'    => '2026-05-01',
            'version' => 'v1',
            'type'    => 'added',
            'summary' => 'Initial public REST API: profile, services, invoices, domains, credit, support tickets.',
        ],
    ],

];
