<?php
// Create dummy verified artist and editor accounts
require_once dirname(__FILE__) . '/config/db.php';

try {
    // Create dummy verified artist
    $artistUsername = 'verified_artist_demo';
    $artistEmail = 'artist@example.com';
    $artistPassword = password_hash('demo123456', PASSWORD_BCRYPT);
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$artistUsername]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_verified_artist, roles) VALUES (?, ?, ?, 1, ?)");
        $stmt->execute([$artistUsername, $artistEmail, $artistPassword, json_encode(['artist'])]);
        echo "✓ Created verified artist account: $artistUsername\n";
        echo "  Password: demo123456\n";
    } else {
        echo "✓ Verified artist account already exists: $artistUsername\n";
    }

    // Create dummy verified editor
    $editorUsername = 'verified_editor_demo';
    $editorEmail = 'editor@example.com';
    $editorPassword = password_hash('demo123456', PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$editorUsername]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_verified_editor, roles) VALUES (?, ?, ?, 1, ?)");
        $stmt->execute([$editorUsername, $editorEmail, $editorPassword, json_encode(['editor'])]);
        echo "✓ Created verified editor account: $editorUsername\n";
        echo "  Password: demo123456\n";
    } else {
        echo "✓ Verified editor account already exists: $editorUsername\n";
    }

    // Create dummy moderator account
    $modUsername = 'moderator_demo';
    $modEmail = 'mod@example.com';
    $modPassword = password_hash('demo123456', PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$modUsername]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, roles) VALUES (?, ?, ?, ?)");
        $stmt->execute([$modUsername, $modEmail, $modPassword, json_encode(['mod'])]);
        echo "✓ Created moderator account: $modUsername\n";
        echo "  Password: demo123456\n";
    } else {
        echo "✓ Moderator account already exists: $modUsername\n";
    }

    echo "\n✓ All dummy accounts created/verified successfully!\n\n";
    echo "=== Demo Account Credentials ===\n";
    echo "Verified Artist:\n";
    echo "  Username: verified_artist_demo\n";
    echo "  Password: demo123456\n\n";
    echo "Verified Editor:\n";
    echo "  Username: verified_editor_demo\n";
    echo "  Password: demo123456\n\n";
    echo "Moderator:\n";
    echo "  Username: moderator_demo\n";
    echo "  Password: demo123456\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>

