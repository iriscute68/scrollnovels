<?php
// api/api_submit_book.php - Enhanced with CSRF protection and validation (Pattern 3 from BookStack)
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['error' => 'not_authenticated']);
  exit;
}

// CSRF Protection (Pattern 3 from BookStack)
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_csrf_token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$title = trim($input['title'] ?? '');
$synopsis = trim($input['synopsis'] ?? '');
$genre = trim($input['genre'] ?? '');
$competition_id = intval($input['competition_id'] ?? 0);
$user_id = current_user_id();

// Validation Rules (Pattern 3 from BookStack)
$errors = [];
if (empty($title)) {
  $errors['title'] = 'Title is required';
} elseif (strlen($title) < 3) {
  $errors['title'] = 'Title must be at least 3 characters';
} elseif (strlen($title) > 255) {
  $errors['title'] = 'Title cannot exceed 255 characters';
}

if (!empty($synopsis) && strlen($synopsis) > 5000) {
  $errors['synopsis'] = 'Synopsis cannot exceed 5000 characters';
}

if (empty($competition_id)) {
  $errors['competition_id'] = 'Competition is required';
}

if (!empty($genre) && strlen($genre) > 100) {
  $errors['genre'] = 'Genre cannot exceed 100 characters';
}

if (!empty($errors)) {
  http_response_code(400);
  echo json_encode(['error' => 'validation_failed', 'errors' => $errors]);
  exit;
}

// Optional: check if competition exists and is active
$compStmt = $pdo->prepare("SELECT id, status FROM competitions WHERE id = ?");
$compStmt->execute([$competition_id]);
$competition = $compStmt->fetch();
if (!$competition) {
  http_response_code(404);
  echo json_encode(['error' => 'competition_not_found']);
  exit;
}
if ($competition['status'] != 'active') {
  // Allow joining only if active
  http_response_code(400);
  echo json_encode(['error' => 'competition_not_active']);
  exit;
}

try {
  $pdo->beginTransaction();
  
  // Generate unique slug from title
  $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($title));
  $slug = strtolower(trim($slug, '-'));
  $base_slug = $slug;
  $counter = 1;
  
  // Check for slug uniqueness
  while (true) {
    $checkStmt = $pdo->prepare("SELECT id FROM books WHERE slug = ? LIMIT 1");
    $checkStmt->execute([$slug]);
    if (!$checkStmt->fetch()) {
      break;
    }
    $slug = $base_slug . '-' . ($counter++);
  }

  // Insert book - mark created_for_competition = 1
  $stmt = $pdo->prepare("INSERT INTO books (user_id, title, synopsis, genre, slug, created_for_competition, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
  $stmt->execute([$user_id, $title, $synopsis, $genre, $slug]);
  $book_id = $pdo->lastInsertId();

  // Insert competition entry
  $stmt2 = $pdo->prepare("INSERT INTO competition_entries (competition_id, book_id, user_id, submitted_at, status) VALUES (?, ?, ?, NOW(), 'active')");
  $stmt2->execute([$competition_id, $book_id, $user_id]);

  $pdo->commit();

  http_response_code(201);
  echo json_encode([
    'success' => true, 
    'book_id' => (int)$book_id,
    'slug' => $slug,
    'message' => 'Story created successfully'
  ]);
  exit;
} catch (Exception $e) {
  $pdo->rollBack();
  error_log("submit_book error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'message' => 'Failed to create story']);
  exit;
}
?>
