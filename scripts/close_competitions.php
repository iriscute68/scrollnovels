<?php
// scripts/close_competitions.php
// run via CLI: php /path/to/scripts/close_competitions.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/notify.php';

// find active competitions past end_date
$stmt = $pdo->prepare("SELECT * FROM competitions WHERE status = 'active' AND end_date <= NOW()");
$stmt->execute();
$toClose = $stmt->fetchAll();

foreach ($toClose as $comp) {
  $compId = $comp['id'];
  $method = $comp['auto_winner_method'] ?? 'none';
  // query approved entries
  $entriesStmt = $pdo->prepare("
    SELECT ce.id, ce.user_id, b.id AS book_id, b.title,
      COALESCE(SUM(bs.views),0) AS views,
      COALESCE(AVG(r.rating),0) AS avg_rating,
      COALESCE(SUM(v.votes),0) AS votes,
      COALESCE((SELECT COUNT(*) FROM chapters ch WHERE ch.book_id = b.id), 0) AS chapters
    FROM competition_entries ce
    JOIN books b ON b.id = ce.book_id
    LEFT JOIN book_stats bs ON bs.book_id = b.id
    LEFT JOIN ratings r ON r.book_id = b.id
    LEFT JOIN (
      SELECT book_id, SUM(1) AS votes FROM book_votes GROUP BY book_id
    ) v ON v.book_id = b.id
    WHERE ce.competition_id = ? AND ce.status = 'approved'
    GROUP BY ce.id
  ");
  $entriesStmt->execute([$compId]);
  $entries = $entriesStmt->fetchAll();
  if (empty($entries)) {
    // mark closed with no winner
    $pdo->prepare("UPDATE competitions SET status = 'closed' WHERE id = ?")->execute([$compId]);
    continue;
  }

  // pick winner
  $pick = null;
  if ($method === 'views') {
    usort($entries, function($a,$b){ return $b['views'] <=> $a['views']; });
    $pick = $entries[0];
  } elseif ($method === 'votes') {
    usort($entries, function($a,$b){ return $b['votes'] <=> $a['votes']; });
    $pick = $entries[0];
  } elseif ($method === 'ratings') {
    usort($entries, function($a,$b){ return $b['avg_rating'] <=> $a['avg_rating']; });
    $pick = $entries[0];
  } elseif ($method === 'completion') {
    usort($entries, function($a,$b){ return $b['chapters'] <=> $a['chapters']; });
    $pick = $entries[0];
  } else {
    // default pick by views
    usort($entries, function($a,$b){ return $b['views'] <=> $a['views']; });
    $pick = $entries[0];
  }

  if ($pick) {
    try {
      $pdo->beginTransaction();
      // mark competition closed and set winner_entry_id
      $pdo->prepare("UPDATE competitions SET winner_entry_id = ?, status = 'closed' WHERE id = ?")->execute([$pick['id'], $compId]);
      // Insert into winners history
      $pdo->prepare("INSERT INTO competition_winners (competition_id, entry_id, winner_user_id, method) VALUES (?, ?, ?, ?)")->execute([$compId, $pick['id'], $pick['user_id'], $method]);
      // notify winner
      notify_user($pick['user_id'], "You won {$comp['title']}!", "Congratulations — your entry '{$pick['title']}' has been declared the winner. Prize: {$comp['prize']}", "/competitions/{$compId}", true);

      // log admin_activity with admin_id null (system)
      $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta) VALUES (?, ?, ?)")->execute([null, 'auto_assign_winner', json_encode(['competition_id'=>$compId,'entry_id'=>$pick['id'],'method'=>$method])]);

      $pdo->commit();
    } catch (Exception $e) {
      $pdo->rollBack();
      error_log("Auto winner error for comp {$compId}: ".$e->getMessage());
    }
  } else {
    // No pick: just close competition
    $pdo->prepare("UPDATE competitions SET status = 'closed' WHERE id = ?")->execute([$compId]);
  }
}
