<?php
// api/track_ad_click.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$ad_id = isset($input['ad_id']) ? (int)$input['ad_id'] : 0;

if (!$ad_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ad_id_required']);
    exit;
}

try {
    $u = $pdo->prepare('UPDATE ads SET clicks = clicks + 1, updated_at = NOW() WHERE id = ?');
    $u->execute([$ad_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}

?>
