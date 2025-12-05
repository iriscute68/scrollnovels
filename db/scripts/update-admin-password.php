<?php
require_once 'inc/db.php';

$hash = password_hash('admin123', PASSWORD_BCRYPT);
echo "New hash: " . $hash . "\n";

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@scrollnovels.com']);

echo "Updated.\n";

// Verify
$stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
$stmt->execute(['admin@scrollnovels.com']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Verified password in DB: " . $row['password'] . "\n";

// Test verify
if (password_verify('admin123', $row['password'])) {
    echo "✓ Password verification: SUCCESS\n";
} else {
    echo "✗ Password verification: FAILED\n";
}
?>

