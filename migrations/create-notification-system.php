<?php
require_once 'config/db.php';

$migrations = [
    'follows' => "
        CREATE TABLE IF NOT EXISTS follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            story_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_follow (user_id, story_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (story_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'notifications' => "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255),
            message TEXT,
            link VARCHAR(500),
            icon VARCHAR(50),
            data JSON,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (is_read),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'user_notification_settings' => "
        CREATE TABLE IF NOT EXISTS user_notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            new_chapter TINYINT(1) DEFAULT 1,
            comment TINYINT(1) DEFAULT 1,
            reply TINYINT(1) DEFAULT 1,
            review TINYINT(1) DEFAULT 1,
            rating TINYINT(1) DEFAULT 1,
            system TINYINT(1) DEFAULT 1,
            monetization TINYINT(1) DEFAULT 1,
            email_notifications TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($migrations as $table_name => $create_sql) {
    try {
        $pdo->exec($create_sql);
        echo "✓ Created or verified $table_name table\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "✓ $table_name table already exists\n";
        } else {
            echo "✗ Error creating $table_name: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n✓ Notification system tables created successfully!\n";
?>
