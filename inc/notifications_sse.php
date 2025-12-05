<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
ignore_user_abort(true);
set_time_limit(0);

session_start();
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
  http_response_code(403);
  exit;
}

$lastId = intval($_GET['last_id'] ?? 0);

while (!connection_aborted()) {
  // Fetch new notifications
  $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC");
  $stmt->execute([$uid, $lastId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $r) {
    $lastId = max($lastId, $r['id']);
    echo "event: notification\n";
    echo "data: " . json_encode($r) . "\n\n";
    ob_flush();
    flush();
  }

  // Fetch global announcements
  $stmt2 = $pdo->prepare("SELECT * FROM announcements WHERE show_on_ticker = 1 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY is_pinned DESC, created_at DESC");
  $stmt2->execute();
  $anns = $stmt2->fetchAll(PDO::FETCH_ASSOC);

  foreach ($anns as $a) {
    echo "event: announcement\n";
    echo "data: " . json_encode($a) . "\n\n";
    ob_flush();
    flush();
  }

  sleep(2);
}
?>
