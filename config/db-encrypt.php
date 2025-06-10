<?php

return [
    // Determines the level of logging.
    // For production environments, we recommend using either 0 or 1.
    // `level` ......: 0=None; 1=High-Level; 2=Mid-Level or 3=Low-Level
    // `indicator` ..: Log indicator used to locate specific items in the log file.
    // ------------------------------------------------------------
    'logging' => [
        'level' => env('DB_ENCRYPT_LOG_LEVEL', 3),
        'indicator' => env('DB_ENCRYPT_LOG_INDICATOR', 'db-encrypt'),
    ],

    // Define the DB encryption key. Will be used to encrypt and decrypt data.
    // ------------------------------------------------------------
    'key' => env('DB_ENCRYPT_KEY'),

    // Local database Primary key format
    // Options: 'int' (default) or 'uuid'
    // ------------------------------------------------------------
    'db' => [
        'primary_key_format' => env('DB_ENCRYPT_DB_PRIMARY_KEY_FORMAT', 'int'), // int or uuid (36)
    ],

    // ... more to follow

];
