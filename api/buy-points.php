<?php
// api/buy-points.php - Request to buy points from admin
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Login required']));
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$package_id = (int)($data['package_id'] ?? 0);

// Define available packages: $10 = 1100 points
$packages = [
    1 => ['price' => 10, 'points' => 1100, 'name' => '1,100 Points ($10)'],
    2 => ['price' => 25, 'points' => 3000, 'name' => '3,000 Points ($25)'],
    3 => ['price' => 50, 'points' => 6500, 'name' => '6,500 Points ($50)'],
    4 => ['price' => 100, 'points' => 14000, 'name' => '14,000 Points ($100)'],
];

if (!isset($packages[$package_id])) {
    exit(json_encode(['success' => false, 'message' => 'Invalid package']));
}

try {
    // Create points purchase requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_purchase_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        payment_proof VARCHAR(500),
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $package = $packages[$package_id];
    
    // Create purchase request
    $stmt = $pdo->prepare("
        INSERT INTO point_purchase_requests (user_id, points, price, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $package['points'], $package['price']]);
    $request_id = $pdo->lastInsertId();
    
    // Notify admins about new purchase request
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get admin users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('admin', 'super_admin') LIMIT 5");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($admins as $admin_id) {
        $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
            VALUES (?, ?, 'points_request', ?, ?, NOW())
        ")->execute([
            $admin_id,
            $user_id,
            htmlspecialchars(($user['username'] ?? 'User') . ' requested to buy ' . $package['points'] . ' points'),
            '/scrollnovels/admin/admin.php?page=points&request=' . $request_id
        ]);
    }
    
    exit(json_encode([
        'success' => true,
        'message' => 'Purchase request submitted! Admins will contact you on Patreon to confirm payment.',
        'request_id' => $request_id
    ]));
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}
