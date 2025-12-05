<?php
// Fix script for critical issues
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

require 'config/db.php';

echo "=== Starting Critical Fixes ===\n\n";

// Issue 1: Fix blog_comment_replies FK constraint
echo "1. Fixing blog_comment_replies FK constraint...\n";
try {
    // First drop existing FK if it exists
    $pdo->exec("ALTER TABLE blog_comment_replies DROP FOREIGN KEY fk_comment_id");
    echo "   - Dropped existing FK (if any)\n";
} catch (Exception $e) {
    echo "   - No existing FK to drop (expected)\n";
}

try {
    $pdo->exec("ALTER TABLE blog_comment_replies ADD CONSTRAINT fk_comment_id FOREIGN KEY (comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE");
    echo "   ✓ FK constraint added successfully\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Issue 2: Add submitted_at column to competition_entries
echo "2. Adding submitted_at column to competition_entries...\n";
try {
    // Check if column already exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='competition_entries' AND COLUMN_NAME='submitted_at'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE competition_entries ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER created_at");
        echo "   ✓ submitted_at column added successfully\n\n";
    } else {
        echo "   ✓ submitted_at column already exists\n\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Issue 3: Verify author_supporters table exists (for supporters API)
echo "3. Verifying author_supporters table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS author_supporters (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        author_id INT UNSIGNED NOT NULL,
        supporter_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED DEFAULT 0,
        points_total INT DEFAULT 0,
        last_supported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_support (author_id, supporter_id),
        INDEX idx_author (author_id),
        INDEX idx_supporter (supporter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ author_supporters table verified/created\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Issue 4: Verify supporters table exists (for monetary support)
echo "4. Verifying supporters table...\n";
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS supporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supporter_id INT NOT NULL,
        author_id INT NOT NULL,
        tip_amount DECIMAL(10, 2) DEFAULT 0,
        patreon_tier VARCHAR(100),
        kofi_reference VARCHAR(255),
        patreon_pledge_id VARCHAR(255),
        status ENUM('active', 'cancelled', 'pending') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_support (supporter_id, author_id),
        INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ supporters table verified/created\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

echo "=== All Critical Fixes Complete ===\n";
?>
