<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard / Passwords / Username
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path — where users land after login/registration.
    |--------------------------------------------------------------------------
    */

    'home' => '/panel',

    /*
    |--------------------------------------------------------------------------
    | Route Prefix / Domain / Middleware
    |--------------------------------------------------------------------------
    */

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'limiters' => [
        'login'      => 'login',
        'two-factor' => 'two-factor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | Email verification and two-factor are intentionally NOT enabled yet —
    | the architecture is ready (see docs/architecture.md), they will be
    | switched on in a later hardening phase.
    */

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        // Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm'         => true,
            'confirmPassword' => true,
        ]),
    ],

];
