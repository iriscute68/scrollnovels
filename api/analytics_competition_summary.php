<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

header('Content-Type: application/json');

$comp_id = intval($_GET['id'] ?? 0);

// Competition overview
$stmt = $pdo->prepare("SELECT
  c.id, c.title,
  COUNT(DISTINCT ce.user_id) AS participants,
  COUNT(ce.id) AS total_entries,
  AVG(ce.total_score) AS avg_score,
  SUM(ce.views) AS total_views,
  SUM(ce.clicks) AS total_clicks
FROM competitions c
LEFT JOIN competition_entries ce ON ce.competition_id = c.id
WHERE c.id = ?
GROUP BY c.id");

$stmt->execute([$comp_id]);
$overview = $stmt->fetch(PDO::FETCH_ASSOC);

// Views by day (last 30 days)
$stmt = $pdo->prepare("SELECT DATE(submitted_at) as date, SUM(views) as count
  FROM competition_entries
  WHERE competition_id = ? AND submitted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY DATE(submitted_at)
  ORDER BY date ASC");
$stmt->execute([$comp_id]);
$views_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top entries
$stmt = $pdo->prepare("SELECT ce.id, ce.total_score, s.title, u.username, ce.votes, ce.views
  FROM competition_entries ce
  JOIN stories s ON s.id = ce.story_id
  JOIN users u ON u.id = ce.user_id
  WHERE ce.competition_id = ?
  ORDER BY ce.total_score DESC LIMIT 10");
$stmt->execute([$comp_id]);
$top_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'overview' => $overview,
  'views_by_day' => $views_by_day,
  'top_entries' => $top_entries
]);
?>
