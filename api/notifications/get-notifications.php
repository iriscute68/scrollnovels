<?php
// api/notifications/get-notifications.php - Get user's notifications with pagination
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;
$filter = $_GET['filter'] ?? 'all'; // all, unread, content, comments, reviews, system, monetization

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        actor_id INT,
        type VARCHAR(50),
        message TEXT,
        url VARCHAR(500),
        data JSON,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $where = "WHERE user_id = ?";
    $params = [$user_id];

    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    } elseif ($filter !== 'all') {
        $where .= " AND type = ?";
        $params[] = $filter;
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM notifications $where";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];

    // Get notifications
    $sql = "SELECT * FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON data
    foreach ($notifications as &$notif) {
        if ($notif['data']) {
            $notif['data'] = json_decode($notif['data'], true);
        }
    }

    // Get unread count
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $unread_stmt->execute([$user_id]);
    $unread_count = $unread_stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ],
        'unread_count' => $unread_count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
