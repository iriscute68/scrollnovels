<?php
// api/discussions/list.php - Threads + replies (merged; pag/filter)
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

$category = $_GET['category'] ?? 'general';
$search = $_GET['search'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

$where = 'WHERE category = ?';
$params = [$category];
if ($search) {
    $where .= ' AND (title LIKE ? OR content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

try {
    $stmt = $pdo->prepare("
        SELECT d.*, u.username as author_name,
               (SELECT COUNT(*) FROM replies r WHERE r.discussion_id = d.id) as reply_count
        FROM discussions d JOIN users u ON d.author_id = u.id 
        $where ORDER BY pinned DESC, created_at DESC LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $threads = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'threads' => $threads]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>