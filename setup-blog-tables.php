<?php
// Create missing database tables for blog and admin systems
require_once __DIR__ . '/config/db.php';

try {
    echo "=== Database Table Creation ===\n\n";

    // 1. Create announcement_reads table
    $check = $pdo->query("SHOW TABLES LIKE 'announcement_reads'");
    if ($check->rowCount() === 0) {
        $sql = "
        CREATE TABLE announcement_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            announcement_id INT NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
            UNIQUE KEY unique_read (user_id, announcement_id),
            INDEX idx_user (user_id),
            INDEX idx_announcement (announcement_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
        echo "✓ Created announcement_reads table\n";
    } else {
        echo "✓ announcement_reads table already exists\n";
    }

    // 2. Create blog_comments table
    $check = $pdo->query("SHOW TABLES LIKE 'blog_comments'");
    if ($check->rowCount() === 0) {
        $sql = "
        CREATE TABLE blog_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_post_id INT NOT NULL,
            user_id INT NOT NULL,
            comment_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (blog_post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_post (blog_post_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
        echo "✓ Created blog_comments table\n";
    } else {
        echo "✓ blog_comments table already exists\n";
    }

    // 3. Verify announcements table has required columns
    $check = $pdo->query("SHOW TABLES LIKE 'announcements'");
    if ($check->rowCount() > 0) {
        $columns = $pdo->query("DESCRIBE announcements")->fetchAll();
        $colNames = array_column($columns, 'Field');
        
        if (!in_array('active_from', $colNames)) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN active_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✓ Added active_from column to announcements\n";
        }
        if (!in_array('active_until', $colNames)) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN active_until TIMESTAMP NULL");
            echo "✓ Added active_until column to announcements\n";
        }
        if (!in_array('is_pinned', $colNames)) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN is_pinned BOOLEAN DEFAULT FALSE");
            echo "✓ Added is_pinned column to announcements\n";
        }
        if (!in_array('type', $colNames)) {
            $pdo->exec("ALTER TABLE announcements ADD COLUMN type VARCHAR(50) DEFAULT 'announcement'");
            echo "✓ Added type column to announcements\n";
        }
    }

    echo "\n=== All tables created successfully ===\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
