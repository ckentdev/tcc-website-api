<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS administrator (seeded on db:seed)
    |--------------------------------------------------------------------------
    */
    'admin_name' => env('CMS_ADMIN_NAME', 'CMS Administrator'),
    'admin_email' => env('CMS_ADMIN_EMAIL', 'cms@tcc.edu.ph'),
    'admin_password' => env('CMS_ADMIN_PASSWORD', env('CMS_SEED_PASSWORD', 'password')),

    /*
    |--------------------------------------------------------------------------
    | Default author password (temporary — users should change after first login)
    |--------------------------------------------------------------------------
    */
    'default_author_password' => env('CMS_DEFAULT_AUTHOR_PASSWORD', '12345678'),

    /*
    |--------------------------------------------------------------------------
    | CMS API token lifetime (days). Set 0 to rely on sanctum.php expiration only.
    |--------------------------------------------------------------------------
    */
    'token_lifetime_days' => (int) env('CMS_TOKEN_LIFETIME_DAYS', 14),

];
