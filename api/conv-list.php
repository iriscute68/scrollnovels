<?php
// api/conv-list.php - wrapper to return conversation list html fragment
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT c.id, c.name, c.participants, c.updated_at, m.content AS last_message, u.username AS other_name FROM conversations c JOIN JSON_TABLE(c.participants, '$[*]' COLUMNS(p_id INT PATH '$')) AS jt ON 1=1 LEFT JOIN chat_messages m ON m.conv_id = c.id AND m.created_at = (SELECT MAX(created_at) FROM chat_messages WHERE conv_id = c.id) LEFT JOIN users u ON u.id = jt.p_id AND u.id != ? WHERE JSON_CONTAINS(c.participants, ?) GROUP BY c.id ORDER BY c.updated_at DESC");
$stmt->execute([$user_id, json_encode($user_id)]);
$convs = $stmt->fetchAll();

foreach ($convs as $c) {
    $other_id = array_values(array_diff(json_decode($c['participants'], true), [$user_id]))[0] ?? null;
    $other_name = $c['other_name'] ?? 'Group';
    $unread = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE conv_id = {$c['id']} AND user_id != $user_id AND status != 'read'")->fetchColumn();
    $active = (isset($_GET['conv']) && $_GET['conv'] == $c['id']) ? 'bg-light' : '';
    echo "<div class=\"contact-item p-3 border-bottom {$active} " . ($unread ? 'unread' : '') . "\" onclick=\"location.href='?conv={$c['id']}'\">";
    echo '<div class="d-flex align-items-center">';
    echo '<div class="me-3"><div class="online-dot"></div></div>';
    echo '<div class="flex-grow-1">';
    echo '<strong>' . htmlspecialchars($other_name) . '</strong>';
    echo '<small class="d-block text-muted text-truncate">' . ($c['last_message'] ? htmlspecialchars(substr($c['last_message'], 0, 30)) . '...' : 'No messages') . '</small>';
    echo '</div>';
    if ($unread) echo '<span class="badge bg-primary">' . (int)$unread . '</span>';
    echo '</div></div>';
}

?>
