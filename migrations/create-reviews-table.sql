-- Create reviews table for book/story reviews
-- Enforces 1 review per user per story with unique constraint

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Review ID',
    `user_id` INT NOT NULL COMMENT 'Foreign key to users.id',
    `book_id` INT NOT NULL COMMENT 'Foreign key to books.id (or stories)',
    `rating` TINYINT NOT NULL COMMENT 'Rating 1-5 stars',
    `review_text` LONGTEXT COMMENT 'Review content',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Ensure 1 review per user per story
    UNIQUE KEY `unique_user_book_review` (`user_id`, `book_id`),
    
    -- Indexes for queries
    KEY `idx_book_id` (`book_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    
    -- Foreign keys
    CONSTRAINT `fk_reviews_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_book_id` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User reviews and ratings for books/stories';

-- Create review reports table for moderation
CREATE TABLE IF NOT EXISTS `review_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Report ID',
    `review_id` INT NOT NULL COMMENT 'Foreign key to reviews.id',
    `user_id` INT NOT NULL COMMENT 'User who reported, FK to users.id',
    `reason` TEXT COMMENT 'Reason for report',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY `idx_review_id` (`review_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    
    -- Foreign keys
    CONSTRAINT `fk_review_reports_review_id` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_reports_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reports about inappropriate reviews for moderation';
