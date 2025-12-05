<?php
// ajax/save_chapter.php - Save chapter
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $story_id = intval($data['story_id'] ?? 0);
    $chapter_id = intval($data['chapter_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $chapter_number = intval($data['chapter_number'] ?? 0);
    $content = $data['content'] ?? '';
    $is_published = intval($data['is_published'] ?? 0);
    $price = floatval($data['price'] ?? 0.99);

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND user_id = ?");
    $stmt->execute([$story_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
    }

    $word_count = str_word_count(strip_tags($content));

    if ($chapter_id) {
        // Update
        $stmt = $pdo->prepare("
            UPDATE chapters 
            SET title = ?, chapter_number = ?, content = ?, is_published = ?, 
                price = ?, word_count = ?, updated_at = NOW()
            WHERE id = ? AND story_id = ?
        ");
        $stmt->execute([$title, $chapter_number, $content, $is_published, $price, $word_count, $chapter_id, $story_id]);
    } else {
        // Create
        $stmt = $pdo->prepare("
            INSERT INTO chapters (story_id, title, chapter_number, content, is_published, price, word_count, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$story_id, $title, $chapter_number, $content, $is_published, $price, $word_count]);
        $chapter_id = $pdo->lastInsertId();
    }

    // Update story metadata
    $pdo->prepare("
        UPDATE story_meta 
        SET total_chapters = (SELECT COUNT(*) FROM chapters WHERE story_id = ? AND is_published = 1),
            total_words = COALESCE((SELECT SUM(word_count) FROM chapters WHERE story_id = ? AND is_published = 1), 0)
        WHERE story_id = ?
    ")->execute([$story_id, $story_id, $story_id]);

    exit(json_encode(['ok' => true, 'chapter_id' => $chapter_id]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
