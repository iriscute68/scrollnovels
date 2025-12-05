<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain');

echo "=== CREATING TEST ACCOUNTS ===\n\n";

// Create test accounts
$testAccounts = [
    ['username' => 'testuser', 'email' => 'testuser@scrollnovels.com', 'password' => 'testuser123', 'role' => 'reader'],
    ['username' => 'testauthor', 'email' => 'author@scrollnovels.com', 'password' => 'author123', 'role' => 'author'],
];

foreach ($testAccounts as $account) {
    try {
        // Check if user already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$account['username'], $account['email']]);
        $existing = $check->fetch();
        
        if ($existing) {
            echo "⚠️  {$account['username']} already exists (ID: {$existing['id']})\n";
        } else {
            // Create new user
            $hash = password_hash($account['password'], PASSWORD_BCRYPT);
            $insert = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            $insert->execute([
                $account['username'],
                $account['email'],
                $hash,
                $account['role']
            ]);
            echo "✅ Created {$account['username']} ({$account['role']})\n";
            echo "   Email: {$account['email']}\n";
            echo "   Password: {$account['password']}\n\n";
        }
    } catch (Exception $e) {
        echo "❌ Error creating {$account['username']}: " . $e->getMessage() . "\n\n";
    }
}

echo "\n=== ALL USERS ===\n";
try {
    $result = $pdo->query("SELECT id, username, email, role FROM users");
    $users = $result->fetchAll();
    foreach ($users as $user) {
        echo "- {$user['username']} ({$user['role']}) - {$user['email']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Setup complete! Try logging in at /pages/login.php\n";
echo "   Username: testuser, Password: testuser123\n";
?>

