<?php
// Diagnostic script for database connectivity issues
echo "=== Database Connection Diagnostic ===\n\n";

// Step 1: Check if we can even try to connect
echo "1. Checking connection parameters...\n";
echo "   Host: localhost\n";
echo "   Port: 3306 (default)\n";
echo "   Database: scroll_novels\n";
echo "   User: root\n";
echo "   Password: (empty)\n\n";

// Step 2: Try to connect with different hosts
$hosts = ['localhost', '127.0.0.1', 'mysql'];
$results = [];

foreach ($hosts as $host) {
    echo "2. Attempting connection to: $host:3306\n";
    try {
        $pdo = new PDO(
            "mysql:host=" . $host . ";dbname=scroll_novels;charset=utf8mb4",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        $result = $pdo->query("SELECT 1 as connected")->fetch();
        echo "   ✓ Connection successful!\n";
        echo "   Query result: " . json_encode($result) . "\n\n";
        
        // Get version
        $version = $pdo->query("SELECT VERSION() as version")->fetch();
        echo "   MySQL Version: " . $version['version'] . "\n\n";
        
        // Check tables
        $tables = $pdo->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='scroll_novels'")->fetch();
        echo "   Tables in scroll_novels: " . $tables['count'] . "\n\n";
        
        $results[$host] = 'SUCCESS';
    } catch (PDOException $e) {
        echo "   ✗ Connection failed\n";
        echo "   Error: " . $e->getMessage() . "\n\n";
        $results[$host] = 'FAILED: ' . $e->getMessage();
    }
}

echo "=== Summary ===\n";
foreach ($results as $host => $result) {
    echo "$host: $result\n";
}

// Step 3: If all failed, check network
if (count(array_filter($results, fn($r) => strpos($r, 'SUCCESS') !== false)) === 0) {
    echo "\n=== Network Diagnostics ===\n";
    echo "All connection attempts failed. Possible causes:\n";
    echo "1. MySQL service is not running\n";
    echo "2. MySQL is listening on a different port\n";
    echo "3. Firewall is blocking connections\n";
    echo "4. Database credentials are wrong\n\n";
    
    // Try to ping localhost
    echo "Attempting to ping localhost...\n";
    exec('ping -n 1 localhost 2>&1', $output);
    echo implode("\n", $output) . "\n";
}
?>
