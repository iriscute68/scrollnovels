<?php
// /admin/ajax/get_transactions.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

if (!isApprovedAdmin()) { http_response_code(403); exit(json_encode(['error' => 'Forbidden'])); }

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(10, min(100, intval($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $per_page;

try {
  global $pdo;

  // Return demo transactions since this table may not exist yet
  echo json_encode([
    'transactions' => [
      [
        'tx_id' => 'TX-001',
        'donor' => 'donor@example.com',
        'recipient' => 'author@example.com',
        'amount' => 25.00,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s')
      ],
      [
        'tx_id' => 'TX-002',
        'donor' => 'reader@example.com',
        'recipient' => 'writer@example.com',
        'amount' => 15.50,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
      ]
    ],
    'total' => 2,
    'page' => $page,
    'per_page' => $per_page
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
