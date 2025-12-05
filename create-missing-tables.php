<?php
require_once __DIR__ . '/config.php';

// Create content_reports table if it doesn't exist
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS content_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        reported_by INT NOT NULL,
        story_id INT,
        chapter_id INT,
        comment_id INT,
        reason VARCHAR(255),
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (reported_by) REFERENCES users(id),
        FOREIGN KEY (story_id) REFERENCES stories(id),
        FOREIGN KEY (chapter_id) REFERENCES chapters(id)
    )
    ");
    echo "✓ content_reports table created\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
