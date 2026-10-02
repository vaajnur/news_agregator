<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'news_aggregator',
        'user' => getenv('DB_USER') ?: 'news',
        'pass' => getenv('DB_PASS') ?: 'news',
        'charset' => 'utf8mb4',
    ],
    'memcached' => [
        'host' => getenv('MEMCACHED_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('MEMCACHED_PORT') ?: 11211),
        'prefix' => 'news_agg:',
        'ttl' => [
            'news_list' => 60,
            'categories' => 300,
            'stats' => 60,
        ],
    ],
    'rss' => [
        'url' => getenv('RSS_URL') ?: 'https://ria.ru/export/rss2/archive/index.xml',
        'timeout' => 20,
        'user_agent' => getenv('RSS_USER_AGENT') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ],
    'app' => [
        'per_page' => 20,
        'timezone' => 'Europe/Moscow',
    ],
];
