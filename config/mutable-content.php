<?php

return [
    'timezone' => env('MUTABLE_CONTENT_TIMEZONE'),

    'schema_cache' => (bool)env('MUTABLE_CONTENT_SCHEMA_CACHE', true),

    'log' => [
        'compress' => [
            'schedule' => env('MUTABLE_CONTENT_LOG_COMPRESS_SCHEDULE', '* * * * *'),
            'limit' => (int)env('MUTABLE_CONTENT_LOG_COMPRESS_LIMIT') ?: 10000,
        ],
    ],
];
