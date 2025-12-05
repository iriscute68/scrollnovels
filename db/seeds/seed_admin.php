<?php
// CLI/web seed helper: creates an admin user if not exists
require_once __DIR__ . '/../../inc/db.php';
// Use inc/auth.php for role helpers if needed

$username = 'admin';
$email = 'admin@example.com';
$password = 'admin123';
$role = 'admin';

try {
    // check users table exists
    $res = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$res) {
        echo "users table does not exist — run migrations first\n";
        exit(1);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    $exists = $stmt->fetch();
    if ($exists) {
        echo "Admin already exists (id: {$exists['id']})\n";
        exit(0);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $ins->execute([$username, $email, $hash, $role]);
    echo "Created admin user: {$username} (password: {$password})\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
