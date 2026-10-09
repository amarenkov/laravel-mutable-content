<?php

return [
    'log' => [
        'compress' => [
            'schedule' => env('MUTABLE_CONTENT_LOG_COMPRESS_SCHEDULE', '* * * * *'),
            'limit' => (int)env('MUTABLE_CONTENT_LOG_COMPRESS_LIMIT') ?: 10000,
        ],
    ],
];
