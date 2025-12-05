-- Reading Points System Database Schema
-- Run this migration to set up reading points tracking

-- 1. Create reading_sessions table
CREATE TABLE IF NOT EXISTS reading_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    chapter_id INT NOT NULL,
    minutes_read INT DEFAULT 0,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_created (created_at)
);

-- 2. Add supporter_points to users table (if not exists)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS supporter_points INT DEFAULT 0;

-- Add index for leaderboard queries
CREATE INDEX IF NOT EXISTS idx_supporter_points ON users(supporter_points DESC);
