<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

function recalc_competition_leaderboard($compId) {
  global $pdo;
  
    $sql = "SELECT ce.id, ce.story_id, ce.user_id, ce.total_score, ce.votes, ce.views, ce.clicks
      FROM competition_entries ce
      WHERE ce.competition_id = ? AND ce.status != 'disqualified'
      ORDER BY ce.total_score DESC, ce.votes DESC, ce.views DESC, ce.submitted_at ASC
      LIMIT 100";
  
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$compId]);
  $entries = $stmt->fetchAll();
  
  $payload = [];
  foreach ($entries as $idx => $entry) {
    $payload[] = [
      'rank' => $idx + 1,
      'entry_id' => $entry['id'],
      'story_id' => $entry['story_id'],
      'author_id' => $entry['user_id'],
      'score' => floatval($entry['total_score']),
      'votes' => intval($entry['votes']),
      'views' => intval($entry['views']),
      'clicks' => intval($entry['clicks'])
    ];
  }
  
  $stmt = $pdo->prepare("INSERT INTO competition_leaderboard (competition_id, payload) VALUES (?, ?) 
    ON DUPLICATE KEY UPDATE payload=VALUES(payload), snapshot_at=NOW()");
  $stmt->execute([$compId, json_encode($payload)]);
}

// Call from POST or command line
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $compId = intval($_POST['comp_id'] ?? 0);
  if ($compId) {
    recalc_competition_leaderboard($compId);
    echo json_encode(['ok' => true]);
  }
}
?>
