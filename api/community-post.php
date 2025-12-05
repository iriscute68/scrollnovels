<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$userId = $_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$content = trim($_POST['content'] ?? '');
$tags = trim($_POST['tags'] ?? '');
// handle optional images
$uploadedImages = [];

if (strlen($title) < 10) {
    http_response_code(400);
    die(json_encode(['error' => 'Title must be at least 10 characters']));
}

if (!in_array($category, ['writing-advice', 'feedback', 'genres', 'events', 'technical'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid category']));
}

if (empty($content)) {
    http_response_code(400);
    die(json_encode(['error' => 'Content required']));
}

try {
    // Ensure community table exists (avoid SQL errors when migrations not run)
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        content LONGTEXT NOT NULL,
        tags VARCHAR(255),
        images JSON NULL,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Process images if provided
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = dirname(__DIR__) . '/assets/uploads/community';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $files = $_FILES['images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $name = basename($files['name'][$i]);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
            if ($files['size'][$i] > 2 * 1024 * 1024) continue; // 2MB limit
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/','_', $name);
            $target = $uploadDir . '/' . uniqid('img_', true) . '_' . $safeName;
            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                $url = rtrim(SITE_URL, '/') . '/assets/uploads/community/' . basename($target);
                $uploadedImages[] = $url;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO community_posts (author_id, title, category, content, tags, images, views, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$userId, $title, $category, $content, $tags, json_encode($uploadedImages)]);
    $postId = $pdo->lastInsertId();
    
    header('Location: ' . SITE_URL . '/pages/community-thread.php?id=' . $postId);
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}
