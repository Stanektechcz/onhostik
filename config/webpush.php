<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push / VAPID (audit 92)
    |--------------------------------------------------------------------------
    |
    | VAPID identifies this server to the browser push services. Generate a
    | keypair once (e.g. `web-push generate-vapid-keys`, or the library's
    | VAPID::createVapidKeys()) and set them here. With no keys configured,
    | sending is skipped gracefully — the subscription flow still works, but no
    | push is delivered until keys are present.
    |
    | The public key is also handed to the browser to create a subscription, so
    | it is safe to expose; the private key is a secret and must never be logged.
    |
    */
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT', 'mailto:admin@onhost.cz'),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'enabled' => (bool) env('VAPID_PUBLIC_KEY', false),

];
