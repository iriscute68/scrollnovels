<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$kofi_url = isset($_POST['kofi_url']) ? trim($_POST['kofi_url']) : '';
$patreon_url = isset($_POST['patreon_url']) ? trim($_POST['patreon_url']) : '';

// Validate URLs (optional - basic validation)
if ($kofi_url && !filter_var($kofi_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Ko-fi URL']);
    exit;
}

if ($patreon_url && !filter_var($patreon_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Patreon URL']);
    exit;
}

// At least one link must be provided
if (!$kofi_url && !$patreon_url) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide at least one support link']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE users SET kofi = ?, patreon = ? WHERE id = ?');
    $stmt->execute([
        $kofi_url ?: null,
        $patreon_url ?: null,
        $user_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Support links saved successfully!',
        'kofi' => $kofi_url ?: '',
        'patreon' => $patreon_url ?: ''
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save links: ' . $e->getMessage()
    ]);
}
?>
