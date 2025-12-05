<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

$user = current_user();
$competition_id = intval($_POST['competition_id'] ?? 0);
$story_id = intval($_POST['story_id'] ?? 0);

if (!$competition_id || !$story_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing parameters']);
  exit;
}

// load competition
$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id=?");
$stmt->execute([$competition_id]);
$comp = $stmt->fetch();

// load story
$stmt = $pdo->prepare("SELECT * FROM stories WHERE id=?");
$stmt->execute([$story_id]);
$story = $stmt->fetch();

if (!$story || $story['user_id'] != $user['id']) {
  http_response_code(403);
  echo json_encode(['error' => 'Not your story']);
  exit;
}

// Check eligibility rules:
if (!$story['is_competition_eligible']) {
  http_response_code(400);
  echo json_encode(['error' => 'Story not marked for competition. Please mark as created-for-competition when creating the story.']);
  exit;
}

// Optional additional checks (created after competition start)
$compStart = strtotime($comp['start_date'] ?? 0);
$storyCreated = strtotime($story['created_at']);
if ($compStart && $storyCreated < $compStart) {
  http_response_code(400);
  echo json_encode(['error' => 'Story must be created after competition start to be eligible.']);
  exit;
}

// Unique constraint check
$stmt = $pdo->prepare("SELECT id FROM competition_submissions WHERE competition_id=? AND story_id=?");
$stmt->execute([$competition_id, $story_id]);
if ($stmt->fetch()) {
  http_response_code(400);
  echo json_encode(['error' => 'Story already submitted']);
  exit;
}

// Insert submission
$pdo->prepare("INSERT INTO competition_submissions (competition_id, story_id, user_id) VALUES (?, ?, ?)")
    ->execute([$competition_id, $story_id, $user['id']]);

echo json_encode(['success' => true]);
?>
