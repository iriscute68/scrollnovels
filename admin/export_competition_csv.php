<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

require_admin();

$comp_id = intval($_GET['comp_id'] ?? 0);

$sql = "SELECT ce.id, ce.story_id, ce.total_score, ce.votes, ce.views, s.title, u.username, u.email
        FROM competition_entries ce
        JOIN stories s ON s.id = ce.story_id
        JOIN users u ON u.id = ce.user_id
        WHERE ce.competition_id = ?
        ORDER BY ce.total_score DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$comp_id]);
$entries = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="competition_entries_' . $comp_id . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Rank', 'Story Title', 'Author', 'Email', 'Score', 'Votes', 'Views']);

foreach ($entries as $idx => $e) {
  fputcsv($output, [
    $idx + 1,
    $e['title'],
    $e['username'],
    $e['email'],
    $e['total_score'],
    $e['votes'],
    $e['views']
  ]);
}

fclose($output);
?>
