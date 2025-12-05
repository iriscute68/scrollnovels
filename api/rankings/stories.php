<?php
/**
 * api/rankings/stories.php - Get story rankings
 * 
 * GET /api/rankings/stories?period=daily&limit=50
 * 
 * Responses:
 * - Success: 200 with ranked stories
 * - Empty: 200 with empty items array and message
 * - Error: 400 or 500 with error message
 */

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/RankingService.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $period = $_GET['period'] ?? 'daily';
    $limit = (int)($_GET['limit'] ?? 50);
    
    // Validate period
    if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
        $period = 'daily';
    }
    
    // Limit constraints
    $limit = min(max($limit, 1), 200); // Between 1 and 200
    
    // Get rankings
    $service = new RankingService($pdo);
    $rankings = $service->getStoryRankings($period, $limit);
    
    if (empty($rankings)) {
        echo json_encode([
            'success' => true,
            'period' => $period,
            'message' => 'No rankings yet for ' . ucfirst($period),
            'items' => [],
        ]);
    } else {
        // Add rank number to each item
        foreach ($rankings as $idx => &$item) {
            $item['rank'] = $idx + 1;
        }
        
        echo json_encode([
            'success' => true,
            'period' => $period,
            'items' => $rankings,
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
}
?>
