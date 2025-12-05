<?php
// api/patreon-webhook.php - Receive Patreon webhooks for membership updates

require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

// Verify webhook signature
$signature = $_SERVER['HTTP_X_PATREON_SIGNATURE'] ?? null;
if (!$signature || !verifyPatreonSignature($signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$input = file_get_contents('php://input');
$event = json_decode($input, true);

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$eventType = $event['data']['type'] ?? null;
$eventData = $event['data']['attributes'] ?? [];

try {
    switch ($eventType) {
        // New membership/pledge created
        case 'members:pledge:create':
            handlePledgeCreate($event, $pdo);
            break;

        // Membership updated (charged, tier changed, etc.)
        case 'members:pledge:update':
            handlePledgeUpdate($event, $pdo);
            break;

        // Membership deleted/cancelled
        case 'members:pledge:delete':
            handlePledgeDelete($event, $pdo);
            break;

        default:
            error_log("Unknown Patreon event type: $eventType");
    }

    echo json_encode(['success' => true, 'event' => $eventType]);

} catch (Exception $e) {
    error_log("Patreon webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Verify webhook signature
 */
function verifyPatreonSignature($signature) {
    $secret = getenv('PATREON_WEBHOOK_SECRET') ?: 'your_webhook_secret';
    $body = file_get_contents('php://input');
    $computedSig = md5($body . $secret);
    return hash_equals($signature, $computedSig);
}

/**
 * Handle new pledge
 */
function handlePledgeCreate($event, $pdo) {
    $patreonUserId = $event['data']['relationships']['patron']['data']['id'] ?? null;
    $tierId = $event['data']['relationships']['reward']['data']['id'] ?? null;

    if (!$patreonUserId) return;

    $stmt = $pdo->prepare("
        SELECT user_id FROM patreon_links WHERE patreon_user_id = ?
    ");
    $stmt->execute([$patreonUserId]);
    $user = $stmt->fetch();

    if ($user) {
        awardPatreonPoints($user['user_id'], $tierId, $pdo);
        error_log("Awarded points for new pledge from $patreonUserId");
    }
}

/**
 * Handle pledge update (monthly charge, tier change, etc.)
 */
function handlePledgeUpdate($event, $pdo) {
    $patreonUserId = $event['data']['relationships']['patron']['data']['id'] ?? null;
    $tierId = $event['data']['relationships']['reward']['data']['id'] ?? null;
    $chargeStatus = $event['data']['attributes']['charge_status'] ?? null;

    if (!$patreonUserId) return;

    $stmt = $pdo->prepare("
        SELECT user_id, last_reward_date FROM patreon_links WHERE patreon_user_id = ?
    ");
    $stmt->execute([$patreonUserId]);
    $user = $stmt->fetch();

    if ($user && $chargeStatus === 'paid') {
        // Only award points once per calendar month
        $lastRewardMonth = $user['last_reward_date'] ? date('Y-m', strtotime($user['last_reward_date'])) : null;
        $currentMonth = date('Y-m');

        if ($lastRewardMonth !== $currentMonth) {
            awardPatreonPoints($user['user_id'], $tierId, $pdo);
            error_log("Awarded monthly points for $patreonUserId");
        }
    }
}

/**
 * Handle pledge deletion/cancellation
 */
function handlePledgeDelete($event, $pdo) {
    $patreonUserId = $event['data']['relationships']['patron']['data']['id'] ?? null;

    if (!$patreonUserId) return;

    $stmt = $pdo->prepare("
        UPDATE patreon_links 
        SET active = FALSE 
        WHERE patreon_user_id = ?
    ");
    $stmt->execute([$patreonUserId]);

    error_log("Deactivated Patreon link for $patreonUserId");
}

/**
 * Award points based on Patreon tier
 */
function awardPatreonPoints($userId, $tierId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT monthly_points FROM patreon_tier_rewards WHERE tier_id = ?
    ");
    $stmt->execute([$tierId]);
    $tier = $stmt->fetch();

    if ($tier && $tier['monthly_points'] > 0) {
        $points = (int)$tier['monthly_points'];

        // Update user points
        $stmt = $pdo->prepare("
            INSERT INTO user_points (user_id, free_points, premium_points, patreon_points, total_points)
            VALUES (?, 0, 0, ?, ?)
            ON DUPLICATE KEY UPDATE
                patreon_points = patreon_points + ?,
                total_points = total_points + ?
        ");
        $stmt->execute([$userId, $points, $points, $points, $points]);

        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO points_transactions (user_id, points, type, source)
            VALUES (?, ?, 'patreon', 'patreon_tier')
        ");
        $stmt->execute([$userId, $points]);
    }
}
?>
