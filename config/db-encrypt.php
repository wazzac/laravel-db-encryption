<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the verbosity of package logging. For production environments,
    | we recommend using level 0 or 1 to minimize log file size.
    |
    | Levels:
    |   0 = None (No logging)
    |   1 = High-Level (Errors and critical operations only)
    |   2 = Mid-Level (Warnings and important operations)
    |   3 = Low-Level (Debug info, all operations)
    |
    | Indicator: Prefix used in log messages for easy filtering
    |   Example: tail -f storage/logs/laravel.log | grep db-encrypt
    |
    */
    'logging' => [
        'level' => env('DB_ENCRYPT_LOG_LEVEL', 0), // Recommended: 0 for production, 3 for development
        'indicator' => env('DB_ENCRYPT_LOG_INDICATOR', 'db-encrypt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | The encryption key used to secure your data. This should be a random,
    | secure string stored in your .env file.
    |
    | IMPORTANT:
    |   - Never commit this key to version control
    |   - Use different keys for each environment
    |   - Generate with: php artisan db-encrypt:generate-key
    |   - Keep backups of old keys until re-encryption is complete
    |
    | If you need to rotate keys, use: php artisan db-encrypt:re-encrypt
    |
    */
    'key' => env('DB_ENCRYPT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the package interacts with your database schema.
    |
    | primary_key_format: Format of your model primary keys
    |   - 'int' (default): Auto-incrementing integers
    |   - 'uuid': UUID strings (36 characters)
    |
    */
    'db' => [
        'primary_key_format' => env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration (Future Enhancement)
    |--------------------------------------------------------------------------
    |
    | Cache settings for improved performance with frequently accessed
    | encrypted data. Coming in future releases.
    |
    */
    // 'cache' => [
    //     'enabled' => env('DB_ENCRYPT_CACHE_ENABLED', false),
    //     'ttl' => env('DB_ENCRYPT_CACHE_TTL', 3600), // seconds
    //     'driver' => env('DB_ENCRYPT_CACHE_DRIVER', 'redis'),
    // ],
];
