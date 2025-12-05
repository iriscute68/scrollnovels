<?php
// includes/db_migrations.php - lightweight schema guards
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/config/db.php';
}

function ensure_competitions_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS competitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            rules LONGTEXT,
            prize VARCHAR(255),
            cover_image VARCHAR(255),
            prize_pool DECIMAL(10,2) DEFAULT 0,
            start_date DATETIME,
            end_date DATETIME,
            max_entries INT DEFAULT 0,
            entry_count INT DEFAULT 0,
            auto_win_by ENUM('none','views','likes','chapters') DEFAULT 'none',
            min_chapters INT DEFAULT 0,
            min_words INT DEFAULT 0,
            prize_info JSON NULL,
            requirements_json JSON NULL,
            created_by INT NULL,
            status ENUM('draft','active','upcoming','closed') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (status), INDEX (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Add missing columns with INFORMATION_SCHEMA fallback (for MySQL < 8)
        $columns = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions'")
                ->fetchAll(PDO::FETCH_COLUMN);
        $has = function($col) use ($columns) { return in_array($col, $columns, true); };
        if (!$has('rules')) $pdo->exec("ALTER TABLE competitions ADD COLUMN rules LONGTEXT");
        if (!$has('prize')) $pdo->exec("ALTER TABLE competitions ADD COLUMN prize VARCHAR(255)");
        if (!$has('cover_image')) $pdo->exec("ALTER TABLE competitions ADD COLUMN cover_image VARCHAR(255)");
        if (!$has('auto_win_by')) $pdo->exec("ALTER TABLE competitions ADD COLUMN auto_win_by ENUM('none','views','likes','chapters') DEFAULT 'none'");
        if (!$has('min_chapters')) $pdo->exec("ALTER TABLE competitions ADD COLUMN min_chapters INT DEFAULT 0");
        if (!$has('min_words')) $pdo->exec("ALTER TABLE competitions ADD COLUMN min_words INT DEFAULT 0");
        if (!$has('max_entries')) $pdo->exec("ALTER TABLE competitions ADD COLUMN max_entries INT DEFAULT 0");
        if (!$has('prize_info')) $pdo->exec("ALTER TABLE competitions ADD COLUMN prize_info JSON NULL");
        if (!$has('requirements_json')) $pdo->exec("ALTER TABLE competitions ADD COLUMN requirements_json JSON NULL");
        if (!$has('created_by')) $pdo->exec("ALTER TABLE competitions ADD COLUMN created_by INT NULL");
        if ($has('name')) $pdo->exec("ALTER TABLE competitions MODIFY COLUMN name VARCHAR(255) NULL");
        if (!$has('title')) $pdo->exec("ALTER TABLE competitions ADD COLUMN title VARCHAR(255) NOT NULL");
    } catch (Exception $e) {
        // Silently ignore on older MySQL that lacks IF NOT EXISTS for ALTER; table creation above should suffice
    }
}

function ensure_announcements_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT,
            link VARCHAR(500),
            image VARCHAR(500),
            active_from DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Optional extras with column checks
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'")
                 ->fetchAll(PDO::FETCH_COLUMN);
        $hasCol = function($c) use ($cols) { return in_array($c, $cols, true); };
        if (!$hasCol('featured_image')) $pdo->exec("ALTER TABLE announcements ADD COLUMN featured_image VARCHAR(500)");
        if (!$hasCol('is_blog')) $pdo->exec("ALTER TABLE announcements ADD COLUMN is_blog TINYINT DEFAULT 0");
    } catch (Exception $e) {}
}

function ensure_saved_stories_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS saved_stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            story_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_saved (user_id, story_id),
            INDEX idx_story (story_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function ensure_reading_progress_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reading_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            story_id INT UNSIGNED NOT NULL,
            chapter_number INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_progress (user_id, story_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function ensure_guide_pages_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS guide_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) UNIQUE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            content LONGTEXT,
            order_index INT DEFAULT 0,
            published TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function ensure_blog_posts_schema(PDO $pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            author VARCHAR(100) DEFAULT 'Staff',
            content LONGTEXT,
            excerpt TEXT,
            category VARCHAR(50) DEFAULT 'Update',
            image VARCHAR(10) DEFAULT '📰',
            badge VARCHAR(50),
            type VARCHAR(50) DEFAULT 'update',
            views INT DEFAULT 0,
            is_pinned TINYINT DEFAULT 0,
            published TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_published (published),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

// Auto-run guards in common entry points
try {
    ensure_competitions_schema($pdo);
    ensure_announcements_schema($pdo);
    ensure_saved_stories_schema($pdo);
    ensure_reading_progress_schema($pdo);
    ensure_guide_pages_schema($pdo);
    ensure_blog_posts_schema($pdo);
} catch (Exception $e) {}

?>