<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Rate limiting check (5 reports per hour per IP)
try {
    global $pdo;
    
    $clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Check rate limit
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$clientIp]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many reports. Please try again later.']);
        exit;
    }
    
    // Get form data
    $reportType = $_POST['type'] ?? ''; // user, story, chapter, comment
    $targetId = (int)($_POST['target_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';
    $description = $_POST['description'] ?? '';
    $honeypot = $_POST['website'] ?? ''; // Honeypot field
    
    if (!$reportType || !$targetId || !$reason || !$description) {
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    // Honeypot check
    if (!empty($honeypot)) {
        // Silently fail spam bot submissions
        echo json_encode(['ok' => true, 'message' => 'Report submitted']);
        exit;
    }
    
    // Validate report type
    if (!in_array($reportType, ['user', 'story', 'chapter', 'comment'])) {
        echo json_encode(['error' => 'Invalid report type']);
        exit;
    }
    
    // Prevent excessive description (spam filter)
    if (strlen($description) > 5000) {
        echo json_encode(['error' => 'Description too long']);
        exit;
    }
    
    // Check for spam keywords
    $spamKeywords = ['viagra', 'casino', 'forex', 'bitcoin', 'pharma', 'cheap'];
    $descLower = strtolower($description . ' ' . $reason);
    foreach ($spamKeywords as $keyword) {
        if (strpos($descLower, $keyword) !== false) {
            echo json_encode(['ok' => true, 'message' => 'Report submitted']);
            exit;
        }
    }
    
    // Create report
    $stmt = $pdo->prepare("
        INSERT INTO reports (type, target_id, reason, description, ip_address, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'open', NOW())
    ");
    $stmt->execute([$reportType, $targetId, $reason, $description, $clientIp]);
    
    // Log rate limit
    $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, created_at) VALUES (?, NOW())");
    $stmt->execute([$clientIp]);
    
    // Log submission
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (action, reason, created_at) VALUES ('public_report', ?, NOW())");
    $stmt->execute(["Public report: $reportType ID: $targetId - $reason"]);
    
    echo json_encode([
        'ok' => true,
        'message' => 'Thank you! Your report has been submitted and will be reviewed by our moderation team.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}
?>
