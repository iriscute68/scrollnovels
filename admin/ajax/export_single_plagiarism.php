<?php
// admin/ajax/export_single_plagiarism.php - Export single plagiarism report as text
require_once __DIR__ . '/../../config.php';
session_start();

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    exit("Forbidden");
}

$id = intval($_GET['id'] ?? 0);
if (!$id) exit("Invalid ID");

$stmt = $pdo->prepare("
    SELECT r.*, c.title AS chapter_title, 
           s.title AS story_title, u.username AS author,
           c.content AS chapter_text
    FROM plagiarism_reports r
    JOIN chapters c ON c.id = r.chapter_id
    JOIN stories s ON s.id = r.story_id
    LEFT JOIN users u ON u.id = s.author_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) exit("Report not found");

header('Content-Type: text/plain; charset=utf-8');
header("Content-Disposition: attachment; filename=plagiarism_report_{$id}.txt");

echo "PLAGIARISM DETECTION REPORT\n";
echo "===========================\n\n";
echo "Report ID: {$r['id']}\n";
echo "Date: {$r['created_at']}\n";
echo "Status: {$r['status']}\n";
echo "Similarity Score: {$r['score']}%\n\n";

echo "Story: {$r['story_title']}\n";
echo "Chapter: {$r['chapter_title']}\n";
echo "Author: {$r['author']}\n\n";

echo "CHAPTER TEXT\n";
echo "============\n";
echo $r['chapter_text'] . "\n\n";

echo "PLAGIARISM MATCHES\n";
echo "==================\n";
$matches = json_decode($r['matches_json'], true) ?: [];
if (empty($matches)) {
    echo "No matches found.\n";
} else {
    foreach ($matches as $i => $m) {
        echo "\nMatch #" . ($i + 1) . "\n";
        echo "Source: {$m['source_title']}\n";
        echo "URL: {$m['source_url']}\n";
        echo "Score: {$m['score']}%\n";
        echo "Snippet:\n";
        echo $m['snippet'] . "\n";
        echo str_repeat('-', 80) . "\n";
    }
}

echo "\n\nReport generated: " . date('Y-m-d H:i:s') . "\n";
?>
