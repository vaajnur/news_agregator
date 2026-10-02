<?php

declare(strict_types=1);

use App\Http\Request;
use App\Repository\CategoryRepository;
use App\Repository\NewsRepository;

$services = require dirname(__DIR__) . '/bootstrap.php';

/** @var array $config */
$config = $services['config'];
/** @var CategoryRepository $categories */
$categories = $services['categories'];
/** @var NewsRepository $news */
$news = $services['news'];

$request = Request::fromGlobals();

$categorySlug = $request->string('category');
$dateFrom = $request->date('date_from');
$dateTo = $request->date('date_to');
$page = max(1, $request->int('page', 1));

$categoryId = null;
if ($categorySlug !== null) {
    $categoryId = $categories->findIdBySlug($categorySlug);
}

$filters = [
    'category_id' => $categoryId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'page' => $page,
    'per_page' => $config['app']['per_page'],
];

$result = $news->search($filters);
$categoryList = $categories->allWithCounts();
$stats = $news->stats();

$buildUrl = static function (array $overrides = []) use ($categorySlug, $dateFrom, $dateTo, $page): string {
    $params = array_filter([
        'category' => $overrides['category'] ?? $categorySlug,
        'date_from' => $overrides['date_from'] ?? $dateFrom,
        'date_to' => $overrides['date_to'] ?? $dateTo,
        'page' => $overrides['page'] ?? $page,
    ], static fn ($v) => $v !== null && $v !== '');

    if (isset($params['page']) && (int) $params['page'] === 1) {
        unset($params['page']);
    }

    $query = http_build_query($params);
    return $query === '' ? '/' : '/?' . $query;
};

require dirname(__DIR__) . '/templates/layout.php';
