<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Breached-password check (audit H116)
    |--------------------------------------------------------------------------
    |
    | Rejects passwords that appear in public breach corpora via Have I Been
    | Pwned. Uses k-anonymity — only the first 5 characters of the SHA-1 hash
    | are sent, never the password itself.
    |
    | Requires outbound HTTPS, so it is off by default: an offline install or
    | the test suite must not fail registration because an API is unreachable.
    | Turn it on in production with AUTH_PASSWORD_BREACH_CHECK=true.
    |
    */
    'password_breach_check' => (bool) env('AUTH_PASSWORD_BREACH_CHECK', false),

    /*
    |--------------------------------------------------------------------------
    | Concurrent session limit (audit 29)
    |--------------------------------------------------------------------------
    |
    | The maximum number of simultaneous logged-in sessions a single user may
    | hold. When a login would exceed this, the OLDEST sessions are evicted so
    | the newest device wins — an attacker who logs in cannot silently coexist
    | with the owner forever, and the owner's next login pushes the intruder
    | out. Remember-me tokens are cycled at the same time so an evicted device
    | cannot walk back in from its cookie.
    |
    | 0 disables the cap entirely (the previous behaviour), so existing
    | installs are unaffected until they opt in. Requires the database session
    | driver — with cookie/array drivers there is no server-side session table
    | to count, and the limiter no-ops rather than pretending to enforce.
    |
    */
    'max_concurrent_sessions' => (int) env('AUTH_MAX_CONCURRENT_SESSIONS', 0),

];
