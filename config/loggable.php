<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Settings
    |--------------------------------------------------------------------------
    |
    | Configure default settings for activity logging
    |
    */
    'defaults' => [
        // Maximum length for text fields before truncation
        'max_text_length' => env('LOGGABLE_MAX_TEXT_LENGTH', 1000),

        // Whether to log metadata by default
        'log_metadata' => env('LOGGABLE_LOG_METADATA', true),

        // Whether to log only dirty (changed) attributes
        'log_only_dirty' => env('LOGGABLE_LOG_ONLY_DIRTY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata Collection
    |--------------------------------------------------------------------------
    |
    | Configure what metadata to collect
    |
    */
    'metadata' => [
        'collect' => [
            'ip_address' => true,
            'user_agent' => true,
            'session_id' => false,
            'request_url' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Clean Up Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of old logs
    |
    */
    'cleanup' => [
        'enabled' => env('LOGGABLE_CLEANUP_ENABLED', false),
        'older_than_days' => env('LOGGABLE_CLEANUP_OLDER_THAN', 90),
        'batch_size' => env('LOGGABLE_CLEANUP_BATCH_SIZE', 1000),
    ],
];
