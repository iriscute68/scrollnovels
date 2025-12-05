<?php
// api/save-ad.php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();
if (!hasRole('admin')) exit(json_encode(['success' => false, 'error' => 'Unauthorized']));

$id = (int)$_POST['id'];
$content = trim($_POST['content']);
$placement = $_POST['placement'];
$story_id = $_POST['story_id'] ? (int)$_POST['story_id'] : null;
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$status = $_POST['status'];

if (empty($content) || !in_array($placement, ['featured','sidebar','top','bottom'])) {
    exit(json_encode(['success' => false, 'error' => 'Invalid data']));
}

if ($id) {
    $stmt = $pdo->prepare("UPDATE ads SET content=?, placement=?, story_id=?, start_date=?, end_date=?, status=? WHERE id=?");
    $stmt->execute([$content, $placement, $story_id, $start_date, $end_date, $status, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO ads (content, placement, story_id, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$content, $placement, $story_id, $start_date, $end_date, $status]);
}

echo json_encode(['success' => true]);
?>