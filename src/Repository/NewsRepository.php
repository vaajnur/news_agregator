<?php

declare(strict_types=1);

namespace App\Repository;

use App\Cache\MemcachedCache;
use PDO;

final class NewsRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MemcachedCache $cache,
        private readonly int $listTtl = 60,
    ) {
    }

    /**
     * @param array{category_id:?int,date_from:?string,date_to:?string,page:int,per_page:int} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int,from_cache:bool}
     */
    public function search(array $filters): array
    {
        $cacheKey = $this->buildCacheKey($filters);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            $cached['from_cache'] = true;
            return $cached;
        }

        $where = [];
        $params = [];

        if ($filters['category_id'] !== null) {
            $where[] = 'n.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if ($filters['date_from'] !== null) {
            $where[] = 'n.published_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to'] !== null) {
            $where[] = 'n.published_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
        $page = max(1, $filters['page']);
        $perPage = max(1, min(100, $filters['per_page']));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM news n WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = <<<SQL
            SELECT
                n.id,
                n.title,
                n.link,
                n.description,
                n.image_url,
                n.published_at,
                c.name AS category_name,
                c.slug AS category_slug
            FROM news n
            INNER JOIN categories c ON c.id = n.category_id
            WHERE {$whereSql}
            ORDER BY n.published_at DESC, n.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $result = [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'from_cache' => false,
        ];

        $this->cache->set($cacheKey, $result, $this->listTtl);

        return $result;
    }

    /** @param array{guid:string,title:string,link:string,description:?string,image_url:?string,category_id:int,published_at:string} $item */
    public function upsert(array $item): string
    {
        $exists = $this->pdo->prepare('SELECT id FROM news WHERE guid = :guid LIMIT 1');
        $exists->execute(['guid' => $item['guid']]);
        $id = $exists->fetchColumn();

        if ($id !== false) {
            $update = $this->pdo->prepare(
                'UPDATE news
                 SET title = :title,
                     link = :link,
                     description = :description,
                     image_url = :image_url,
                     category_id = :category_id,
                     published_at = :published_at
                 WHERE id = :id'
            );
            $update->execute([
                'title' => $item['title'],
                'link' => $item['link'],
                'description' => $item['description'],
                'image_url' => $item['image_url'],
                'category_id' => $item['category_id'],
                'published_at' => $item['published_at'],
                'id' => $id,
            ]);

            return 'updated';
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO news (guid, title, link, description, image_url, category_id, published_at)
             VALUES (:guid, :title, :link, :description, :image_url, :category_id, :published_at)'
        );
        $insert->execute([
            'guid' => $item['guid'],
            'title' => $item['title'],
            'link' => $item['link'],
            'description' => $item['description'],
            'image_url' => $item['image_url'],
            'category_id' => $item['category_id'],
            'published_at' => $item['published_at'],
        ]);

        return 'inserted';
    }

    /** @return array{total:int,today:int} */
    public function stats(): array
    {
        return $this->cache->remember('news:stats', $this->listTtl, function (): array {
            $total = (int) $this->pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
            $todayStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM news WHERE published_at >= CURDATE() AND published_at < CURDATE() + INTERVAL 1 DAY"
            );
            $today = (int) $todayStmt->fetchColumn();

            return [
                'total' => $total,
                'today' => $today,
            ];
        });
    }

    public function invalidateListCache(): void
    {
        // Prefix-based flush is not available without tracking keys;
        // short TTL + full flush of app namespace via flush on import is acceptable here.
        $this->cache->flush();
    }

    /** @param array{category_id:?int,date_from:?string,date_to:?string,page:int,per_page:int} $filters */
    private function buildCacheKey(array $filters): string
    {
        return 'news:list:' . md5(json_encode($filters, JSON_THROW_ON_ERROR));
    }
}
