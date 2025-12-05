<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="competition_entries_' . date('Ymd_His') . '.csv"');

$comp_id = intval($_GET['id'] ?? 0);

$out = fopen('php://output', 'w');
fputcsv($out, ['rank', 'entry_id', 'story_title', 'author', 'score', 'votes', 'views', 'clicks', 'submitted_at', 'status']);

$stmt = $pdo->prepare("SELECT ce.id AS entry_id, s.title AS story_title, u.username, ce.total_score, ce.votes, ce.views, ce.clicks, ce.submitted_at, ce.status
  FROM competition_entries ce
  JOIN stories s ON s.id = ce.story_id
  JOIN users u ON u.id = ce.user_id
  WHERE ce.competition_id = ?
  ORDER BY ce.total_score DESC, ce.votes DESC, ce.views DESC");

$stmt->execute([$comp_id]);
$rank = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $rank++;
  fputcsv($out, [
    $rank,
    $row['entry_id'],
    $row['story_title'],
    $row['username'],
    $row['total_score'],
    $row['votes'],
    $row['views'],
    $row['clicks'],
    $row['submitted_at'],
    $row['status']
  ]);
}

fclose($out);
?>
