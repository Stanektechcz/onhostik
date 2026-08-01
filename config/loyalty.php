<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Loyalty points
    |--------------------------------------------------------------------------
    |
    | How much a customer must spend (in the invoice's major currency unit, i.e.
    | CZK) to earn one loyalty point when an invoice is paid. Points are redeemed
    | against the admin-managed reward catalog.
    |
    */

    'czk_per_point' => (int) env('LOYALTY_CZK_PER_POINT', 10),

];
