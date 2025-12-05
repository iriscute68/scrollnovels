<?php
// scripts/create_forum_support_tables.php
require_once dirname(__DIR__) . '/config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discussion_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        author_id INT DEFAULT NULL,
        content LONGTEXT,
        upvotes INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert a default category if none exists
    $cnt = $pdo->query('SELECT COUNT(*) as c FROM forum_categories')->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    if ($cnt == 0) {
        $stmt = $pdo->prepare('INSERT INTO forum_categories (name, slug) VALUES (?, ?)');
        $stmt->execute(['General Chat', 'general-chat']);
    }
    echo "Created support tables and ensured default category.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
return 0;
