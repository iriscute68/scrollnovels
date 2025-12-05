<?php
// admin/assign_winner.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/notify.php';
require_admin();

$competition_id = intval($_GET['competition_id'] ?? 0);
if (!$competition_id) {
  header('Location: /admin/competitions.php');
  exit;
}

// Load competition
$cstmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$cstmt->execute([$competition_id]);
$competition = $cstmt->fetch();
if (!$competition) {
  header('Location: /admin/competitions.php');
  exit;
}

// Load approved entries for selection
$entries = $pdo->prepare("
  SELECT ce.id, ce.book_id, b.title, u.username, COALESCE(SUM(bs.views),0) AS views
  FROM competition_entries ce
  JOIN books b ON b.id = ce.book_id
  JOIN users u ON u.id = ce.user_id
  LEFT JOIN book_stats bs ON bs.book_id = b.id
  WHERE ce.competition_id = ? AND ce.status = 'approved'
  GROUP BY ce.id
  ORDER BY views DESC
");
$entries->execute([$competition_id]);
$entries = $entries->fetchAll();

// handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $entry_id = intval($_POST['entry_id'] ?? 0);
  if ($entry_id) {
    // assign winner
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE competitions SET winner_entry_id = ?, status = 'closed' WHERE id = ?")->execute([$entry_id, $competition_id]);
    // Insert into competition_winners
    // find user id of entry
    $e = $pdo->prepare("SELECT user_id FROM competition_entries WHERE id = ?");
    $e->execute([$entry_id]);
    $ev = $e->fetch();
    $winner_user_id = $ev['user_id'] ?? null;
    $pdo->prepare("INSERT INTO competition_winners (competition_id, entry_id, winner_user_id, method) VALUES (?, ?, ?, ?)")->execute([$competition_id, $entry_id, $winner_user_id, 'manual']);
    // Log
    $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta) VALUES (?, ?, ?)")->execute([current_user_id(), 'assign_winner', json_encode(['competition_id'=>$competition_id, 'entry_id'=>$entry_id])]);
    $pdo->commit();

    // notify winner
    notify_user($winner_user_id, "You won '{$competition['title']}'!", "Congratulations — your entry has been selected as the winner. Prize: {$competition['prize']}", "/competitions/{$competition_id}", true);

    header('Location: /admin/competitions.php');
    exit;
  }
}

require_once __DIR__ . '/../inc/header.php';
?>
<div class="container">
  <div class="card">
    <h1>Assign Winner — <?= htmlspecialchars($competition['title']) ?></h1>
    <p>Choose a winning entry (manual assignment). This will close the competition.</p>

    <form method="post">
      <label>Select Entry</label>
      <select name="entry_id" class="input" required>
        <option value="">-- choose --</option>
        <?php foreach ($entries as $en): ?>
          <option value="<?= (int)$en['id'] ?>"><?= htmlspecialchars($en['title']) ?> — <?= htmlspecialchars($en['username']) ?> (<?= number_format($en['views']) ?> views)</option>
        <?php endforeach; ?>
      </select>

      <div style="margin-top:12px">
        <button class="btn btn-gold" type="submit">Assign Winner & Close</button>
        <a class="btn" href="/admin/competitions.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
