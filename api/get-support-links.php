<?php
// Modal to show author's support links (Ko-fi / Patreon)
// This will be called via AJAX when clicking on support button

require_once dirname(__DIR__) . '/config/db.php';

$story_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No story ID provided']);
    exit;
}

try {
    // Fetch story author and their support links
    $stmt = $pdo->prepare("
        SELECT u.kofi, u.patreon, s.title, s.author_id
        FROM stories s
        JOIN users u ON s.author_id = u.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$story_id]);
    $author = $stmt->fetch();

    if (!$author) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Story not found']);
        exit;
    }

    // If no support links set
    if (!$author['kofi'] && !$author['patreon']) {
        echo json_encode([
            'success' => true,
            'has_links' => false,
            'message' => 'This author hasn\'t set up support links yet.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'has_links' => true,
        'story_title' => $author['title'],
        'kofi' => $author['kofi'],
        'patreon' => $author['patreon']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
