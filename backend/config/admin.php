<?php

/*
|--------------------------------------------------------------------------
| ADMIN CONFIG — first-admin bootstrap credentials
|--------------------------------------------------------------------------
| Read through config() so the values survive `php artisan config:cache`
| (env() returns null for everything once the config is cached — reading
| these directly via env() in the auth service would make the one-time
| first-admin bootstrap impossible in production).
*/

return [
    'bootstrap_email' => env('ADMIN_BOOTSTRAP_EMAIL', 'admin@permetheon.com'),
    'bootstrap_password' => env('ADMIN_BOOTSTRAP_PASSWORD'),
];
