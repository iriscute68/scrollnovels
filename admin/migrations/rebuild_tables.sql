-- Complete Scroll Novels Database Schema
-- Reset and recreate all core tables

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS stories;
DROP TABLE IF EXISTS chapters;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS forum_topics;
DROP TABLE IF EXISTS forum_categories;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS follows;
DROP TABLE IF EXISTS notifications;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    bio TEXT,
    avatar_url VARCHAR(500),
    role ENUM('reader', 'author', 'editor', 'admin', 'moderator') DEFAULT 'reader',
    status ENUM('active', 'suspended', 'verified') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verification_date TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Stories Table
CREATE TABLE stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    genre VARCHAR(100),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    rating DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_author (author_id),
    INDEX idx_status (status)
);

-- Chapters Table
CREATE TABLE chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    chapter_number INT,
    title VARCHAR(255),
    content LONGTEXT,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_story (story_id)
);

-- Comments Table
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chapter_id INT NOT NULL,
    content TEXT,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_chapter (chapter_id)
);

-- Reviews Table
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    story_id INT NOT NULL,
    rating INT,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_story (story_id)
);

-- Forum Categories
CREATE TABLE forum_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Forum Topics Table
CREATE TABLE forum_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT,
    views INT DEFAULT 0,
    replies INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES forum_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_user (user_id)
);

-- Achievements Table
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(500),
    icon VARCHAR(50),
    total INT DEFAULT 1,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Achievements Table
CREATE TABLE user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    progress INT DEFAULT 0,
    unlocked TINYINT(1) DEFAULT 0,
    unlocked_date DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id)
);

-- Follows Table
CREATE TABLE follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, following_id),
    INDEX idx_following (following_id)
);

-- Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(255),
    message TEXT,
    related_user_id INT,
    related_story_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (related_story_id) REFERENCES stories(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_is_read (is_read)
);

-- Insert 30 achievements
INSERT INTO achievements (name, description, icon, total, category) VALUES
('First Login', 'Logged into your account for the first time', '🌱', 1, 'Milestone'),
('Bookworm', 'Read 5 chapters', '📖', 5, 'Reading'),
('Fast Reader', 'Read 20 chapters in 24 hours', '⚡', 20, 'Reading'),
('Supporter', 'Made your first donation', '💖', 1, 'Community'),
('Big Tipper', 'Donated 50 GHS or more', '💰', 50, 'Community'),
('Critic', 'Posted 1 review', '✍️', 1, 'Community'),
('Reviewer Pro', 'Posted 10 reviews', '⭐', 10, 'Community'),
('Loyal Reader', 'Visited the site 7 days in a row', '🔥', 7, 'Milestone'),
('Reading Marathon', 'Read for 4 hours straight', '⏳', 4, 'Reading'),
('Early Bird', 'Logged in before 6 AM', '🌅', 1, 'Milestone'),
('Night Owl', 'Read past midnight', '🌙', 1, 'Milestone'),
('Social Star', 'Liked 20 chapters', '👍', 20, 'Community'),
('Explorer', 'Visited 10 different books', '🗺️', 10, 'Reading'),
('Collector', 'Added 5 books to your library', '📚', 5, 'Reading'),
('Super Fan', 'Followed 10 authors', '🎀', 10, 'Community'),
('Artist Spotlight', 'Uploaded a profile picture', '📸', 1, 'Milestone'),
('Community Helper', 'Posted in the forum', '💬', 1, 'Community'),
('Silent Ninja', 'Read 50 chapters quietly', '🥷', 50, 'Reading'),
('Chapter Devourer', 'Read 200 chapters in total', '🍽️', 200, 'Reading'),
('Veteran', 'Account older than 1 year', '🏆', 12, 'Milestone'),
('Mystery Solver', 'Completed a mystery novel', '🕵️', 1, 'Reading'),
('Romantic Soul', 'Completed a romance novel', '💘', 1, 'Reading'),
('Fantasy Hero', 'Completed a fantasy novel', '🪄', 1, 'Reading'),
('Top Donor', 'Donated over 200 GHS', '👑', 200, 'Community'),
('VIP Reader', 'Reached level 10 reading rank', '🌟', 10, 'Milestone'),
('Ultra Fan', 'Liked 100 chapters', '❤️', 100, 'Community'),
('Archivist', 'Read 10 completed novels', '🏺', 10, 'Reading'),
('Speed Demon', 'Finished a chapter under 2 minutes', '🚀', 1, 'Reading'),
('Elite Reader', 'Reach top 10 in leaderboard', '🥇', 1, 'Milestone'),
('Legend', 'Complete ALL achievements', '🔥💎', 30, 'Milestone');

-- Insert test users
INSERT INTO users (username, email, password, first_name, last_name, role, status, is_verified) VALUES
('testuser', 'testuser@scrollnovels.com', '$2y$10$zBFpMLhP8VhkWBpf9MpOXeM2O1ckVWiQgU3iU9Gq1PzQzvLxu5WrS', 'Test', 'User', 'reader', 'active', 1),
('testauthor', 'author@scrollnovels.com', '$2y$10$zBFpMLhP8VhkWBpf9MpOXeM2O1ckVWiQgU3iU9Gq1PzQzvLxu5WrS', 'Test', 'Author', 'author', 'active', 1);
