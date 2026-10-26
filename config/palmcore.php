<?php

use App\Models\Tenant;

return [

    'name' => 'PAJUIL CMMS',

    'version' => '0.1.0',

    'tenant_model' => Tenant::class,

    'billing_email' => env('PALMCORE_BILLING_EMAIL', 'hola@palmcore.app'),

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
        'events' => ['created', 'updated', 'deleted', 'restored'],
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
    | Horómetros
    |--------------------------------------------------------------------------
    | stale_reading_days: días sin lectura tras los cuales un equipo con plan
    | preventivo por horómetro levanta alerta. Un plan por horas que no recibe
    | lecturas no falla: deja de generar OTs en silencio. Al doble de este plazo
    | la alerta pasa a crítica.
    */
    'meters' => [
        'stale_reading_days' => env('PALMCORE_METER_STALE_DAYS', 7),
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

    /*
    |--------------------------------------------------------------------------
    | Security Policy
    |--------------------------------------------------------------------------
    | require_two_factor_for_owners: when true, tenant owners are prompted to
    | enable 2FA in addition to super admins (who are always prompted).
    */
    'security' => [
        'require_two_factor_for_owners' => env('PALMCORE_REQUIRE_2FA_FOR_OWNERS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | R-100 — pago de fruta por calidad
    |--------------------------------------------------------------------------
    | Coeficientes oficiales de la fórmula: cada kilo de racimo vale su porcentaje
    | de extracción y la fruta suelta suma 0,4 puntos por cada 1 %. FACIND (costo de
    | proceso más pérdidas permitidas) bajó de 4,7 % a 4,1 % en la fórmula definitiva
    | del director (2026-10-24). El precio es el de la tonelada de aceite (CPO) con
    | el que se liquida la fruta.
    */
    'r100' => [
        'oil_price_per_ton' => env('R100_OIL_PRICE_PER_TON'),
        'bunch_values' => [
            'ripe' => 0.21,
            'overripe' => 0.125,
            'unripe' => 0.11,
            'abnormal' => 0.10,
            'rotten' => 0.015,
        ],
        'loose_fruit_value' => 0.40,
        'industrial_factor' => 0.041,
        'kilos_per_long_stalk' => 1.5,
        // Calificación que ve el proveedor, medida sobre la TEA ANTES de restar el
        // FACIND: así califica la fruta y no cambia cuando cambia el costo de la
        // planta. Sale de los ejemplos de la presentación (con FACIND 4,7 %):
        // 20,24 % «calidad excelente», 19,01 % «buena calidad», 18,83 % «mucho RP y RSM».
        'quality_levels' => [
            'excellent' => 0.247,
            'good' => 0.237,
        ],
        // Lo que pierde quien no hace paloterapia (fórmula del ingeniero, 2026-10-26):
        // kilos × precio del kilo de aceite × (fruta del racimo × lo que se recupera
        // × valor de la fruta suelta − valor del racimo). Ej.: 400 kg de sobremaduro
        // a $4.000 el kilo → 400 × 4.000 × (50 % × 70 % × 40 % − 12,5 %) = $24.000.
        'threshing' => [
            'fruit_share' => 0.50,
            'recovered_share' => 0.70,
        ],
    ],

];
