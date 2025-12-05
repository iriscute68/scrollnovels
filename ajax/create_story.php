<?php
// ajax/create_story.php - Create new story
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $genre = $data['genre'] ?? '';
    $maturity_rating = $data['maturity_rating'] ?? 'G';
    $tags = $data['tags'] ?? '';
    $allow_comments = intval($data['allow_comments'] ?? 1);
    $is_competition_eligible = intval($data['is_competition_eligible'] ?? 0);
    $has_paywall = intval($data['has_paywall'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (!$title || !$description) {
        exit(json_encode(['ok' => false, 'message' => 'Title and description required']));
    }

    // Generate slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $counter = 1;
    $original_slug = $slug;
    
    while (true) {
        $check_slug = $slug . ($counter > 1 ? '-' . $counter : '');
        $stmt = $pdo->prepare("SELECT id FROM stories WHERE slug = ?");
        $stmt->execute([$check_slug]);
        if (!$stmt->fetch()) {
            $slug = $check_slug;
            break;
        }
        $counter++;
    }

    // Create story
    $stmt = $pdo->prepare("
        INSERT INTO stories (user_id, title, slug, description, genre, maturity_rating, tags, 
                            allow_comments, is_competition_eligible, has_paywall, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $user_id, $title, $slug, $description, $genre, $maturity_rating, $tags,
        $allow_comments, $is_competition_eligible, $has_paywall, 'draft'
    ]);

    $story_id = $pdo->lastInsertId();

    // Log activity
    $pdo->prepare("
        INSERT INTO admin_activity_logs (user_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $user_id,
        'story_create',
        json_encode(['story_id' => $story_id, 'title' => $title])
    ]);

    exit(json_encode(['ok' => true, 'story_id' => $story_id]));
} catch (Exception $e) {
    error_log('Story creation error: ' . $e->getMessage());
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
