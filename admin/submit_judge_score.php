<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

header('Content-Type: application/json');

$judgeId = current_user_id();
$entryId = intval($_POST['entry_id'] ?? 0);
$score = floatval($_POST['score'] ?? 0);
$comment = isset($_POST['comment']) ? $_POST['comment'] : null;
$rubric = isset($_POST['rubric']) ? $_POST['rubric'] : null;

// Check assignment
$stmt = $pdo->prepare("SELECT cj.id FROM competition_judges cj 
  JOIN competition_entries ce ON ce.competition_id = cj.competition_id 
  WHERE cj.user_id = ? AND ce.id = ?");
$stmt->execute([$judgeId, $entryId]);

if (!$stmt->fetch()) {
  http_response_code(403);
  echo json_encode(['error' => 'Not assigned']);
  exit;
}

// Upsert judge_scores
$stmt = $pdo->prepare("INSERT INTO judge_scores (entry_id, judge_id, score, rubric, comment) 
  VALUES (?, ?, ?, ?, ?) 
  ON DUPLICATE KEY UPDATE score=VALUES(score), rubric=VALUES(rubric), comment=VALUES(comment)");
$stmt->execute([$entryId, $judgeId, $score, $rubric, $comment]);

// Recalculate aggregated score
$stmt = $pdo->prepare("SELECT AVG(score) as avgScore, COUNT(*) as judges FROM judge_scores WHERE entry_id = ?");
$stmt->execute([$entryId]);
$row = $stmt->fetch();
$avg = $row ? floatval($row['avgScore']) : 0.0;
$count = $row ? intval($row['judges']) : 0;

// Update competition_entries
$pdo->prepare("UPDATE competition_entries SET total_score = ?, votes = ? WHERE id = ?")
    ->execute([$avg, $count, $entryId]);

// Get competition ID for leaderboard update
$compId = $pdo->query("SELECT competition_id FROM competition_entries WHERE id = " . intval($entryId))->fetchColumn();
recalc_competition_leaderboard($compId);

// Log admin action
$log = $pdo->prepare("INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data) VALUES (?, ?, ?, ?, ?)");
$log->execute([$judgeId, 'judge_score', 'entry', $entryId, json_encode(['score' => $score, 'comment' => $comment])]);

echo json_encode(['ok' => true, 'avg' => $avg]);
?>
