# Новостной агрегатор

Native PHP 8 / MySQL / Memcached. Сбор RSS с РИА Новости, хранение в БД, веб-выдача с фильтрами и кешированием.

## Быстрый старт

```bash
docker compose up -d --build
docker compose exec app php bin/fetch.php
```

Откройте http://localhost:8080

## Структура

- `bin/fetch.php` — CLI-импорт RSS (в контейнере по cron каждые 5 минут)
- `public/` — веб-интерфейс
- `src/` — PHP-классы (RSS, репозитории, кеш, импорт)
- `sql/schema.sql` — схема БД с индексами
- `config/config.php` — конфигурация через env

## Фильтры

- категория
- диапазон дат (`date_from`, `date_to`)
- пагинация

Списки новостей и категории кешируются в Memcached. После импорта кеш сбрасывается.
