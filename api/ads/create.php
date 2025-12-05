<?php
// api/ads/create.php - Create new ad (user initiates purchase)

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/ads.php';

header('Content-Type: application/json');

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($data['book_id']) || empty($data['package'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)$data['book_id'];
    $package = $data['package'];

    // Verify package exists
    $config = require dirname(__DIR__, 2) . '/config/ads.php';
    if (!isset($config['packages'][$package])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid package']);
        exit;
    }

    $packageData = $config['packages'][$package];

    // Verify user owns the book
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$book_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not own this book']);
        exit;
    }

    // Create the ad
    $stmt = $pdo->prepare("
        INSERT INTO ads (user_id, book_id, package_views, amount, payment_status, admin_verified)
        VALUES (?, ?, ?, ?, 'pending', false)
    ");
    $stmt->execute([
        $user_id,
        $book_id,
        $packageData['views'],
        $packageData['amount']
    ]);

    $ad_id = $pdo->lastInsertId();

    // Generate Patreon URL with tracking parameters
    $patreon_url = $config['patreon_url'];
    $patreon_url .= '?ad_id=' . $ad_id . '&book_id=' . $book_id . '&amount=' . $packageData['amount'];

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'ad_id' => $ad_id,
        'patreon_url' => $patreon_url
    ]);

} catch (Exception $e) {
    error_log("Ad creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
