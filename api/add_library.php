<?php
// api/add_library.php - toggle saved_stories for current user
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$story_id = (int)($input['story_id'] ?? 0);

if (!$story_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid story id']);
    exit;
}

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_stories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_save (user_id, story_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT 1 FROM saved_stories WHERE user_id = ? AND story_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $story_id]);
    $exists = (bool)$stmt->fetchColumn();

    if ($exists) {
        $pdo->prepare("DELETE FROM saved_stories WHERE user_id = ? AND story_id = ?")->execute([$_SESSION['user_id'], $story_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'inLibrary' => false, 'ok' => true]);
    } else {
        $pdo->prepare("INSERT INTO saved_stories (user_id, story_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $story_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'inLibrary' => true, 'ok' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
