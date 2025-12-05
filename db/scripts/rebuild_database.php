<?php
// rebuild_database.php
require_once __DIR__ . '/admin/inc/db.php';

$sqlFile = __DIR__ . '/admin/migrations/rebuild_tables.sql';
$sql = file_get_contents($sqlFile);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));

$count = 0;
$errors = [];

try {
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $count++;
        } catch (Exception $e) {
            $errors[] = $statement . " => " . $e->getMessage();
        }
    }
    
    echo "<h2>✅ Database Rebuild Complete</h2>";
    echo "<p>Executed $count SQL statements successfully</p>";
    
    if (!empty($errors)) {
        echo "<h3>⚠️ Errors:</h3>";
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
    }
    
    // Verify
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $achievementCount = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
    
    echo "<h3>✅ Verification:</h3>";
    echo "<p>Users in database: <strong>$userCount</strong></p>";
    echo "<p>Achievements in database: <strong>$achievementCount</strong></p>";
    echo "<p><a href='/index.php'>Return to homepage</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

