<?php

declare(strict_types=1);

/** @var array $result */
/** @var list<array{id:int,name:string,slug:string,news_count:int}> $categoryList */
/** @var array{total:int,today:int} $stats */
/** @var ?string $categorySlug */
/** @var ?string $dateFrom */
/** @var ?string $dateTo */
/** @var callable $buildUrl */

$e = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новостной агрегатор</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="page">
    <header class="header">
        <div class="brand">
            <p class="brand__eyebrow">PHP · MySQL · Memcached</p>
            <h1 class="brand__title">Новостной агрегатор</h1>
            <p class="brand__lead">Лента РИА Новости с фильтрами по категории и дате, кешированием выдачи и фоновым обновлением RSS.</p>
        </div>
        <div class="stats" aria-label="Статистика">
            <div class="stats__item">
                <span class="stats__value"><?= (int) $stats['total'] ?></span>
                <span class="stats__label">всего</span>
            </div>
            <div class="stats__item">
                <span class="stats__value"><?= (int) $stats['today'] ?></span>
                <span class="stats__label">сегодня</span>
            </div>
            <div class="stats__item stats__item--cache">
                <span class="stats__value"><?= !empty($result['from_cache']) ? 'HIT' : 'MISS' ?></span>
                <span class="stats__label">кеш</span>
            </div>
        </div>
    </header>

    <form class="filters" method="get" action="/">
        <label class="filters__field">
            <span>Категория</span>
            <select name="category">
                <option value="">Все категории</option>
                <?php foreach ($categoryList as $category): ?>
                    <option value="<?= $e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= $e($category['name']) ?> (<?= (int) $category['news_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="filters__field">
            <span>С даты</span>
            <input type="date" name="date_from" value="<?= $e($dateFrom) ?>">
        </label>

        <label class="filters__field">
            <span>По дату</span>
            <input type="date" name="date_to" value="<?= $e($dateTo) ?>">
        </label>

        <div class="filters__actions">
            <button type="submit" class="btn btn--primary">Применить</button>
            <a class="btn btn--ghost" href="/">Сбросить</a>
        </div>
    </form>

    <main class="feed">
        <?php if ($result['items'] === []): ?>
            <p class="empty">Новости не найдены. Запустите сбор RSS: <code>php bin/fetch.php</code></p>
        <?php else: ?>
            <ul class="feed__list">
                <?php foreach ($result['items'] as $item): ?>
                    <li class="news<?= empty($item['image_url']) ? ' news--no-media' : '' ?>">
                        <?php if (!empty($item['image_url'])): ?>
                            <a class="news__media" href="<?= $e($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= $e($item['image_url']) ?>" alt="" loading="lazy" width="220" height="140">
                            </a>
                        <?php endif; ?>
                        <div class="news__body">
                            <div class="news__meta">
                                <a class="news__category" href="<?= $e($buildUrl(['category' => $item['category_slug'], 'page' => 1])) ?>">
                                    <?= $e($item['category_name']) ?>
                                </a>
                                <time datetime="<?= $e($item['published_at']) ?>">
                                    <?= $e((new DateTimeImmutable($item['published_at']))->format('d.m.Y H:i')) ?>
                                </time>
                            </div>
                            <h2 class="news__title">
                                <a href="<?= $e($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= $e($item['title']) ?>
                                </a>
                            </h2>
                            <?php if (!empty($item['description'])): ?>
                                <p class="news__excerpt"><?= $e(mb_strimwidth(strip_tags($item['description']), 0, 220, '…')) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($result['pages'] > 1): ?>
                <nav class="pagination" aria-label="Страницы">
                    <?php if ($result['page'] > 1): ?>
                        <a href="<?= $e($buildUrl(['page' => $result['page'] - 1])) ?>">← Назад</a>
                    <?php endif; ?>
                    <span>Страница <?= (int) $result['page'] ?> из <?= (int) $result['pages'] ?></span>
                    <?php if ($result['page'] < $result['pages']): ?>
                        <a href="<?= $e($buildUrl(['page' => $result['page'] + 1])) ?>">Вперёд →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="footer">
        Источник: РИА Новости RSS · обновление каждые 5 минут
    </footer>
</div>
</body>
</html>
