<?php
// ajax/get_chapters.php - Get story chapters
require_once __DIR__ . '/../config.php';

$story_id = intval($_GET['story_id'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT id, chapter_number, title FROM chapters 
        WHERE story_id = ? 
        ORDER BY sequence ASC
    ");
    $stmt->execute([$story_id]);
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    exit(json_encode(['ok' => true, 'chapters' => $chapters]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'chapters' => []]));
}
?>
