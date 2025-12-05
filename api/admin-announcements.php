<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'create_announcement') {
    $title = $data['title'] ?? null;
    $content = $data['content'] ?? null;
    $link = $data['link'] ?? null;
    $image = $data['image'] ?? null;
    
    if (!$title || !$content) {
        echo json_encode(['success' => false, 'error' => 'Title and content required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, link, image, created_at, active_from) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$title, $content, $link, $image]);
        $announcementId = $pdo->lastInsertId();
        
        // Also create a blog post for this announcement
        try {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . $announcementId;
            $excerpt = substr(strip_tags($content), 0, 200);
            $blogStmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, author_id, status, announcement_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())");
            $blogStmt->execute([$title, $slug, $content, $excerpt, $_SESSION['user_id'], $announcementId]);
        } catch (Exception $blogErr) {
            error_log('Blog post creation failed: ' . $blogErr->getMessage());
        }
        
        // Notify all users about the new announcement
        try {
            $usersStmt = $pdo->query("SELECT id FROM users WHERE id != " . (int)$_SESSION['user_id'] . " LIMIT 1000");
            $users = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $notifUrl = "/pages/blog-view.php?id=" . $announcementId;
            $notifMsg = "📢 New Announcement: " . substr($title, 0, 50);
            
            foreach ($users as $userId) {
                notify($pdo, $userId, $_SESSION['user_id'], 'announcement', $notifMsg, $notifUrl);
            }
        } catch (Exception $notifErr) {
            error_log('Notification failed: ' . $notifErr->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Announcement created',
            'id' => $announcementId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($action === 'update_announcement') {
    $id = $data['id'] ?? null;
    $title = $data['title'] ?? null;
    $content = $data['content'] ?? null;
    $link = $data['link'] ?? null;
    $image = $data['image'] ?? null;
    
    if (!$id || !$title || !$content) {
        echo json_encode(['success' => false, 'error' => 'ID, title, and content required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, link = ?, image = ? WHERE id = ?");
        $stmt->execute([$title, $content, $link, $image, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Announcement updated']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($action === 'delete_announcement') {
    $id = $data['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
