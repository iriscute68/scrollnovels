<?php
// api/create-competition.php - Create competition (admin only)
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Verify admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

try {
    $stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['admin_level'] < 1) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Admin access required']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Server error']));
}

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = $_SESSION['user_id'];

// Validate required fields
$required = ['title', 'description', 'category', 'start_date', 'end_date'];
$errors = [];

foreach ($required as $field) {
    if (empty($data[$field])) {
        $errors[] = "$field is required";
    }
}

if (!empty($errors)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'errors' => $errors]));
}

$title = trim($data['title']);
$description = trim($data['description']);
$category = trim($data['category']);
$start_date = $data['start_date'];
$end_date = $data['end_date'];
$entry_limit = (int)($data['entry_limit'] ?? 100);
$prize_pool = (float)($data['prize_pool'] ?? 0);
$rules = trim($data['rules'] ?? '');
$status = $data['status'] ?? 'draft';
$banner_image = $data['banner_image'] ?? null;

// Validate dates
try {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    if ($end <= $start) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'End date must be after start date']));
    }
} catch (Exception $e) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid date format']));
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO competitions (title, description, category, start_date, end_date, entry_limit, 
        prize_pool, rules, status, banner_image, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $title, $description, $category, $start_date, $end_date,
        $entry_limit, $prize_pool, $rules, $status, $banner_image, $admin_id
    ]);
    
    $comp_id = $pdo->lastInsertId();
    
    // Notify all users about new competition
    if ($status === 'active') {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) 
            SELECT id, 'competition', ?, ?, ?, 'competition' FROM users
        ");
        $notif_title = "New Competition: $title";
        $notif_msg = "A new $category competition has started!";
        $stmt->execute([$notif_title, $notif_msg, $comp_id]);
    }
    
    http_response_code(201);
    echo json_encode(['success' => true, 'competition_id' => $comp_id]);
    
} catch (Exception $e) {
    error_log('Competition creation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create competition']);
}
?>
