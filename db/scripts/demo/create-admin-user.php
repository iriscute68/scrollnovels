<?php
require_once __DIR__ . '/../../../config/db.php';

echo "=== Users in Database ===\n\n";

$stmt = $pdo->prepare('SELECT id, username, email, roles FROM users LIMIT 10');
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($users) {
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Username: {$user['username']} | Email: {$user['email']} | Roles: {$user['roles']}\n";
    }
} else {
    echo "No users found in database!\n";
}

echo "\n=== Creating Admin User ===\n\n";

// Create the admin user
$username = 'moderator_demo';
$email = 'admin@scrollnovels.com';
$password_hash = password_hash('demo123456', PASSWORD_BCRYPT);
$roles = json_encode(['admin']);

try {
    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, roles, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$username, $email, $password_hash, $roles]);
    $user_id = $pdo->lastInsertId();
    
    echo "✓ Admin user created successfully!\n";
    echo "  ID: $user_id\n";
    echo "  Username: $username\n";
    echo "  Email: $email\n";
    echo "  Password: demo123456\n";
    echo "  Roles: admin\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>

