<?php
// ajax/submit_competition_entry.php - User submits a story to a competition
require_once __DIR__ . '/../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$competition_id = intval($_POST['competition_id'] ?? 0);
$story_id = intval($_POST['story_id'] ?? 0);

if (!$competition_id || !$story_id) {
    echo json_encode(['ok' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Fetch competition details
    $comp = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
    $comp->execute([$competition_id]);
    $competition = $comp->fetch(PDO::FETCH_ASSOC);

    if (!$competition) {
        echo json_encode(['ok' => false, 'message' => 'Competition not found']);
        exit;
    }

    // Check if competition is open
    $now = new DateTime();
    $start = new DateTime($competition['start_date']);
    $end = new DateTime($competition['end_date']);

    if ($now < $start) {
        echo json_encode(['ok' => false, 'message' => 'Competition has not started yet']);
        exit;
    }

    if ($now > $end) {
        echo json_encode(['ok' => false, 'message' => 'Competition has ended']);
        exit;
    }

    // Fetch story details
    $story = $pdo->prepare("SELECT * FROM stories WHERE id = ? AND author_id = ?");
    $story->execute([$story_id, $user_id]);
    $story_data = $story->fetch(PDO::FETCH_ASSOC);

    if (!$story_data) {
        echo json_encode(['ok' => false, 'message' => 'Story not found or you don\'t own it']);
        exit;
    }

    // Check if story is eligible for competition
    if (!$story_data['is_competition_eligible']) {
        echo json_encode(['ok' => false, 'message' => 'Story is not marked as competition eligible']);
        exit;
    }

    // Check if story was created after competition started
    $story_created = new DateTime($story_data['created_at']);
    if ($story_created < $start) {
        echo json_encode(['ok' => false, 'message' => 'Story was created before this competition started']);
        exit;
    }

    // Check if already submitted
    $existing = $pdo->prepare("
        SELECT id FROM competition_entries 
        WHERE competition_id = ? AND story_id = ? AND user_id = ?
    ");
    $existing->execute([$competition_id, $story_id, $user_id]);

    if ($existing->rowCount() > 0) {
        echo json_encode(['ok' => false, 'message' => 'You have already submitted this story to this competition']);
        exit;
    }

    // Check submission limit (if any)
    $submission_limit = $competition['submission_limit'] ?? 0;
    if ($submission_limit > 0) {
        $count = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM competition_entries
            WHERE competition_id = ? AND user_id = ?
        ");
        $count->execute([$competition_id, $user_id]);
        $current = $count->fetch(PDO::FETCH_ASSOC)['cnt'];

        if ($current >= $submission_limit) {
            echo json_encode(['ok' => false, 'message' => "Submission limit ({$submission_limit}) reached"]);
            exit;
        }
    }

    // Create entry (set status to 'active' to match schema)
    $insert = $pdo->prepare("\
        INSERT INTO competition_entries (competition_id, story_id, user_id, submitted_at, status)\
        VALUES (?, ?, ?, NOW(), 'active')\
    ");
    $insert->execute([$competition_id, $story_id, $user_id]);

    $entry_id = $pdo->lastInsertId();

    // Log activity
    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, created_at)
        VALUES (?, ?, NOW())
    ")->execute([$user_id, "Submitted story #{$story_id} to competition #{$competition_id}"]);

    echo json_encode([
        'ok' => true,
        'message' => 'Story submitted successfully!',
        'entry_id' => $entry_id,
        'redirect' => "/competitions/{$competition_id}"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
