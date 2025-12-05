-- db/migrations/20251115_competitions_system.sql
-- Full competition management system migration

-- 1. Enhanced competitions table (add missing columns if needed)
ALTER TABLE competitions ADD COLUMN IF NOT EXISTS winner_entry_id INT NULL;
ALTER TABLE competitions ADD COLUMN IF NOT EXISTS auto_win_by VARCHAR(50) DEFAULT 'none';
ALTER TABLE competitions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- 2. Notifications table (for in-site messages)
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

-- 3. Admin activity log (for audit trail)
CREATE TABLE IF NOT EXISTS admin_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  action VARCHAR(255),
  meta JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Competition winners history (audit)
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

-- 5. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_comp_entries_comp ON competition_entries (competition_id);
CREATE INDEX IF NOT EXISTS idx_comp_entries_user ON competition_entries (user_id);
CREATE INDEX IF NOT EXISTS idx_comp_entries_status ON competition_entries (status);
CREATE INDEX IF NOT EXISTS idx_book_stats_book ON book_stats (book_id);
CREATE INDEX IF NOT EXISTS idx_competitions_status ON competitions (status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications (user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_admin_activity_admin ON admin_activity (admin_id);

-- 6. Ensure competition_entries table has all needed columns
ALTER TABLE competition_entries ADD COLUMN IF NOT EXISTS status ENUM('submitted','disqualified','approved') DEFAULT 'submitted';

-- Verification: Show all competition-related tables
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'scroll_novels' AND TABLE_NAME LIKE '%compet%';
