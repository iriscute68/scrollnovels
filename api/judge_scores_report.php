<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

header('Content-Type: application/json');

$entry_id = intval($_GET['id'] ?? 0);

// Get judge scores
$stmt = $pdo->prepare("SELECT js.judge_id, u.username, js.score, js.rubric, js.comment, js.submitted_at
  FROM judge_scores js
  JOIN users u ON u.id = js.judge_id
  WHERE js.entry_id = ?
  ORDER BY js.submitted_at ASC");

$stmt->execute([$entry_id]);
$scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['scores' => $scores]);
?>
