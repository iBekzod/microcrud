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
    'connection' => '',

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Enable or disable authorization checks in CRUD controllers.
    | You can override this in your application's config/microcrud.php
    |
    */
    'authorize' => true,

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
        'enabled' => false,

        // Default cache TTL in seconds
        'ttl' => 3600,

        // Cache driver (leave empty to use default)
        'driver' => '',

        // Validate if cache driver supports tagging
        // Note: Only Redis, Memcached, and DynamoDB support cache tagging
        'validate_tagging' => true,

        // Auto-disable cache if driver doesn't support required features
        'auto_disable_on_error' => true,
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
        'enabled' => false,

        // Queue connection (leave empty to use default)
        'connection' => '',

        // Validate queue configuration before dispatching jobs
        'validate' => true,

        // Auto-disable jobs if queue is misconfigured
        'auto_disable_on_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Configure supported locales and default locale.
    |
    */
    'locales' => ['en', 'ru', 'uz'],
    'locale' => 'en',
    'timezone' => 'UTC',

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
        'validate_on_boot' => true,

        // Auto-disable features that fail validation instead of throwing errors
        'auto_disable_on_error' => true,

        // Strict mode: throw exceptions instead of logging warnings
        'strict_mode' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP request logging behavior.
    |
    */
    'logging' => [
        // Log request headers
        'log_headers' => false,

        // Log request body
        'log_body' => true,

        // Log response body
        'log_response' => false,
    ],
];
