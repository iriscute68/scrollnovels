<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

try {
    global $pdo;
    
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $query = "SELECT id, username, email, role, status, created_at, last_login FROM users WHERE 1=1";
    $params = [];
    
    if ($role) {
        $query .= " AND role = ?";
        $params[] = $role;
    }
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'Username', 'Email', 'Role', 'Status', 'Created', 'Last Login']);
    
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['username'],
            $user['email'],
            $user['role'],
            $user['status'],
            $user['created_at'],
            $user['last_login'] ?? 'Never'
        ]);
    }
    fclose($output);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit;
?>
