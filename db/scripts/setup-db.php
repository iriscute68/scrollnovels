<?php
/**
 * Database Setup Script - Initializes the scroll_novels database
 * Run this once from browser: http://localhost/scrollnovels/setup-db.php
 */

// Database connection credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'scroll_novels';

// Connect to MySQL
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Setting up Scroll Novels Database...</h2>";
    
    // Step 1: Create database if not exists
    echo "<p><strong>Step 1:</strong> Creating database...</p>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created/verified<br>";
    
    // Step 2: Select database
    $pdo->exec("USE `$db`");
    echo "✓ Database selected<br>";
    
    // Step 3: Read and execute schema.sql
    echo "<p><strong>Step 2:</strong> Creating tables from schema.sql...</p>";
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    
    // Remove line comments and split by semicolon
    $lines = explode("\n", $schema);
    $sql = '';
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if (empty($line) || substr($line, 0, 2) === '--') continue;
        $sql .= "\n" . $line;
    }
    
    // Execute statements
    $statements = array_filter(explode(';', $sql));
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (Exception $e) {
                echo "⚠ Statement error (may be OK if table exists): " . $e->getMessage() . "<br>";
            }
        }
    }
    echo "✓ Tables created<br>";
    
    // Step 4: Load initial data
    echo "<p><strong>Step 3:</strong> Seeding initial data...</p>";
    
    // Insert default roles
    $roles = ['admin', 'author', 'user'];
    foreach ($roles as $role) {
        try {
            $pdo->exec("INSERT IGNORE INTO roles (name) VALUES ('$role')");
        } catch (Exception $e) {
            echo "⚠ Role insert: " . $e->getMessage() . "<br>";
        }
    }
    echo "✓ Roles created<br>";
    
    // Insert default website rules
    $rules = [
        ['No Spam', 'Do not post spam or repetitive content.', 'General'],
        ['Be Respectful', 'Treat others with kindness and respect.', 'Community'],
        ['No Plagiarism', 'Original content only. Respect intellectual property.', 'Content'],
        ['No Explicit Content', 'Keep content appropriate for general audiences.', 'Content'],
    ];
    
    foreach ($rules as [$title, $desc, $cat]) {
        try {
            $pdo->exec("INSERT IGNORE INTO website_rules (title, description, category) VALUES ('$title', '" . addslashes($desc) . "', '$cat')");
        } catch (Exception $e) {
            echo "⚠ Rule insert: " . $e->getMessage() . "<br>";
        }
    }
    echo "✓ Website rules created<br>";
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 18px;'><strong>✓ Database setup complete!</strong></p>";
    echo "<p>You can now delete this file or <a href='index.php'>go to home</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Database Error:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Make sure MySQL is running and user 'root' has no password (or edit the script)</p>";
}
?>

