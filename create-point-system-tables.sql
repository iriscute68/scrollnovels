-- Point System Database Tables

-- 1. Points Transactions (tracks all point earning/spending)
CREATE TABLE IF NOT EXISTS points_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(36) NOT NULL,
    points INT NOT NULL,
    type ENUM('free', 'premium', 'patreon') DEFAULT 'free',
    source ENUM('daily_login', 'reading', 'ad_watch', 'task_complete', 'event_reward', 'purchase', 'patreon_tier', 'admin_grant') DEFAULT 'daily_login',
    reference_id INT COMMENT 'Foreign key to related table',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User Points Balance (current balance for each user)
CREATE TABLE IF NOT EXISTS user_points (
    user_id CHAR(36) PRIMARY KEY,
    free_points INT DEFAULT 0,
    premium_points INT DEFAULT 0,
    patreon_points INT DEFAULT 0,
    total_points INT DEFAULT 0,
    last_login DATE,
    daily_login_claimed BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Book Support (tracking support/donations to books)
CREATE TABLE IF NOT EXISTS book_support (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(36) NOT NULL,
    book_id INT NOT NULL,
    points_spent INT NOT NULL,
    point_type ENUM('free', 'premium', 'patreon') DEFAULT 'free',
    multiplier DECIMAL(3,2) DEFAULT 1.00 COMMENT '1.0 for free, 2.0 for premium, 3.0 for patreon',
    effective_points INT GENERATED ALWAYS AS (CAST(points_spent * multiplier AS SIGNED)) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_book_id (book_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Book Rankings (calculated daily, weekly, monthly)
CREATE TABLE IF NOT EXISTS book_rankings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    rank_type ENUM('daily', 'weekly', 'monthly', 'all_time') NOT NULL,
    total_support_points INT DEFAULT 0,
    supporter_count INT DEFAULT 0,
    rank_position INT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ranking (book_id, rank_type),
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    INDEX idx_rank_type (rank_type),
    INDEX idx_total_support (total_support_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Patreon Links (linking user Patreon accounts to website)
CREATE TABLE IF NOT EXISTS patreon_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(36) NOT NULL UNIQUE,
    patreon_user_id VARCHAR(50) NOT NULL UNIQUE,
    patreon_email VARCHAR(255),
    tier_id VARCHAR(50),
    tier_name VARCHAR(100),
    amount_cents INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    last_reward_date TIMESTAMP,
    next_reward_date TIMESTAMP,
    access_token VARCHAR(255),
    refresh_token VARCHAR(255),
    token_expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_patreon_user_id (patreon_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Patreon Tier Rewards (configuration for point rewards per tier)
CREATE TABLE IF NOT EXISTS patreon_tier_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tier_id VARCHAR(50) UNIQUE,
    tier_name VARCHAR(100) NOT NULL,
    price_cents INT,
    monthly_points INT DEFAULT 0,
    bonus_multiplier DECIMAL(3,2) DEFAULT 1.0 COMMENT '3.0 for triple boost in rankings',
    features JSON COMMENT 'Array of features: no_ads, early_chapters, free_coins, vip_badge',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. User Tasks (for earning free points)
CREATE TABLE IF NOT EXISTS user_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(36) NOT NULL,
    task_type ENUM('daily_login', 'read_3_chapters', 'watch_ad', 'leave_review', 'invite_friend') DEFAULT 'daily_login',
    points_reward INT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_task_type (task_type),
    INDEX idx_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Creator Bonus Points (authors' weekly boost for their own books)
CREATE TABLE IF NOT EXISTS creator_bonus_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(36) NOT NULL,
    book_id INT NOT NULL,
    points_to_spend INT DEFAULT 100 COMMENT 'Weekly allowance',
    points_used INT DEFAULT 0,
    week_start_date DATE,
    reset_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_author_weekly (user_id, book_id, week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Point Decay Log (optional - tracks when points expire)
CREATE TABLE IF NOT EXISTS point_decay_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    original_points INT,
    decayed_points INT,
    decay_percentage INT COMMENT 'E.g., 20 for 20% decay',
    decay_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default Patreon tier rewards
INSERT INTO patreon_tier_rewards (tier_id, tier_name, price_cents, monthly_points, bonus_multiplier, features) VALUES
('bronze_tier', 'Bronze Supporter', 500, 500, 2.0, '["vip_badge"]'),
('silver_tier', 'Silver Supporter', 1000, 1200, 2.5, '["vip_badge", "early_chapters"]'),
('gold_tier', 'Gold Supporter', 2000, 3000, 3.0, '["no_ads", "early_chapters", "vip_badge"]'),
('diamond_tier', 'Diamond Supporter', 5000, 10000, 3.0, '["no_ads", "early_chapters", "free_coins", "vip_badge", "boost_support"]')
ON DUPLICATE KEY UPDATE monthly_points=VALUES(monthly_points);
