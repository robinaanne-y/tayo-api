<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'media/*' is on top of the framework default ('api/*',
    // 'sanctum/csrf-cookie') so avatar/thumbnail images (served by
    // MediaController, not the raw /storage symlink -- see routes/web.php)
    // are also sent CORS headers. The Flutter web build fetches them
    // cross-origin via CanvasKit, which fails the request entirely (not
    // just a decode error) without Access-Control-Allow-Origin.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'media/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
