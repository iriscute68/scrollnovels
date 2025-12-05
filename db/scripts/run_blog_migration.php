<?php
// migrations/005_blog_comments.sql runner
require_once __DIR__ . '/config.php';

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS blog_comments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        blog_post_id INT NOT NULL,
        user_id INT NOT NULL,
        comment_text TEXT NOT NULL,
        is_approved TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (blog_post_id) REFERENCES announcements(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_blog_post (blog_post_id),
        INDEX idx_user (user_id),
        INDEX idx_approved (is_approved)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

try {
    foreach ($sql_statements as $sql) {
        $pdo->exec($sql);
        echo "✓ Executed: " . substr($sql, 0, 50) . "...\n";
    }
    echo "\n✅ Blog comments migration complete!\n";
} catch (Exception $e) {
    die("Migration error: " . $e->getMessage());
}
?>

