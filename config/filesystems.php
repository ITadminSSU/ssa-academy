<?php

// On some shared hosts (e.g. Hostinger) the web-accessible "public/storage"
// symlink points at the whole "storage" directory instead of "storage/app/public".
// In that case public files are only reachable under "/storage/app/public/...".
// Setting PUBLIC_STORAGE_PATH=app/public makes every generated media URL use that
// working path. Leave it empty for a standard Laravel install (e.g. local dev).
$publicStoragePath = trim((string) env('PUBLIC_STORAGE_PATH', ''), '/');
$publicDiskUrl = rtrim(env('APP_URL') . '/storage' . ($publicStoragePath !== '' ? '/' . $publicStoragePath : ''), '/');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => $publicDiskUrl,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Storage Path Segment
    |--------------------------------------------------------------------------
    |
    | Extra path segment inserted into public media URLs when the host's
    | "public/storage" symlink resolves to the whole storage directory rather
    | than storage/app/public. Consumed by public_asset_url().
    |
    */

    'public_storage_path' => $publicStoragePath,

];
