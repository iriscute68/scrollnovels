<?php
// api/create-competition-book.php - Create a book for competition entry
header('Content-Type: application/json');
session_start();

require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$competition_id = (int)($data['competition_id'] ?? 0);
$book_title = trim($data['book_title'] ?? '');

if (!$competition_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Competition ID required']);
    exit;
}

if (strlen($book_title) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Book title must be at least 3 characters']);
    exit;
}

try {
    // Verify competition exists
    $stmt = $pdo->prepare("SELECT id, title FROM competitions WHERE id = ?");
    $stmt->execute([$competition_id]);
    $comp = $stmt->fetch();
    
    if (!$comp) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Competition not found']);
        exit;
    }
    
    // Create new book/story with competition tag
    $slug = slugify($book_title);
    $description = 'Entry for ' . htmlspecialchars($comp['title']) . ' competition';
    
    $stmt = $pdo->prepare("
        INSERT INTO stories (user_id, title, slug, description, status, content_type, created_at)
        VALUES (?, ?, ?, ?, 'draft', 'novel', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $book_title, $slug, $description]);
    $book_id = $pdo->lastInsertId();
    
    // Tag the story with 'competition' tag
    // First get or create the competition tag
    $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = 'competition' LIMIT 1");
    $stmt->execute();
    $tag = $stmt->fetch();
    
    if (!$tag) {
        $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
        $stmt->execute(['competition', 'competition']);
        $tag_id = $pdo->lastInsertId();
    } else {
        $tag_id = $tag['id'];
    }
    
    // Add story_tag relationship
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO story_tags (story_id, tag_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$book_id, $tag_id]);
    
    // Record competition entry
    $stmt = $pdo->prepare("
        INSERT INTO competition_entries (competition_id, user_id, story_id, entry_date)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$competition_id, $_SESSION['user_id'], $book_id]);
    
    // Send notification
    notify(
        $pdo,
        $_SESSION['user_id'],
        $_SESSION['user_id'],
        'competition',
        'You have successfully joined the ' . htmlspecialchars($comp['title']) . ' competition!',
        '/pages/book.php?id=' . $book_id
    );
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book created successfully!',
        'book_id' => $book_id,
        'book_link' => site_url('/pages/book.php?id=' . $book_id)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
