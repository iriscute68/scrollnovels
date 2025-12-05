<?php
// Admin setup: create missing tables for plagiarism, chapter history, etc.
require_once __DIR__ . '/../config/db.php';

echo "Creating missing admin feature tables...\n";

$tables = array(
    "CREATE TABLE IF NOT EXISTS plagiarism_scans (
        id INT PRIMARY KEY AUTO_INCREMENT,
        chapter_id INT NOT NULL,
        story_id INT NOT NULL,
        admin_id INT,
        status VARCHAR(50) DEFAULT 'queued',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        report_id INT,
        compare_url VARCHAR(255),
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        KEY (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS plagiarism_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        scan_id INT,
        chapter_id INT NOT NULL,
        story_id INT NOT NULL,
        admin_id INT,
        score FLOAT DEFAULT 0.0,
        matches_json LONGTEXT,
        status VARCHAR(50) DEFAULT 'open',
        resolved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        KEY (status),
        KEY (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS chapter_versions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        chapter_id INT NOT NULL,
        title VARCHAR(255),
        content LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(100),
        target_type VARCHAR(100),
        target_id INT,
        reporter_id INT,
        reporter_ip VARCHAR(45),
        reason TEXT,
        priority VARCHAR(50) DEFAULT 'normal',
        status VARCHAR(50) DEFAULT 'open',
        assignee_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        KEY (status),
        KEY (target_type),
        KEY (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS judge_scores (
        id INT PRIMARY KEY AUTO_INCREMENT,
        entry_id INT,
        judge_id INT,
        score FLOAT,
        rubric LONGTEXT,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (entry_id, judge_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Table created/verified\n";
    } catch (Exception $e) {
        echo "⚠ " . substr($e->getMessage(), 0, 100) . "\n";
    }
}

// Alter chapters table to add is_paid column if missing
try {
    $pdo->exec("ALTER TABLE chapters ADD COLUMN is_paid TINYINT DEFAULT 0");
    echo "✓ is_paid column added to chapters\n";
} catch (Exception $e) {
    // Column may already exist
    echo "ℹ is_paid column check: " . substr($e->getMessage(), 0, 50) . "\n";
}

echo "Setup complete.\n";

?>
