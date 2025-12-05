<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: text/html');

echo "<h1>Database Status Report</h1>";

// Get table list
echo "<h2>Tables in Database</h2>";
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='scroll_novels' ORDER BY TABLE_NAME")->fetchAll();
echo "<p>Total: " . count($tables) . " tables</p>";
echo "<ul>";
foreach ($tables as $t) {
    echo "<li>" . $t['TABLE_NAME'] . "</li>";
}
echo "</ul>";

// Check data counts
echo "<h2>Data Summary</h2>";
$tables_to_check = ['users', 'stories', 'chapters', 'comments', 'reviews', 'achievements', 'forum_topics'];
foreach ($tables_to_check as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch()['cnt'];
        echo "<p>$table: <strong>$count records</strong></p>";
    } catch (Exception $e) {
        echo "<p>$table: Error - " . $e->getMessage() . "</p>";
    }
}

// Show some users
echo "<h2>Users</h2>";
try {
    $users = $pdo->query("SELECT id, username, email, role FROM users")->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['email']}</td><td>{$u['role']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li><a href='/pages/login.php'>Try Login</a> (testuser / testuser123)</li>";
echo "<li><a href='/'>View Homepage</a></li>";
echo "<li><a href='/admin/'>Admin Panel</a> (admin / admin123)</li>";
echo "</ul>";
?>

