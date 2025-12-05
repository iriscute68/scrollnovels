<?php
// admin/ajax/submit_judge_score.php - Submit judge scores for competition entries

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
session_start();

// Only admins/judges can score
if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'judge'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Forbidden']));
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$entry_id = intval($body['entry_id'] ?? 0);
$score = floatval($body['score'] ?? 0);
$rubric = trim($body['rubric'] ?? '');
$comment = trim($body['comment'] ?? '');
$judge_id = $_SESSION['user_id'] ?? null;

if (!$entry_id || $score < 0 || $score > 100) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid entry_id or score (0-100)']));
}

try {
    // Check if entry exists
    $stmt = $pdo->prepare('SELECT id FROM judge_scores WHERE entry_id = ? AND judge_id = ? LIMIT 1');
    $stmt->execute([$entry_id, $judge_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing score
        $stmt = $pdo->prepare("
            UPDATE judge_scores 
            SET score = ?, rubric = ?, comment = ?, updated_at = NOW()
            WHERE entry_id = ? AND judge_id = ?
        ");
        $stmt->execute([$score, $rubric, $comment, $entry_id, $judge_id]);
        $message = 'Score updated';
    } else {
        // Insert new score
        $stmt = $pdo->prepare("
            INSERT INTO judge_scores (entry_id, judge_id, score, rubric, comment, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$entry_id, $judge_id, $score, $rubric, $comment]);
        $message = 'Score submitted';
    }
    
    // Log activity
    try {
        $log = $pdo->prepare("
            INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $log->execute([
            $judge_id,
            'judge_score',
            'entry',
            $entry_id,
            json_encode(['score' => $score, 'rubric' => $rubric])
        ]);
    } catch (Exception $e) {}
    
    echo json_encode(['ok' => true, 'message' => $message]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>
