<?php
// api/notifications/update-settings.php - Update notification preferences
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$allowed_fields = ['new_chapter', 'comment', 'reply', 'review', 'rating', 'system', 'monetization', 'email_notifications'];
$updates = [];
$params = [];

foreach ($allowed_fields as $field) {
    if (isset($data[$field])) {
        $updates[] = "$field = ?";
        $params[] = (int)(bool)$data[$field];
    }
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

try {
    $params[] = $user_id;
    $sql = "UPDATE user_notification_settings SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Get updated settings
    $get = $pdo->prepare("SELECT * FROM user_notification_settings WHERE user_id = ?");
    $get->execute([$user_id]);
    $settings = $get->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $settings, 'message' => 'Settings updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
