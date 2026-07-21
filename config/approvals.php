<?php

declare(strict_types=1);

use App\Domains\Approvals\Executors\ExecuteResellerPayoutPaid;

return [

    /*
    |--------------------------------------------------------------------------
    | Four-eyes approval (audit 74)
    |--------------------------------------------------------------------------
    |
    | Per-action dual-control config. An action listed here with enabled=true is
    | staged for a second admin instead of running immediately. Everything is
    | OPT-IN: with enabled=false (the default) the operation behaves exactly as
    | it did before, so switching this on is a deliberate policy choice.
    |
    | min_amount_minor (optional): only require approval when the action's
    | 'amount_minor' context meets or exceeds this — small payouts pass through,
    | large ones need a second signature.
    |
    | executor: the class (implementing ApprovalExecutor) that performs the
    | action once approved. Nothing runs until then.
    |
    */
    'actions' => [
        'reseller_payout_paid' => [
            'enabled'          => (bool) env('APPROVALS_PAYOUT_ENABLED', false),
            'min_amount_minor' => (int) env('APPROVALS_PAYOUT_MIN_MINOR', 0),
            'executor'         => ExecuteResellerPayoutPaid::class,
            'label'            => 'Výplata reselleru označena jako zaplacená',
        ],
    ],

];
