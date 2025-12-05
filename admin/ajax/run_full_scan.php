<?php
// admin/ajax/run_full_scan.php - Queue batch scans on recent chapters
require_once __DIR__ . '/../../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

// Pick 200 recent chapters not scanned in last 30 days
$stmt = $pdo->prepare("
    SELECT c.id FROM chapters c 
    LEFT JOIN plagiarism_scans ps ON ps.chapter_id = c.id 
        AND ps.requested_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE ps.id IS NULL 
    ORDER BY c.created_at DESC 
    LIMIT 200
");
$stmt->execute();
$chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
$ins = $pdo->prepare("
    INSERT INTO plagiarism_scans 
    (chapter_id, story_id, admin_id, status, requested_at) 
    VALUES (?, ?, ?, 'queued', NOW())
");

foreach ($chapters as $c) {
    // Fetch story_id
    $s = $pdo->prepare("SELECT story_id FROM chapters WHERE id = ? LIMIT 1");
    $s->execute([$c['id']]);
    $si = $s->fetchColumn();
    
    $ins->execute([$c['id'], $si ?: null, $_SESSION['admin_user']['id'] ?? null]);
    $count++;
}

// Log activity
$pdo->prepare("INSERT INTO admin_activity_logs (admin_id, action, created_at) VALUES (?, ?, NOW())")
    ->execute([$_SESSION['admin_user']['id'] ?? null, "Queued batch scan for $count chapters"]);

echo json_encode(['ok' => true, 'message' => "Queued $count chapters for scanning"]);
