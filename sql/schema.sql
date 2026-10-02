CREATE DATABASE IF NOT EXISTS news_aggregator
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE news_aggregator;

CREATE TABLE categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    slug       VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE news (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guid         VARCHAR(512) NOT NULL,
    title        VARCHAR(512) NOT NULL,
    link         VARCHAR(1024) NOT NULL,
    description  TEXT NULL,
    image_url    VARCHAR(1024) NULL,
    category_id  INT UNSIGNED NOT NULL,
    published_at DATETIME NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_news_guid (guid),
    KEY idx_news_published (published_at),
    KEY idx_news_category_published (category_id, published_at),
    KEY idx_news_date_category (published_at, category_id),
    CONSTRAINT fk_news_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE import_runs (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at     DATETIME NOT NULL,
    finished_at    DATETIME NULL,
    fetched_count  INT UNSIGNED NOT NULL DEFAULT 0,
    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count  INT UNSIGNED NOT NULL DEFAULT 0,
    status         ENUM('running', 'success', 'failed') NOT NULL DEFAULT 'running',
    error_message  TEXT NULL,
    KEY idx_import_runs_started (started_at)
) ENGINE=InnoDB;
