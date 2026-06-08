<?php

return [

    'name' => 'PalmCore EAM/CMMS',

    'version' => '0.1.0',

    'tenant_model' => App\Models\Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    | Controls which model events are asynchronously logged to audit_logs.
    | Set 'enabled' to false in tests that don't need audit output.
    */
    'audit' => [
        'enabled' => env('PALMCORE_AUDIT_ENABLED', true),
        'queue' => env('PALMCORE_AUDIT_QUEUE', 'audit'),
        'events' => ['created', 'updated', 'deleted'],
        'exclude_models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Digital Signature Configuration
    |--------------------------------------------------------------------------
    */
    'signature' => [
        'disk' => env('PALMCORE_SIGNATURE_DISK', 'private'),
        'path' => 'signatures',
        'hash_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    | alerts_job_interval_minutes: how often the DashboardAlertsJob runs.
    | kpi_snapshot_hour: hour (UTC) when the nightly KPI snapshot job runs.
    */
    'dashboard' => [
        'alerts_job_interval_minutes' => env('PALMCORE_ALERTS_INTERVAL', 15),
        'kpi_snapshot_hour' => env('PALMCORE_KPI_SNAPSHOT_HOUR', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Storage
    |--------------------------------------------------------------------------
    | S3-compatible storage for work order photos, documents, and attachments.
    | Use 'minio' for self-hosted; switch to 's3' for AWS in production.
    */
    'media' => [
        'disk' => env('PALMCORE_MEDIA_DISK', 'minio'),
        'max_file_size_mb' => env('PALMCORE_MEDIA_MAX_MB', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Strategy
    |--------------------------------------------------------------------------
    | resolution: 'subdomain' | 'header' — how the active tenant is identified.
    */
    'tenancy' => [
        'resolution' => env('PALMCORE_TENANT_RESOLUTION', 'subdomain'),
    ],

];
