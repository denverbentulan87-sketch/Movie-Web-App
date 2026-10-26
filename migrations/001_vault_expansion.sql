-- ============================================================================
-- Cinema Vault — migration 001: personal movie library expansion
-- ----------------------------------------------------------------------------
-- Adds: favorites, priority, rewatch tracking, personal notes, future-ready
-- movie metadata, tags, collections and watch history.
--
-- Idempotent: safe to run more than once. Run it with:
--     php migrate.php
-- or import this file in phpMyAdmin (MariaDB 10.4+ / XAMPP).
-- Existing rows are preserved; nothing is dropped or rewritten except the
-- watch-history backfill at the bottom (which only touches Watched movies
-- that have no history yet).
-- ============================================================================

-- ── 1. movie_watchlist: new columns ─────────────────────────────────────────
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `is_favorite` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `priority` ENUM('Low','Normal','High') NOT NULL DEFAULT 'Normal';
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `watch_count` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `last_watched_at` DATE NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `personal_notes` TEXT NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `notes_spoiler` TINYINT(1) NOT NULL DEFAULT 0;
-- future-ready metadata (all optional; filled manually now, by an API later)
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `external_movie_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `release_date` DATE NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `release_year` SMALLINT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `runtime_minutes` SMALLINT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `director` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `cast_members` TEXT NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `language` VARCHAR(50) NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `movie_watchlist` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ── 2. movie_watchlist: indexes ─────────────────────────────────────────────
-- (watchlist_id, user_id) lets child tables use a composite foreign key, so the
-- database itself refuses to link a movie to another user's tag/collection.
ALTER TABLE `movie_watchlist` ADD UNIQUE INDEX IF NOT EXISTS `uq_movie_owner` (`watchlist_id`, `user_id`);
ALTER TABLE `movie_watchlist` ADD INDEX IF NOT EXISTS `idx_user_status_priority` (`user_id`, `status`, `priority`);
ALTER TABLE `movie_watchlist` ADD INDEX IF NOT EXISTS `idx_user_favorite` (`user_id`, `is_favorite`);
ALTER TABLE `movie_watchlist` ADD INDEX IF NOT EXISTS `idx_user_external` (`user_id`, `external_movie_id`);

-- ── 3. tags ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tags` (
  `tag_id`     INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `name`       VARCHAR(50) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `uq_tags_user_name` (`user_id`, `name`),
  UNIQUE KEY `uq_tags_owner` (`tag_id`, `user_id`),
  CONSTRAINT `fk_tags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `movie_tags` (
  `movie_id`   INT(11) NOT NULL,
  `tag_id`     INT(11) NOT NULL,
  `user_id`    INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movie_id`, `tag_id`),
  KEY `idx_movie_tags_tag` (`tag_id`),
  CONSTRAINT `fk_movie_tags_movie` FOREIGN KEY (`movie_id`, `user_id`) REFERENCES `movie_watchlist` (`watchlist_id`, `user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_movie_tags_tag`   FOREIGN KEY (`tag_id`, `user_id`)   REFERENCES `tags` (`tag_id`, `user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 4. collections ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `collections` (
  `collection_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11) NOT NULL,
  `name`          VARCHAR(100) NOT NULL,
  `description`   VARCHAR(500) NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`collection_id`),
  UNIQUE KEY `uq_collections_user_name` (`user_id`, `name`),
  UNIQUE KEY `uq_collections_owner` (`collection_id`, `user_id`),
  CONSTRAINT `fk_collections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `collection_movies` (
  `collection_id` INT(11) NOT NULL,
  `movie_id`      INT(11) NOT NULL,
  `user_id`       INT(11) NOT NULL,
  `added_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`collection_id`, `movie_id`),
  KEY `idx_collection_movies_movie` (`movie_id`),
  CONSTRAINT `fk_collection_movies_collection` FOREIGN KEY (`collection_id`, `user_id`) REFERENCES `collections` (`collection_id`, `user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_collection_movies_movie`      FOREIGN KEY (`movie_id`, `user_id`)      REFERENCES `movie_watchlist` (`watchlist_id`, `user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 5. watch history ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `watch_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `movie_id`   INT(11) NOT NULL,
  `user_id`    INT(11) NOT NULL,
  `watched_at` DATE NOT NULL,
  `rating`     TINYINT UNSIGNED NULL DEFAULT NULL,
  `notes`      TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_history_user_date` (`user_id`, `watched_at`),
  KEY `idx_history_movie_date` (`movie_id`, `watched_at`),
  CONSTRAINT `fk_history_movie` FOREIGN KEY (`movie_id`, `user_id`) REFERENCES `movie_watchlist` (`watchlist_id`, `user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 6. backfill: every existing Watched movie gets one history entry ────────
INSERT INTO `watch_history` (`movie_id`, `user_id`, `watched_at`, `rating`, `notes`)
SELECT m.`watchlist_id`, m.`user_id`, DATE(m.`date_added`), NULLIF(m.`rating`, 0), NULL
FROM `movie_watchlist` m
WHERE m.`status` = 'Watched'
  AND NOT EXISTS (SELECT 1 FROM `watch_history` w WHERE w.`movie_id` = m.`watchlist_id`);

UPDATE `movie_watchlist` m
SET m.`watch_count` = (SELECT COUNT(*) FROM `watch_history` w WHERE w.`movie_id` = m.`watchlist_id`),
    m.`last_watched_at` = (SELECT MAX(w2.`watched_at`) FROM `watch_history` w2 WHERE w2.`movie_id` = m.`watchlist_id`)
WHERE m.`status` = 'Watched' AND m.`watch_count` = 0;
