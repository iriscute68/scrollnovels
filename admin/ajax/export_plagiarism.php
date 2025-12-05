<?php
// admin/ajax/export_plagiarism.php - Export plagiarism reports to CSV

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

session_start();

// Only admins can export
if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    exit('Forbidden');
}

try {
    // Fetch all plagiarism reports with related data
    $stmt = $pdo->query("
        SELECT 
            pr.id,
            pr.scan_id,
            pr.chapter_id,
            pr.story_id,
            pr.score,
            pr.status,
            pr.created_at,
            pr.resolved_at,
            c.title AS chapter_title,
            s.title AS story_title,
            u.username AS author
        FROM plagiarism_reports pr
        LEFT JOIN chapters c ON c.id = pr.chapter_id
        LEFT JOIN stories s ON s.id = pr.story_id
        LEFT JOIN users u ON u.id = s.author_id
        ORDER BY pr.created_at DESC
    ");
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV
    $filename = 'plagiarism-reports-' . date('Y-m-d-H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, [
        'Report ID',
        'Scan ID',
        'Chapter ID',
        'Chapter Title',
        'Story ID',
        'Story Title',
        'Author',
        'Plagiarism Score',
        'Status',
        'Created At',
        'Resolved At'
    ]);
    
    // CSV Data
    foreach ($reports as $row) {
        fputcsv($output, [
            $row['id'],
            $row['scan_id'],
            $row['chapter_id'],
            $row['chapter_title'] ?? '',
            $row['story_id'],
            $row['story_title'] ?? '',
            $row['author'] ?? '',
            $row['score'],
            $row['status'],
            $row['created_at'],
            $row['resolved_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
