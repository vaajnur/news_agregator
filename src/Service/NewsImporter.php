<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CategoryRepository;
use App\Repository\NewsRepository;
use App\Rss\RssFetcher;
use PDO;
use Throwable;

final class NewsImporter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RssFetcher $fetcher,
        private readonly CategoryRepository $categories,
        private readonly NewsRepository $news,
    ) {
    }

    /** @return array{fetched:int,inserted:int,updated:int} */
    public function import(): array
    {
        $runId = $this->startRun();

        try {
            $items = $this->fetcher->fetch();
            $inserted = 0;
            $updated = 0;

            $this->pdo->beginTransaction();

            foreach ($items as $item) {
                $categoryId = $this->categories->findOrCreate($item['category']);
                $status = $this->news->upsert([
                    'guid' => $item['guid'],
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'description' => $item['description'],
                    'image_url' => $item['image_url'],
                    'category_id' => $categoryId,
                    'published_at' => $item['published_at'],
                ]);

                if ($status === 'inserted') {
                    $inserted++;
                } else {
                    $updated++;
                }
            }

            $this->pdo->commit();

            $this->categories->invalidateCache();
            $this->news->invalidateListCache();

            $result = [
                'fetched' => count($items),
                'inserted' => $inserted,
                'updated' => $updated,
            ];

            $this->finishRun($runId, 'success', $result);

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->finishRun($runId, 'failed', [
                'fetched' => 0,
                'inserted' => 0,
                'updated' => 0,
            ], $e->getMessage());

            throw $e;
        }
    }

    private function startRun(): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO import_runs (started_at, status) VALUES (NOW(), :status)'
        );
        $stmt->execute(['status' => 'running']);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{fetched:int,inserted:int,updated:int} $stats */
    private function finishRun(int $runId, string $status, array $stats, ?string $error = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE import_runs
             SET finished_at = NOW(),
                 fetched_count = :fetched,
                 inserted_count = :inserted,
                 updated_count = :updated,
                 status = :status,
                 error_message = :error
             WHERE id = :id'
        );
        $stmt->execute([
            'fetched' => $stats['fetched'],
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'status' => $status,
            'error' => $error,
            'id' => $runId,
        ]);
    }
}
