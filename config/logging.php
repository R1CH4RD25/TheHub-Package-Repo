<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],
        
        'daily' => [
            'driver' => 'daily',
            'path' => __DIR__ . '/../storage/logs/laravel.log',
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],
    ],
];
