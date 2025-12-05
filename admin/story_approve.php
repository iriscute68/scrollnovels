<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_admin();

$id = intval($_POST["id"] ?? 0);
$action = $_POST["action"] ?? '';

if ($id && $action) {
  if ($action === 'approve') {
    $pdo->prepare("UPDATE stories SET status='published', published_at=NOW() WHERE id=?")->execute([$id]);
  } else {
    $pdo->prepare("UPDATE stories SET status='archived' WHERE id=?")->execute([$id]);
  }
}

header("Location: stories_moderation.php");
exit;
?>
