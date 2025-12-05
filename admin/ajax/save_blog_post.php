<?php
// admin/ajax/save_blog_post.php - Save blog post/announcement
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// Check login - support multiple session keys
$logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_user']) || isset($_SESSION['admin_id']);
if (!$logged_in) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized - please login']));
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? ($_SESSION['admin_user']['id'] ?? 0);

$data = json_decode(file_get_contents('php://input'), true);

try {
    $id = intval($data['id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $type = $data['type'] ?? 'announcement';
    $level = $data['level'] ?? 'info';
    $show_on_ticker = intval($data['show_on_ticker'] ?? 0);
    $is_pinned = intval($data['is_pinned'] ?? 0);
    $is_featured = intval($data['is_featured'] ?? 0);
    $active_from = !empty($data['active_from']) ? $data['active_from'] : date('Y-m-d H:i:s');
    $active_until = !empty($data['active_until']) ? $data['active_until'] : null;
    $featured_image = trim($data['featured_image'] ?? '');
    $summary = trim($data['summary'] ?? substr(strip_tags($content), 0, 200));

    if (!$title || !$content) {
        exit(json_encode(['ok' => false, 'message' => 'Title and content required']));
    }

    // Ensure announcements table has required columns
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('level', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN level VARCHAR(50) DEFAULT 'info'");
    if (!in_array('show_on_ticker', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN show_on_ticker TINYINT DEFAULT 0");
    if (!in_array('is_pinned', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN is_pinned TINYINT DEFAULT 0");
    if (!in_array('active_until', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN active_until DATETIME NULL");
    if (!in_array('summary', $cols)) $pdo->exec("ALTER TABLE announcements ADD COLUMN summary TEXT");

    if ($id) {
        // Update existing announcement
        $stmt = $pdo->prepare("
            UPDATE announcements 
            SET title = ?, content = ?, level = ?, show_on_ticker = ?, 
                is_pinned = ?, active_from = ?, active_until = ?, summary = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, $level, $show_on_ticker, $is_pinned, $active_from, $active_until, $summary, $id]);
        $ann_id = $id;
    } else {
        // Create new announcement
        $stmt = $pdo->prepare("
            INSERT INTO announcements (title, content, level, show_on_ticker, is_pinned, active_from, active_until, summary, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$title, $content, $level, $show_on_ticker, $is_pinned, $active_from, $active_until, $summary]);
        $ann_id = $pdo->lastInsertId();
    }

    // Also save to blog_posts table for blog page display
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
        announcement_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Check if blog_posts has announcement_id column
    $blogCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blog_posts'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('announcement_id', $blogCols)) {
        $pdo->exec("ALTER TABLE blog_posts ADD COLUMN announcement_id INT NULL");
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title));
    
    // Check if blog post exists for this announcement
    $existingBlog = $pdo->prepare("SELECT id FROM blog_posts WHERE announcement_id = ?");
    $existingBlog->execute([$ann_id]);
    $blogRow = $existingBlog->fetch();
    
    if ($blogRow) {
        // Update existing blog post
        $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, author_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$title, $slug, $content, $summary, $featured_image, $user_id, $blogRow['id']]);
    } else {
        // Create new blog post linked to announcement
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, announcement_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW())");
        $stmt->execute([$title, $slug, $content, $summary, $featured_image, $user_id, $ann_id]);
    }

    exit(json_encode(['ok' => true, 'message' => 'Announcement published successfully! It will appear on the homepage and blog.', 'id' => $ann_id]));
} catch (Exception $e) {
    error_log('Blog save error: ' . $e->getMessage());
    exit(json_encode(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]));
}
?>
