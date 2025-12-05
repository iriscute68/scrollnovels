<?php
// admin/ajax/get_assigned_reports.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    $adminId = $_SESSION['user_id'] ?? 0;
    
    // Get assigned reports for this admin
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE assignee_id = ? ORDER BY created_at DESC LIMIT 200");
    $stmt->execute([$adminId]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary stats
    $summary = [];
    $summary['total'] = count($reports);
    $summary['open'] = count(array_filter($reports, fn($r) => $r['status'] === 'open'));
    $summary['in_review'] = count(array_filter($reports, fn($r) => $r['status'] === 'in_review'));
    
    echo json_encode([
        'ok' => true,
        'reports' => $reports,
        'summary' => $summary
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
