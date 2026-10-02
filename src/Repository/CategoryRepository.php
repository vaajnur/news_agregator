<?php

declare(strict_types=1);

namespace App\Repository;

use App\Cache\MemcachedCache;
use PDO;

final class CategoryRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MemcachedCache $cache,
        private readonly int $ttl = 300,
    ) {
    }

    /** @return list<array{id:int,name:string,slug:string,news_count:int}> */
    public function allWithCounts(): array
    {
        return $this->cache->remember('categories:with_counts', $this->ttl, function (): array {
            $sql = <<<'SQL'
                SELECT c.id, c.name, c.slug, COUNT(n.id) AS news_count
                FROM categories c
                LEFT JOIN news n ON n.category_id = c.id
                GROUP BY c.id, c.name, c.slug
                HAVING news_count > 0
                ORDER BY c.name ASC
            SQL;

            $rows = $this->pdo->query($sql)->fetchAll();

            return array_map(static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'slug' => (string) $row['slug'],
                    'news_count' => (int) $row['news_count'],
                ];
            }, $rows);
        });
    }

    public function findIdBySlug(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function findOrCreate(string $name): int
    {
        $slug = $this->slugify($name);

        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $insert->execute([
            'name' => $name,
            'slug' => $slug,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function invalidateCache(): void
    {
        $this->cache->delete('categories:with_counts');
    }

    private function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'category';
    }
}
