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
    */
    'key' => env('DB_ENCRYPT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Previous Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Old keys, comma separated in the .env file. They are only used to read
    | existing data and to match search hashes; new data always uses `key`.
    |
    | To rotate keys:
    |   1. php artisan db-encrypt:generate-key --rotate
    |   2. php artisan db-encrypt:re-encrypt
    |   3. Remove DB_ENCRYPT_PREVIOUS_KEYS from your .env file
    |
    */
    'previous_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DB_ENCRYPT_PREVIOUS_KEYS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Legacy v1.0 Support
    |--------------------------------------------------------------------------
    |
    | Allows data written by v1.0.x (AES-256-CBC with an unkeyed search hash) to
    | be read and searched. Run db-encrypt:re-encrypt to upgrade it, then set
    | this to false: while it is on, an attacker with write access to the
    | database can submit unauthenticated payloads. db-encrypt:status shows
    | how many legacy values remain.
    |
    */
    'legacy_support' => env('DB_ENCRYPT_LEGACY_SUPPORT', true),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the package interacts with your database schema.
    |
    | primary_key_format: Format of your model primary keys
    |   - 'int' (default): Auto-incrementing integers
    |   - 'uuid': UUID or ULID strings (up to 36 characters)
    |
    | Read when the migration runs, and applies to every encrypted model.
    |
    */
    'db' => [
        'primary_key_format' => env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'),
    ],
];
