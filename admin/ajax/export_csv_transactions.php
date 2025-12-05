<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="transactions_export_' . date('Y-m-d_H-i-s') . '.csv"');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

try {
    global $pdo;
    
    $authorId = $_GET['author_id'] ?? '';
    $type = $_GET['type'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 1000), 10000);
    
    $query = "SELECT t.id, u.username, t.type, t.amount, t.description, t.created_at FROM transactions t LEFT JOIN users u ON t.author_id = u.id WHERE 1=1";
    $params = [];
    
    if ($authorId) {
        $query .= " AND t.author_id = ?";
        $params[] = $authorId;
    }
    if ($type) {
        $query .= " AND t.type = ?";
        $params[] = $type;
    }
    
    $query .= " ORDER BY t.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Transaction ID', 'Author', 'Type', 'Amount', 'Description', 'Date']);
    
    foreach ($transactions as $tx) {
        fputcsv($output, [
            $tx['id'],
            $tx['username'],
            $tx['type'],
            $tx['amount'],
            $tx['description'],
            $tx['created_at']
        ]);
    }
    fclose($output);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit;
?>
