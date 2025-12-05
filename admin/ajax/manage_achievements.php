<?php
// /admin/ajax/manage_achievements.php
// Handle achievement operations via AJAX

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Check admin authentication
require_once dirname(__DIR__) . '/../config/db.php';
require_once dirname(__DIR__) . '/../includes/auth.php';

// For testing: allow both admin and regular users for GET requests
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$isAdmin = isset($_SESSION['admin_id']);
$userId = $_SESSION['user_id'] ?? null;

// Response helper
function respond($success, $message, $data = []) {
    wp_die(json_encode(array_merge(['success' => $success, 'message' => $message], $data)));
}

try {
    switch ($action) {
        case 'get_user_achievements':
            // Get achievements for a specific user
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $stmt = $pdo->prepare("
                SELECT a.* 
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                ORDER BY a.category, a.total
            ");
            $stmt->execute([$userId]);
            $achievements = $stmt->fetchAll();
            
            respond(true, 'Achievements retrieved', ['achievements' => $achievements]);
            break;

        case 'unlock_achievement':
            // Unlock an achievement for a user
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $achievementId = (int)($_POST['achievement_id'] ?? 0);
            if (!$achievementId) {
                respond(false, 'Invalid achievement ID');
            }
            
            // Check if already unlocked
            $check = $pdo->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
            $check->execute([$userId, $achievementId]);
            if ($check->fetch()) {
                respond(false, 'Achievement already unlocked');
            }
            
            // Unlock achievement
            $stmt = $pdo->prepare("INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$userId, $achievementId]);
            
            respond(true, 'Achievement unlocked!');
            break;

        case 'list_achievements':
            // List all achievements (for admin)
            if (!$isAdmin) {
                respond(false, 'Admin access required');
            }
            
            $achievements = $pdo->query("SELECT * FROM achievements ORDER BY category, total")->fetchAll();
            respond(true, 'Achievements retrieved', ['achievements' => $achievements, 'count' => count($achievements)]);
            break;

        case 'create_achievement':
            // Create new achievement (admin only)
            if (!$isAdmin) {
                respond(false, 'Admin access required');
            }
            
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '🏅');
            $category = trim($_POST['category'] ?? 'Other');
            $total = (int)($_POST['total'] ?? 1);
            
            if (!$name) {
                respond(false, 'Achievement name required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO achievements (name, description, icon, category, total, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $icon, $category, $total]);
            
            respond(true, 'Achievement created successfully', ['achievement_id' => $pdo->lastInsertId()]);
            break;

        case 'get_achievement_stats':
            // Get statistics about achievements
            $stats = [];
            
            // Total achievements
            $stats['total_achievements'] = $pdo->query("SELECT COUNT(*) as cnt FROM achievements")->fetch()['cnt'];
            
            // Users with achievements
            $stats['users_with_achievements'] = $pdo->query("
                SELECT COUNT(DISTINCT user_id) as cnt FROM user_achievements
            ")->fetch()['cnt'];
            
            // Most unlocked achievement
            $most = $pdo->query("
                SELECT a.name, COUNT(ua.id) as unlock_count
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id
                GROUP BY a.id
                ORDER BY unlock_count DESC
                LIMIT 1
            ")->fetch();
            $stats['most_unlocked'] = $most;
            
            respond(true, 'Stats retrieved', $stats);
            break;

        default:
            respond(false, 'Unknown action: ' . htmlspecialchars($action ?? 'none'));
    }
} catch (Exception $e) {
    respond(false, 'Error: ' . $e->getMessage());
}

function wp_die($message) {
    echo $message;
    exit;
}
?>
