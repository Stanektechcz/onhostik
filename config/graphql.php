<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | GraphQL introspection
    |--------------------------------------------------------------------------
    |
    | Introspection lets a client download the entire schema. That is exactly
    | what you want in development (tooling, autocomplete) and exactly what you
    | do not want in production, where it hands an attacker the full attack
    | surface. Defaults to APP_DEBUG, so production is closed unless opened
    | deliberately.
    |
    */

    'introspection' => (bool) env('GRAPHQL_INTROSPECTION', (bool) env('APP_DEBUG', false)),

];
