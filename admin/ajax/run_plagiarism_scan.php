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
    
    if (!$chapterId) {
        echo json_encode(['error' => 'Missing chapter_id']);
        exit;
    }
    
    // Get chapter content
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ?");
    $stmt->execute([$chapterId]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chapter) {
        echo json_encode(['error' => 'Chapter not found']);
        exit;
    }
    
    // Create plagiarism scan record
    $stmt = $pdo->prepare("INSERT INTO plagiarism_scans (chapter_id, status, created_at) VALUES (?, 'pending', NOW())");
    $stmt->execute([$chapterId]);
    $scanId = $pdo->lastInsertId();
    
    // Queue for scanning (would integrate with plagiarism service like Copyscape, Turnitin, etc)
    // For now, store as pending
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'run_plagiarism_scan', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "Chapter $chapterId scan started"]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Plagiarism scan queued',
        'scan_id' => $scanId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
