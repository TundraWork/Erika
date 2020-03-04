<?php

/*
 * NOTE:
 * This configure file's structure should keep consistence with the one in Laravel.
 * Otherwise Redis database connection will fail.
 */

return [
    'redis' => [
        'cluster' => false,
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DATABASE', 0),
        ],
    ],
];
