<?php
// admin/mod.php - Approve/reject/delete (merged; PDO updates, auth)
$header = 'Content-Type: application/json';
header($header);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?? [];
// CSRF may be sent in JSON body or X-CSRF-Token header
$csrf = $input['csrf'] ?? ($_POST['csrf'] ?? '') ;
if (!$csrf) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrf = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $csrf;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($csrf)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request (CSRF)']);
    exit;
}
$type = $input['type'] ?? '';  // story/discussion/user
$id = (int)($input['id'] ?? 0);
$action = $input['action'] ?? '';  // approve/reject/delete

if (!$type || !$id || !in_array($action, ['approve', 'reject', 'delete'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid params']);
    exit;
}

try {
    switch ($type) {
        case 'story':
            if ($action === 'approve') {
                $stmt = $pdo->prepare('UPDATE stories SET status = "published" WHERE id = ?');
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare('UPDATE stories SET status = "rejected" WHERE id = ?');
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM stories WHERE id = ?');
            }
            break;
        case 'discussion':
            if ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM discussions WHERE id = ?');
            }
            // Add approve/reject for threads if needed
            break;
        case 'user':
            if ($action === 'ban') {
                // Remove existing roles and set 'banned' role via user_roles
                $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?');
                $stmt->execute([$id]);
                $r = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
                $r->execute(['banned']);
                $role_id = $r->fetchColumn();
                if (!$role_id) {
                    $ins = $pdo->prepare('INSERT INTO roles (name) VALUES (?)');
                    $ins->execute(['banned']);
                    $role_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
                $stmt->execute([$id, $role_id]);
                // nothing to return via $stmt rowCount for this flow
                echo json_encode(['ok' => true]);
                exit;
            }
            break;
        default:
            throw new Exception('Unknown type');
    }
    $stmt->execute([$id]);
    $affected = $stmt->rowCount();

    echo json_encode(['ok' => true, 'affected' => $affected]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>