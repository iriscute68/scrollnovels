<?php
require_once 'config/db.php';

$tables = [
    'reviews' => "
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review_text TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_review (story_id, user_id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'review_reports' => "
        CREATE TABLE IF NOT EXISTS review_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (status),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($tables as $table_name => $create_sql) {
    try {
        $pdo->exec($create_sql);
        echo "✓ Created or verified $table_name table\n";
    } catch (Exception $e) {
        echo "✗ Error creating $table_name: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Review system tables created successfully!\n";
?>
