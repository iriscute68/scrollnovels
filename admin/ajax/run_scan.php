<?php
// Minimal plagiarism scan endpoint (admin)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','super_admin','moderator'])) {
    http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Forbidden']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$chapter_id = intval($body['chapter_id'] ?? 0);
if (!$chapter_id) { echo json_encode(['ok'=>false,'message'=>'Missing chapter_id']); exit; }

// Fetch chapter
$stmt = $pdo->prepare('SELECT c.id, c.title, c.content, s.title AS story_title, s.id AS story_id, u.username AS author FROM chapters c JOIN stories s ON s.id=c.story_id LEFT JOIN users u ON u.id=s.author_id WHERE c.id = ? LIMIT 1');
$stmt->execute([$chapter_id]);
$chapter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$chapter) { echo json_encode(['ok'=>false,'message'=>'Chapter not found']); exit; }

// create scan job
try {
    $ins = $pdo->prepare('INSERT INTO plagiarism_scans (chapter_id, story_id, admin_id, status, requested_at) VALUES (?, ?, ?, "queued", NOW())');
    $ins->execute([$chapter_id, $chapter['story_id'], $_SESSION['user_id'] ?? null]);
    $scan_id = $pdo->lastInsertId();

    // simple inline placeholder scan (score 0.0 no matches)
    $result = ['score' => 0.0, 'matches' => []];

    $ins2 = $pdo->prepare('INSERT INTO plagiarism_reports (scan_id, chapter_id, story_id, admin_id, score, matches_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "open", NOW())');
    $ins2->execute([$scan_id, $chapter_id, $chapter['story_id'], $_SESSION['user_id'] ?? null, $result['score'], json_encode($result['matches'])]);
    $report_id = $pdo->lastInsertId();

    $pdo->prepare('UPDATE plagiarism_scans SET status="completed", completed_at=NOW(), report_id = ? WHERE id = ?')->execute([$report_id, $scan_id]);

    echo json_encode(['ok'=>true,'report_id'=>$report_id,'scan_id'=>$scan_id]);
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
?>
