<?php
// api/supporters/give-points.php - Deduct points from user and credit author
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$author_id = (int)($input['author_id'] ?? 0);
$story_id = (int)($input['story_id'] ?? 0);
$points = (int)($input['points'] ?? 0);

if (!$author_id || !$points || $points < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

if ($author_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot support yourself']);
    exit;
}

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_points (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL UNIQUE,
        points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('earn', 'redeem', 'purchase', 'admin') NOT NULL,
        points INT NOT NULL,
        description VARCHAR(255),
        story_id INT UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_type (type),
        INDEX idx_story (story_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS author_supporters (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        author_id INT UNSIGNED NOT NULL,
        supporter_id INT UNSIGNED NOT NULL,
        story_id INT UNSIGNED DEFAULT 0,
        points_total INT DEFAULT 0,
        last_supported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_author (author_id),
        INDEX idx_supporter (supporter_id),
        INDEX idx_story (story_id),
        UNIQUE KEY unique_support (author_id, supporter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Tables exist
}

try {
    $pdo->beginTransaction();
    
    // Check user's points balance
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        // Create user points record
        $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, 0)")->execute([$user_id]);
        $current_points = 0;
    } else {
        $current_points = (int)$result['points'];
    }
    
    if ($current_points < $points) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient points. You have ' . $current_points . ' points.'
        ]);
        exit;
    }
    
    // Deduct points from user
    $stmt = $pdo->prepare("UPDATE user_points SET points = points - ? WHERE user_id = ?");
    $stmt->execute([$points, $user_id]);

    // Create transaction record for supporter (negative points)
    $stmt = $pdo->prepare("INSERT INTO point_transactions (user_id, type, points, description) VALUES (?, 'redeem', ?, ?)");
    $stmt->execute([$user_id, -$points, "Supported author with {$points} points"]);

    // Credit the author: ensure they have a user_points row and add points
    $stmt = $pdo->prepare("INSERT INTO user_points (user_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = points + VALUES(points)");
    $stmt->execute([$author_id, $points]);

    // Create transaction record for author (earn)
    $stmt = $pdo->prepare("INSERT INTO point_transactions (user_id, type, points, description) VALUES (?, 'earn', ?, ?)");
    $stmt->execute([$author_id, $points, "Received support of {$points} points"]);
    
    // Update or insert author_supporters record
    // Aggregate all points by supporter per author (story_id defaults to 0)
    $stmt = $pdo->prepare("
        INSERT INTO author_supporters (author_id, supporter_id, story_id, points_total, last_supported_at)
        VALUES (?, ?, 0, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            points_total = points_total + VALUES(points_total),
            last_supported_at = NOW()
    ");
    $stmt->execute([$author_id, $user_id, $points]);
    
    // Send notification to author
    try {
        $actor_name = $_SESSION['username'] ?? null;
        $notifyMsg = ($actor_name ? "{$actor_name} supported you with {$points} points!" : "Someone supported you with {$points} points!");
        notify($pdo, $author_id, $user_id, 'support', $notifyMsg, '/pages/book.php?id=' . $story_id);
    } catch (Exception $e) {
        // Notification failed but transaction successful
    }
    
    $pdo->commit();
    
    // Get new balance
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $new_balance = (int)($stmt->fetch()['points'] ?? 0);
    // Get author's balance and top supporters summary
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
    $stmt->execute([$author_id]);
    $author_balance = (int)($stmt->fetch()['points'] ?? 0);

    $stmt = $pdo->prepare("SELECT a.supporter_id, a.points_total, a.last_supported_at, u.username, u.profile_image
        FROM author_supporters a
        LEFT JOIN users u ON a.supporter_id = u.id
        WHERE a.author_id = ?
        ORDER BY a.points_total DESC, a.last_supported_at DESC
        LIMIT 10");
    $stmt->execute([$author_id]);
    $top_supporters = $stmt->fetchAll();

    // Render a small HTML snippet for the supporters list (so frontend can inject without extra request)
    $supporters_html = '';
    if (!empty($top_supporters)) {
        foreach ($top_supporters as $idx => $sp) {
            $username = htmlspecialchars($sp['username'] ?? 'Anonymous', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $points_total = number_format((int)($sp['points_total'] ?? 0));
            $profileUrl = site_url('/pages/profile.php?user_id=' . (int)($sp['supporter_id'] ?? 0));
            $imgHtml = '';
            if (!empty($sp['profile_image'])) {
                $imgHtml = '<img src="' . htmlspecialchars($sp['profile_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="avatar" class="w-full h-full object-cover">';
            } else {
                $imgHtml = '<span class="text-lg">👤</span>';
            }
            $last = !empty($sp['last_supported_at']) ? ('<div class="text-xs text-gray-500">' . htmlspecialchars(time_ago($sp['last_supported_at'])) . '</div>') : '';

            $supporters_html .= "<div class=\"p-3 rounded-lg flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition\">";
            $supporters_html .= "<div class=\"flex items-center gap-3\">";
            $supporters_html .= "<a href=\"{$profileUrl}\" class=\"w-10 h-10 rounded-full bg-gray-100 overflow-hidden inline-block\">{$imgHtml}</a>";
            $supporters_html .= "<div><a href=\"{$profileUrl}\" class=\"font-semibold text-sm\">{$username}</a>" . $last . "</div>";
            $supporters_html .= "</div>"; // end left
            $supporters_html .= "<div class=\"text-right\"><div class=\"text-sm font-semibold\">{$points_total} pts</div></div>";
            $supporters_html .= "</div>";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your support!',
        'new_balance' => $new_balance,
        'author_balance' => $author_balance,
        'author_supporters' => $top_supporters,
        'author_supporters_html' => $supporters_html
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed: ' . $e->getMessage()
    ]);
}
