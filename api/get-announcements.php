<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
$stmt = $pdo->prepare("SELECT id, title, slug, content, link FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
exit;
