-- 1. Add competition_winner support (nullable until assigned)
ALTER TABLE IF EXISTS competitions
  ADD COLUMN IF NOT EXISTS winner_entry_id INT NULL,
  ADD COLUMN IF NOT EXISTS auto_winner_method VARCHAR(50) DEFAULT 'none';

-- 2. In-site notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT,
  url VARCHAR(255),
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Indexes to speed leaderboard queries
CREATE INDEX IF NOT EXISTS idx_comp_entries_comp ON competition_entries (competition_id);
CREATE INDEX IF NOT EXISTS idx_book_stats_book ON book_stats (book_id);
CREATE INDEX IF NOT EXISTS idx_competitions_status ON competitions (status);

-- 4. Admin log table (if not yet created)
CREATE TABLE IF NOT EXISTS admin_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  action VARCHAR(255),
  meta JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Competition winner history (audit)
CREATE TABLE IF NOT EXISTS competition_winners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NOT NULL,
  entry_id INT NOT NULL,
  winner_user_id INT NOT NULL,
  method VARCHAR(100),
  awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
  FOREIGN KEY (entry_id) REFERENCES competition_entries(id) ON DELETE CASCADE,
  FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Blog posts table (stores header metadata)
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(100) DEFAULT 'Update',
  tags VARCHAR(512) DEFAULT '',
  excerpt TEXT,
  cover_image VARCHAR(255) DEFAULT NULL,
  status ENUM('draft','published') DEFAULT 'draft',
  blocks JSON NOT NULL,
  views BIGINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  published_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Blog posts indexes
CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_posts_user ON posts(user_id);
