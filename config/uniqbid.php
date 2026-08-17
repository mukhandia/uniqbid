<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap admin account
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan uniqbid:ensure-admin` on deploy to guarantee a
    | staging/production admin can sign in. Prefer setting ADMIN_PASSWORD in
    | the server .env and changing it after first login.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'UniqBid Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@uniqbid.co.ke'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
