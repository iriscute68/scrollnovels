-- Ads System Database Schema
-- Run this migration to set up ad system tables

-- 1. Create ads table
CREATE TABLE IF NOT EXISTS ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    package_views BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_status VARCHAR(50) DEFAULT 'pending',
    admin_verified BOOLEAN DEFAULT FALSE,
    proof_image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_status (payment_status)
);

-- 2. Create ad_messages table for chat
CREATE TABLE IF NOT EXISTS ad_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    sender VARCHAR(50) NOT NULL,
    message TEXT,
    image VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    INDEX idx_ad (ad_id)
);

-- 3. Update stories table with sponsored fields (if not exists)
ALTER TABLE stories 
ADD COLUMN IF NOT EXISTS is_sponsored BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS boost_level INT DEFAULT 0;

-- Add indexes for sorting
CREATE INDEX IF NOT EXISTS idx_sponsored ON stories(is_sponsored);
CREATE INDEX IF NOT EXISTS idx_boost ON stories(boost_level);
