<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $chapterId = $_POST['chapter_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (!$chapterId) {
        echo json_encode(['error' => 'Missing chapter_id']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ?");
    $stmt->execute([$chapterId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original) {
        echo json_encode(['error' => 'Chapter not found']);
        exit;
    }
    
    $changes = [];
    if ($title && $title !== $original['title']) {
        $changes[] = "title updated";
    }
    if ($content && strlen($content) > 0) {
        $changes[] = "content updated";
    }
    
    $updates = [];
    $params = [];
    
    if ($title) {
        $updates[] = "title = ?";
        $params[] = $title;
    }
    if ($content) {
        $updates[] = "content = ?";
        $params[] = $content;
    }
    
    if (!empty($updates)) {
        $updates[] = "updated_at = NOW()";
        $params[] = $chapterId;
        
        // SAVE CHAPTER HISTORY (before updating)
        try {
            $hist = $pdo->prepare("
                INSERT INTO chapter_versions (chapter_id, admin_id, old_title, old_content, change_summary, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $hist->execute([
                $chapterId,
                $_SESSION['user_id'],
                $original['title'],
                $original['content'],
                implode(", ", $changes)
            ]);
        } catch (Exception $e) {
            // History table may not exist, continue anyway
        }
        
        $query = "UPDATE chapters SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'edit_chapter', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "Chapter $chapterId: " . implode(", ", $changes)]);
    
    echo json_encode(['ok' => true, 'message' => 'Chapter updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
