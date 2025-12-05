<?php
// Create posts table for blog system
require_once __DIR__ . '/config/db.php';

try {
    // Check if table exists
    $check = $pdo->query("SHOW TABLES LIKE 'posts'");
    if ($check->rowCount() > 0) {
        echo "posts table already exists\n";
        exit;
    }

    // Create posts table
    $sql = "
    CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        category VARCHAR(50),
        tags VARCHAR(255),
        excerpt TEXT,
        cover_image VARCHAR(255),
        blocks LONGTEXT NOT NULL COMMENT 'JSON blocks for Quill editor',
        status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        published_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_slug (slug),
        INDEX idx_status (status),
        INDEX idx_user (user_id),
        INDEX idx_published (published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✓ posts table created successfully\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
