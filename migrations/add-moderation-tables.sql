-- Add suspension_until column to users table
ALTER TABLE users ADD COLUMN suspension_until DATETIME NULL DEFAULT NULL AFTER status;

-- Create user_mutes table for tracking muted users
CREATE TABLE IF NOT EXISTS user_mutes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    moderator_id INT NOT NULL,
    reason VARCHAR(255),
    muted_until DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create user_moderation_log table for tracking all moderation actions
CREATE TABLE IF NOT EXISTS user_moderation_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    moderator_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'mute', 'temp_ban', 'perm_ban', 'unmute', 'unban'
    reason VARCHAR(255),
    duration_days INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX idx_user_mutes_muted_until ON user_mutes(muted_until);
CREATE INDEX idx_user_moderation_log_user ON user_moderation_log(user_id);
