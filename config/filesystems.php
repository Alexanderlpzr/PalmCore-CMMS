<?php

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
    | Logical Disks for Persistent Files
    |--------------------------------------------------------------------------
    |
    | These indirections decouple call sites from a concrete disk name so the
    | same code runs on local storage in development and on Cloudflare R2 (or
    | any S3-compatible object store) in production by changing only env vars.
    |
    | - persistent_disk: publicly-referenced persistent assets (equipment
    |   photos, tenant logos, QR codes, documents). Defaults to the local
    |   "public" disk to preserve existing behaviour.
    | - private_disk: access-controlled persistent files (work order media and
    |   other private attachments). Defaults to the local "work_orders_private"
    |   disk.
    |
    | Set PERSISTENT_DISK=r2 and PRIVATE_DISK=r2 in production to make uploads
    | survive redeploys, restarts, and horizontal scaling.
    |
    */

    'persistent_disk' => env('PERSISTENT_DISK', 'public'),

    'private_disk' => env('PRIVATE_DISK', 'work_orders_private'),

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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'reports' => [
            'driver' => 'local',
            'root' => storage_path('app/reports'),
            'throw' => false,
            'report' => false,
        ],

        'work_orders_private' => [
            'driver' => 'local',
            'root' => storage_path('app/work-orders-private'),
            'throw' => false,
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

        // Cloudflare R2 — S3-compatible object storage. Private by default;
        // files are served through time-limited signed URLs (see file_signed_url()).
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY'),
            'secret' => env('R2_SECRET_KEY'),
            'region' => env('R2_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            // R2 requires path-style addressing.
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE', true),
            'visibility' => 'private',
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

];
