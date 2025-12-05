<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();

$active = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status='active'")->fetchColumn();
$upcoming = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status='upcoming'")->fetchColumn();
$closed = $pdo->query("SELECT COUNT(*) FROM competitions WHERE status='closed'")->fetchColumn();

echo json_encode(['active' => (int)$active, 'upcoming' => (int)$upcoming, 'closed' => (int)$closed]);
