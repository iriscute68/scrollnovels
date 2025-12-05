<?php
// api/patreon-connect.php - OAuth callback handler for Patreon

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Patreon OAuth credentials (set these in your .env or config)
define('PATREON_CLIENT_ID', getenv('PATREON_CLIENT_ID') ?: 'your_client_id');
define('PATREON_CLIENT_SECRET', getenv('PATREON_CLIENT_SECRET') ?: 'your_client_secret');
define('PATREON_REDIRECT_URI', site_url('/api/patreon-callback.php'));

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'connect';

// STEP 1: Generate auth URL
if ($action === 'get_auth_url') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['patreon_state'] = $state;

    $authUrl = 'https://www.patreon.com/oauth2/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => PATREON_CLIENT_ID,
        'redirect_uri' => PATREON_REDIRECT_URI,
        'scope' => 'identity identity[email] campaigns campaigns.members',
        'state' => $state
    ]);

    echo json_encode(['auth_url' => $authUrl]);
    exit;
}

// STEP 2: Handle callback (after user approves)
if ($action === 'callback') {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    if ($error) {
        http_response_code(400);
        echo json_encode(['error' => $error]);
        exit;
    }

    if (!$code || $state !== ($_SESSION['patreon_state'] ?? null)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid state or authorization code']);
        exit;
    }

    try {
        // Exchange code for access token
        $tokenResponse = callPatreonAPI('https://www.patreon.com/api/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => PATREON_CLIENT_ID,
            'client_secret' => PATREON_CLIENT_SECRET,
            'redirect_uri' => PATREON_REDIRECT_URI
        ]);

        if (!isset($tokenResponse['access_token'])) {
            throw new Exception('No access token in response');
        }

        $accessToken = $tokenResponse['access_token'];
        $refreshToken = $tokenResponse['refresh_token'] ?? null;
        $expiresIn = $tokenResponse['expires_in'] ?? 3600;

        // Get user info from Patreon
        $userInfo = callPatreonAPI(
            'https://www.patreon.com/api/oauth2/v2/identity?include=memberships.currently_entitled_tiers',
            [],
            $accessToken
        );

        if (!isset($userInfo['data']['id'])) {
            throw new Exception('Failed to get Patreon user ID');
        }

        $patreonUserId = $userInfo['data']['id'];
        $patreonEmail = $userInfo['data']['attributes']['email'] ?? null;

        // Get tier info if available
        $tierInfo = null;
        $tierId = null;
        $tierName = null;
        $amountCents = 0;

        if (isset($userInfo['included']) && is_array($userInfo['included'])) {
            foreach ($userInfo['included'] as $item) {
                if ($item['type'] === 'tier' && isset($item['attributes'])) {
                    $tierId = $item['id'];
                    $tierName = $item['attributes']['title'] ?? 'Supporter';
                    $amountCents = $item['attributes']['amount_cents'] ?? 0;
                    break;
                }
            }
        }

        // Check if user already has Patreon linked
        $stmt = $pdo->prepare("SELECT id FROM patreon_links WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existingLink = $stmt->fetch();

        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        if ($existingLink) {
            // Update existing link
            $stmt = $pdo->prepare("
                UPDATE patreon_links SET
                    patreon_user_id = ?,
                    patreon_email = ?,
                    tier_id = ?,
                    tier_name = ?,
                    amount_cents = ?,
                    active = TRUE,
                    access_token = ?,
                    refresh_token = ?,
                    token_expires_at = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([
                $patreonUserId, $patreonEmail, $tierId, $tierName,
                $amountCents, $accessToken, $refreshToken, $expiresAt, $userId
            ]);
        } else {
            // Create new link
            $stmt = $pdo->prepare("
                INSERT INTO patreon_links 
                (user_id, patreon_user_id, patreon_email, tier_id, tier_name, amount_cents, active, access_token, refresh_token, token_expires_at)
                VALUES (?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $patreonUserId, $patreonEmail, $tierId, $tierName,
                $amountCents, $accessToken, $refreshToken, $expiresAt
            ]);
        }

        // Award initial points based on tier
        if ($tierId) {
            awardPatreonPoints($userId, $tierId, $pdo);
        }

        $_SESSION['patreon_linked'] = true;

        echo json_encode([
            'success' => true,
            'message' => '✓ Patreon account linked successfully!',
            'tier' => $tierName,
            'email' => $patreonEmail
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// STEP 3: Unlink Patreon account
if ($action === 'unlink') {
    try {
        $stmt = $pdo->prepare("
            UPDATE patreon_links 
            SET active = FALSE 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true, 'message' => 'Patreon account unlinked']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/**
 * Call Patreon API
 */
function callPatreonAPI($url, $postData = [], $accessToken = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    if ($accessToken) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken"
        ]);
    }

    if (!empty($postData)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Patreon API error: HTTP $httpCode");
    }

    return json_decode($response, true);
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

        // Ensure user has points record
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

        // Update last reward date
        $stmt = $pdo->prepare("
            UPDATE patreon_links 
            SET last_reward_date = NOW(),
                next_reward_date = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }
}
?>
