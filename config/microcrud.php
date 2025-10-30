<?php

/*
 * MicroCRUD Package Configuration
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Specify a custom database connection for MicroCRUD operations.
    | Leave empty to use the default application database connection.
    |
    */
    'connection' => env('MICROCRUD_DB_CONNECTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Enable or disable authorization checks in CRUD controllers.
    |
    */
    'authorize' => env('MICROCRUD_AUTHORIZE', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for MicroCRUD services.
    |
    */
    'cache' => [
        // Enable/disable caching globally
        'enabled' => env('MICROCRUD_CACHE_ENABLED', false),

        // Default cache TTL in seconds
        'ttl' => env('MICROCRUD_CACHE_TTL', 3600),

        // Cache driver (leave empty to use default)
        'driver' => env('MICROCRUD_CACHE_DRIVER', ''),

        // Validate if cache driver supports tagging
        // Note: Only Redis, Memcached, and DynamoDB support cache tagging
        'validate_tagging' => env('MICROCRUD_CACHE_VALIDATE_TAGGING', true),

        // Auto-disable cache if driver doesn't support required features
        'auto_disable_on_error' => env('MICROCRUD_CACHE_AUTO_DISABLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue/job behavior for async operations.
    |
    */
    'queue' => [
        // Enable/disable queue jobs globally
        'enabled' => env('MICROCRUD_QUEUE_ENABLED', false),

        // Queue connection (leave empty to use default)
        'connection' => env('MICROCRUD_QUEUE_CONNECTION', ''),

        // Validate queue configuration before dispatching jobs
        'validate' => env('MICROCRUD_QUEUE_VALIDATE', true),

        // Auto-disable jobs if queue is misconfigured
        'auto_disable_on_error' => env('MICROCRUD_QUEUE_AUTO_DISABLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Configure supported locales and default locale.
    |
    */
    'locales' => array_filter(explode(',', env('MICROCRUD_LOCALES', 'en,ru,uz'))),
    'locale' => env('MICROCRUD_LOCALE', 'en'),
    'timezone' => env('MICROCRUD_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control package behavior and error handling.
    |
    */
    'features' => [
        // Validate configuration on package boot
        'validate_on_boot' => env('MICROCRUD_VALIDATE_ON_BOOT', true),

        // Auto-disable features that fail validation instead of throwing errors
        'auto_disable_on_error' => env('MICROCRUD_AUTO_DISABLE_FEATURES', true),

        // Strict mode: throw exceptions instead of logging warnings
        'strict_mode' => env('MICROCRUD_STRICT_MODE', false),
    ],
];
