<?php
/**
 * api/rankings/writers.php - Get top writers rankings
 * 
 * GET /api/rankings/writers?period=monthly&limit=200
 * 
 * Responses:
 * - Success: 200 with ranked writers
 * - Empty: 200 with empty items array
 * - Error: 400 or 500 with error message
 */

session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/RankingService.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $period = $_GET['period'] ?? 'monthly';
    $limit = (int)($_GET['limit'] ?? 200);
    
    // Validate period
    if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
        $period = 'monthly';
    }
    
    // Limit constraints
    $limit = min(max($limit, 1), 200); // Between 1 and 200
    
    // Get rankings
    $service = new RankingService($pdo);
    $writers = $service->getTopWriters($period, $limit);
    
    if (empty($writers)) {
        echo json_encode([
            'success' => true,
            'period' => $period,
            'message' => 'No writer rankings yet for ' . ucfirst($period),
            'items' => [],
        ]);
    } else {
        // Add rank number to each item
        foreach ($writers as $idx => &$item) {
            $item['rank'] = $idx + 1;
        }
        
        echo json_encode([
            'success' => true,
            'period' => $period,
            'items' => $writers,
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
