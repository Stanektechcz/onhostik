<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth2 authorization-code grant
    |--------------------------------------------------------------------------
    |
    | Lifetimes for the three artifacts of the flow. Access tokens are Sanctum
    | tokens (so they work with the REST API and GraphQL); refresh tokens let a
    | client renew without sending the user back through consent.
    |
    */

    'authorization_code_ttl_minutes' => (int) env('OAUTH_CODE_TTL_MINUTES', 10),
    'access_token_ttl_minutes'       => (int) env('OAUTH_ACCESS_TTL_MINUTES', 60),
    'refresh_token_ttl_days'         => (int) env('OAUTH_REFRESH_TTL_DAYS', 30),

];
