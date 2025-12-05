<?php
// /admin/ajax/get_kpis.php - KPI statistics
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['error' => 'Forbidden'])); }

header('Content-Type: application/json');

try {
  global $pdo;
  
  // Total users
  $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
  
  // Authors (users with stories)
  $authors = $pdo->query("SELECT COUNT(DISTINCT author_id) FROM stories")->fetchColumn() ?? 0;
  
  // Total stories
  $stories = $pdo->query("SELECT COUNT(*) FROM stories")->fetchColumn() ?? 0;
  
  // Total chapters
  $chapters = $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn() ?? 0;
  
  // Revenue (demo)
  $revenue = 12450.00;
  $pending_withdrawals = 3735.00;
  
  // Recent activity (demo)
  $recent_activity = [
    "New user registered",
    "Story approved",
    "Support ticket opened"
  ];
  
  // Recent payments (demo)
  $recent_payments_html = '<ul><li>$25.00 - Author A - Now</li></ul>';
  
  echo json_encode([
    'total_users' => intval($users),
    'total_authors' => intval($authors),
    'total_stories' => intval($stories),
    'total_chapters' => intval($chapters),
    'revenue_mtd' => $revenue,
    'pending_withdrawals' => $pending_withdrawals,
    'recent_activity' => $recent_activity,
    'recent_payments_html' => $recent_payments_html
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
