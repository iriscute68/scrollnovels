<?php
require_once __DIR__ . '/../../config/db.php';

echo "=== Updating Admin User ===\n\n";

// Update the existing moderator_demo user
$username = 'moderator_demo';
$email = 'admin@scrollnovels.com';
$roles = json_encode(['admin']);

try {
    $stmt = $pdo->prepare('
        UPDATE users 
        SET email = ?, roles = ?
        WHERE username = ?
    ');
    $stmt->execute([$email, $roles, $username]);
    
    echo "✓ Admin user updated successfully!\n";
    echo "  Username: $username\n";
    echo "  Email: $email\n";
    echo "  Roles: admin\n";
    echo "  Password: demo123456\n\n";
    
    // Verify the update
    $stmt = $pdo->prepare('SELECT id, username, email, roles FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Verification:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Username: {$user['username']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Roles: {$user['roles']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>

