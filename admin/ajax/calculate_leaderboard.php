<?php
// admin/ajax/calculate_leaderboard.php - Calculate competition leaderboard rankings

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
session_start();

// Only admins can calculate
if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Forbidden']));
}

$competition_id = intval($_GET['competition_id'] ?? 0);
if (!$competition_id) {
    exit(json_encode(['ok' => false, 'message' => 'Missing competition_id']));
}

try {
    // Get all entries for this competition with average judge scores
    $stmt = $pdo->query("
        SELECT 
            es.id AS entry_id,
            es.story_id,
            es.user_id,
            s.title AS story_title,
            u.username,
            ROUND(AVG(js.score), 2) AS avg_score,
            COUNT(js.id) AS judge_count
        FROM judge_scores js
        JOIN (
            SELECT id, story_id, user_id 
            FROM submissions 
            WHERE competition_id = $competition_id
        ) es ON es.id = js.entry_id
        JOIN stories s ON s.id = es.story_id
        JOIN users u ON u.id = es.user_id
        GROUP BY es.id
        ORDER BY avg_score DESC
    ");
    
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add ranking
    $rank = 0;
    foreach ($leaderboard as &$entry) {
        $entry['rank'] = ++$rank;
    }
    
    // Log the calculation
    try {
        $log = $pdo->prepare("
            INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $log->execute([
            $_SESSION['user_id'],
            'calculate_leaderboard',
            'competition',
            $competition_id
        ]);
    } catch (Exception $e) {}
    
    echo json_encode([
        'ok' => true,
        'leaderboard' => $leaderboard,
        'competition_id' => $competition_id,
        'entry_count' => count($leaderboard)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>
