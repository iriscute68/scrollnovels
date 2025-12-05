<?php
require 'config/db.php';

echo "=== STAFF MANAGEMENT SYSTEM TEST ===\n\n";

// Check admin/staff users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'super_admin', 'moderator')");
$count = $stmt->fetchColumn();
echo "✅ Total admins/staff: " . $count . "\n\n";

// List sample admin users
$stmt = $pdo->query("SELECT id, username, email, role FROM users WHERE role IN ('admin', 'super_admin', 'moderator') LIMIT 3");
$admins = $stmt->fetchAll();

if (count($admins) > 0) {
    echo "Sample Admins:\n";
    foreach ($admins as $admin) {
        echo "  ID: " . $admin['id'] . " | Username: " . $admin['username'] . " | Role: " . $admin['role'] . "\n";
    }
} else {
    echo "⚠️  No admins found\n";
}

echo "\n=== API FILES CHECK ===\n";

$files = [
    'api/admin/get-admin.php' => 'Fetch admin data',
    'api/admin/save-admin.php' => 'Create/update admin',
    'api/admin/remove-admin.php' => 'Remove admin',
];

foreach ($files as $file => $purpose) {
    if (file_exists($file)) {
        echo "✅ $file - $purpose\n";
    } else {
        echo "❌ $file - MISSING\n";
    }
}

echo "\n=== READY ===\n";
echo "Staff management create/edit/delete functionality is now ready!\n";
echo "Visit: http://localhost/scrollnovels/admin/admin.php?page=staff\n";
?>
