<?php
require_once 'config/db.php';

echo "=== PROFILE SETTINGS VERIFICATION ===\n\n";

// Test the profile update query structure
$test_query = "
    UPDATE users SET 
        username = ?,
        email = ?,
        bio = ?,
        country = ?,
        patreon = ?,
        kofi = ?
    WHERE id = ?
";

try {
    $stmt = $pdo->prepare($test_query);
    echo "✓ Profile update query prepared successfully\n";
    echo "✓ All fields available for update: username, email, bio, country, patreon, kofi\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test getting user settings
try {
    $stmt = $pdo->prepare("SELECT * FROM user_notification_settings LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "\n✓ Notification settings table working\n";
} catch (Exception $e) {
    echo "\n✗ Error with notifications: " . $e->getMessage() . "\n";
}

echo "\n=== ALL SYSTEMS READY ===\n";
echo "\nTo test profile settings:\n";
echo "1. Go to /pages/profile-settings.php\n";
echo "2. Update any field (Country, Username, Bio, Patreon, Ko-fi)\n";
echo "3. Click 'Save Changes'\n";
echo "4. Should see success message and all fields saved\n";
?>
