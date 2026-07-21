<?php

return [
    'enabled' => (bool) env('BUNNY_STREAM_ENABLED', false),
    'library_id' => env('BUNNY_STREAM_LIBRARY_ID', ''),
    'api_key' => env('BUNNY_STREAM_API_KEY', ''),
    'cdn_hostname' => env('BUNNY_STREAM_CDN_HOSTNAME', ''),
    'token_auth_key' => env('BUNNY_STREAM_TOKEN_AUTH_KEY', ''),
];
