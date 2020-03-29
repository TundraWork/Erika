<?php

return [
    'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
    'port' => env('CLICKHOUSE_PORT', '8123'),
    'database' => env('CLICKHOUSE_DATABASE', 'default'),
    'user' => [
        'read' => [
            'username' => env('CLICKHOUSE_USER_READ_USERNAME', 'guest'),
            'password' => env('CLICKHOUSE_USER_READ_PASSWORD', ''),
        ],
        'write' => [
            'username' => env('CLICKHOUSE_USER_WRITE_USERNAME', 'default'),
            'password' => env('CLICKHOUSE_USER_WRITE_PASSWORD', ''),
        ]
    ]
];
