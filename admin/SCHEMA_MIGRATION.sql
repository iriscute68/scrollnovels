-- Admin Panel Database Schema Migration
-- Run this to create all necessary tables for the admin system

-- User activity logs
CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  action TEXT,
  ip VARCHAR(64) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Moderation logs
CREATE TABLE IF NOT EXISTS moderation_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  action VARCHAR(255),
  target_id INT DEFAULT NULL,
  target_type VARCHAR(50),
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id),
  INDEX (target_id),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User warnings
CREATE TABLE IF NOT EXISTS user_warnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  reason TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Story change logs (audit trail)
CREATE TABLE IF NOT EXISTS story_change_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  story_id INT DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  before_data JSON,
  after_data JSON,
  action VARCHAR(50) DEFAULT 'edit',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (story_id),
  INDEX (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chapter logs
CREATE TABLE IF NOT EXISTS chapter_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  chapter_id INT DEFAULT NULL,
  event TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (chapter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chapter monetization
CREATE TABLE IF NOT EXISTS chapter_monetization (
  chapter_id INT PRIMARY KEY,
  is_paid TINYINT(1) DEFAULT 0,
  price DECIMAL(10, 2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Story tags
CREATE TABLE IF NOT EXISTS story_tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  story_id INT DEFAULT NULL,
  tag_id INT DEFAULT NULL,
  INDEX (story_id),
  INDEX (tag_id),
  UNIQUE KEY story_tag_unique (story_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags
CREATE TABLE IF NOT EXISTS tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL UNIQUE,
  slug VARCHAR(191) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tag change logs
CREATE TABLE IF NOT EXISTS tag_change_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tag_id INT DEFAULT NULL,
  admin_id INT DEFAULT NULL,
  before_data JSON,
  after_data JSON,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (tag_id),
  INDEX (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tag merge logs
CREATE TABLE IF NOT EXISTS tag_merge_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  primary_tag INT NOT NULL,
  merged_tag INT NOT NULL,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genres
CREATE TABLE IF NOT EXISTS genres (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comment reports
CREATE TABLE IF NOT EXISTS comment_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT DEFAULT NULL,
  reporter_id INT DEFAULT NULL,
  reason TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (comment_id),
  INDEX (reporter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blacklist words
CREATE TABLE IF NOT EXISTS blacklist_words (
  id INT AUTO_INCREMENT PRIMARY KEY,
  word VARCHAR(255) NOT NULL UNIQUE,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pinned reviews
CREATE TABLE IF NOT EXISTS pinned_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id INT NOT NULL UNIQUE,
  pinned_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Featured stories
CREATE TABLE IF NOT EXISTS featured_stories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  story_id INT NOT NULL,
  featured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  starts_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users (if separate from main users table)
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255),
  role VARCHAR(50) DEFAULT 'moderator',
  permissions JSON DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support tickets
CREATE TABLE IF NOT EXISTS support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  subject VARCHAR(255),
  body TEXT,
  status VARCHAR(50) DEFAULT 'open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support ticket messages
CREATE TABLE IF NOT EXISTS support_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT DEFAULT NULL,
  sender_id INT DEFAULT NULL,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (ticket_id),
  INDEX (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings
CREATE TABLE IF NOT EXISTS site_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(255) NOT NULL UNIQUE,
  value TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alter existing chapters table to add sort_order if needed
ALTER TABLE chapters ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

-- Alter users table to ensure roles column exists (JSON)
ALTER TABLE users ADD COLUMN IF NOT EXISTS roles JSON DEFAULT JSON_ARRAY('reader');

-- All tables updated successfully
