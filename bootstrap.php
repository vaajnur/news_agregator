<?php

declare(strict_types=1);

use App\Cache\MemcachedCache;
use App\Database\Connection;
use App\Repository\CategoryRepository;
use App\Repository\NewsRepository;
use App\Rss\RssFetcher;
use App\Service\NewsImporter;

require_once __DIR__ . '/src/Autoloader.php';

$config = require __DIR__ . '/config/config.php';

date_default_timezone_set($config['app']['timezone']);

$pdo = Connection::get($config['db']);
$cache = new MemcachedCache($config['memcached']);

$categoryRepository = new CategoryRepository(
    $pdo,
    $cache,
    $config['memcached']['ttl']['categories']
);

$newsRepository = new NewsRepository(
    $pdo,
    $cache,
    $config['memcached']['ttl']['news_list']
);

$rssFetcher = new RssFetcher($config['rss']);

$newsImporter = new NewsImporter(
    $pdo,
    $rssFetcher,
    $categoryRepository,
    $newsRepository
);

return [
    'config' => $config,
    'pdo' => $pdo,
    'cache' => $cache,
    'categories' => $categoryRepository,
    'news' => $newsRepository,
    'importer' => $newsImporter,
];
