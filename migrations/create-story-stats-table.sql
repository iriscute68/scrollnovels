-- Create story_stats table for ranking aggregation
-- This stores daily aggregated metrics for ranking computation

CREATE TABLE IF NOT EXISTS `story_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary key',
    `story_id` INT NOT NULL COMMENT 'Foreign key to stories.id',
    `date` DATE NOT NULL COMMENT 'Day the stats belong to (YYYY-MM-DD)',
    
    -- Metrics
    `views` INT UNSIGNED DEFAULT 0 COMMENT 'Daily views',
    `unique_views` INT UNSIGNED DEFAULT 0 COMMENT 'Unique user views that day',
    `favorites` INT UNSIGNED DEFAULT 0 COMMENT 'New favorites added that day',
    `comments` INT UNSIGNED DEFAULT 0 COMMENT 'New comments that day',
    `reading_seconds` INT UNSIGNED DEFAULT 0 COMMENT 'Total reading time in seconds',
    `boosts` INT UNSIGNED DEFAULT 0 COMMENT 'Story boosts purchased/awarded that day',
    
    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY `unique_story_date` (`story_id`, `date`),
    KEY `idx_date` (`date`),
    KEY `idx_created_at` (`created_at`),
    
    -- Foreign key
    CONSTRAINT `fk_story_stats_story_id` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily aggregated story metrics for ranking computation';
