<?php
// api/user-stories.php - Get current user's stories
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.cover, s.status, s.word_count,
               (SELECT COUNT(*) FROM chapters WHERE story_id = s.id) as chapter_count
        FROM stories s
        WHERE s.author_id = ? AND s.status IN ('published', 'active', 'draft')
        ORDER BY s.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($stories);
} catch (Exception $e) {
    error_log('user-stories error: ' . $e->getMessage());
    echo json_encode([]);
}
