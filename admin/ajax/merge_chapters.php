<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Basic admin check - adapt to your auth system
session_start();
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin','super_admin','moderator'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$from = intval($_POST['from_chapter'] ?? 0);
$to = intval($_POST['to_chapter'] ?? 0);
$story_id = intval($_POST['story_id'] ?? 0);
$newTitle = trim($_POST['new_title'] ?? 'Merged Chapter');

if (!$from || !$to || $from === $to) {
    echo json_encode(['ok' => false, 'message' => 'Invalid chapter IDs']);
    exit;
}

try {
    // Fetch both chapters (ensure they belong to same story if provided)
    $stmt = $pdo->prepare('SELECT id, story_id, title, content FROM chapters WHERE id IN (?, ?)');
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) < 2) {
        echo json_encode(['ok' => false, 'message' => 'One or more chapters not found']);
        exit;
    }

    // Determine order: from content appended into to
    $fromContent = '';
    foreach ($rows as $r) {
        if ($r['id'] == $from) $fromContent = $r['content'];
    }

    $pdo->beginTransaction();

    // Append content to target chapter
    $upd = $pdo->prepare("UPDATE chapters SET content = CONCAT(IFNULL(content,''), '\n\n', ?) WHERE id = ?");
    $upd->execute([$fromContent, $to]);

    // Optionally delete the source chapter
    $del = $pdo->prepare('DELETE FROM chapters WHERE id = ?');
    $del->execute([$from]);

    // Log admin activity if table exists
    try {
        $log = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $adminId = $_SESSION['user_id'] ?? null;
        $log->execute([$adminId, 'merge_chapters', 'chapter', $to, json_encode(['merged_from' => $from])]);
    } catch (Exception $e) {
        // ignore if logging table doesn't exist
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'Chapters merged']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

?>
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
    
    $chapter1Id = $_POST['chapter1_id'] ?? 0;
    $chapter2Id = $_POST['chapter2_id'] ?? 0;
    
    if (!$chapter1Id || !$chapter2Id) {
        echo json_encode(['error' => 'Missing chapter IDs']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id IN (?, ?)");
    $stmt->execute([$chapter1Id, $chapter2Id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($chapters) !== 2) {
        echo json_encode(['error' => 'One or both chapters not found']);
        exit;
    }
    
    $ch1 = $chapters[0];
    $ch2 = $chapters[1];
    
    if ($ch1['story_id'] !== $ch2['story_id']) {
        echo json_encode(['error' => 'Chapters must be from the same story']);
        exit;
    }
    
    $mergedContent = $ch1['content'] . "\n\n---\n\n" . $ch2['content'];
    $mergedTitle = $ch1['title'] . " & " . $ch2['title'];
    
    $stmt = $pdo->prepare("UPDATE chapters SET title = ?, content = ?, word_count = ? WHERE id = ?");
    $wordCount = str_word_count(strip_tags($mergedContent));
    $stmt->execute([$mergedTitle, $mergedContent, $wordCount, $chapter1Id]);
    
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$chapter2Id]);
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'merge_chapters', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "Chapters $chapter1Id + $chapter2Id merged"]);
    
    echo json_encode(['ok' => true, 'message' => 'Chapters merged successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
