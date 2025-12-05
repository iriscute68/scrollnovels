<?php
// Create test announcement
require_once __DIR__ . '/config/db.php';

// Check and add missing columns to announcements table
try {
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('link', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN link VARCHAR(500)");
    if (!in_array('image', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN image VARCHAR(500)");
    if (!in_array('active_from', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN active_from DATETIME");
    echo "Ensured columns exist\n";
} catch (Exception $e) {
    echo "Column check: " . $e->getMessage() . "\n";
}

// Insert a test announcement
try {
    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, link, image, active_from, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        '🎉 Welcome to Scroll Novels!',
        '<p>We are excited to announce the launch of our new features!</p><ul><li><strong>Rich Text Editor</strong> - Create beautiful posts with formatting</li><li><strong>Competitions</strong> - Join writing competitions and win prizes</li><li><strong>Community</strong> - Connect with other writers</li></ul><p>Stay tuned for more updates!</p>',
        '/scrollnovels/pages/guides.php',
        null
    ]);
    echo "Created announcement in 'announcements' table with ID: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Error creating announcement: " . $e->getMessage() . "\n";
}

// Also insert into blog_posts table  
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255),
        content LONGTEXT,
        excerpt TEXT,
        featured_image VARCHAR(500),
        author_id INT,
        status ENUM('draft','published') DEFAULT 'published',
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, status, created_at) VALUES (?, ?, ?, ?, 'published', NOW())");
    $stmt->execute([
        '🎉 Welcome to Scroll Novels!',
        'welcome-to-scroll-novels',
        '<p>We are excited to announce the launch of our new features!</p><ul><li><strong>Rich Text Editor</strong> - Create beautiful posts with formatting</li><li><strong>Competitions</strong> - Join writing competitions and win prizes</li><li><strong>Community</strong> - Connect with other writers</li></ul><p>Stay tuned for more updates!</p>',
        'We are excited to announce the launch of our new features!'
    ]);
    echo "Created blog post in 'blog_posts' table with ID: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Error creating blog post: " . $e->getMessage() . "\n";
}

echo "\nDone! Check your homepage and admin announcements page.\n";
