<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','super_admin','moderator'])) {
    http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Forbidden']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = intval($body['id'] ?? 0);
$action = $body['action'] ?? '';
if (!$id || !$action) { echo json_encode(['ok'=>false,'message'=>'Missing']); exit; }

$allowed = ['mark_resolved','warn_author','suspend_author','delete_chapter','ignored'];
if (!in_array($action, $allowed)) { echo json_encode(['ok'=>false,'message'=>'Invalid action']); exit; }

$stmt = $pdo->prepare('SELECT * FROM plagiarism_reports WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) { echo json_encode(['ok'=>false,'message'=>'Not found']); exit; }

try {
    if ($action === 'mark_resolved') {
        $pdo->prepare('UPDATE plagiarism_reports SET status = ?, resolved_at = NOW() WHERE id = ?')->execute(['resolved', $id]);
        echo json_encode(['ok'=>true]); exit;
    }
    if ($action === 'ignored') {
        $pdo->prepare('UPDATE plagiarism_reports SET status = ? WHERE id = ?')->execute(['ignored', $id]);
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'delete_chapter') {
        $chapterId = intval($r['chapter_id']);
        $pdo->prepare('DELETE FROM chapters WHERE id = ?')->execute([$chapterId]);
        $pdo->prepare('UPDATE plagiarism_reports SET status = ? WHERE id = ?')->execute(['deleted_chapter', $id]);
        echo json_encode(['ok'=>true]); exit;
    }

    // warn_author / suspend_author - placeholders that log action
    if (in_array($action, ['warn_author','suspend_author'])) {
        $pdo->prepare('INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data) VALUES (?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'] ?? null, $action, 'report', $id, json_encode(['report_id'=>$id])]);
        echo json_encode(['ok'=>true]); exit;
    }

} catch (Exception $e) {
    http_response_code(500); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); exit;
}
?>
